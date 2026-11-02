<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a unit's marks are weighed, set by the Academic Registrar.
 *
 * A trainer or lecturer enters the marks; this table says what each of those marks is worth on the unit's final
 * grade — CAT, assignment, theory, practical and attendance, adding to 100 — and the mark a student must reach to
 * pass the unit. The weights sit on the unit rather than on a cohort, so every run of the same course is graded the
 * same way and a student's transcript never disagrees with the sheet the marks were taken on.
 *
 * Nothing here rewrites the track: the track still says how far a trainee has gone, and this says what the marks
 * they earned come to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_grading', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_module_id')->unique()->constrained('training_modules')->cascadeOnDelete();
            $table->unsignedTinyInteger('cat_pct')->default(20);
            $table->unsignedTinyInteger('assignment_pct')->default(10);
            $table->unsignedTinyInteger('theory_pct')->default(40);
            $table->unsignedTinyInteger('practical_pct')->default(20);
            $table->unsignedTinyInteger('attendance_pct')->default(10);
            $table->decimal('pass_pct', 5, 2)->default(40);
            $table->string('note', 500)->nullable();
            $table->foreignId('set_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_grading');
    }
};
