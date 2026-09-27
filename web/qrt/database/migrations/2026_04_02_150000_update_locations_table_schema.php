<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            if (!Schema::hasColumn('locations', 'location_name')) {
                $table->string('location_name')->after('id');
            }
            if (!Schema::hasColumn('locations', 'barangay')) {
                $table->string('barangay')->after('location_name');
            }
            if (!Schema::hasColumn('locations', 'latitude')) {
                $table->decimal('latitude', 10, 8);
            }
            if (!Schema::hasColumn('locations', 'longitude')) {
                $table->decimal('longitude', 11, 8);
            }

            // Add timestamps if they don't exist
            if (!Schema::hasColumn('locations', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'location_name',
                'barangay',
                'latitude',
                'longitude'
            ]);

            if (Schema::hasColumn('locations', 'created_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
