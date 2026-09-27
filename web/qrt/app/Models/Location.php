<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;
    
    // Enabled timestamps to allow the controller to use latest() and match updated migrations
    public $timestamps = true;

    protected $fillable = [
        'location_name',
        'barangay',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Get the user that owns the location record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the schedules associated with the location.
     */
    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Get the attendance records associated with the location.
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
