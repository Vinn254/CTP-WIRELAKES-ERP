<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The modules under one of the programme's trainings. A training can run with none — the office adds the units
 * a trainee has to get through before a certificate is put forward, and each one says what the trainee must be
 * able to do. A mark is per seat, so one trainee's progress never moves another's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('outcome', 500)->nullable();
            $table->unsignedSmallInteger('hours')->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['training_id', 'position']);
        });

        Schema::create('module_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('training_module_id')->constrained('training_modules')->cascadeOnDelete();
            $table->date('done_on');
            $table->string('note', 1000)->nullable();
            $table->foreignId('recorded_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by_fundi')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->timestamps();
            $table->unique(['enrollment_id', 'training_module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_completions');
        Schema::dropIfExists('training_modules');
    }
};
