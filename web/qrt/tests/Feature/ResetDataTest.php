<?php

namespace Tests\Feature;

use App\Models\Alarm;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Location;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResetDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_keeps_only_admins_and_stations(): void
    {
        Storage::fake('public');

        $location = Location::create(['location_name' => 'Station 1', 'barangay' => 'Poblacion', 'latitude' => 0, 'longitude' => 0]);
        // The very first user becomes an admin (see User::booted).
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $admin->createToken('web');
        $resident = User::create(['name' => 'R', 'email' => 'r@example.com', 'password' => 'password', 'role' => 'resident', 'location_id' => $location->id, 'avatar' => 'avatars/r.jpg']);
        $personnel = User::create(['name' => 'P', 'email' => 'p@example.com', 'password' => 'password', 'role' => 'personnel']);
        $resident->createToken('app');
        Storage::disk('public')->put('avatars/r.jpg', 'x');
        Storage::disk('public')->put('chat/photo.jpg', 'x');

        $attendance = Attendance::create(['user_id' => $personnel->id, 'location_id' => $location->id, 'date' => now()->toDateString(), 'time_in' => now(), 'status' => 'On Time']);
        AuditLog::create(['user_id' => $personnel->id, 'attendance_id' => $attendance->id, 'location_id' => $location->id, 'action' => 'time_in', 'logged_at' => now()]);
        Report::create(['user_id' => $resident->id, 'title' => 'T', 'description' => 'D', 'status' => 'pending', 'location_id' => $location->id]);
        Alarm::create(['user_id' => $resident->id, 'location_id' => $location->id, 'latitude' => 1, 'longitude' => 1, 'status' => 'triggered']);
        $conversation = Conversation::create(['resident_id' => $resident->id, 'personnel_id' => $personnel->id]);
        $conversation->messages()->create(['sender_id' => $resident->id, 'body' => 'hi']);

        $this->artisan('qrt:reset-data')
            ->expectsConfirmation('Delete all of this? It cannot be undone', 'yes')
            ->assertSuccessful();

        $this->assertSame([$admin->id], User::pluck('id')->all());
        $this->assertSame(1, Location::count());
        $this->assertSame(1, $admin->tokens()->count());
        foreach ([Attendance::class, AuditLog::class, Report::class, Alarm::class, Conversation::class] as $model) {
            $this->assertSame(0, $model::count(), $model);
        }
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        Storage::disk('public')->assertMissing('avatars/r.jpg');
        Storage::disk('public')->assertMissing('chat/photo.jpg');

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_answering_no_deletes_nothing(): void
    {
        User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        User::create(['name' => 'R', 'email' => 'r@example.com', 'password' => 'password', 'role' => 'resident']);

        $this->artisan('qrt:reset-data')
            ->expectsConfirmation('Delete all of this? It cannot be undone', 'no')
            ->assertSuccessful();

        $this->assertSame(2, User::count());
    }
}
