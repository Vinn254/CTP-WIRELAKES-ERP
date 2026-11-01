<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The papers a person sends in, and the profile edits they ask the office to make. One row per upload and per
 * request, on the same shape as leave: the person writes from their own page — a staff desk or a member's portal —
 * and only the desk that owns their record answers it. Human Resources answers an office worker or an E-Fundi;
 * a CTP student's papers and corrections are answered by the Academic Registrar. Nothing is deleted when a copy is
 * sent back: the row stays with the note that went with it, and a better scan is written over it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_documents', function (Blueprint $table) {
            $table->id();
            // Who the paper belongs to: a staff file, a CTP application or an E-Fundi registration.
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->string('kind', 40);
            $table->string('file_path');
            $table->string('file_name', 180);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('status', ['pending', 'approved', 'returned'])->default('pending');
            $table->string('decision_note', 500)->nullable();
            $table->foreignId('uploaded_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'status']);
            $table->index('status');
        });

        Schema::create('profile_changes', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            // Only the fields asked for, so the desk reads the change instead of a whole form.
            $table->json('changes');
            $table->text('note')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->string('decision_note', 500)->nullable();
            $table->foreignId('requested_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_changes');
        Schema::dropIfExists('member_documents');
    }
};
