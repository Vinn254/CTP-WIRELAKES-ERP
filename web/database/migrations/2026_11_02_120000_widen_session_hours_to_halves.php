<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A class on the timetable can sit for two and a half hours, and the register now takes its hours from the
     * timetable rather than from what the trainer typed — so the column has to hold the half hour.
     */
    public function up(): void
    {
        Schema::table('cohort_sessions', function (Blueprint $table) {
            // A class on the timetable can sit for two and a half hours, and the register now takes its hours from
            // the timetable rather than from what the trainer typed — so the column has to hold the half hour.
            $table->decimal('hours', 3, 1)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('cohort_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('hours')->default(0)->change();
        });
    }
};
