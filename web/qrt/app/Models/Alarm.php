<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\IsIncident;
use Illuminate\Database\Eloquent\Model;

class Alarm extends Model
{
    use HasFactory, IsIncident;

    public function incidentType(): string
    {
        return 'alarm';
    }

    public function activeStatus(): string
    {
        return 'responding';
    }

    public function incidentLabel(): string
    {
        return 'SOS #' . $this->id;
    }

    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'status',
        'responded_by',
        'responded_at',
        'location_id',
        'action_taken',
        'resolution_requested_at',
        'resolution_requested_by',
        'resolved_at',
        'resolution_method',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'responded_at' => 'datetime',
        'resolution_requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

}
