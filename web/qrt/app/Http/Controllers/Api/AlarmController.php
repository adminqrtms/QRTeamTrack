<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alarm;
use App\Models\Attendance;
use Illuminate\Http\Request;
use App\Models\Location;
use Illuminate\Support\Facades\Validator;

class AlarmController extends Controller
{
    // View all alarms (Latest first)
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Alarm::with(['user', 'responder', 'location']);

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
        $alarms = $query->orderByRaw("FIELD(status, 'triggered', 'responding', 'responded', 'resolved', 'false_alarm')")
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

        return response()->json([
            'status' => 'success',
            'message' => 'Alarm triggered successfully',
            'data' => $alarm
        ], 201);
    }

    // View specific alarm details
    public function show($id)
    {
        $alarm = Alarm::with(['user', 'responder', 'location'])->find($id);

        if (!$alarm) {
            return response()->json(['message' => 'Alarm not found'], 404);
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

        $alarm->status = $request->status;

        if (in_array($request->status, ['responding', 'responded']) && !$alarm->responded_by) {
            $alarm->responded_by = $request->user()->id;
            $alarm->responded_at = now();
        }

        if ($request->has('action_taken')) {
            $alarm->action_taken = $request->action_taken;
        }

        $alarm->save();

        return response()->json(['message' => 'Alarm updated', 'data' => $alarm]);
    }
}
