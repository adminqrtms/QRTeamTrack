<?php

namespace App\Http\Controllers\Api;

use App\Events\CallSignal;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\User;
use App\Services\LiveKitService;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CallController extends Controller
{
    public function __construct(
        private LiveKitService $liveKit,
        private PushNotificationService $push,
    ) {
    }

    /**
     * Start a call in a conversation: the other person's phone rings.
     */
    public function start(Request $request, $conversationId)
    {
        $request->validate(['type' => 'required|in:audio,video']);

        $user = $request->user();
        $conversation = Conversation::forUser($user)->find($conversationId);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }

        // A ringing call left over from an app that closed mid-ring doesn't count after 2 minutes.
        $busy = Call::where('conversation_id', $conversation->id)
            ->where(function ($q) {
                $q->where(fn ($r) => $r->where('status', 'ringing')->where('created_at', '>', now()->subMinutes(2)))
                    ->orWhere(fn ($a) => $a->where('status', 'accepted')->where('accepted_at', '>', now()->subHours(3)));
            })
            ->exists();

        if ($busy) {
            return response()->json(['message' => 'A call is already in progress.'], 409);
        }

        $call = Call::create([
            'conversation_id' => $conversation->id,
            'caller_id' => $user->id,
            'callee_id' => $conversation->otherParticipantId($user),
            'type' => $request->type,
            'status' => 'ringing',
            'room_name' => 'call-' . Str::uuid(),
        ]);

        $payload = $this->present($call);
        $this->broadcastSafely(new CallSignal($call, $call->callee_id, 'call.incoming', $payload));

        // Ring the other phone even if the app is closed
        $this->push->sendDataToUsersAfterResponse([$call->callee_id], [
            'type' => 'call',
            'call_id' => $call->id,
            'call_type' => $call->type,
            'caller_name' => $user->name,
            'title' => 'Incoming ' . ($call->type === 'video' ? 'video' : 'voice') . ' call',
            'body' => $user->name . ' is calling you',
        ]);

        return response()->json([
            'data' => $payload,
            'livekit' => $this->joinInfo($request, $call, $user),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $call = $this->findForUser($request->user(), $id);
        if (!$call) {
            return response()->json(['message' => 'Call not found'], 404);
        }

        return response()->json(['data' => $this->present($call)]);
    }

    /**
     * The person being called answers.
     */
    public function accept(Request $request, $id)
    {
        $user = $request->user();
        $call = $this->findForUser($user, $id);

        if (!$call || $call->callee_id !== $user->id) {
            return response()->json(['message' => 'Call not found'], 404);
        }
        if ($call->status !== 'ringing') {
            return response()->json(['message' => 'This call has already ended.', 'data' => $this->present($call)], 409);
        }

        $call->update(['status' => 'accepted', 'accepted_at' => now()]);

        $payload = $this->present($call);
        $this->broadcastSafely(new CallSignal($call, $call->caller_id, 'call.accepted', $payload));

        return response()->json([
            'data' => $payload,
            'livekit' => $this->joinInfo($request, $call, $user),
        ]);
    }

    /**
     * The person being called rejects it.
     */
    public function decline(Request $request, $id)
    {
        $user = $request->user();
        $call = $this->findForUser($user, $id);

        if (!$call || $call->callee_id !== $user->id) {
            return response()->json(['message' => 'Call not found'], 404);
        }

        if ($call->status === 'ringing') {
            $this->finish($call, 'declined', $user);
        }

        return response()->json(['data' => $this->present($call)]);
    }

    /**
     * Hang up (either side). While still ringing, the caller cancels it;
     * reason=timeout marks it as missed (no answer).
     */
    public function end(Request $request, $id)
    {
        $user = $request->user();
        $call = $this->findForUser($user, $id);

        if (!$call) {
            return response()->json(['message' => 'Call not found'], 404);
        }

        if ($call->status === 'ringing') {
            if ($user->id === $call->callee_id) {
                $this->finish($call, 'declined', $user);
            } else {
                $this->finish($call, $request->input('reason') === 'timeout' ? 'missed' : 'cancelled', $user);
            }
        } elseif ($call->status === 'accepted') {
            $this->finish($call, 'ended', $user);
        }

        return response()->json(['data' => $this->present($call)]);
    }

    private function finish(Call $call, string $status, User $by): void
    {
        $call->update(['status' => $status, 'ended_at' => now()]);

        $payload = $this->present($call);
        $this->broadcastSafely(new CallSignal($call, $call->otherParticipantId($by), 'call.ended', $payload));

        if ($status !== 'ended') {
            // Stop the ringing screen/notification on the other phone if it's in the background
            $this->push->sendDataToUsersAfterResponse([$call->callee_id], [
                'type' => 'call_ended',
                'call_id' => $call->id,
            ]);
        }

        $this->recordInChat($call);

        if (in_array($status, ['missed', 'cancelled'], true)) {
            $caller = $call->caller;
            $this->push->sendToUsersAfterResponse(
                [$call->callee_id],
                'Missed ' . ($call->type === 'video' ? 'video' : 'voice') . ' call',
                $caller?->name . ' tried to call you.',
                [
                    'type' => 'chat',
                    'conversation_id' => $call->conversation_id,
                    'tag' => 'chat_' . $call->conversation_id,
                ],
            );
        }
    }

    /**
     * Adds a line like "📞 Voice call · 2:05" or "🎥 Missed video call" to the chat.
     */
    private function recordInChat(Call $call): void
    {
        $label = $call->type === 'video' ? 'video call' : 'voice call';
        $icon = $call->type === 'video' ? '🎥' : '📞';

        $text = match ($call->status) {
            'ended' => $icon . ' ' . ucfirst($label) . ' · ' . gmdate($call->durationSeconds() >= 3600 ? 'G:i:s' : 'i:s', $call->durationSeconds()),
            'declined' => $icon . ' Declined ' . $label,
            default => $icon . ' Missed ' . $label,
        };

        $conversation = $call->conversation;
        $message = $conversation->messages()->create([
            'sender_id' => $call->caller_id,
            'body' => $text,
        ]);
        $conversation->update(['last_message_at' => $message->created_at]);

        // Both screens should show it (the caller sent it, the callee receives it).
        try {
            broadcast(new MessageSent($message, $call->callee_id));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function joinInfo(Request $request, Call $call, User $user): array
    {
        return [
            'url' => $this->liveKit->url($request),
            'token' => $this->liveKit->token($call->room_name, 'user-' . $user->id, $user->name),
            'room' => $call->room_name,
        ];
    }

    private function findForUser(User $user, $id): ?Call
    {
        $call = Call::find($id);

        return $call && $call->hasParticipant($user) ? $call : null;
    }

    private function present(Call $call): array
    {
        $call->loadMissing(['caller', 'callee']);

        $person = fn (?User $u) => $u ? [
            'id' => $u->id,
            'name' => $u->name,
            'role' => $u->role,
            'avatar' => $u->avatar,
        ] : null;

        return [
            'id' => $call->id,
            'conversation_id' => $call->conversation_id,
            'type' => $call->type,
            'status' => $call->status,
            'caller' => $person($call->caller),
            'callee' => $person($call->callee),
            'accepted_at' => $call->accepted_at,
            'ended_at' => $call->ended_at,
            'created_at' => $call->created_at,
        ];
    }

    private function broadcastSafely($event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
