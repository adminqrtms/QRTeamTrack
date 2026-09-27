<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
        // For Personnel Status (Active/Inactive)
        $table->string('status')->default('active'); 
        
        // For Profile Management & Contact
        $table->string('phone_number')->nullable();
        $table->text('address')->nullable();
        $table->string('avatar')->nullable(); // Profile picture
        
        // For Salary Calculation (Personnel)
        $table->decimal('hourly_rate', 8, 2)->nullable();
        
        // For Real-time Location (Quick access for "Nearby" logic)
        $table->decimal('last_latitude', 10, 8)->nullable();
        $table->decimal('last_longitude', 11, 8)->nullable();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'phone_number',
                'address',
                'avatar',
                'hourly_rate',
                'last_latitude',
                'last_longitude'
            ]);
        });
    }
};