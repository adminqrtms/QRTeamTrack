<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration 2026_03_25_160000 renamed these report columns, but the Report
     * model, controllers and mobile app all use the original names. Rename them
     * back so a freshly migrated database matches the code. Databases that
     * already use the original names are left untouched.
     */
    public function up(): void
    {
        $renames = [
            'resident_id' => 'user_id',
            'concern' => 'title',
            'assigned_personnel_id' => 'assigned_to',
        ];

        foreach ($renames as $from => $to) {
            if (Schema::hasColumn('reports', $from) && !Schema::hasColumn('reports', $to)) {
                Schema::table('reports', function (Blueprint $table) use ($from, $to) {
                    $table->renameColumn($from, $to);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty: the original names are the correct ones.
    }
};
