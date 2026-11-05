<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The theory and practical hours belong to the unit, not to the intake.
 *
 * A welder's unit and a solar unit are not taught over the same number of hours, so asking once per intake for a
 * theory figure and a practical figure produced a number that fitted nobody. The Registrar gives the split when they
 * register the unit under a programme; `hours` stays as the total the lists already print, kept in step with the split.
 *
 * The intake keeps its own two columns and simply stops being written to them — a run already recorded keeps its
 * figures on the row, and the hours a trainee reads now come from the units registered on that intake.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_modules', function (Blueprint $table) {
            $table->unsignedSmallInteger('theory_hours')->nullable()->after('hours');
            $table->unsignedSmallInteger('practical_hours')->nullable()->after('theory_hours');
        });
    }

    public function down(): void
    {
        Schema::table('training_modules', function (Blueprint $table) {
            $table->dropColumn(['theory_hours', 'practical_hours']);
        });
    }
};
