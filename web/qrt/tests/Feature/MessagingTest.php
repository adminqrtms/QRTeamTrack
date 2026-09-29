<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Models\Alarm;
use App\Models\Attendance;
use App\Models\Conversation;
use App\Models\Location;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;
    private User $resident;
    private User $personnel;

    protected function setUp(): void
    {
        parent::setUp();

        // Migration 2026_03_25_160000 renames these report columns, but the
        // Report model still uses the original names. Align the test schema
        // with the model so report-based chats can be exercised.
        Schema::table('reports', function (Blueprint $table) {
            $table->renameColumn('resident_id', 'user_id');
            $table->renameColumn('concern', 'title');
            $table->renameColumn('assigned_personnel_id', 'assigned_to');
        });

        $this->location = Location::create(['location_name' => 'Station 1', 'barangay' => 'Poblacion', 'latitude' => 0, 'longitude' => 0]);

        // The very first user becomes an admin (see User::booted), so create one up front.
        $this->makeUser(['role' => 'admin']);

        $this->resident = $this->makeUser([
            'role' => 'resident',
            'is_active' => true,
            'location_id' => $this->location->id,
        ]);
        $this->personnel = $this->makeUser(['role' => 'personnel', 'is_active' => true]);
    }

    /**
     * The users table has no email_verified_at column, so the default
     * factory can't be used here.
     */
    private function makeUser(array $attributes): User
    {
        static $count = 0;
        $count++;

        return User::create(array_merge([
            'name' => "User $count",
            'email' => "user$count@example.com",
            'password' => 'password',
        ], $attributes));
    }

    private function timeIn(User $personnel): void
    {
        Attendance::create([
            'user_id' => $personnel->id,
            'location_id' => $this->location->id,
            'time_in' => now(),
            'date' => now()->toDateString(),
        ]);
    }

    private function makeReport(array $attributes = []): Report
    {
        return Report::create(array_merge([
            'user_id' => $this->resident->id,
            'title' => 'Noise complaint',
            'description' => 'Loud music',
            'status' => 'pending',
            'type' => 'complaint',
            'location_id' => $this->location->id,
        ], $attributes));
    }

    public function test_resident_can_start_direct_chat_with_personnel(): void
    {
        Sanctum::actingAs($this->resident);

        $response = $this->postJson('/api/conversations', ['personnel_id' => $this->personnel->id]);

        $response->assertCreated()
            ->assertJsonPath('data.other_user.id', $this->personnel->id)
            ->assertJsonPath('data.subject', null);

        // Opening it again returns the same conversation.
        $this->postJson('/api/conversations', ['personnel_id' => $this->personnel->id])
            ->assertOk()
            ->assertJsonPath('data.id', $response->json('data.id'));
    }

    public function test_personnel_cannot_start_direct_chat(): void
    {
        $otherPersonnel = $this->makeUser(['role' => 'personnel', 'is_active' => true]);
        Sanctum::actingAs($this->personnel);

        $this->postJson('/api/conversations', ['personnel_id' => $otherPersonnel->id])
            ->assertForbidden();
    }

    public function test_resident_cannot_direct_chat_with_a_non_personnel_user(): void
    {
        $otherResident = $this->makeUser(['role' => 'resident', 'is_active' => true]);
        Sanctum::actingAs($this->resident);

        $this->postJson('/api/conversations', ['personnel_id' => $otherResident->id])
            ->assertNotFound();
    }

    public function test_report_chat_links_resident_and_assigned_personnel(): void
    {
        $report = $this->makeReport(['assigned_to' => $this->personnel->id]);

        Sanctum::actingAs($this->resident);
        $fromResident = $this->postJson('/api/conversations', ['report_id' => $report->id])
            ->assertCreated()
            ->assertJsonPath('data.other_user.id', $this->personnel->id)
            ->assertJsonPath('data.subject.type', 'report');

        // The personnel opening the same report lands in the same conversation.
        Sanctum::actingAs($this->personnel);
        $this->postJson('/api/conversations', ['report_id' => $report->id])
            ->assertOk()
            ->assertJsonPath('data.id', $fromResident->json('data.id'))
            ->assertJsonPath('data.other_user.id', $this->resident->id);
    }

    public function test_resident_cannot_chat_about_unassigned_or_others_reports(): void
    {
        $unassigned = $this->makeReport();
        $otherResident = $this->makeUser(['role' => 'resident', 'is_active' => true]);
        $othersReport = $this->makeReport(['user_id' => $otherResident->id, 'assigned_to' => $this->personnel->id]);

        Sanctum::actingAs($this->resident);
        $this->postJson('/api/conversations', ['report_id' => $unassigned->id])->assertStatus(422);
        $this->postJson('/api/conversations', ['report_id' => $othersReport->id])->assertForbidden();
    }

    public function test_personnel_must_be_on_duty_at_the_location_or_assigned(): void
    {
        $report = $this->makeReport();

        Sanctum::actingAs($this->personnel);
        $this->postJson('/api/conversations', ['report_id' => $report->id])->assertForbidden();

        $this->timeIn($this->personnel);
        $this->postJson('/api/conversations', ['report_id' => $report->id])->assertCreated();
    }

    public function test_alarm_chat_requires_a_responder(): void
    {
        $alarm = Alarm::create([
            'user_id' => $this->resident->id,
            'location_id' => $this->location->id,
            'latitude' => 1,
            'longitude' => 1,
            'status' => 'triggered',
        ]);

        Sanctum::actingAs($this->resident);
        $this->postJson('/api/conversations', ['alarm_id' => $alarm->id])->assertStatus(422);

        $alarm->update(['status' => 'responding', 'responded_by' => $this->personnel->id]);
        $this->postJson('/api/conversations', ['alarm_id' => $alarm->id])
            ->assertCreated()
            ->assertJsonPath('data.other_user.id', $this->personnel->id)
            ->assertJsonPath('data.subject.type', 'alarm');
    }

    public function test_sending_messages_unread_counts_and_read_receipts(): void
    {
        Event::fake([MessageSent::class, MessagesRead::class]);

        $conversation = Conversation::create([
            'resident_id' => $this->resident->id,
            'personnel_id' => $this->personnel->id,
        ]);

        Sanctum::actingAs($this->resident);
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Hello'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Hello')
            ->assertJsonPath('data.sender_id', $this->resident->id);

        Event::assertDispatched(MessageSent::class, fn ($event) => $event->recipientId === $this->personnel->id);

        // Empty messages are rejected.
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => ''])
            ->assertStatus(422);

        Sanctum::actingAs($this->personnel);
        $this->getJson('/api/conversations/unread-count')->assertJson(['unread_count' => 1]);
        $this->getJson('/api/conversations')
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.last_message.body', 'Hello');

        $this->postJson("/api/conversations/{$conversation->id}/read")->assertOk();
        Event::assertDispatched(MessagesRead::class);

        $this->getJson('/api/conversations/unread-count')->assertJson(['unread_count' => 0]);
    }

    public function test_messages_can_include_an_image(): void
    {
        Storage::fake('public');
        Event::fake([MessageSent::class]);

        $conversation = Conversation::create([
            'resident_id' => $this->resident->id,
            'personnel_id' => $this->personnel->id,
        ]);

        Sanctum::actingAs($this->personnel);
        $response = $this->post("/api/conversations/{$conversation->id}/messages", [
            'image' => UploadedFile::fake()->image('scene.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        Storage::disk('public')->assertExists($response->json('data.image'));
    }

    public function test_message_history_paging(): void
    {
        $conversation = Conversation::create([
            'resident_id' => $this->resident->id,
            'personnel_id' => $this->personnel->id,
        ]);
        for ($i = 1; $i <= 60; $i++) {
            $conversation->messages()->create(['sender_id' => $this->resident->id, 'body' => "Message $i"]);
        }

        Sanctum::actingAs($this->personnel);

        $latest = $this->getJson("/api/conversations/{$conversation->id}/messages")->assertOk()->json('data');
        $this->assertCount(50, $latest);
        $this->assertSame('Message 11', $latest[0]['body']);
        $this->assertSame('Message 60', $latest[49]['body']);

        $older = $this->getJson("/api/conversations/{$conversation->id}/messages?before_id={$latest[0]['id']}")->json('data');
        $this->assertCount(10, $older);
        $this->assertSame('Message 1', $older[0]['body']);

        $newer = $this->getJson("/api/conversations/{$conversation->id}/messages?after_id={$latest[47]['id']}")->json('data');
        $this->assertSame(['Message 59', 'Message 60'], array_column($newer, 'body'));
    }

    public function test_outsiders_cannot_read_or_post_in_a_conversation(): void
    {
        $conversation = Conversation::create([
            'resident_id' => $this->resident->id,
            'personnel_id' => $this->personnel->id,
        ]);
        $outsider = $this->makeUser(['role' => 'resident', 'is_active' => true]);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/conversations/{$conversation->id}/messages")->assertNotFound();
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'hi'])->assertNotFound();
        $this->getJson('/api/conversations')->assertJsonCount(0, 'data');
    }

    public function test_private_channel_authorization(): void
    {
        config(['broadcasting.default' => 'reverb']);
        config(['broadcasting.connections.reverb.key' => 'test-key']);
        config(['broadcasting.connections.reverb.secret' => 'test-secret']);
        config(['broadcasting.connections.reverb.app_id' => 'test']);
        // Channels were registered on the test's "null" broadcaster at boot.
        require base_path('routes/channels.php');

        $conversation = Conversation::create([
            'resident_id' => $this->resident->id,
            'personnel_id' => $this->personnel->id,
        ]);
        $outsider = $this->makeUser(['role' => 'resident', 'is_active' => true]);

        $auth = fn (string $channel) => $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
        ]);

        Sanctum::actingAs($this->resident);
        $auth("private-conversation.{$conversation->id}")->assertOk()->assertJsonStructure(['auth']);
        $auth("private-user.{$this->resident->id}")->assertOk();
        $auth("private-user.{$this->personnel->id}")->assertForbidden();

        Sanctum::actingAs($outsider);
        $auth("private-conversation.{$conversation->id}")->assertForbidden();
    }

    public function test_message_is_saved_even_if_the_websocket_server_is_down(): void
    {
        // Point broadcasting at a Reverb server that isn't running.
        config(['broadcasting.default' => 'reverb']);
        config(['broadcasting.connections.reverb.key' => 'test-key']);
        config(['broadcasting.connections.reverb.secret' => 'test-secret']);
        config(['broadcasting.connections.reverb.app_id' => 'test']);
        config(['broadcasting.connections.reverb.options.host' => '127.0.0.1']);
        config(['broadcasting.connections.reverb.options.port' => 1]);
        config(['broadcasting.connections.reverb.options.scheme' => 'http']);
        config(['broadcasting.connections.reverb.options.useTLS' => false]);

        $conversation = Conversation::create([
            'resident_id' => $this->resident->id,
            'personnel_id' => $this->personnel->id,
        ]);

        Sanctum::actingAs($this->resident);
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Still delivered'])
            ->assertCreated();

        $this->assertDatabaseHas('messages', ['body' => 'Still delivered']);
    }
}
