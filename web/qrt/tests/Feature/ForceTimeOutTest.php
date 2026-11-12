<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ForceTimeOutTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;
    private User $admin;
    private User $personnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->location = Location::create(['location_name' => 'Station 1', 'barangay' => 'Poblacion', 'latitude' => 10, 'longitude' => 120]);
        // The very first user becomes an admin (see User::booted).
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $this->personnel = User::create([
            'name' => 'Juan',
            'email' => 'juan@example.com',
            'password' => 'password',
            'role' => 'personnel',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Juan timed in at 08:00 (Manila time) and it is now 10:30. */
    private function onDutySinceEight(): Attendance
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12 10:30:00', 'Asia/Manila'));

        return Attendance::create([
            'user_id' => $this->personnel->id,
            'location_id' => $this->location->id,
            'date' => '2026-11-12',
            'time_in' => '08:00:00',
            'status' => 'On Time',
        ]);
    }

    public function test_personnel_list_shows_who_is_on_duty(): void
    {
        $this->onDutySinceEight();

        $this->actingAs($this->admin)->get('/admin/personnels')
            ->assertOk()
            ->assertSee('On duty')
            ->assertSee('Force Time Out');
    }

    public function test_admin_can_force_time_out(): void
    {
        $attendance = $this->onDutySinceEight();

        $this->actingAs($this->admin)
            ->post("/admin/personnels/{$this->personnel->id}/force-time-out")
            ->assertRedirect()
            ->assertSessionHas('success');

        $attendance->refresh();
        $this->assertNotNull($attendance->time_out);
        $this->assertEquals(2.5, (float) $attendance->hours_worked);

        $log = AuditLog::where('user_id', $this->personnel->id)->sole();
        $this->assertSame(AuditLog::ACTION_TIME_OUT, $log->action);
        $this->assertStringContainsString('Timed out by admin Admin', $log->description);

        // The app now sees them as off duty, so they can log out
        Sanctum::actingAs($this->personnel);
        $this->getJson('/api/personnel/attendance/status')->assertJson(['is_on_duty' => false]);
    }

    public function test_force_time_out_when_not_on_duty_changes_nothing(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/personnels/{$this->personnel->id}/force-time-out")
            ->assertRedirect();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_only_admins_can_force_time_out(): void
    {
        $attendance = $this->onDutySinceEight();

        $this->actingAs($this->personnel)
            ->post("/admin/personnels/{$this->personnel->id}/force-time-out")
            ->assertRedirect(route('login'));

        $this->assertNull($attendance->fresh()->time_out);
    }

    public function test_app_time_out_still_records_hours_worked(): void
    {
        $attendance = $this->onDutySinceEight();
        Sanctum::actingAs($this->personnel);

        $this->postJson('/api/personnel/attendance/toggle', ['latitude' => 10, 'longitude' => 120])
            ->assertOk()
            ->assertJson(['data' => ['is_on_duty' => false, 'session_hours' => 2.5]]);

        $this->assertNotNull($attendance->fresh()->time_out);
    }
}
