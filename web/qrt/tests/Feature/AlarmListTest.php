<?php

namespace Tests\Feature;

use App\Models\Alarm;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AlarmListTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_alarm_list_puts_active_alarms_first(): void
    {
        $location = Location::create(['location_name' => 'Station 1', 'barangay' => 'Poblacion', 'latitude' => 0, 'longitude' => 0]);
        // The very first user becomes an admin (see User::booted).
        User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $resident = User::create([
            'name' => 'Resident',
            'email' => 'resident@example.com',
            'password' => 'password',
            'role' => 'resident',
            'location_id' => $location->id,
        ]);

        foreach (['resolved', 'triggered', 'false_alarm'] as $status) {
            Alarm::create([
                'user_id' => $resident->id,
                'location_id' => $location->id,
                'latitude' => 1,
                'longitude' => 1,
                'status' => $status,
            ]);
        }

        Sanctum::actingAs($resident);

        $statuses = array_column($this->getJson('/api/alarms')->assertOk()->json('data'), 'status');
        $this->assertSame(['triggered', 'resolved', 'false_alarm'], $statuses);
    }
}
