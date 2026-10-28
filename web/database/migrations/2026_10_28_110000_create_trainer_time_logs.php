<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The week a trainer signs. One line per day worked — in, out, what was actually done, the department the day was
 * spent on and the units delivered — exactly the sheet an office signs by hand. The trainer fills it as the week
 * goes, signs the whole week once, and the Training desk answers line by line with its approval signature.
 * Human Resources reads the same sheet, unaltered, as proof of the work before pay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->cascadeOnDelete();
            $table->date('week_start');
            $table->date('log_date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->string('task', 500);
            $table->string('initials', 10)->nullable();
            $table->string('department', 90)->nullable();
            $table->foreignId('training_id')->nullable()->constrained('trainings')->nullOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained('cohorts')->nullOnDelete();
            $table->unsignedTinyInteger('units')->default(0);
            $table->decimal('hours', 4, 2)->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'returned'])->default('draft');
            $table->string('approval_note', 500)->nullable();
            $table->foreignId('signed_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // A day is written once and corrected in place — never a second line for the same trainer and date.
            $table->unique(['trainer_id', 'log_date']);
            $table->index(['trainer_id', 'week_start']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_time_logs');
    }
};
