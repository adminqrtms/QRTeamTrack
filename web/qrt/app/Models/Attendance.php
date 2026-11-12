<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'location_id',
        'time_in',
        'time_out',
        'hours_worked',
        'status',
        'date',
    ];

    protected $casts = [
        'time_in' => 'datetime',
        'time_out' => 'datetime',
        'hours_worked' => 'decimal:2',
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Close this duty session at $now and work out the hours worked.
     * Used by the app's TIME OUT button and by the admin's Force Time Out.
     */
    public function timeOut(Carbon $now, string $timezone = 'Asia/Manila'): void
    {
        $this->time_out = $now;

        // Use getRawOriginal to get the exact time string from DB (e.g., "11:14:00")
        // This prevents the 8-hour timezone shift caused by automatic model casting.
        // Normalize to H:i:s: SQLite stores a full datetime here, MySQL's TIME column only the time.
        $timeInString = Carbon::parse($this->getRawOriginal('time_in'))->format('H:i:s');
        $dateString = $this->date->toDateString();

        $timeIn = Carbon::parse("$dateString $timeInString", $timezone)->startOfMinute();

        // Use absolute difference to ensure no negative numbers
        $minutes = abs($now->diffInMinutes($timeIn));
        $this->hours_worked = round($minutes / 60, 2);

        $this->save();
    }
}
