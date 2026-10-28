<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoicing on top of Module 11. An invoice is a demand for money, never a movement of it: the payment against it
 * is what posts the income line on the money register, so the register stays the single source of money (§14.3).
 * A donor, a CTP, an E-Fundi or anyone else can be billed; a member only ever sees their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            // Who is being billed: donor, ctp, fundi or another party the office types in.
            $table->string('billed_to', 12);
            $table->foreignId('ctp_id')->nullable()->constrained('ctp_registrations')->nullOnDelete();
            $table->foreignId('fundi_id')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            // The address the invoice goes to. It is carried on the invoice itself so a later edit of the member
            // record cannot rewrite what was actually sent.
            $table->string('party_name', 150);
            $table->string('party_email', 150)->nullable();
            $table->string('party_phone', 30)->nullable();
            $table->string('title', 190);
            $table->string('details', 1000)->nullable();
            $table->decimal('amount_ksh', 14, 2);
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            // open, settled or cancelled — partly paid is worked out from the payments, never typed.
            $table->string('status', 12)->default('open');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('donation_id')->nullable()->constrained('donations')->nullOnDelete();
            $table->string('note', 1000)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->foreignId('raised_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'billed_to']);
            $table->index(['ctp_id']);
            $table->index(['fundi_id']);
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_ksh', 14, 2);
            $table->date('happened_on');
            $table->string('method', 20)->default('mpesa');
            $table->string('reference', 60)->nullable();
            $table->string('note', 500)->nullable();
            // A payment that was wrong is voided with its reason and stays on the invoice — never deleted.
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('recorded_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['invoice_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoices');
    }
};
