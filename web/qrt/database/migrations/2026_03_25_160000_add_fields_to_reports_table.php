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
        Schema::table('reports', function (Blueprint $table) {
            if (Schema::hasColumn('reports', 'user_id')) {
                $table->renameColumn('user_id', 'resident_id');
            }
            if (Schema::hasColumn('reports', 'title')) {
                $table->renameColumn('title', 'concern');
            }
            if (Schema::hasColumn('reports', 'assigned_to')) {
                $table->renameColumn('assigned_to', 'assigned_personnel_id');
            }

            $table->string('type')->default('incident')->after('description');
            $table->decimal('latitude', 10, 8)->nullable()->after('image');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('barangay')->nullable()->after('longitude');

            if (Schema::hasColumn('reports', 'status')) {
                $table->string('status')->default('pending')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->renameColumn('resident_id', 'user_id');
            $table->renameColumn('concern', 'title');
            $table->renameColumn('assigned_personnel_id', 'assigned_to');

            $table->dropColumn(['type', 'latitude', 'longitude', 'barangay']);
            
            // Revert status to enum as per original table definition
            $table->enum('status', ['pending', 'ongoing', 'resolved'])->default('pending')->change();
        });
    }
};
