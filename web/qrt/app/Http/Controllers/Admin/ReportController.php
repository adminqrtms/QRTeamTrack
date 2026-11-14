<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Alarm;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Manage Report - List all reports.
     * Can handle "Manage report if the incident is already resolved" by showing status.
     */
    /**
     * Reports tab: incident reports AND SOS alarms from the app, newest first.
     * Filters: ?type=report|sos  ?status=active|awaiting|resolved
     */
    public function index(Request $request)
    {
        \App\Services\IncidentWorkflow::autoResolveOverdue();

        $type = $request->query('type');
        $status = $request->query('status');
        $closed = ['resolved', 'closed', 'false_alarm', Report::AWAITING_CONFIRMATION];

        $applyStatus = function ($query) use ($status, $closed) {
            match ($status) {
                'active' => $query->whereNotIn('status', $closed),
                'awaiting' => $query->where('status', Report::AWAITING_CONFIRMATION),
                'resolved' => $query->where('status', 'resolved'),
                default => null,
            };

            return $query;
        };

        $items = collect();

        if ($type !== 'sos') {
            $items = $items->concat($applyStatus(Report::with(['user:id,name', 'responders:id,name']))->get()
                ->map(fn (Report $r) => (object) [
                    'kind' => 'report',
                    'ref' => '#INC-' . $r->id,
                    'title' => $r->title,
                    'reporter' => $r->user->name ?? 'Unknown',
                    'status' => $r->status,
                    'method' => $r->resolution_method,
                    'responders' => $r->responders->pluck('name'),
                    'created_at' => $r->created_at,
                    'updated_at' => $r->updated_at,
                    'url' => route('admin.reports.show', $r->id),
                    'key' => 'report-' . $r->id,
                ]));
        }

        if ($type !== 'report') {
            $items = $items->concat($applyStatus(Alarm::with(['user:id,name', 'responders:id,name', 'location:id,location_name']))->get()
                ->map(fn (Alarm $a) => (object) [
                    'kind' => 'sos',
                    'ref' => '#SOS-' . $a->id,
                    'title' => 'SOS emergency' . ($a->location ? ' – ' . $a->location->location_name : ''),
                    'reporter' => $a->user->name ?? 'Unknown',
                    'status' => $a->status,
                    'method' => $a->resolution_method,
                    'responders' => $a->responders->pluck('name'),
                    'created_at' => $a->created_at,
                    'updated_at' => $a->updated_at,
                    'url' => route('admin.sos.show', $a->id),
                    'key' => 'sos-' . $a->id,
                ]));
        }

        $items = $items->sortByDesc('created_at')->values();
        $perPage = 15;
        $page = max(1, (int) $request->query('page', 1));
        $reports = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.reports.index', compact('reports', 'type', 'status'));
    }

    /** One SOS alarm: resident, place, response team and resolution. */
    public function showSos($id)
    {
        \App\Services\IncidentWorkflow::autoResolveOverdue();
        $alarm = Alarm::with(['user', 'location', 'responders', 'resolutionRequester:id,name'])->findOrFail($id);

        return view('admin.reports.sos', compact('alarm'));
    }

    /**
     * Display the specified report.
     */
    public function show($id)
    {
        \App\Services\IncidentWorkflow::autoResolveOverdue();
        $report = Report::with(['user', 'personnel', 'location', 'responders', 'resolutionRequester:id,name'])->findOrFail($id);
        return view('admin.reports.show', compact('report'));
    }

    /**
     * Update report status (e.g., mark as Resolved).
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,ongoing,in_progress,resolved,closed',
        ]);

        $report = Report::findOrFail($id);

        // Resolving needs the resident's agreement, even from the admin
        if ($request->status === 'resolved' && $report->status !== 'resolved') {
            app(\App\Services\IncidentWorkflow::class)->requestResolution($report, $request->user());

            return back()->with('success', 'The resident has been asked to confirm that the report is resolved.');
        }

        $report->status = $request->status;
        $report->save();

        return back()->with('success', 'Report status updated successfully.');
    }

    /**
     * Monthly Report.
     * Aggregates reports for the current month or a selected month.
     */
    public function monthly(Request $request)
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        
        $reports = Report::where('created_at', 'like', "$month%")->get();
        $totalIncidents = $reports->count();
        $resolvedCount = $reports->where('status', 'resolved')->count();

        return view('admin.reports.monthly', compact('reports', 'month', 'totalIncidents', 'resolvedCount'));
    }
}
