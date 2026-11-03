<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Registrar puts a name against each class hour of a programme's week.
 *
 * A programme week already says which unit sits on which day at what time, but not who stands in front of it. The
 * Academic Registrar hands each class to a lecturer while laying the week out, so a trainer can see the classes they
 * are given on their own timetable before an intake is booked under them, and a student's week says whose class it is.
 *
 * Nothing existing changes: a class already on a week keeps no lecturer until the Registrar names one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->foreignId('trainer_id')->nullable()->after('training_module_id')
                ->constrained('trainers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trainer_id');
        });
    }
};
