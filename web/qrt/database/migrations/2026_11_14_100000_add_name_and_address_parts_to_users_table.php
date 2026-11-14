<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate first / middle / last name and a structured address
     * (street, barangay, city, province). `name` and `address` stay as the
     * combined text the rest of the system and the app already use.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            $table->string('street')->nullable()->after('address');
            $table->string('barangay', 150)->nullable()->after('street');
            $table->string('city', 150)->nullable()->after('barangay');
            $table->string('province', 150)->nullable()->after('city');
        });

        // Existing accounts: last word becomes the last name, the rest the first name
        DB::table('users')->select('id', 'name')->orderBy('id')->each(function ($user) {
            [$first, $last] = \App\Models\User::splitName((string) $user->name);
            DB::table('users')->where('id', $user->id)->update(['first_name' => $first, 'last_name' => $last]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name', 'street', 'barangay', 'city', 'province']);
        });
    }
};
