<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'personnel_id',
        'location_id',
        'schedule_date_start',
        'schedule_date_end',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'schedule_date_start' => 'date',
        'schedule_date_end' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];


    public function user()
    {
        return $this->belongsTo(User::class, 'personnel_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
