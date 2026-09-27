<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'location_id',
        'last_latitude',
        'last_longitude',
        'last_seen',
        'hourly_rate',
        'phone_number',
        'address',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen' => 'datetime',
            'hourly_rate' => 'decimal:2',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($user) {
            // If no users exist, make the first one an admin
            if (static::count() === 0) {
                $user->role = 'admin';
                $user->is_active = true;
            }
        });
    }

    /**
     * Get the reports created by the user.
     */
    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Get the alarms triggered or associated with the user.
     */
    public function alarms()
    {
        return $this->hasMany(Alarm::class);
    }

    /**
     * Get the schedules/shifts for the user.
     */
    public function schedules()
    {
        // Explicitly define 'personnel_id' as the foreign key to match your migration rename
        return $this->hasMany(Schedule::class, 'personnel_id');
    }

    /**
     * Get the locations recorded for the user.
     */
    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Get the fixed location associated with the resident.
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function latestLocation()
    {
        return $this->hasOne(Location::class)->latestOfMany();
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get the personnel profile associated with the user.
     * Since Personnel extends User, this returns the same record 
     * but scoped to the Personnel model.
     */
    public function personnel()
    {
        return $this->hasOne(Personnel::class, 'id', 'id');
    }
}
