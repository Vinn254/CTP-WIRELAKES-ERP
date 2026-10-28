<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A day a cohort actually sat. The register itself is NOT stored twice: each line the trainer ticks becomes one
 * attendance entry on that trainee's own record (ProgressEntry), which is what the track's attendance weight and the
 * certificate gate already read. This table holds what a register needs on top of the ticks — the session, who took
 * it, and the signed sheet they photographed or scanned in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohort_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained('cohorts')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('trainers')->nullOnDelete();
            $table->date('held_on');
            $table->enum('kind', ['theory', 'practical', 'assessment'])->default('theory');
            $table->string('topic', 190)->nullable();
            $table->unsignedTinyInteger('hours')->default(0);
            $table->string('sheet_path')->nullable();
            $table->string('sheet_name', 190)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('taken_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('taken_by_fundi')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cohort_id', 'held_on', 'kind']);
            $table->index('held_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_sessions');
    }
};
