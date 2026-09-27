<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            if (Schema::hasColumn('schedules', 'user_id')) {
                $table->renameColumn('user_id', 'personnel_id');
            }
            
            $table->foreignId('location_id')->after('id')->constrained('locations')->onDelete('cascade');
            $table->date('schedule_date_start')->after('location_id');
            $table->date('schedule_date_end')->after('schedule_date_start')->nullable();
            $table->string('status')->default('active')->after('end_time');
            
            if (Schema::hasColumn('schedules', 'shift_name')) {
                $table->dropColumn('shift_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->renameColumn('personnel_id', 'user_id');
            $table->dropForeign(['location_id']);
            $table->dropColumn(['location_id', 'schedule_date_start', 'schedule_date_end', 'status']);
            $table->string('shift_name')->nullable();
        });
    }
};
