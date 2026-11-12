<?php

namespace Tests\Feature;

use App\Models\Alarm;
use App\Models\Attendance;
use App\Models\Location;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;
    private User $admin;
    private User $resident;
    private User $otherResident;
    private User $personnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->location = Location::create(['location_name' => 'Station 1', 'barangay' => 'Poblacion', 'latitude' => 0, 'longitude' => 0]);
        // The very first user becomes an admin (see User::booted).
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $this->resident = $this->makeUser('resident', 'resident@example.com');
        $this->otherResident = $this->makeUser('resident', 'other@example.com');
        $this->personnel = $this->makeUser('personnel', 'personnel@example.com');
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'location_id' => $this->location->id,
        ]);
    }

    private function report(): Report
    {
        return Report::create([
            'user_id' => $this->resident->id,
            'title' => 'Flood',
            'description' => 'Water is rising',
            'status' => 'pending',
            'location_id' => $this->location->id,
        ]);
    }

    private function alarm(): Alarm
    {
        return Alarm::create([
            'user_id' => $this->resident->id,
            'location_id' => $this->location->id,
            'latitude' => 1,
            'longitude' => 1,
            'status' => 'triggered',
        ]);
    }

    public function test_web_registration_does_not_open_the_admin_panel(): void
    {
        $this->post('/register', [
            'name' => 'Stranger',
            'email' => 'stranger@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_can_open_the_admin_panel(): void
    {
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_deactivated_admin_is_signed_out_of_the_panel(): void
    {
        $this->admin->update(['is_active' => false]);

        $this->actingAs($this->admin)->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_deactivated_account_cannot_log_in_to_the_app(): void
    {
        $this->personnel->update(['is_active' => false]);

        $this->postJson('/api/login', ['email' => 'personnel@example.com', 'password' => 'password'])
            ->assertStatus(403);
    }

    public function test_deactivating_personnel_signs_them_out_of_the_app(): void
    {
        $this->personnel->createToken('auth_token');

        $this->actingAs($this->admin)->post("/admin/personnels/{$this->personnel->id}/toggle-status");

        $this->assertFalse((bool) $this->personnel->fresh()->is_active);
        $this->assertSame(0, $this->personnel->tokens()->count());
    }

    public function test_resident_cannot_change_a_report(): void
    {
        $report = $this->report();
        Sanctum::actingAs($this->resident);

        $this->putJson("/api/reports/{$report->id}", ['status' => 'resolved'])->assertForbidden();
        $this->assertSame('pending', $report->fresh()->status);
    }

    public function test_personnel_update_only_changes_status_and_action(): void
    {
        $report = $this->report();
        Sanctum::actingAs($this->personnel);

        $this->putJson("/api/reports/{$report->id}", [
            'status' => 'ongoing',
            'action_taken' => 'On the way',
            'user_id' => $this->otherResident->id,
            'title' => 'Changed',
        ])->assertOk();

        $report->refresh();
        $this->assertSame('ongoing', $report->status);
        $this->assertSame('On the way', $report->action_taken);
        $this->assertSame($this->resident->id, $report->user_id);
        $this->assertSame('Flood', $report->title);
        $this->assertSame($this->personnel->id, $report->respondent_by);
    }

    public function test_resident_sees_only_their_own_report(): void
    {
        $report = $this->report();

        Sanctum::actingAs($this->otherResident);
        $this->getJson("/api/reports/{$report->id}")->assertForbidden();
        $this->deleteJson("/api/reports/{$report->id}")->assertForbidden();

        Sanctum::actingAs($this->resident);
        $this->getJson("/api/reports/{$report->id}")->assertOk();
    }

    public function test_resident_can_cancel_only_their_own_sos(): void
    {
        $alarm = $this->alarm();

        Sanctum::actingAs($this->otherResident);
        $this->putJson("/api/alarms/{$alarm->id}", ['status' => 'false_alarm'])->assertForbidden();
        $this->getJson("/api/alarms/{$alarm->id}")->assertForbidden();

        Sanctum::actingAs($this->resident);
        $this->putJson("/api/alarms/{$alarm->id}", ['status' => 'resolved'])->assertForbidden();
        $this->putJson("/api/alarms/{$alarm->id}", ['status' => 'false_alarm'])->assertOk();
        $this->assertSame('false_alarm', $alarm->fresh()->status);
    }

    public function test_personnel_can_respond_to_an_sos(): void
    {
        $alarm = $this->alarm();
        Sanctum::actingAs($this->personnel);

        $this->putJson("/api/alarms/{$alarm->id}", ['status' => 'responding'])->assertOk();
        $this->assertSame($this->personnel->id, (int) $alarm->fresh()->responded_by);
    }

    public function test_available_stations_lists_locations_with_personnel_on_duty(): void
    {
        $empty = Location::create(['location_name' => 'Station 2', 'barangay' => 'Poblacion', 'latitude' => 0, 'longitude' => 0]);
        Attendance::create([
            'user_id' => $this->personnel->id,
            'location_id' => $this->location->id,
            'date' => now()->toDateString(),
            'time_in' => now(),
            'status' => 'On Time',
        ]);

        Sanctum::actingAs($this->resident);

        $data = $this->getJson('/api/stations/available')->assertOk()->json('data');
        $this->assertSame([[
            'id' => $this->location->id,
            'name' => 'Station 1',
            'active_count' => 1,
        ]], $data);
        $this->assertNotContains($empty->id, array_column($data, 'id'));
    }
}
