<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versions of the mobile app the phones can update to (in-app updates).
     */
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version_code')->unique(); // Android build number
            $table->string('version_name', 50);                // shown to users, e.g. 1.0.5
            $table->text('notes')->nullable();                  // what's new, one change per line
            $table->boolean('required')->default(false);        // users can't skip it
            $table->string('apk_path');
            $table->unsignedBigInteger('apk_size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
