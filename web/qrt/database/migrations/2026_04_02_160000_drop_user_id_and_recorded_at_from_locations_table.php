<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            if (Schema::hasColumn('locations', 'user_id')) {
                // Drop the foreign key first before dropping the column
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('locations', 'recorded_at')) {
                $table->dropColumn('recorded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('recorded_at')->nullable();
        });
    }
};
