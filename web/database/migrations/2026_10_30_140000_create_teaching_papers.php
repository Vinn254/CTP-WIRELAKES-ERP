<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The papers a trainer fills in and forwards to the Academic Registrar.
 *
 * Each is a piece of teaching paperwork with the same life: drafted by the trainer, forwarded to the registrar,
 * approved or sent back with what to fix. Only an approved paper counts — its ticks and its marks are then written
 * onto each trainee's own record (ProgressEntry), which is what the track, the attendance floor and the certificate
 * gate already read. Nothing is stored twice: the register holds a day, the sheet holds a unit's marks, and the
 * trainee's record holds the result.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The class day that already had a table but no code: the register a trainer ticks.
        Schema::table('cohort_sessions', function (Blueprint $table) {
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->index('status');
        });

        Schema::create('cohort_session_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('cohort_sessions')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->boolean('present')->default(false);
            $table->string('note', 190)->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'enrollment_id']);
        });

        Schema::create('unit_mark_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained('cohorts')->cascadeOnDelete();
            $table->foreignId('training_module_id')->constrained('training_modules')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('trainers')->nullOnDelete();
            $table->date('covered_on');
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('taken_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('taken_by_fundi')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->timestamps();

            // One sheet per unit per day: a re-run of a unit is a new day, never a second sheet over the first.
            $table->unique(['cohort_id', 'training_module_id', 'covered_on']);
            $table->index('status');
        });

        Schema::create('unit_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_id')->constrained('unit_mark_sheets')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->decimal('score', 5, 2)->nullable();
            $table->string('note', 190)->nullable();
            $table->timestamps();

            $table->unique(['sheet_id', 'enrollment_id', 'kind']);
        });

        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained('cohorts')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('trainers')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('taken_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('taken_by_fundi')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->timestamps();

            $table->unique('cohort_id');
            $table->index('status');
        });

        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_id')->constrained('timetables')->cascadeOnDelete();
            $table->string('day', 9);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->foreignId('training_module_id')->nullable()->constrained('training_modules')->nullOnDelete();
            $table->string('kind', 20)->default('theory');
            $table->string('room', 120)->nullable();
            $table->timestamps();

            $table->index(['timetable_id', 'day']);
        });

        // A lesson plan is the teaching plan for the whole period — lesson 1 to the last lesson — so the paper is one
        // per cohort and every lesson is a line on it.
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained('cohorts')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('trainers')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('taken_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('taken_by_fundi')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->timestamps();

            $table->unique('cohort_id');
            $table->index('status');
        });

        Schema::create('lesson_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_plan_id')->constrained('lesson_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('training_module_id')->nullable()->constrained('training_modules')->nullOnDelete();
            $table->date('planned_on')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('topic', 190);
            $table->text('summary');
            $table->text('objectives')->nullable();
            $table->text('activities')->nullable();
            $table->text('resources')->nullable();
            $table->text('assessment_note')->nullable();
            $table->timestamps();

            $table->index(['lesson_plan_id', 'position']);
        });

        Schema::create('training_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained('cohorts')->cascadeOnDelete();
            $table->foreignId('training_module_id')->nullable()->constrained('training_modules')->nullOnDelete();
            $table->foreignId('lesson_line_id')->nullable()->constrained('lesson_lines')->nullOnDelete();
            $table->date('given_on')->nullable();
            $table->string('title', 190);
            $table->string('kind', 20)->default('note');
            $table->string('note', 500)->nullable();
            $table->string('file_path');
            $table->string('file_name', 190)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->foreignId('uploaded_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_fundi')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->timestamps();

            $table->index(['cohort_id', 'training_module_id']);
        });

        // What wrote a line on a trainee's record. An approved register or mark sheet settles onto ProgressEntry, and
        // the link back means a corrected paper re-approved replaces its own lines instead of adding a second set.
        Schema::table('progress_entries', function (Blueprint $table) {
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('progress_entries', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn(['source_type', 'source_id']);
        });

        Schema::dropIfExists('training_materials');
        Schema::dropIfExists('lesson_lines');
        Schema::dropIfExists('lesson_plans');
        Schema::dropIfExists('timetable_slots');
        Schema::dropIfExists('timetables');
        Schema::dropIfExists('unit_marks');
        Schema::dropIfExists('unit_mark_sheets');
        Schema::dropIfExists('cohort_session_marks');

        Schema::table('cohort_sessions', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note']);
        });
    }
};
