<?php

namespace Tests\Feature;

use App\Models\AppRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $apk;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->apk = tempnam(sys_get_temp_dir(), 'apk');
        file_put_contents($this->apk, str_repeat('A', 2048));
    }

    protected function tearDown(): void
    {
        @unlink($this->apk);
        parent::tearDown();
    }

    private function publish(int $code, string $name, array $options = []): void
    {
        $this->artisan('qrt:app-publish', array_merge([
            'apk' => $this->apk,
            '--code' => $code,
            '--name' => $name,
        ], $options))->assertSuccessful();
    }

    public function test_next_version_starts_after_the_first_installs(): void
    {
        $this->artisan('qrt:app-next-version')->expectsOutput('2')->assertSuccessful();

        $this->publish(2, '1.0.2');
        $this->artisan('qrt:app-next-version')->expectsOutput('3')->assertSuccessful();
    }

    public function test_no_release_means_no_update(): void
    {
        $this->getJson('/api/app/latest')->assertOk()->assertJson(['data' => null]);
    }

    public function test_publish_then_phones_see_and_download_it(): void
    {
        $notes = tempnam(sys_get_temp_dir(), 'notes');
        file_put_contents($notes, "- Chat photos now load\n\n* Registration checks each field\n");
        $this->publish(2, '1.0.2', ['--notes-file' => $notes]);
        unlink($notes);

        $data = $this->getJson('/api/app/latest?current=1')->assertOk()->json('data');
        $this->assertSame(2, $data['version_code']);
        $this->assertSame('1.0.2', $data['version_name']);
        $this->assertSame(['Chat photos now load', 'Registration checks each field'], $data['changes']);
        $this->assertFalse($data['required']);
        $this->assertSame(2048, $data['size']);

        $response = $this->get($data['download_path'])->assertOk();
        $this->assertSame('application/vnd.android.package-archive', $response->headers->get('Content-Type'));
        $this->assertSame(2048, (int) $response->headers->get('Content-Length'));
    }

    public function test_a_skipped_required_update_makes_the_newest_required(): void
    {
        $this->publish(2, '1.0.2', ['--required' => true]);
        $this->publish(3, '1.0.3');

        // Phone still on build 1 missed the required 1.0.2
        $this->assertTrue($this->getJson('/api/app/latest?current=1')->json('data.required'));
        // Phone on 1.0.2 can skip 1.0.3
        $this->assertFalse($this->getJson('/api/app/latest?current=2')->json('data.required'));
    }

    public function test_cannot_publish_an_older_build_number(): void
    {
        $this->publish(3, '1.0.3');

        $this->artisan('qrt:app-publish', ['apk' => $this->apk, '--code' => 3, '--name' => '1.0.3'])
            ->assertFailed();
        $this->assertSame(1, AppRelease::count());
    }

    public function test_admin_can_edit_and_delete_releases(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $this->publish(2, '1.0.2');
        $release = AppRelease::sole();

        $this->actingAs($admin)->get('/admin/app-updates')->assertOk()->assertSee('Version 1.0.2');

        $this->actingAs($admin)->put("/admin/app-updates/{$release->id}", [
            'notes' => 'Faster SOS',
            'required' => '1',
        ])->assertRedirect();
        $release->refresh();
        $this->assertSame('Faster SOS', $release->notes);
        $this->assertTrue($release->required);

        $this->actingAs($admin)->delete("/admin/app-updates/{$release->id}")->assertRedirect();
        $this->assertSame(0, AppRelease::count());
        Storage::disk('local')->assertMissing($release->apk_path);
    }

    public function test_public_download_page_with_qr_code(): void
    {
        config(['services.app_download.public_url' => 'https://qrt-demo.ngrok-free.app']);

        // Before anything is published
        $this->get('/download')->assertOk()->assertSee("isn't available for download yet", false);

        $this->publish(2, '1.0.2');
        $release = AppRelease::sole();

        $this->get('/download')
            ->assertOk()
            ->assertSee('Version')
            ->assertSee('1.0.2')
            ->assertSee("/api/app/releases/{$release->id}/download", false)
            ->assertSee('https://qrt-demo.ngrok-free.app/download')
            ->assertSee('<svg', false);

        $this->get('/download/latest.apk')->assertRedirect("/api/app/releases/{$release->id}/download");
    }

    public function test_admin_page_shows_the_download_link_and_qr(): void
    {
        config(['services.app_download.public_url' => 'https://qrt-demo.ngrok-free.app']);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);

        $this->actingAs($admin)->get('/admin/app-updates')
            ->assertOk()
            ->assertSee('https://qrt-demo.ngrok-free.app/download')
            ->assertSee('<svg', false)
            ->assertDontSee('only works on this PC');
    }

    public function test_residents_cannot_open_the_admin_page(): void
    {
        User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $resident = User::create(['name' => 'R', 'email' => 'r@example.com', 'password' => 'password', 'role' => 'resident']);

        $this->actingAs($resident)->get('/admin/app-updates')->assertRedirect(route('login'));
    }
}
