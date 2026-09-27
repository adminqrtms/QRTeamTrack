<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->foreignId('respondent_by')->nullable()->constrained('users');
            $table->timestamp('responded_at')->nullable();
            $table->text('action_taken')->nullable(); // Target station
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('location_id');
            $table->dropColumn('respondent_by');
            $table->dropColumn('responded_at');
            $table->dropColumn('action_taken');
        });
    }
};
