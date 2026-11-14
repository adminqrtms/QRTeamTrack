<?php

namespace App\Services;

use App\Models\Alarm;
use App\Models\Attendance;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Team response and mutual resolution for reports and SOS alarms.
 *
 * A responder can only ASK to resolve: the incident waits for the resident
 * ("awaiting_confirmation"). The resident confirms (resolved) or says it is
 * not yet resolved (back to ongoing). Without an answer it resolves by itself
 * after AUTO_RESOLVE_HOURS.
 */
class IncidentWorkflow
{
    public const AUTO_RESOLVE_HOURS = 24;

    public function __construct(private PushNotificationService $push)
    {
    }

    /**
     * Personnel may join when on duty at the incident's station
     * (any station when the incident has none), or when already assigned.
     */
    public function canJoin(Model $incident, User $user): bool
    {
        if ($user->role !== 'personnel') {
            return false;
        }
        if ($incident instanceof Report && (int) $incident->assigned_to === (int) $user->id) {
            return true;
        }

        return Attendance::where('user_id', $user->id)
            ->whereNull('time_out')
            ->when($incident->location_id, fn ($q) => $q->where('location_id', $incident->location_id))
            ->exists();
    }

    /** Responder taps "Resolved": wait for the resident to confirm. */
    public function requestResolution(Model $incident, User $by): void
    {
        $incident->forceFill([
            'status' => $incident::AWAITING_CONFIRMATION,
            'resolution_requested_at' => now(),
            'resolution_requested_by' => $by->id,
            'resolved_at' => null,
            'resolution_method' => null,
        ])->save();

        $what = $incident instanceof Alarm ? 'your SOS' : 'your report "' . $incident->incidentLabel() . '"';
        $this->push->sendToUsersAfterResponse(
            [$incident->user_id],
            'Is it resolved?',
            "{$by->name} marked {$what} as resolved. Please confirm in the app.",
            $this->pushData($incident, 'resolution_request'),
        );
    }

    /** Resident's answer to the responder's "resolved". */
    public function residentAnswer(Model $incident, User $resident, bool $resolved): void
    {
        if ($resolved) {
            $incident->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolution_method' => 'confirmed',
            ])->save();
            $title = 'Resolved ✓';
            $body = "{$resident->name} confirmed that " . $incident->incidentLabel() . ' is resolved.';
        } else {
            $incident->forceFill([
                'status' => $incident->activeStatus(),
                'resolution_requested_at' => null,
                'resolution_requested_by' => null,
            ])->save();
            $title = 'Not yet resolved';
            $body = "{$resident->name} says " . $incident->incidentLabel() . ' is not yet resolved.';
        }

        $this->push->sendToUsersAfterResponse(
            $incident->responders()->pluck('users.id'),
            $title,
            $body,
            $this->pushData($incident, $resolved ? 'resolution_confirmed' : 'resolution_rejected'),
        );
    }

    /**
     * Resolves incidents the resident didn't answer within AUTO_RESOLVE_HOURS.
     * Called when lists are opened (no cron needed on the PC) and hourly by the scheduler.
     */
    public static function autoResolveOverdue(): int
    {
        $count = 0;
        $deadline = now()->subHours(self::AUTO_RESOLVE_HOURS);

        foreach ([Report::class, Alarm::class] as $model) {
            $model::where('status', $model::AWAITING_CONFIRMATION)
                ->where('resolution_requested_at', '<=', $deadline)
                ->get()
                ->each(function ($incident) use (&$count) {
                    $incident->forceFill([
                        'status' => 'resolved',
                        'resolved_at' => $incident->resolution_requested_at->copy()->addHours(self::AUTO_RESOLVE_HOURS),
                        'resolution_method' => 'auto',
                    ])->save();
                    $count++;
                });
        }

        return $count;
    }

    private function pushData(Model $incident, string $event): array
    {
        $type = $incident->incidentType();

        // Own type: an "alarm" push would open the personnel's EMERGENCY pop-up
        return [
            'type' => 'resolution',
            'incident_type' => $type,
            'incident_id' => $incident->id,
            'event' => $event,
            'tag' => "resolution_{$type}_{$incident->id}",
        ];
    }
}
