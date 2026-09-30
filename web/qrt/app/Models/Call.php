<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    public const ACTIVE_STATUSES = ['ringing', 'accepted'];

    protected $fillable = [
        'conversation_id',
        'caller_id',
        'callee_id',
        'type',
        'status',
        'room_name',
        'accepted_at',
        'ended_at',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'caller_id' => 'integer',
        'callee_id' => 'integer',
        'accepted_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function caller()
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function callee()
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function hasParticipant(User $user): bool
    {
        return $this->caller_id === $user->id || $this->callee_id === $user->id;
    }

    public function otherParticipantId(User $user): int
    {
        return $this->caller_id === $user->id ? $this->callee_id : $this->caller_id;
    }

    /**
     * Talk time in seconds (0 if the call was never answered).
     */
    public function durationSeconds(): int
    {
        if (!$this->accepted_at || !$this->ended_at) {
            return 0;
        }

        return max(0, $this->ended_at->getTimestamp() - $this->accepted_at->getTimestamp());
    }
}
