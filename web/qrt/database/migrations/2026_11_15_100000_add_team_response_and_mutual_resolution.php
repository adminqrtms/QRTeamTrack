<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - incident_responders: every personnel who responded to a report or SOS (team response)
     * - mutual resolution: a responder asks to resolve, the resident confirms
     *   (or it resolves by itself after 24 hours without an answer)
     */
    public function up(): void
    {
        Schema::create('incident_responders', function (Blueprint $table) {
            $table->id();
            $table->string('incident_type', 20); // report | alarm
            $table->unsignedBigInteger('incident_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['incident_type', 'incident_id', 'user_id']);
            $table->index(['incident_type', 'incident_id']);
        });

        foreach (['reports', 'alarms'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('resolution_requested_at')->nullable();
                $table->foreignId('resolution_requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->string('resolution_method', 20)->nullable(); // confirmed | auto
            });
        }

        // Carry over the responders already recorded
        $now = now();
        foreach (DB::table('reports')->whereNotNull('respondent_by')->get(['id', 'respondent_by', 'respondent_at']) as $r) {
            DB::table('incident_responders')->insertOrIgnore([
                'incident_type' => 'report', 'incident_id' => $r->id, 'user_id' => $r->respondent_by,
                'joined_at' => $r->respondent_at ?? $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        foreach (DB::table('alarms')->whereNotNull('responded_by')->get(['id', 'responded_by', 'responded_at']) as $a) {
            DB::table('incident_responders')->insertOrIgnore([
                'incident_type' => 'alarm', 'incident_id' => $a->id, 'user_id' => $a->responded_by,
                'joined_at' => $a->responded_at ?? $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        foreach (['reports', 'alarms'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('resolution_requested_by');
                $table->dropColumn(['resolution_requested_at', 'resolved_at', 'resolution_method']);
            });
        }
        Schema::dropIfExists('incident_responders');
    }
};
