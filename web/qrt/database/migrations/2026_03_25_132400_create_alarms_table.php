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
        Schema::create('alarms', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Resident who triggered it
        $table->decimal('latitude', 10, 8);
        $table->decimal('longitude', 11, 8);
        
        // Status: 'triggered', 'responded', 'resolved', 'false_alarm'
        $table->string('status')->default('triggered');
        
        // Personnel who responded
        $table->foreignId('responded_by')->nullable()->constrained('users');
        $table->timestamp('responded_at')->nullable();
        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarms');
    }
};



