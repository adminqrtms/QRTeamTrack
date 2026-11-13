<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->location = Location::create(['location_name' => 'Station 1', 'barangay' => 'Poblacion', 'latitude' => 0, 'longitude' => 0]);
        // The very first user becomes an admin (see User::booted).
        User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
    }

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'secret123',
            'phone_number' => '09171234567',
            'address' => 'Purok 3, Poblacion',
            'location_id' => $this->location->id,
        ], $overrides);
    }

    public function test_valid_registration_creates_a_resident(): void
    {
        $this->postJson('/api/register', $this->valid(['email' => ' Juan@Example.com ', 'name' => " Ma. Señora O'Neil "]))
            ->assertCreated()
            ->assertJsonStructure(['token']);

        $user = User::where('email', 'juan@example.com')->sole();
        $this->assertSame('resident', $user->role);
        $this->assertSame("Ma. Señora O'Neil", $user->name);
    }

    public function test_invalid_fields_are_rejected_with_clear_messages(): void
    {
        $cases = [
            'name' => ['Juan123', 'Use letters only'],
            'phone_number' => ['12345', 'starting with 09'],
            'password' => ['abcdefgh', 'letters and numbers'],
            'address' => ['', 'home address'],
            'email' => ['not-an-email', 'valid email'],
        ];

        foreach ($cases as $field => [$value, $message]) {
            $errors = $this->postJson('/api/register', $this->valid([$field => $value]))
                ->assertStatus(422)
                ->json('errors');
            $this->assertArrayHasKey($field, $errors, $field);
            $this->assertStringContainsString($message, $errors[$field][0], $field);
        }

        $this->assertSame(1, User::count());
    }

    public function test_email_must_be_unique_ignoring_case(): void
    {
        $this->postJson('/api/register', $this->valid())->assertCreated();

        $this->postJson('/api/register', $this->valid(['email' => 'JUAN@example.com', 'phone_number' => '09181234567']))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'This email is already registered. Try logging in instead.');
    }
}
