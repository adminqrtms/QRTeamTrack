<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alarm;
use App\Models\Attendance;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Services\IncidentWorkflow;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AlarmController extends Controller
{
    // View all alarms (Latest first)
    public function index(Request $request)
    {
        IncidentWorkflow::autoResolveOverdue();

        $user = $request->user();
        $query = Alarm::with(['user', 'responder', 'location', 'responders:id,name,phone_number']);

        // Allow filtering by status
        if ($request->has('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }

        // Filter logic: Personnel only see alarms from their assigned location
        if ($user->role === 'personnel') {
            // Get the current active attendance session for the personnel
            $currentAttendance = Attendance::where('user_id', $user->id)
                ->whereNull('time_out')
                ->latest()
                ->first();

            if (!$currentAttendance) {
                return response()->json(['data' => []]);
            }

            // Filter alarms based on the location where they are currently timed in
            $query->where('alarms.location_id', $currentAttendance->location_id);
        } 
        // Residents only see their own triggered alarms
        elseif ($user->role === 'resident') {
            $query->where('user_id', $user->id);
        }
        
        // Prioritize 'triggered' alarms so they appear first in the API response
        // CASE instead of MySQL's FIELD() so this also works on SQLite
        $alarms = $query->orderByRaw("CASE status WHEN 'triggered' THEN 1 WHEN 'responding' THEN 2 WHEN 'responded' THEN 3 WHEN 'awaiting_confirmation' THEN 4 WHEN 'resolved' THEN 5 WHEN 'false_alarm' THEN 6 ELSE 7 END")
            ->latest()
            ->get();
            
        return response()->json(['data' => $alarms]);
    }

    /**
     * Fetch locations/stations that currently have personnel on duty.
     * This is called by the resident when their local area has no responders.
     */
    public function availableStations()
    {
        // Fetch locations that have at least one person timed in (on duty)
        $available = Location::whereHas('attendances', function ($query) {
                $query->whereNull('time_out');
            })
            ->withCount(['attendances as active_count' => function ($query) {
                $query->whereNull('time_out');
            }])
            ->get()
            ->map(function ($location) {
                return [
                    'id' => $location->id,
                    'name' => $location->location_name,
                    'active_count' => $location->active_count,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $available
        ]);
    }

    // Resident triggers an alarm
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $targetLocationId = $request->location_id;

        // 1. If no specific location was chosen, check local on-duty status
        if (!$targetLocationId) {
            // Check if there are personnel at the user's location who haven't taken time_out
            $onDutyCount = Attendance::where('location_id', $user->location_id)
                ->whereNull('time_out')
                ->count();

            // 2. Return 'no_personnel' to trigger the choice dialog in Flutter
            if ($onDutyCount === 0) {
                return response()->json([
                    'status' => 'no_personnel',
                    'message' => 'No personnel on duty in your assigned area.'
                ], 200);
            }

            // Use the resident's home location_id
            $targetLocationId = $user->location_id; 
        }

        if (!$targetLocationId) {
            return response()->json(['message' => 'Your profile is not assigned to a location.'], 400);
        }

        // 3. Create the alarm record
        $alarm = Alarm::create([
            'user_id' => $user->id,
            'location_id' => $targetLocationId,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'status' => 'triggered',
        ]);

        // Alert every personnel on duty at the target location, even if their app is closed
        $onDutyPersonnelIds = Attendance::where('location_id', $targetLocationId)
            ->whereNull('time_out')
            ->pluck('user_id');

        // Shows up in storage/logs/laravel.log, to check who an SOS reached
        Log::info("SOS #{$alarm->id} from {$user->name} at location {$targetLocationId}: "
            . $onDutyPersonnelIds->count() . ' personnel on duty (user ids: ' . $onDutyPersonnelIds->implode(', ') . ')');

        app(PushNotificationService::class)->sendToUsersAfterResponse(
            $onDutyPersonnelIds,
            'EMERGENCY SOS!',
            'Resident ' . $user->name . ' needs help' . ($user->address ? ' at ' . $user->address : '') . '.',
            [
                'type' => 'alarm',
                'alarm_id' => $alarm->id,
                'tag' => 'alarm_' . $alarm->id,
            ],
            PushNotificationService::CHANNEL_EMERGENCY,
            fullScreen: true,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Alarm triggered successfully',
            'data' => $alarm
        ], 201);
    }

    // View specific alarm details
    public function show(Request $request, $id)
    {
        IncidentWorkflow::autoResolveOverdue();
        $alarm = Alarm::with(['user', 'responder', 'location', 'responders:id,name,phone_number', 'resolutionRequester:id,name'])->find($id);

        if (!$alarm) {
            return response()->json(['message' => 'Alarm not found'], 404);
        }

        // Residents may only open their own SOS alarms
        if ($request->user()->role === 'resident' && (int) $alarm->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'You can only view your own alarms.'], 403);
        }

        return response()->json(['data' => $alarm]);
    }

    // Personnel responds to or resolves an alarm
    public function update(Request $request, $id)
    {
        $alarm = Alarm::find($id);

        if (!$alarm) {
            return response()->json(['message' => 'Alarm not found'], 404);
        }

        $request->validate([
            'status' => 'required|in:triggered,responding,responded,resolved,false_alarm',
            'action_taken' => 'nullable|string',
        ]);

        // Residents may only cancel (mark as false alarm) their own SOS
        $user = $request->user();
        if (!in_array($user->role, ['personnel', 'admin'])) {
            if ((int) $alarm->user_id !== (int) $user->id || $request->status !== 'false_alarm') {
                return response()->json(['message' => 'You can only cancel your own SOS.'], 403);
            }
        }

        // Personnel who act on an SOS are part of its response team
        if ($user->role === 'personnel' && $request->status !== 'false_alarm') {
            $alarm->addResponder($user);
        }

        $requestResolution = $request->status === 'resolved' && $alarm->status !== 'resolved'
            && $user->role !== 'resident';
        if (!$requestResolution) {
            $alarm->status = $request->status;
        }
        $responderJustAssigned = false;

        if (in_array($request->status, ['responding', 'responded']) && !$alarm->responded_by) {
            $alarm->responded_by = $request->user()->id;
            $alarm->responded_at = now();
            $responderJustAssigned = true;
        }

        if ($request->has('action_taken')) {
            $alarm->action_taken = $request->action_taken;
        }

        $alarm->save();

        // "Resolved" needs the resident's agreement: wait for their confirmation
        if ($requestResolution) {
            app(IncidentWorkflow::class)->requestResolution($alarm, $user);
        }

        // Let the resident know help is on the way
        if ($responderJustAssigned && (int) $alarm->user_id !== (int) $request->user()->id) {
            app(PushNotificationService::class)->sendToUsersAfterResponse(
                [$alarm->user_id],
                'Help is on the way!',
                'Rescuer ' . $request->user()->name . ' is now responding to your SOS.',
                [
                    'type' => 'alarm',
                    'alarm_id' => $alarm->id,
                    'tag' => 'alarm_' . $alarm->id,
                ],
                PushNotificationService::CHANNEL_EMERGENCY,
            );
        }

        return response()->json([
            'message' => $requestResolution ? 'Waiting for the resident to confirm that it is resolved.' : 'Alarm updated',
            'data' => $alarm->load('responders:id,name,phone_number'),
        ]);
    }

    /** Personnel joins the response team of an SOS. */
    public function join(Request $request, $id)
    {
        $alarm = Alarm::find($id);
        if (!$alarm) {
            return response()->json(['message' => 'Alarm not found'], 404);
        }

        $user = $request->user();
        $workflow = app(IncidentWorkflow::class);
        if (!$alarm->hasResponder($user) && !$workflow->canJoin($alarm, $user)) {
            return response()->json(['message' => 'Time in at this station first to join the response.'], 403);
        }
        if (in_array($alarm->status, ['resolved', 'false_alarm'])) {
            return response()->json(['message' => 'This SOS is already closed.'], 422);
        }

        $isFirst = !$alarm->responded_by;
        $alarm->addResponder($user);
        if ($alarm->status === 'triggered') {
            $alarm->forceFill([
                'status' => 'responding',
                'responded_by' => $alarm->responded_by ?? $user->id,
                'responded_at' => $alarm->responded_at ?? now(),
            ])->save();
        }

        if ($isFirst) {
            app(PushNotificationService::class)->sendToUsersAfterResponse(
                [$alarm->user_id],
                'Help is on the way!',
                'Rescuer ' . $user->name . ' is now responding to your SOS.',
                ['type' => 'alarm', 'alarm_id' => $alarm->id, 'tag' => 'alarm_' . $alarm->id],
                PushNotificationService::CHANNEL_EMERGENCY,
            );
        }

        return response()->json([
            'message' => 'You joined the response.',
            'data' => $alarm->load('responders:id,name,phone_number'),
        ]);
    }

    /** Resident confirms (resolved = true) or rejects the responder's "resolved". */
    public function answerResolution(Request $request, $id)
    {
        $request->validate(['resolved' => 'required|boolean']);

        $alarm = Alarm::find($id);
        if (!$alarm) {
            return response()->json(['message' => 'Alarm not found'], 404);
        }
        if ((int) $alarm->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Only the resident who sent the SOS can confirm.'], 403);
        }
        if (!$alarm->isAwaitingConfirmation()) {
            return response()->json(['message' => 'This SOS is not waiting for your confirmation.'], 422);
        }

        app(IncidentWorkflow::class)->residentAnswer($alarm, $request->user(), $request->boolean('resolved'));

        return response()->json([
            'message' => $request->boolean('resolved') ? 'Thank you! Your SOS is now resolved.' : 'The responders were told it is not yet resolved.',
            'data' => $alarm->fresh()->load('responders:id,name,phone_number'),
        ]);
    }
}
