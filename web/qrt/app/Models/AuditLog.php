<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const ACTION_TIME_IN = 'time_in';
    public const ACTION_TIME_OUT = 'time_out';

    public const ACTIONS = [
        self::ACTION_TIME_IN => 'Time In',
        self::ACTION_TIME_OUT => 'Time Out',
    ];

    protected $fillable = [
        'user_id',
        'attendance_id',
        'location_id',
        'action',
        'description',
        'latitude',
        'longitude',
        'ip_address',
        'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? ucwords(str_replace('_', ' ', $this->action));
    }
}
