<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('user_id')->constrained('locations')->onDelete('set null');
        });
    }

    public function down() {
        Schema::table('attendances', function (Blueprint $table) { $table->dropColumn('location_id'); });
    }
};
