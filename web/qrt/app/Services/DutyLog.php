<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * A personnel's duty sessions with the assigned shift next to the actual
 * time in / time out. Pay counts only the time inside the assigned shift:
 * shift 3:00-5:00 PM, in 2:50 PM, out 5:20 PM -> paid 3:00-5:00 = 2.00 hrs.
 */
class DutyLog
{
    private const TZ = 'Asia/Manila';

    /** One row per duty session between $from and $to (inclusive). */
    public static function rows(User $personnel, Carbon $from, Carbon $to): Collection
    {
        $attendances = Attendance::with('location')
            ->where('user_id', $personnel->id)
            // whereDate: SQLite stores the date as "2026-11-15 00:00:00"
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->orderBy('date')
            ->orderBy('time_in')
            ->get();

        $schedules = Schedule::with('location')
            ->where('personnel_id', $personnel->id)
            ->whereDate('schedule_date_start', '<=', $to->toDateString())
            ->where(fn ($q) => $q->whereNull('schedule_date_end')
                ->orWhereDate('schedule_date_end', '>=', $from->toDateString()))
            ->get();

        $rate = (float) ($personnel->hourly_rate ?? 0);

        return $attendances->map(function (Attendance $a) use ($schedules, $rate) {
            $date = $a->date->toDateString();
            $schedule = $schedules->first(fn ($s) => $s->schedule_date_start->toDateString() <= $date
                && (!$s->schedule_date_end || $s->schedule_date_end->toDateString() >= $date));

            $in = self::at($date, $a->getRawOriginal('time_in'));
            $out = $a->time_out ? self::at($date, $a->getRawOriginal('time_out')) : null;
            if ($out && $out->lt($in)) {
                $out->addDay(); // timed out after midnight
            }

            $shiftStart = $shiftEnd = null;
            if ($schedule) {
                $shiftStart = self::at($date, $schedule->getRawOriginal('start_time'));
                $shiftEnd = self::at($date, $schedule->getRawOriginal('end_time'));
                if ($shiftEnd->lte($shiftStart)) {
                    $shiftEnd->addDay(); // overnight shift
                }
            }

            $actualMinutes = $out ? $in->diffInMinutes($out) : null;
            $paidMinutes = null;
            if ($out) {
                $paidMinutes = $schedule
                    ? max(0, (int) $in->max($shiftStart)->diffInMinutes($out->min($shiftEnd), false))
                    : 0;
            }
            $paidHours = $paidMinutes !== null ? round($paidMinutes / 60, 2) : null;

            return (object) [
                'date' => $a->date,
                'location' => $a->location->location_name ?? ($schedule?->location->location_name ?? 'N/A'),
                'shift_start' => $shiftStart,
                'shift_end' => $shiftEnd,
                'time_in' => $in,
                'time_out' => $out,
                'status' => $a->status,
                'actual_hours' => $actualMinutes !== null ? round($actualMinutes / 60, 2) : null,
                'paid_hours' => $paidHours,
                'pay' => $paidHours !== null ? round($paidHours * $rate, 2) : null,
                'has_schedule' => (bool) $schedule,
            ];
        });
    }

    /** Totals for a set of rows. */
    public static function totals(Collection $rows): object
    {
        return (object) [
            'sessions' => $rows->count(),
            'days' => $rows->pluck('date')->map->toDateString()->unique()->count(),
            'paid_hours' => round($rows->sum('paid_hours'), 2),
            'actual_hours' => round($rows->sum('actual_hours'), 2),
            'pay' => round($rows->sum('pay'), 2),
            'late' => $rows->where('status', 'Late')->count(),
        ];
    }

    /** "2026-11-14" + "15:05:00" (MySQL TIME) or a full datetime (SQLite). */
    private static function at(string $date, $time): Carbon
    {
        return Carbon::parse($date . ' ' . Carbon::parse((string) $time)->format('H:i:s'), self::TZ);
    }
}
