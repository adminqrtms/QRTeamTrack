<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\IsIncident;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory, IsIncident;

    public function incidentType(): string
    {
        return 'report';
    }

    public function activeStatus(): string
    {
        return 'ongoing';
    }

    public function incidentLabel(): string
    {
        return $this->title ?: 'Report #' . $this->id;
    }

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
        'resolution_requested_at',
        'resolution_requested_by',
        'resolved_at',
        'resolution_method',
    ];

    protected $casts = [
        'respondent_at' => 'datetime',
        'latitude' => 'double',
        'longitude' => 'double',
        'user_id' => 'integer',
        'assigned_to' => 'integer',
        'respondent_by' => 'integer',
        'location_id' => 'integer',
        'resolution_requested_at' => 'datetime',
        'resolved_at' => 'datetime',
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
