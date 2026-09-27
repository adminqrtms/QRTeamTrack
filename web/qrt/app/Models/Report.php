<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'image',
        'status',
        'assigned_to',
        'type',
        'latitude',
        'longitude',
        'location_id',
        'respondent_by',
        'respondent_at',
        'action_taken',
    ];

    protected $casts = [
        'respondent_at' => 'datetime',
        'latitude' => 'double',
        'longitude' => 'double',
        'user_id' => 'integer',
        'assigned_to' => 'integer',
        'respondent_by' => 'integer',
        'location_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resident()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function personnel()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function respondent()
    {
        return $this->belongsTo(User::class, 'respondent_by');
    }
}
