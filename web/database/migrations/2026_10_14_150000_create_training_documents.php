<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The paper a trainee's training is proved with — a partner school's report, a competency sheet, a photo of a
 * finished board. It sits on the seat, is dated inside the cohort's own period, and counts toward the track
 * percentage that carries a CTP into certification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('training_module_id')->nullable()->constrained('training_modules')->nullOnDelete();
            $table->string('title', 150);
            $table->enum('kind', ['school_report', 'assessment_paper', 'competency_sheet', 'photo', 'other'])->default('other');
            $table->date('dated_on');
            $table->string('file_path', 500);
            $table->string('file_name', 190)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('note', 1000)->nullable();
            $table->foreignId('uploaded_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_fundi')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->timestamps();
            $table->index(['enrollment_id', 'dated_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_documents');
    }
};
