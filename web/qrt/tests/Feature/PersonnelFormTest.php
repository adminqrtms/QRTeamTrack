<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // The very first user becomes an admin (see User::booted).
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
    }

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'phone_number' => '09171234567',
            'street' => '123 Sinsuat Avenue',
            'province' => 'Maguindanao del Norte',
            'city' => 'Cotabato City',
            'barangay' => 'Rosary Heights',
            'hourly_rate' => '57.63',
            'password' => 'Secret#123',
            'password_confirmation' => 'Secret#123',
        ], $overrides);
    }

    public function test_add_personnel_saves_name_parts_and_address(): void
    {
        $this->actingAs($this->admin)->post('/admin/personnels', $this->valid())
            ->assertRedirect(route('admin.personnels.index'))
            ->assertSessionHasNoErrors();

        $p = User::where('email', 'juan@example.com')->sole();
        $this->assertSame('personnel', $p->role);
        $this->assertSame('Juan Santos Dela Cruz', $p->name);
        $this->assertSame(['Juan', 'Santos', 'Dela Cruz'], [$p->first_name, $p->middle_name, $p->last_name]);
        $this->assertSame('123 Sinsuat Avenue, Brgy. Rosary Heights, Cotabato City, Maguindanao del Norte', $p->address);
        $this->assertSame('Rosary Heights', $p->barangay);
    }

    public function test_middle_name_and_street_are_optional(): void
    {
        $this->actingAs($this->admin)->post('/admin/personnels', $this->valid(['middle_name' => '', 'street' => '']))
            ->assertSessionHasNoErrors();

        $p = User::where('email', 'juan@example.com')->sole();
        $this->assertSame('Juan Dela Cruz', $p->name);
        $this->assertSame('Brgy. Rosary Heights, Cotabato City, Maguindanao del Norte', $p->address);
    }

    public function test_hourly_rate_limits_are_accepted(): void
    {
        foreach (['50', '99.99', '57.5'] as $i => $rate) {
            $this->actingAs($this->admin)
                ->post('/admin/personnels', $this->valid(['hourly_rate' => $rate, 'email' => "rate{$i}@example.com"]))
                ->assertSessionHasNoErrors();
        }
        $this->assertEquals([50.0, 57.5, 99.99], User::where('role', 'personnel')->orderBy('hourly_rate')->pluck('hourly_rate')->map(fn ($r) => (float) $r)->all());
    }

    public function test_invalid_values_are_rejected(): void
    {
        $cases = [
            ['first_name', '', 'first name field is required'],
            ['last_name', 'Cruz2', 'only contain letters'],
            ['phone_number', '0917123456', 'exactly 11 digits'],      // 10 digits
            ['phone_number', '091712345678', 'exactly 11 digits'],    // 12 digits
            ['phone_number', '19171234567', 'exactly 11 digits'],     // not 09
            ['barangay', 'Not A Barangay', 'from the lists'],
            ['city', 'Davao City', 'from the lists'],                 // city not in that province
            ['hourly_rate', '100', '₱99.99'],
            ['hourly_rate', '100.00', '₱99.99'],
            ['hourly_rate', '49.99', '₱99.99'],
            ['hourly_rate', '57.555', 'at most 2 decimals'],
            ['hourly_rate', '', 'hourly rate field is required'],
        ];

        foreach ($cases as [$field, $value, $message]) {
            $errors = $this->actingAs($this->admin)
                ->post('/admin/personnels', $this->valid([$field => $value]))
                ->assertSessionHasErrors()
                ->baseResponse->getSession()->get('errors')->all();
            $this->assertTrue(
                collect($errors)->contains(fn ($e) => str_contains($e, $message)),
                "{$field}={$value}: expected '{$message}' in: " . implode(' | ', $errors)
            );
        }

        $this->assertSame(0, User::where('role', 'personnel')->count());
    }

    public function test_password_needs_8_characters_a_capital_and_a_symbol(): void
    {
        foreach (['Sh#1', 'secret#123', 'Secret1234'] as $weak) {
            $this->actingAs($this->admin)
                ->post('/admin/personnels', $this->valid(['password' => $weak, 'password_confirmation' => $weak]))
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($this->admin)->post('/admin/personnels', $this->valid())->assertSessionHasNoErrors();
    }

    public function test_edit_keeps_password_when_left_empty(): void
    {
        $this->actingAs($this->admin)->post('/admin/personnels', $this->valid());
        $p = User::where('email', 'juan@example.com')->sole();
        $oldHash = $p->password;

        $this->actingAs($this->admin)->get("/admin/personnels/{$p->id}/edit")
            ->assertOk()->assertSee('Dela Cruz')->assertSee('Rosary Heights');

        $this->actingAs($this->admin)->put("/admin/personnels/{$p->id}", $this->valid([
            'first_name' => 'Pedro', 'password' => '', 'password_confirmation' => '',
        ]))->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('Pedro Santos Dela Cruz', $p->name);
        $this->assertSame($oldHash, $p->password);
    }

    public function test_name_changed_in_the_app_updates_the_parts(): void
    {
        $user = User::create(['name' => 'Maria Clara Reyes', 'email' => 'm@example.com', 'password' => 'password', 'role' => 'resident']);

        $this->assertSame(['Maria Clara', null, 'Reyes'], [$user->first_name, $user->middle_name, $user->last_name]);
    }

    public function test_forms_show_required_markers(): void
    {
        $this->actingAs($this->admin)->get('/admin/personnels/create')
            ->assertOk()
            ->assertSee('form-label fw-bold small required', false)
            ->assertSee('At least 1 capital letter')
            ->assertSee('ph-addresses.json', false);

        $this->actingAs($this->admin)->get('/admin/schedules/create')
            ->assertOk()
            ->assertSee('All fields marked', false);
    }
}
