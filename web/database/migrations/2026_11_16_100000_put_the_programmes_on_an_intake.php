<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The programmes an intake teaches.
 *
 * An intake at a site is rarely one course: the same group of trainees is mobilised together and sits several
 * programmes — welding alongside electrical — and each programme brings its own units and its own lecturer. Until
 * now a cohort held one training id, so a second course meant a second intake with a second set of seats.
 *
 * The list is kept beside the old single id rather than replacing it, because every paper already written (marks,
 * timetables, grading) names one programme. The id on the cohort stays the intake's lead programme; the table here
 * is the whole list, and a trainee's own programme picks which units of it reach them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohort_training', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['cohort_id', 'training_id']);
        });

        DB::statement('INSERT INTO `cohort_training` (`cohort_id`, `training_id`, `created_at`, `updated_at`)
            SELECT `id`, `training_id`, NOW(), NOW() FROM `cohorts`
            WHERE `training_id` IS NOT NULL
              AND NOT EXISTS (SELECT 1 FROM `cohort_training` ct WHERE ct.`cohort_id` = `cohorts`.`id`
                AND ct.`training_id` = `cohorts`.`training_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_training');
    }
};
