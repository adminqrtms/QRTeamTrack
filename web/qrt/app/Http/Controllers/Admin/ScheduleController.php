<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Location;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Display Weekly Scheduling for Personnel.
     */
    public function index()
    {
        $schedules = Schedule::with(['user', 'location'])
            ->orderBy('schedule_date_start', 'desc')
            ->orderBy('start_time', 'asc')
            ->get();

        $today = now()->toDateString();
        $locations = Location::withCount(['schedules' => function ($query) use ($today) {
            $query->whereDate('schedule_date_start', '<=', $today)
                  ->whereDate('schedule_date_end', '>=', $today);
        }])->get();

        return view('admin.schedules.index', compact('schedules', 'locations'));
    }

    /**
     * Show form to Assign Personnel duty.
     */
    public function create()
    {
        $personnel = User::where('role', 'personnel')->where('is_active', true)->get();
        
        $today = now()->toDateString();
        $locations = Location::withCount(['schedules' => function ($query) use ($today) {
            $query->whereDate('schedule_date_start', '<=', $today)
                  ->whereDate('schedule_date_end', '>=', $today);
        }])->get();

        return view('admin.schedules.create', compact('personnel', 'locations'));
    }

    /**
     * Store a new duty assignment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'personnel_id' => 'required|exists:users,id',
            'location_id' => 'required|exists:locations,id',
            'schedule_date_start' => 'required|date',
            'schedule_date_end' => 'required|date|after_or_equal:schedule_date_start',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);

        // Check if the selected personnel is active
        $personnel = User::find($request->personnel_id);
        if ($personnel && !$personnel->is_active) {
            return back()->withInput()->withErrors(['personnel_id' => 'Cannot assign schedule: The selected personnel is currently inactive.']);
        }


        // Check for any existing schedule for this personnel within the date range
        $isOverlapping = Schedule::where('personnel_id', $request->personnel_id)
            ->where(function ($query) use ($request) {
                $query->where('schedule_date_start', '<=', $request->schedule_date_end)
                      ->where('schedule_date_end', '>=', $request->schedule_date_start);
            })
            ->exists();

        if ($isOverlapping) {
            return back()->withInput()->withErrors(['schedule' => 'Overlap detected: This personnel is already assigned to a duty during the selected date range.']);
        }

        // Check if the location has reached the maximum capacity (e.g., 10 personnel)
        $locationOccupancyCount = Schedule::where('location_id', $request->location_id)
            ->where(function ($query) use ($request) {
                $query->where('schedule_date_start', '<=', $request->schedule_date_end)
                      ->where('schedule_date_end', '>=', $request->schedule_date_start);
            })
            ->count();

        if ($locationOccupancyCount >= 10) {
            return back()->withInput()->withErrors(['location_id' => 'Location capacity reached: This location already has the maximum limit of 10 personnel assigned for the selected dates.']);
        }

        Schedule::create($request->all());

        return redirect()->route('admin.schedules.index')->with('success', 'Duty assigned successfully.');
    }

    /**
     * Show the form for editing the specified schedule.
     */
    public function edit($id)
    {
        $schedule = Schedule::findOrFail($id);
        $personnel = User::where('role', 'personnel')->where('is_active', true)->get();

        $today = now()->toDateString();
        $locations = Location::withCount(['schedules' => function ($query) use ($today) {
            $query->whereDate('schedule_date_start', '<=', $today)
                  ->whereDate('schedule_date_end', '>=', $today);
        }])->get();

        return view('admin.schedules.edit', compact('schedule', 'personnel', 'locations'));
    }

    /**
     * Update the specified schedule in storage.
     */
    public function update(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        $request->validate([
            'personnel_id' => 'required|exists:users,id',
            'location_id' => 'required|exists:locations,id',
            'schedule_date_start' => 'required|date',
            'schedule_date_end' => 'required|date|after_or_equal:schedule_date_start',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        // Check if the selected personnel is active
        $personnel = User::find($request->personnel_id);
        if ($personnel && !$personnel->is_active) {
            return back()->withInput()->withErrors(['personnel_id' => 'Update failed: The selected personnel is currently inactive.']);
        }

        // Check for existing schedules for this personnel in the same range, excluding current record
        $isOverlapping = Schedule::where('personnel_id', $request->personnel_id)
            ->where('id', '!=', $schedule->id) // Exclude the current schedule
            ->where(function ($query) use ($request) {
                $query->where('schedule_date_start', '<=', $request->schedule_date_end)
                      ->where('schedule_date_end', '>=', $request->schedule_date_start);
            })
            ->exists();

        if ($isOverlapping) {
            return back()->withInput()->withErrors(['schedule' => 'Update failed: The personnel is already scheduled for duty during the selected date range.']);
        }

        // Check if the location has reached the maximum capacity, excluding the current schedule record
        $locationOccupancyCount = Schedule::where('location_id', $request->location_id)
            ->where('id', '!=', $schedule->id)
            ->where(function ($query) use ($request) {
                $query->where('schedule_date_start', '<=', $request->schedule_date_end)
                      ->where('schedule_date_end', '>=', $request->schedule_date_start);
            })
            ->count();

        if ($locationOccupancyCount >= 10) {
            return back()->withInput()->withErrors(['location_id' => 'Update failed: This location has reached its maximum capacity of 10 personnel for the selected dates.']);
        }

        $schedule->update($request->all());

        return redirect()->route('admin.schedules.index')->with('success', 'Schedule updated successfully.');
    }

    /**
     * Delete a schedule.
     */
    public function destroy($id)
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();

        return back()->with('success', 'Schedule removed.');
    }
}
