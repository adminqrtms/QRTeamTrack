<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Manage Report - List all reports.
     * Can handle "Manage report if the incident is already resolved" by showing status.
     */
    public function index(Request $request)
    {
        $query = Report::with(['user', 'personnel'])->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $reports = $query->paginate(10);
        return view('admin.reports.index', compact('reports'));
    }

    /**
     * Display the specified report.
     */
    public function show($id)
    {
        $report = Report::with(['user', 'personnel', 'location'])->findOrFail($id);
        return view('admin.reports.show', compact('report'));
    }

    /**
     * Update report status (e.g., mark as Resolved).
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,resolved,closed',
        ]);

        $report = Report::findOrFail($id);
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
