<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Report::with(['resident', 'personnel', 'location', 'respondent']);

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

            if (!$currentAttendance) {
                return response()->json(['data' => []]);
            }

            $query->where('location_id', $currentAttendance->location_id);
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

    public function show($id)
    {
        $report = Report::with(['resident', 'personnel', 'respondent'])->find($id);

        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        return response()->json(['data' => $report]);
    }

    public function update(Request $request, $id)
    {
        $report = Report::find($id);

        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $data = $request->all();

        // Capture respondent details when action is first taken (moving from pending)
        if (in_array($request->status, ['ongoing', 'resolved']) && !$report->respondent_at) {
            $data['respondent_by'] = $request->user()->id;
            $data['respondent_at'] = now();
            $data['assigned_to'] = $report->assigned_to ?? $request->user()->id;
        }

        $report->update($data);

        // Reload relationships to include respondent details in the response
        $report->load(['resident', 'personnel', 'location', 'respondent']);

        return response()->json(['message' => 'Report updated successfully', 'data' => $report]);
    }

    public function destroy($id)
    {
        $report = Report::find($id);
        if (!$report) return response()->json(['message' => 'Report not found'], 404);

        $report->delete();
        return response()->json(['message' => 'Report deleted successfully']);
    }
}
