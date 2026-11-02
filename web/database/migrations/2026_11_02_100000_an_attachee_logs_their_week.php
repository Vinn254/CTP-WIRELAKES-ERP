<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An attachment is training, not a job — it is not paid. What an attachee hands in instead is the week: the days
 * they sat at the site, the clock times, what they did and the site supervisor's initials, sent to the Academic
 * Registrar on the same paper queue a trainer's register travels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ctp_registration_id')->constrained('ctp_registrations')->cascadeOnDelete();
            // The placement the week is written against, and the intake the attachee came from — both kept so the
            // Registrar reads the paper with its context, and neither is lost when an order is later closed.
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained('cohorts')->nullOnDelete();
            $table->date('week_start');
            $table->enum('status', ['draft', 'submitted', 'approved', 'returned'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['ctp_registration_id', 'week_start']);
            $table->index('status');
        });

        Schema::create('attachment_log_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_log_id')->constrained('attachment_logs')->cascadeOnDelete();
            $table->date('log_date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->string('task', 500);
            $table->decimal('hours', 4, 2)->nullable();
            $table->string('initials', 10)->nullable();
            $table->timestamps();

            $table->unique(['attachment_log_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_log_lines');
        Schema::dropIfExists('attachment_logs');
    }
};
