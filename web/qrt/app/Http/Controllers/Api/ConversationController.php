<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Http\Controllers\Controller;
use App\Models\Alarm;
use App\Models\Attendance;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConversationController extends Controller
{
    /**
     * List the logged-in user's conversations, most recent activity first.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $conversations = Conversation::forUser($user)
            ->with(['resident', 'personnel', 'report', 'alarm', 'latestMessage'])
            ->withCount(['messages as unread_count' => function ($query) use ($user) {
                $query->whereNull('read_at')->where('sender_id', '!=', $user->id);
            }])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($conversation) => $this->present($conversation, $user));

        return response()->json(['data' => $conversations]);
    }

    /**
     * Total unread messages across all conversations (for badges).
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();

        $count = Message::whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->whereHas('conversation', fn ($q) => $q->forUser($user))
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    /**
     * Open (find or create) a conversation.
     *
     * Accepts exactly one of:
     *  - report_id:    chat about a report (resident <-> assigned/responding personnel)
     *  - alarm_id:     chat about an SOS alarm (resident <-> responder)
     *  - personnel_id: a resident starting a direct chat with an active personnel
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'report_id' => 'nullable|integer|required_without_all:alarm_id,personnel_id',
            'alarm_id' => 'nullable|integer',
            'personnel_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if (!in_array($user->role, ['resident', 'personnel'])) {
            return response()->json(['message' => 'Only residents and personnel can use messaging.'], 403);
        }

        if ($request->filled('report_id')) {
            $participants = $this->participantsForReport($user, (int) $request->report_id);
        } elseif ($request->filled('alarm_id')) {
            $participants = $this->participantsForAlarm($user, (int) $request->alarm_id);
        } else {
            $participants = $this->participantsForDirect($user, (int) $request->personnel_id);
        }

        if (isset($participants['error'])) {
            return response()->json(['message' => $participants['error']], $participants['status']);
        }

        $conversation = Conversation::firstOrCreate([
            'resident_id' => $participants['resident_id'],
            'personnel_id' => $participants['personnel_id'],
            'report_id' => $participants['report_id'] ?? null,
            'alarm_id' => $participants['alarm_id'] ?? null,
        ]);

        $conversation->load(['resident', 'personnel', 'report', 'alarm', 'latestMessage'])
            ->loadCount(['messages as unread_count' => function ($query) use ($user) {
                $query->whereNull('read_at')->where('sender_id', '!=', $user->id);
            }]);

        return response()->json([
            'data' => $this->present($conversation, $user),
        ], $conversation->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Show a single conversation.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $conversation = $this->findForUser($user, $id);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }

        $conversation->load(['resident', 'personnel', 'report', 'alarm', 'latestMessage'])
            ->loadCount(['messages as unread_count' => function ($query) use ($user) {
                $query->whereNull('read_at')->where('sender_id', '!=', $user->id);
            }]);

        return response()->json(['data' => $this->present($conversation, $user)]);
    }

    /**
     * Messages in a conversation, oldest first.
     *
     * - no params:  the latest 50 messages
     * - after_id:   messages newer than the given id (used to catch up)
     * - before_id:  the 50 messages older than the given id (load more history)
     */
    public function messages(Request $request, $id)
    {
        $user = $request->user();
        $conversation = $this->findForUser($user, $id);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }

        $query = $conversation->messages();

        if ($request->filled('after_id')) {
            $messages = $query->where('id', '>', (int) $request->after_id)
                ->orderBy('id')
                ->limit(200)
                ->get();
        } else {
            if ($request->filled('before_id')) {
                $query->where('id', '<', (int) $request->before_id);
            }
            $messages = $query->orderByDesc('id')->limit(50)->get()->reverse()->values();
        }

        return response()->json(['data' => $messages]);
    }

    /**
     * Send a message (text and/or image).
     */
    public function sendMessage(Request $request, $id)
    {
        $user = $request->user();
        $conversation = $this->findForUser($user, $id);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'nullable|string|max:5000|required_without:image',
            'image' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('chat', 'public');
        }

        $body = $request->filled('body') ? trim($request->body) : null;

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $body,
            'image' => $imagePath,
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);

        $this->broadcastSafely(new MessageSent($message, $conversation->otherParticipantId($user)));

        return response()->json(['data' => $message], 201);
    }

    /**
     * Mark every message from the other participant as read.
     */
    public function markRead(Request $request, $id)
    {
        $user = $request->user();
        $conversation = $this->findForUser($user, $id);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }

        $lastReadId = $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->max('id');

        if ($lastReadId) {
            $readAt = now();
            $conversation->messages()
                ->whereNull('read_at')
                ->where('sender_id', '!=', $user->id)
                ->where('id', '<=', $lastReadId)
                ->update(['read_at' => $readAt]);

            $this->broadcastSafely(new MessagesRead($conversation, $user->id, $lastReadId, $readAt));
        }

        return response()->json(['message' => 'Messages marked as read', 'last_read_id' => $lastReadId]);
    }

    private function findForUser(User $user, $id): ?Conversation
    {
        return Conversation::forUser($user)->find($id);
    }

    /**
     * Broadcasting must never block chat: if the WebSocket server is down,
     * the message is still saved and the app catches up by fetching.
     */
    private function broadcastSafely($event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Whether a personnel is currently timed in at the given location.
     */
    private function isOnDutyAt(User $user, $locationId): bool
    {
        if (!$locationId) {
            return false;
        }

        return Attendance::where('user_id', $user->id)
            ->whereNull('time_out')
            ->where('location_id', $locationId)
            ->exists();
    }

    private function participantsForReport(User $user, int $reportId): array
    {
        $report = Report::find($reportId);

        if (!$report) {
            return ['error' => 'Report not found', 'status' => 404];
        }

        if ($user->role === 'resident') {
            if ($report->user_id !== $user->id) {
                return ['error' => 'You can only message about your own reports.', 'status' => 403];
            }

            $personnelId = $report->respondent_by ?? $report->assigned_to;
            if (!$personnelId) {
                return ['error' => 'No personnel has been assigned to this report yet.', 'status' => 422];
            }

            return ['resident_id' => $user->id, 'personnel_id' => $personnelId, 'report_id' => $report->id];
        }

        $involved = in_array($user->id, [$report->assigned_to, $report->respondent_by], true)
            || $this->isOnDutyAt($user, $report->location_id);

        if (!$involved) {
            return ['error' => 'You are not handling this report.', 'status' => 403];
        }

        return ['resident_id' => $report->user_id, 'personnel_id' => $user->id, 'report_id' => $report->id];
    }

    private function participantsForAlarm(User $user, int $alarmId): array
    {
        $alarm = Alarm::find($alarmId);

        if (!$alarm) {
            return ['error' => 'Alarm not found', 'status' => 404];
        }

        if ($user->role === 'resident') {
            if ((int) $alarm->user_id !== $user->id) {
                return ['error' => 'You can only message about your own alarms.', 'status' => 403];
            }

            if (!$alarm->responded_by) {
                return ['error' => 'No responder has accepted this alarm yet.', 'status' => 422];
            }

            return ['resident_id' => $user->id, 'personnel_id' => (int) $alarm->responded_by, 'alarm_id' => $alarm->id];
        }

        $involved = (int) $alarm->responded_by === $user->id
            || $this->isOnDutyAt($user, $alarm->location_id);

        if (!$involved) {
            return ['error' => 'You are not handling this alarm.', 'status' => 403];
        }

        return ['resident_id' => (int) $alarm->user_id, 'personnel_id' => $user->id, 'alarm_id' => $alarm->id];
    }

    private function participantsForDirect(User $user, int $personnelId): array
    {
        if ($user->role !== 'resident') {
            return ['error' => 'Personnel can start a chat from a report or an alarm.', 'status' => 403];
        }

        $personnel = User::where('role', 'personnel')
            ->where('is_active', true)
            ->find($personnelId);

        if (!$personnel) {
            return ['error' => 'Personnel not found', 'status' => 404];
        }

        return ['resident_id' => $user->id, 'personnel_id' => $personnel->id];
    }

    /**
     * Shape a conversation for the mobile app, from the given user's point of view.
     */
    private function present(Conversation $conversation, User $user): array
    {
        $other = $conversation->resident_id === $user->id
            ? $conversation->personnel
            : $conversation->resident;

        $subject = null;
        if ($conversation->report) {
            $subject = [
                'type' => 'report',
                'id' => $conversation->report->id,
                'title' => $conversation->report->title,
                'status' => $conversation->report->status,
            ];
        } elseif ($conversation->alarm) {
            $subject = [
                'type' => 'alarm',
                'id' => $conversation->alarm->id,
                'title' => 'SOS Alarm #' . $conversation->alarm->id,
                'status' => $conversation->alarm->status,
            ];
        }

        return [
            'id' => $conversation->id,
            'resident_id' => $conversation->resident_id,
            'personnel_id' => $conversation->personnel_id,
            'report_id' => $conversation->report_id,
            'alarm_id' => $conversation->alarm_id,
            'subject' => $subject,
            'other_user' => $other ? [
                'id' => $other->id,
                'name' => $other->name,
                'role' => $other->role,
                'avatar' => $other->avatar,
                'phone_number' => $other->phone_number,
            ] : null,
            'last_message' => $conversation->latestMessage,
            'last_message_at' => $conversation->last_message_at,
            'unread_count' => (int) ($conversation->unread_count ?? 0),
            'created_at' => $conversation->created_at,
        ];
    }
}
