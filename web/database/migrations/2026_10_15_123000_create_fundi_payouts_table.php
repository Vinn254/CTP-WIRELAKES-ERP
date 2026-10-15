<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 11 — E-Fundi payouts (§14.6). Project income and training income are kept on one table but on two
 * deliberately different paths: a project payout waits for the hirer's payment and is approved and released by
 * Finance; a training payout waits for the end-of-cohort report, is confirmed and approved by the Programme
 * Manager and only then released from the training budget. The columns each formula needs are kept apart so
 * neither stream can be paid out of the other's money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('stream', 20);
            $table->string('stage', 24)->default('awaiting_hirer_payment');
            // Which budget the money comes out of — the project's own line, or training for a trainer.
            $table->string('budget_category', 32)->nullable();
            $table->foreignId('fundi_id')->constrained('fundi_registrations')->cascadeOnDelete();
            // The trainer row the rate came from (training stream only).
            $table->foreignId('trainer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained('cohorts')->nullOnDelete();

            // Project income: agreed amount − materials − commission − mobilization credit − penalties.
            $table->decimal('agreed_amount_ksh', 14, 2)->nullable();
            $table->decimal('materials_ksh', 14, 2)->default(0);
            $table->decimal('commission_ksh', 14, 2)->default(0);
            $table->decimal('mobilization_ksh', 14, 2)->default(0);
            $table->decimal('penalty_ksh', 14, 2)->default(0);
            $table->date('hirer_paid_on')->nullable();
            $table->string('hirer_payment_ref', 80)->nullable();

            // Training income: (per-trainee fee × certified trainees) + workshop-hosting fee.
            $table->unsignedInteger('certified_count')->nullable();
            $table->decimal('per_trainee_fee_ksh', 14, 2)->nullable();
            $table->decimal('hosting_fee_ksh', 14, 2)->default(0);
            $table->date('report_submitted_on')->nullable();
            $table->string('report_note', 1000)->nullable();

            $table->decimal('gross_ksh', 14, 2)->default(0);
            $table->decimal('net_ksh', 14, 2)->default(0);
            $table->string('calculation', 500)->nullable();

            $table->string('method', 20)->default('mpesa');
            $table->string('payee_phone', 40)->nullable();
            $table->string('payee_account', 120)->nullable();
            $table->string('provider_ref', 80)->nullable();
            $table->string('note', 1000)->nullable();
            $table->foreignId('raised_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_on')->nullable();
            $table->foreignId('released_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_on')->nullable();
            $table->string('held_reason', 1000)->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['stream', 'stage']);
            $table->index(['fundi_id', 'stream']);
            $table->index(['project_id']);
            $table->index(['cohort_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
