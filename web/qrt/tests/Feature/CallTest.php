<?php

namespace Tests\Feature;

use App\Events\CallSignal;
use App\Events\MessageSent;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallTest extends TestCase
{
    use RefreshDatabase;

    private User $resident;
    private User $personnel;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        // The very first user becomes an admin (see User::booted).
        $this->makeUser('admin');
        $this->resident = $this->makeUser('resident');
        $this->personnel = $this->makeUser('personnel');
        $this->conversation = Conversation::create([
            'resident_id' => $this->resident->id,
            'personnel_id' => $this->personnel->id,
        ]);

        config(['services.fcm.credentials' => '/nonexistent/firebase-credentials.json']);
        Event::fake([CallSignal::class, MessageSent::class]);
    }

    private function makeUser(string $role): User
    {
        static $count = 0;
        $count++;

        return User::create([
            'name' => ucfirst($role) . " $count",
            'email' => "call$count@example.com",
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function decodeToken(string $jwt): array
    {
        [$header, $payload, $signature] = explode('.', $jwt);
        $decode = fn ($part) => base64_decode(strtr($part, '-_', '+/'));

        $expected = hash_hmac('sha256', "$header.$payload", 'secret', true);
        $this->assertTrue(hash_equals($expected, $decode($signature)), 'LiveKit token signature is invalid');
        $this->assertSame('HS256', json_decode($decode($header), true)['alg']);

        return json_decode($decode($payload), true);
    }

    private function startCall(string $type = 'audio'): array
    {
        Sanctum::actingAs($this->resident);

        return $this->postJson("/api/conversations/{$this->conversation->id}/calls", ['type' => $type])
            ->assertCreated()
            ->json();
    }

    public function test_starting_a_call_rings_the_other_person_and_returns_a_valid_token(): void
    {
        $response = $this->startCall('video');

        $this->assertSame('ringing', $response['data']['status']);
        $this->assertSame('video', $response['data']['type']);
        $this->assertSame($this->personnel->id, $response['data']['callee']['id']);

        // Local dev: LiveKit on the same host as the API, port 7880
        $this->assertSame('ws://localhost:7880', $response['livekit']['url']);

        $claims = $this->decodeToken($response['livekit']['token']);
        $this->assertSame('devkey', $claims['iss']);
        $this->assertSame('user-' . $this->resident->id, $claims['sub']);
        $this->assertSame($response['livekit']['room'], $claims['video']['room']);
        $this->assertTrue($claims['video']['roomJoin']);
        $this->assertGreaterThan(time(), $claims['exp']);

        Event::assertDispatched(CallSignal::class, fn ($e) => $e->signal === 'call.incoming'
            && $e->recipientId === $this->personnel->id);
    }

    public function test_livekit_url_can_be_configured(): void
    {
        config(['services.livekit.url' => 'wss://qrt.livekit.cloud']);

        $this->assertSame('wss://qrt.livekit.cloud', $this->startCall()['livekit']['url']);
    }

    public function test_outsiders_cannot_call_or_see_a_call(): void
    {
        $outsider = $this->makeUser('resident');
        $callId = $this->startCall()['data']['id'];

        Sanctum::actingAs($outsider);
        $this->postJson("/api/conversations/{$this->conversation->id}/calls", ['type' => 'audio'])->assertNotFound();
        $this->getJson("/api/calls/{$callId}")->assertNotFound();
        $this->postJson("/api/calls/{$callId}/accept")->assertNotFound();
    }

    public function test_only_one_call_at_a_time_per_conversation(): void
    {
        $this->startCall();

        $this->postJson("/api/conversations/{$this->conversation->id}/calls", ['type' => 'audio'])
            ->assertStatus(409);
    }

    public function test_a_stale_ringing_call_does_not_block_a_new_one(): void
    {
        $callId = $this->startCall()['data']['id'];
        Call::whereKey($callId)->update(['created_at' => now()->subMinutes(5)]);

        $this->postJson("/api/conversations/{$this->conversation->id}/calls", ['type' => 'audio'])
            ->assertCreated();
    }

    public function test_callee_accepts_and_both_join_the_same_room(): void
    {
        $started = $this->startCall();

        Sanctum::actingAs($this->personnel);
        $accepted = $this->postJson("/api/calls/{$started['data']['id']}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->json();

        $claims = $this->decodeToken($accepted['livekit']['token']);
        $this->assertSame('user-' . $this->personnel->id, $claims['sub']);
        $this->assertSame($started['livekit']['room'], $claims['video']['room']);

        Event::assertDispatched(CallSignal::class, fn ($e) => $e->signal === 'call.accepted'
            && $e->recipientId === $this->resident->id);

        // Accepting twice doesn't work
        $this->postJson("/api/calls/{$started['data']['id']}/accept")->assertStatus(409);
    }

    public function test_the_caller_cannot_accept_their_own_call(): void
    {
        $callId = $this->startCall()['data']['id'];

        $this->postJson("/api/calls/{$callId}/accept")->assertNotFound();
    }

    public function test_declining_tells_the_caller_and_is_recorded_in_the_chat(): void
    {
        $callId = $this->startCall()['data']['id'];

        Sanctum::actingAs($this->personnel);
        $this->postJson("/api/calls/{$callId}/decline")->assertJsonPath('data.status', 'declined');

        Event::assertDispatched(CallSignal::class, fn ($e) => $e->signal === 'call.ended'
            && $e->recipientId === $this->resident->id);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'body' => '📞 Declined voice call',
        ]);
    }

    public function test_unanswered_call_is_recorded_as_missed(): void
    {
        $callId = $this->startCall('video')['data']['id'];

        $this->postJson("/api/calls/{$callId}/end", ['reason' => 'timeout'])
            ->assertJsonPath('data.status', 'missed');

        $this->assertDatabaseHas('messages', ['body' => '🎥 Missed video call']);
        Event::assertDispatched(CallSignal::class, fn ($e) => $e->signal === 'call.ended'
            && $e->recipientId === $this->personnel->id);
    }

    public function test_hanging_up_an_answered_call_records_its_length(): void
    {
        $callId = $this->startCall()['data']['id'];

        Sanctum::actingAs($this->personnel);
        $this->postJson("/api/calls/{$callId}/accept")->assertOk();
        Call::whereKey($callId)->update(['accepted_at' => now()->subSeconds(125)]);

        $this->postJson("/api/calls/{$callId}/end")->assertJsonPath('data.status', 'ended');
        $this->assertDatabaseHas('messages', ['body' => '📞 Voice call · 02:05']);

        // Ending again changes nothing
        $this->postJson("/api/calls/{$callId}/end")->assertJsonPath('data.status', 'ended');
        $this->assertSame(1, $this->conversation->messages()->count());
    }

    public function test_incoming_call_rings_the_phone_through_firebase(): void
    {
        // A throwaway service account key, like the one from the Firebase console.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, json_encode([
            'project_id' => 'qrt-test',
            'client_email' => 'push@qrt-test.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ]));
        config(['services.fcm.credentials' => $path]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access-token']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/qrt-test/messages/1']),
        ]);
        DeviceToken::create(['user_id' => $this->personnel->id, 'token' => 'personnel-phone']);

        $callId = $this->startCall('video')['data']['id'];

        $sent = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn ($request) => str_contains($request->url(), 'messages:send'))
            ->map(fn ($request) => $request->data()['message'])
            ->values();

        $this->assertCount(1, $sent);
        $this->assertSame('personnel-phone', $sent[0]['token']);
        $this->assertArrayNotHasKey('notification', $sent[0]);
        $this->assertSame('call', $sent[0]['data']['type']);
        $this->assertSame((string) $callId, $sent[0]['data']['call_id']);
        $this->assertSame('video', $sent[0]['data']['call_type']);
        $this->assertSame($this->resident->name, $sent[0]['data']['caller_name']);

        @unlink($path);
    }
}
