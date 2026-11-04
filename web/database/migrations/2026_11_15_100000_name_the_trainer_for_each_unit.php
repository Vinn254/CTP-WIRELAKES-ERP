<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The trainer the Registrar names for each unit of an intake.
 *
 * A cohort has always carried one trainer, which only fits a run handed to a single person. An intake is normally
 * taught by several — a welding lecturer, an electrical lecturer, a safety lecturer — and the unit is the natural
 * line to split them on, because it is already the line the students are marked on. So the unit ticked onto a
 * cohort now takes the trainer who teaches it. The name is optional: a cohort with one trainer and no unit names
 * keeps working exactly as it did, and a unit left unnamed falls back to the cohort's own trainer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cohort_module', function (Blueprint $table) {
            $table->unsignedBigInteger('trainer_id')->nullable()->after('training_module_id');
            $table->foreign('trainer_id')->references('id')->on('trainers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cohort_module', function (Blueprint $table) {
            $table->dropForeign(['trainer_id']);
            $table->dropColumn('trainer_id');
        });
    }
};
