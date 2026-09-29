<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'resident_id',
        'personnel_id',
        'report_id',
        'alarm_id',
        'last_message_at',
    ];

    protected $casts = [
        'resident_id' => 'integer',
        'personnel_id' => 'integer',
        'report_id' => 'integer',
        'alarm_id' => 'integer',
        'last_message_at' => 'datetime',
    ];

    public function resident()
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    public function personnel()
    {
        return $this->belongsTo(User::class, 'personnel_id');
    }

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function alarm()
    {
        return $this->belongsTo(Alarm::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Scope conversations to the ones the given user takes part in.
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('resident_id', $user->id)
                ->orWhere('personnel_id', $user->id);
        });
    }

    public function hasParticipant(User $user): bool
    {
        return $this->resident_id === $user->id || $this->personnel_id === $user->id;
    }

    /**
     * The id of the participant on the other side of the conversation.
     */
    public function otherParticipantId(User $user): int
    {
        return $this->resident_id === $user->id ? $this->personnel_id : $this->resident_id;
    }
}
