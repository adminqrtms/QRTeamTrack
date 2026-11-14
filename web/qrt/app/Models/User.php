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
        'first_name',
        'middle_name',
        'last_name',
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
        'street',
        'barangay',
        'city',
        'province',
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
    /**
     * "Juan Dela Cruz" -> ["Juan Dela", "Cruz"]: last word is the last name.
     * Used for accounts whose name was typed as one field.
     */
    public static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) < 2) {
            return [$parts[0] ?? null, null];
        }
        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }

    /** "Juan", "Santos", "Dela Cruz" -> "Juan Santos Dela Cruz" */
    public static function fullName(?string $first, ?string $middle, ?string $last): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([$first, $middle, $last]))));
    }

    protected static function booted(): void
    {
        // Keep the combined `name` and the separate name parts in step
        static::saving(function ($user) {
            if ($user->isDirty(['first_name', 'middle_name', 'last_name']) && $user->first_name) {
                $user->name = static::fullName($user->first_name, $user->middle_name, $user->last_name);
            } elseif ($user->isDirty('name') && $user->name) {
                [$user->first_name, $user->last_name] = static::splitName($user->name);
                $user->middle_name = null;
            }
        });

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
    /**
     * Firebase push notification tokens of the user's phones.
     */
    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function personnel()
    {
        return $this->hasOne(Personnel::class, 'id', 'id');
    }
}
