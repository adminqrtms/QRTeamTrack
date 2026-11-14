<?php

namespace App\Services;

use App\Models\Alarm;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything on a personnel's log page (and its printable version):
 * duty sessions, incident responses and solved cases for a period.
 */
class PersonnelLog
{
    /**
     * The period from the request: a month (?month=2026-11, default this month)
     * or a date range (?mode=range&from=2026-11-01&to=2026-11-15).
     */
    public static function period(Request $request): object
    {
        $tz = 'Asia/Manila';

        if ($request->query('mode') === 'range') {
            $from = self::date($request->query('from')) ?? now($tz)->startOfMonth();
            $to = self::date($request->query('to')) ?? now($tz);
            if ($to->lt($from)) {
                [$from, $to] = [$to, $from];
            }

            return (object) [
                'mode' => 'range',
                'from' => $from->startOfDay(),
                'to' => $to->endOfDay(),
                'month' => $from->format('Y-m'),
                'label' => $from->format('M j, Y') . ' – ' . $to->format('M j, Y'),
            ];
        }

        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month'))
            ? Carbon::createFromFormat('Y-m-d', $request->query('month') . '-01', $tz)
            : now($tz)->startOfMonth();

        return (object) [
            'mode' => 'month',
            'from' => $month->copy()->startOfMonth(),
            'to' => $month->copy()->endOfMonth(),
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
        ];
    }

    public static function build(User $personnel, object $period): array
    {
        IncidentWorkflow::autoResolveOverdue();

        $duties = DutyLog::rows($personnel, $period->from, $period->to);
        $responses = self::responses($personnel, $period);

        return [
            'duties' => $duties,
            'totals' => DutyLog::totals($duties),
            'responses' => $responses,
            'solved' => $responses->where('is_solved', true)->values(),
        ];
    }

    /** Reports and SOS the personnel joined during the period. */
    private static function responses(User $personnel, object $period): Collection
    {
        $joined = DB::table('incident_responders')
            ->where('user_id', $personnel->id)
            ->whereBetween('joined_at', [$period->from, $period->to])
            ->orderBy('joined_at')
            ->get();

        $reports = Report::with(['resident:id,name', 'responders:id,name', 'location:id,location_name'])
            ->whereIn('id', $joined->where('incident_type', 'report')->pluck('incident_id'))->get()->keyBy('id');
        $alarms = Alarm::with(['user:id,name', 'responders:id,name', 'location:id,location_name'])
            ->whereIn('id', $joined->where('incident_type', 'alarm')->pluck('incident_id'))->get()->keyBy('id');

        return $joined->map(function ($row) use ($reports, $alarms, $personnel) {
            $incident = $row->incident_type === 'report' ? $reports->get($row->incident_id) : $alarms->get($row->incident_id);
            if (!$incident) {
                return null;
            }

            return (object) [
                'type' => $row->incident_type === 'report' ? 'Report' : 'SOS',
                'id' => $incident->id,
                'label' => $incident->incidentLabel(),
                'resident' => ($row->incident_type === 'report' ? $incident->resident : $incident->user)?->name ?? 'Unknown',
                'location' => $incident->location->location_name ?? 'N/A',
                'joined_at' => Carbon::parse($row->joined_at),
                'status' => $incident->status,
                'teammates' => $incident->responders->where('id', '!=', $personnel->id)->pluck('name')->values(),
                'resolved_at' => $incident->resolved_at,
                'resolution_method' => $incident->resolution_method,
                'is_solved' => $incident->status === 'resolved' && $incident->resolution_method !== null,
            ];
        })->filter()->values();
    }

    private static function date(?string $value): ?Carbon
    {
        if (!$value || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $value, 'Asia/Manila');
    }
}
