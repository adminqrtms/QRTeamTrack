<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\Validator;
use App\Services\IncidentWorkflow;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        IncidentWorkflow::autoResolveOverdue();

        $user = $request->user();
        $query = Report::with(['resident', 'personnel', 'location', 'respondent', 'responders:id,name,phone_number']);

        // Allow filtering by status (e.g., status=pending,ongoing)
        if ($request->has('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }

        // Personnel only see reports from their current active duty location
        if ($user->role === 'personnel') {
            $currentAttendance = Attendance::where('user_id', $user->id)
                ->whereNull('time_out')
                ->latest()
                ->first();

            // On duty: everything at their station. Always: the ones they responded to
            // (so resolved incidents stay in their list, as in the resident's app).
            // ?responded=1: only the ones this personnel responded to (history screen)
            if ($request->boolean('responded')) {
                $currentAttendance = null;
            }
            $query->where(function ($q) use ($currentAttendance, $user) {
                if ($currentAttendance) {
                    $q->where('location_id', $currentAttendance->location_id);
                }
                $q->orWhereHas('responders', fn ($r) => $r->where('users.id', $user->id));
            });
        } 
        // Residents should only see their own submitted reports
        elseif ($user->role === 'resident') {
            $query->where('user_id', $user->id);
        }

        // Fetch reports with related user and assignee data
        $reports = $query->latest()->get();
        return response()->json(['data' => $reports]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'concern' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'nullable|string|in:incident,complaint,daily_report',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'image' => 'nullable|image|max:2048', // Validate as image file, max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            $imagePath = null;
            
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('reports', 'public');
            }

            // Logic: Find personnel on duty at the same location as the resident
            $onDutyPersonnel = Attendance::where('location_id', $user->location_id)
                ->whereNull('time_out')
                ->latest()
                ->first();

            $report = Report::create([
                'user_id' => $user->id, 
                'title' => $request->concern,
                'description' => $request->description,
                'image' => $imagePath,
                'status' => 'pending',
                'type' => $request->type ?? 'incident',
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'assigned_to' => $onDutyPersonnel ? $onDutyPersonnel->user_id : null,
                'location_id' => $user->location_id,
            ]);

            return response()->json([
                'message' => 'Report created successfully',
                'data' => $report
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create report: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        IncidentWorkflow::autoResolveOverdue();
        $report = Report::with(['resident', 'personnel', 'respondent', 'responders:id,name,phone_number', 'resolutionRequester:id,name'])->find($id);

        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        // Residents may only open their own reports
        if ($request->user()->role === 'resident' && (int) $report->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'You can only view your own reports.'], 403);
        }

        return response()->json(['data' => $report]);
    }

    public function update(Request $request, $id)
    {
        $report = Report::find($id);

        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        // Only responders (personnel) and admins change a report's status
        if (!in_array($request->user()->role, ['personnel', 'admin'])) {
            return response()->json(['message' => 'Only responders can update reports.'], 403);
        }

        $request->validate([
            'status' => 'sometimes|required|in:pending,ongoing,in_progress,resolved,closed,false_alarm',
            'action_taken' => 'nullable|string',
        ]);

        $user = $request->user();

        // Only these fields may change here (never the owner, location, etc.)
        $data = $request->only(['action_taken']);

        // Capture respondent details when action is first taken (moving from pending)
        if (in_array($request->status, ['ongoing', 'resolved']) && !$report->respondent_at) {
            $data['respondent_by'] = $user->id;
            $data['respondent_at'] = now();
            $data['assigned_to'] = $report->assigned_to ?? $user->id;
        }

        // Personnel who act on a report are part of its response team
        if ($user->role === 'personnel' && $request->filled('status')) {
            $report->addResponder($user);
        }

        // "Resolved" needs the resident's agreement: wait for their confirmation
        $message = 'Report updated successfully';
        if ($request->status === 'resolved' && $report->status !== 'resolved') {
            $report->fill($data)->save();
            app(IncidentWorkflow::class)->requestResolution($report, $user);
            $message = 'Waiting for the resident to confirm that it is resolved.';
        } else {
            if ($request->filled('status')) {
                $data['status'] = $request->status;
            }
            $report->update($data);
        }

        // Reload relationships to include respondent details in the response
        $report->load(['resident', 'personnel', 'location', 'respondent', 'responders:id,name,phone_number']);

        return response()->json(['message' => $message, 'data' => $report]);
    }

    /** Personnel joins the response team of a report. */
    public function join(Request $request, $id)
    {
        $report = Report::find($id);
        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $user = $request->user();
        $workflow = app(IncidentWorkflow::class);
        if (!$report->hasResponder($user) && !$workflow->canJoin($report, $user)) {
            return response()->json(['message' => 'Time in at this station first to join the response.'], 403);
        }
        if (in_array($report->status, ['resolved', 'closed', 'false_alarm'])) {
            return response()->json(['message' => 'This report is already closed.'], 422);
        }

        $report->addResponder($user);
        if ($report->status === 'pending') {
            $report->forceFill([
                'status' => 'ongoing',
                'respondent_by' => $report->respondent_by ?? $user->id,
                'respondent_at' => $report->respondent_at ?? now(),
                'assigned_to' => $report->assigned_to ?? $user->id,
            ])->save();
        }

        return response()->json([
            'message' => 'You joined the response.',
            'data' => $report->load('responders:id,name,phone_number'),
        ]);
    }

    /** Resident confirms (resolved = true) or rejects the responder's "resolved". */
    public function answerResolution(Request $request, $id)
    {
        $request->validate(['resolved' => 'required|boolean']);

        $report = Report::find($id);
        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }
        if ((int) $report->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Only the resident who reported it can confirm.'], 403);
        }
        if (!$report->isAwaitingConfirmation()) {
            return response()->json(['message' => 'This report is not waiting for your confirmation.'], 422);
        }

        app(IncidentWorkflow::class)->residentAnswer($report, $request->user(), $request->boolean('resolved'));

        return response()->json([
            'message' => $request->boolean('resolved') ? 'Thank you! The report is now resolved.' : 'The responders were told it is not yet resolved.',
            'data' => $report->fresh()->load('responders:id,name,phone_number'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $report = Report::find($id);
        if (!$report) return response()->json(['message' => 'Report not found'], 404);

        // Only the resident who sent it, or an admin, may delete a report
        $user = $request->user();
        if ($user->role !== 'admin' && (int) $report->user_id !== (int) $user->id) {
            return response()->json(['message' => 'You can only delete your own reports.'], 403);
        }

        $report->delete();
        return response()->json(['message' => 'Report deleted successfully']);
    }
}
