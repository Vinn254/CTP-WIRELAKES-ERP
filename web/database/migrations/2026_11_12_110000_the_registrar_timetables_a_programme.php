<?php

use App\Models\Cohort;
use App\Models\Timetable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Registrar lays out the week for a programme, not only for one cohort. A timetable therefore hangs either on a
 * cohort (the trainer's own week) or on a training (the office's programme week) — never on both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->foreignId('training_id')->nullable()->constrained('trainings')->nullOnDelete();
        });

        Schema::table('timetables', function (Blueprint $table) {
            $table->dropForeign(['cohort_id']);
            $table->unsignedBigInteger('cohort_id')->nullable()->change();
            $table->foreign('cohort_id')->references('id')->on('cohorts')->cascadeOnDelete();
        });

        // Every week a trainer wrote belongs to the programme its cohort runs, so the programme's own screens read it.
        Timetable::whereNotNull('cohort_id')->chunkById(100, function ($papers) {
            foreach ($papers as $paper) {
                $paper->update(['training_id' => Cohort::find($paper->cohort_id)?->training_id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->dropForeign(['cohort_id']);
            $table->unsignedBigInteger('cohort_id')->change();
            $table->foreign('cohort_id')->references('id')->on('cohorts')->cascadeOnDelete();
        });

        Schema::table('timetables', function (Blueprint $table) {
            $table->dropForeign(['training_id']);
            $table->dropColumn('training_id');
        });
    }
};
