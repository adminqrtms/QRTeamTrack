<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\User;

class PersonnelAttendanceController extends Controller
{
    public function toggle(Request $request, Personnel $personnel)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $timezone = 'Asia/Manila';
        $now = Carbon::now()->setTimezone($timezone)->startOfMinute();
        $lat = (float) $request->latitude;
        $lon = (float) $request->longitude;

        // Update the personnel's last known location immediately
        $personnel->update([
            'last_latitude' => $lat,
            'last_longitude' => $lon,
            'last_seen' => $now,
        ]);
        
        // 1. TIME OUT: Close the latest open session
        $attendance = Attendance::where('user_id', $personnel->id)
            ->whereNull('time_out')
            ->first();

        if ($attendance) {
            // Validate Geofence for Time Out
            $location = $attendance->location;
            if ($location) {
                $distance = $this->calculateDistance($lat, $lon, $location->latitude, $location->longitude);
                if ($distance > 200) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Time Out denied: You are too far (" . round($distance) . "m) from " . $location->location_name
                    ], 403);
                }
            }

            $attendance->time_out = $now;
            
            // Use getRawOriginal to get the exact time string from DB (e.g., "11:14:00")
            // This prevents the 8-hour timezone shift caused by automatic model casting.
            $timeInString = $attendance->getRawOriginal('time_in');
            $dateString = $attendance->date->toDateString();
            
            $timeIn = Carbon::parse("$dateString $timeInString", $timezone)->startOfMinute();

            // Use absolute difference to ensure no negative numbers
            $minutes = abs($now->diffInMinutes($timeIn));
            $attendance->hours_worked = round($minutes / 60, 2);
            
            $attendance->save();

            // Collate all sessions for the same shift date to calculate total daily earnings
            $dailyTotalHours = Attendance::where('user_id', $personnel->id)
                ->where('date', $attendance->date)
                ->whereNotNull('time_out')
                ->sum('hours_worked');

            $hourlyRate = $personnel->hourly_rate ?? 0;
            $dailyEarnings = round($dailyTotalHours * $hourlyRate, 2);

            // Calculate Monthly Summary for immediate mobile feedback
            $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
            $endOfMonth = Carbon::now()->endOfMonth()->toDateString();
            
            $monthlyTotalHours = Attendance::where('user_id', $personnel->id)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->sum('hours_worked');
            $monthlySalary = round($monthlyTotalHours * $hourlyRate, 2);

            return response()->json([
                'status' => 'success',
                'message' => "Timed Out: {$personnel->name}.",
                'data' => [
                    'is_on_duty' => false,
                    'session_hours' => $attendance->hours_worked,
                    'daily_total_hours' => $dailyTotalHours,
                    'daily_earnings' => $dailyEarnings,
                    'monthly_total_hours' => round($monthlyTotalHours, 2),
                    'monthly_salary' => $monthlySalary,
                    'monthly_salary_formatted' => '₱' . number_format($monthlySalary, 2),
                    'hourly_rate' => $hourlyRate
                ]
            ]);
        }

        // 2. TIME IN: Validate Schedule and Geofence
        $schedule = $personnel->schedules()->with('location')
            ->whereDate('schedule_date_start', '<=', $now->toDateString())
            ->whereDate('schedule_date_end', '>=', $now->toDateString())
            ->first();

        if (!$schedule) {
            return response()->json([
                'status' => 'error',
                'message' => "Attendance denied: No active schedule for today."
            ], 403);
        }

        // Validate Geofence (Personnel must be within 200m of the location)
        if (!$lat || !$lon) {
            return response()->json([
                'status' => 'error',
                'message' => "Attendance denied: No GPS data available."
            ], 422);
        }

        $distance = $this->calculateDistance($lat, $lon, $schedule->location->latitude, $schedule->location->longitude);

        if ($distance > 200) { // Meters
            return response()->json([
                'status' => 'error',
                'message' => "Attendance denied: Too far (" . round($distance) . "m) from " . $schedule->location->location_name
            ], 403);
        }

        $alreadyTimedInToday = Attendance::where('user_id', $personnel->id)
            ->where('date', $now->toDateString())
            ->exists();

        $status = $alreadyTimedInToday ? 'On Time' : $this->determineStatus($schedule, $now);

        Attendance::create([
            'user_id' => $personnel->id,
            'date' => $now->toDateString(),
            'location_id' => $schedule->location_id,
            'time_in' => $now,
            'status' => $status
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Timed In: {$personnel->name}. Marked as: {$status}",
            'data' => [
                'is_on_duty' => true,
                'attendance_status' => $status,
                'location_name' => $schedule->location->location_name
            ]
        ]);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    private function determineStatus($schedule, $now)
    {
        $timezone = 'Asia/Manila';

        try {
            // start_time is cast to datetime, so we handle it as a Carbon instance
            $startTimeString = $schedule->start_time instanceof Carbon 
                ? $schedule->start_time->format('H:i:s') 
                : $schedule->start_time;

            $scheduledStartTime = Carbon::parse($now->toDateString() . ' ' . $startTimeString, $timezone)
                ->startOfMinute();

        } catch (\Exception $e) {
            return 'On Time';
        }

        // Mark as Late if checking in 15+ minutes after the scheduled start
        return $now->gt($scheduledStartTime->addMinutes(15)) ? 'Late' : 'On Time';
    }
    
    public function show(Personnel $personnel)
    {
        $attendances = $personnel->attendances()->with('location')->latest()->paginate(10);
        return view('admin.personnels.attendance', compact('personnel', 'attendances'));
    }

    /**
     * Get the monthly salary summary for the authenticated personnel.
     */
    public function monthlySummary(Request $request)
    {
        $user = $request->user();
        $personnel = $user->personnel;

        if (!$personnel) {
            return response()->json([
                'status' => 'error',
                'message' => 'Personnel profile not found.'
            ], 404);
        }

        $month = $request->query('month', Carbon::now()->format('Y-m'));
        
        $startOfMonth = Carbon::parse($month)->startOfMonth();
        $endOfMonth = Carbon::parse($month)->endOfMonth();

        $totalHours = Attendance::where('user_id', $personnel->id)
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('hours_worked');

        $monthlySalary = round($totalHours * ($personnel->hourly_rate ?? 0), 2);

        return response()->json([
            'status' => 'success',
            'month' => $month,
            'total_hours' => round($totalHours, 2),
            'hourly_rate' => $personnel->hourly_rate ?? 0,
            'monthly_salary' => $monthlySalary,
            'monthly_salary_formatted' => '₱' . number_format($monthlySalary, 2),
        ]);
    }

    /**
     * Get the full attendance history for the personnel.
     */
    public function history(Request $request)
    {
        $personnel = $request->user()->personnel;

        if (!$personnel) {
            return response()->json(['status' => 'error', 'message' => 'Personnel not found'], 404);
        }

        $attendances = Attendance::where('user_id', $personnel->id)
            ->with('location')
            ->orderBy('date', 'desc')
            ->orderBy('time_in', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $attendances
        ]);
    }

    /**
     * Check if the personnel currently has an open attendance session.
     */
    public function checkStatus(Request $request)
    {
        $personnel = $request->user()->personnel;

        if (!$personnel) {
            return response()->json(['status' => 'error', 'message' => 'Personnel not found'], 404);
        }

        $activeAttendance = Attendance::with('location')
            ->where('user_id', $personnel->id)
            ->whereNull('time_out')
            ->first();

        return response()->json([
            'is_on_duty' => !!$activeAttendance,
            'location_name' => $activeAttendance ? $activeAttendance->location->location_name : null
        ]);
    }

    /**
     * Get the authenticated personnel's schedules.
     */
    public function mySchedules(Request $request)
    {
        $user = $request->user();
        
        // Try to get schedules through the personnel relationship first
        // as defined in your api.php toggle logic
        $query = $user->personnel ? $user->personnel->schedules() : $user->schedules();

        if (!$query) {
            return response()->json(['status' => 'success', 'data' => []]);
        }

        $schedules = $query->with('location')
                   ->where('schedule_date_end', '>=', now()->toDateString())
                   ->orderBy('schedule_date_start', 'asc')
                   ->get();

        return response()->json(['status' => 'success', 'data' => $schedules]);
    }

    /**
     * Get all active personnel with their current schedules for the colleagues list.
     */
    public function colleagues(Request $request)
    {
        $today = now()->toDateString();

        $personnel = User::where('role', 'personnel')
            ->with(['personnel.schedules' => function ($query) use ($today) {
                $query->whereDate('schedule_date_start', '<=', $today)
                    ->whereDate('schedule_date_end', '>=', $today)
                    ->with('location');
            }])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $personnel
        ]);
    }
}