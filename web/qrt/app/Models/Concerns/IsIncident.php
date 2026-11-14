<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Shared by reports and SOS alarms: the team of responders and the
 * mutual-resolution fields (see App\Services\IncidentWorkflow).
 */
trait IsIncident
{
    /** Waiting for the resident to confirm the responder's "resolved". */
    public const AWAITING_CONFIRMATION = 'awaiting_confirmation';

    abstract public function incidentType(): string;      // report | alarm

    abstract public function activeStatus(): string;      // status while being handled

    abstract public function incidentLabel(): string;     // e.g. "Flood near the bridge" / "SOS #12"

    public function responders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'incident_responders', 'incident_id', 'user_id')
            ->withPivotValue('incident_type', $this->incidentType())
            ->withPivot('joined_at')
            ->withTimestamps()
            ->orderBy('incident_responders.joined_at');
    }

    public function resolutionRequester()
    {
        return $this->belongsTo(User::class, 'resolution_requested_by');
    }

    public function hasResponder(User $user): bool
    {
        return $this->responders()->where('users.id', $user->id)->exists();
    }

    /** Adds $user to the response team (once). Returns true if newly added. */
    public function addResponder(User $user): bool
    {
        if ($this->hasResponder($user)) {
            return false;
        }
        $this->responders()->attach($user->id, ['joined_at' => now()]);

        return true;
    }

    public function isAwaitingConfirmation(): bool
    {
        return $this->status === self::AWAITING_CONFIRMATION;
    }
}
