<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A class day on the register is one class from the timetable, not one day: a cohort can sit a theory class in the
 * morning and a second theory class in the afternoon, and each has its own attendance to tick and forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The plain index goes in first: MySQL will not release the unique one the cohort foreign key leans on
        // until another index covers the same leading column.
        Schema::table('cohort_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('training_module_id')->nullable()->after('kind');
            $table->index(['cohort_id', 'held_on', 'kind'], 'cohort_sessions_cohort_day_index');
            $table->foreign('training_module_id')->references('id')->on('training_modules')->nullOnDelete();
        });

        Schema::table('cohort_sessions', function (Blueprint $table) {
            $table->dropUnique('cohort_sessions_cohort_id_held_on_kind_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cohort_sessions', function (Blueprint $table) {
            $table->dropForeign(['training_module_id']);
            $table->dropIndex('cohort_sessions_cohort_day_index');
            $table->dropColumn('training_module_id');
        });

        Schema::table('cohort_sessions', function (Blueprint $table) {
            $table->unique(['cohort_id', 'held_on', 'kind']);
        });
    }
};
