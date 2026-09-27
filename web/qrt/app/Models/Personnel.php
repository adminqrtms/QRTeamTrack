<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Personnel extends User
{
    /**
     * The "booted" method of the model.
     * Automatically filters all queries to only show users with role 'personnel'.
     */
    protected $table = 'users'; // Use the same table as User

    protected static function booted(): void
    {
        static::addGlobalScope('personnel_role', function (Builder $builder) {
            $builder->where('role', 'personnel');
        });

        // Force role to personnel when creating via this model
        static::creating(function ($personnel) {
            $personnel->role = 'personnel';
        });
    }
}
