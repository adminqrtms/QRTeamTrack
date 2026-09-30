<?php

namespace Tests\Feature;

use App\Models\Alarm;
use App\Models\Attendance;
use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const SEND_URL = 'https://fcm.googleapis.com/v1/projects/qrt-test/messages:send';

    private Location $location;
    private User $resident;
    private User $personnel;
    private string $credentialsPath;
    private int $fcmStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        $this->location = Location::create(['location_name' => 'Station 1', 'barangay' => 'Poblacion', 'latitude' => 0, 'longitude' => 0]);
        // The very first user becomes an admin (see User::booted).
        $this->makeUser('admin');
        $this->resident = $this->makeUser('resident', ['location_id' => $this->location->id]);
        $this->personnel = $this->makeUser('personnel');

        // A throwaway service account key, like the one from the Firebase console.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'qrt-test',
            'client_email' => 'push@qrt-test.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.fcm.credentials' => $this->credentialsPath]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access-token', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => fn () => $this->fcmStatus === 200
                ? Http::response(['name' => 'projects/qrt-test/messages/1'])
                : Http::response(['error' => ['status' => 'NOT_FOUND']], $this->fcmStatus),
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);
        parent::tearDown();
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        static $count = 0;
        $count++;

        return User::create(array_merge([
            'name' => ucfirst($role) . " $count",
            'email' => "push$count@example.com",
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    private function sentMessages(): array
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => $pair[0]->url() === self::SEND_URL)
            ->map(fn ($pair) => $pair[0]->data()['message'])
            ->values()
            ->all();
    }

    public function test_devices_can_register_and_unregister_their_token(): void
    {
        Sanctum::actingAs($this->resident);
        $this->postJson('/api/device-tokens', ['token' => 'phone-1', 'platform' => 'android'])->assertOk();
        $this->assertDatabaseHas('device_tokens', ['token' => 'phone-1', 'user_id' => $this->resident->id]);

        // Another user logging in on the same phone takes the token over.
        Sanctum::actingAs($this->personnel);
        $this->postJson('/api/device-tokens', ['token' => 'phone-1'])->assertOk();
        $this->assertSame(1, DeviceToken::count());
        $this->assertDatabaseHas('device_tokens', ['token' => 'phone-1', 'user_id' => $this->personnel->id]);

        $this->deleteJson('/api/device-tokens', ['token' => 'phone-1'])->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_new_chat_message_notifies_the_recipient(): void
    {
        DeviceToken::create(['user_id' => $this->personnel->id, 'token' => 'personnel-phone']);
        DeviceToken::create(['user_id' => $this->resident->id, 'token' => 'resident-phone']);
        $conversation = Conversation::create([
            'resident_id' => $this->resident->id,
            'personnel_id' => $this->personnel->id,
        ]);

        Sanctum::actingAs($this->resident);
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Please help'])
            ->assertCreated();

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('personnel-phone', $messages[0]['token']);
        $this->assertSame($this->resident->name, $messages[0]['notification']['title']);
        $this->assertSame('Please help', $messages[0]['notification']['body']);
        $this->assertSame(['type' => 'chat', 'conversation_id' => (string) $conversation->id, 'tag' => 'chat_' . $conversation->id], $messages[0]['data']);
        $this->assertSame('chat_channel', $messages[0]['android']['notification']['channel_id']);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::SEND_URL
            && $request->hasHeader('Authorization', 'Bearer test-access-token'));
    }

    public function test_google_access_token_request_is_a_valid_signed_jwt(): void
    {
        DeviceToken::create(['user_id' => $this->personnel->id, 'token' => 'personnel-phone']);
        $conversation = Conversation::create([
            'resident_id' => $this->resident->id,
            'personnel_id' => $this->personnel->id,
        ]);

        Sanctum::actingAs($this->resident);
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Hi'])->assertCreated();

        $tokenRequest = collect(Http::recorded())
            ->first(fn ($pair) => $pair[0]->url() === 'https://oauth2.googleapis.com/token')[0];
        $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $tokenRequest['grant_type']);

        [$header, $payload, $signature] = explode('.', $tokenRequest['assertion']);
        $decode = fn ($part) => base64_decode(strtr($part, '-_', '+/'));

        $credentials = json_decode(file_get_contents($this->credentialsPath), true);
        $publicKey = openssl_pkey_get_details(openssl_pkey_get_private($credentials['private_key']))['key'];
        $this->assertSame(1, openssl_verify("$header.$payload", $decode($signature), $publicKey, OPENSSL_ALGO_SHA256));

        $claims = json_decode($decode($payload), true);
        $this->assertSame('RS256', json_decode($decode($header), true)['alg']);
        $this->assertSame($credentials['client_email'], $claims['iss']);
        $this->assertSame('https://www.googleapis.com/auth/firebase.messaging', $claims['scope']);
        $this->assertSame('https://oauth2.googleapis.com/token', $claims['aud']);
        $this->assertSame(3600, $claims['exp'] - $claims['iat']);
    }

    public function test_sos_alerts_on_duty_personnel_at_the_location(): void
    {
        $offDuty = $this->makeUser('personnel');
        DeviceToken::create(['user_id' => $this->personnel->id, 'token' => 'on-duty-phone']);
        DeviceToken::create(['user_id' => $offDuty->id, 'token' => 'off-duty-phone']);
        Attendance::create([
            'user_id' => $this->personnel->id,
            'location_id' => $this->location->id,
            'time_in' => now(),
            'date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($this->resident);
        $alarmId = $this->postJson('/api/alarms', ['latitude' => 1, 'longitude' => 1])
            ->assertCreated()
            ->json('data.id');

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('on-duty-phone', $messages[0]['token']);

        // Sent as a data-only message so the app can show a full-screen alert.
        $this->assertArrayNotHasKey('notification', $messages[0]);
        $this->assertSame('EMERGENCY SOS!', $messages[0]['data']['title']);
        $this->assertStringContainsString($this->resident->name, $messages[0]['data']['body']);
        $this->assertSame('1', $messages[0]['data']['full_screen']);
        $this->assertSame('alarm', $messages[0]['data']['type']);
        $this->assertSame((string) $alarmId, $messages[0]['data']['alarm_id']);
        $this->assertSame('high', $messages[0]['android']['priority']);
    }

    public function test_resident_is_told_when_a_responder_accepts_the_sos(): void
    {
        DeviceToken::create(['user_id' => $this->resident->id, 'token' => 'resident-phone']);
        $alarm = Alarm::create([
            'user_id' => $this->resident->id,
            'location_id' => $this->location->id,
            'latitude' => 1,
            'longitude' => 1,
            'status' => 'triggered',
        ]);

        Sanctum::actingAs($this->personnel);
        $this->putJson("/api/alarms/{$alarm->id}", ['status' => 'responding'])->assertOk();

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('resident-phone', $messages[0]['token']);
        $this->assertSame('Help is on the way!', $messages[0]['notification']['title']);
    }

    public function test_later_alarm_updates_do_not_notify_the_resident_again(): void
    {
        DeviceToken::create(['user_id' => $this->resident->id, 'token' => 'resident-phone']);
        $alarm = Alarm::create([
            'user_id' => $this->resident->id,
            'location_id' => $this->location->id,
            'latitude' => 1,
            'longitude' => 1,
            'status' => 'responding',
            'responded_by' => $this->personnel->id,
            'responded_at' => now(),
        ]);

        Sanctum::actingAs($this->personnel);
        $this->putJson("/api/alarms/{$alarm->id}", ['status' => 'resolved'])->assertOk();

        $this->assertCount(0, $this->sentMessages());
    }

    public function test_uninstalled_app_tokens_are_removed(): void
    {
        $this->fcmStatus = 404;
        DeviceToken::create(['user_id' => $this->personnel->id, 'token' => 'stale-phone']);
        $conversation = Conversation::create([
            'resident_id' => $this->resident->id,
            'personnel_id' => $this->personnel->id,
        ]);

        Sanctum::actingAs($this->resident);
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Hello'])->assertCreated();

        $this->assertSame(0, DeviceToken::count());
    }

    public function test_nothing_is_sent_without_firebase_credentials(): void
    {
        config(['services.fcm.credentials' => '/nonexistent/firebase-credentials.json']);
        DeviceToken::create(['user_id' => $this->personnel->id, 'token' => 'personnel-phone']);
        $conversation = Conversation::create([
            'resident_id' => $this->resident->id,
            'personnel_id' => $this->personnel->id,
        ]);

        Sanctum::actingAs($this->resident);
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Hello'])->assertCreated();

        Http::assertNothingSent();
    }
}
