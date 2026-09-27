<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('status')->default('On Time')->after('hours_worked');
        });
    }

    public function down() {
        Schema::table('attendances', function (Blueprint $table) { $table->dropColumn('status'); });
    }
};
