<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The month's payroll. Human Resources runs it: one line per person on the staff file, the pay they are on, the
 * allowances added and every deduction taken off — statutory first, then any loan or advance — leaving the net the
 * person is actually owed. The run then goes to Finance, which sees the staff to be paid and settles them one by
 * one; each settlement posts its own line to the money register. One run per month, corrected in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->date('period')->unique();
            $table->enum('status', ['prepared', 'sent', 'returned', 'paid'])->default('prepared');
            $table->string('note', 500)->nullable();
            $table->string('return_reason', 500)->nullable();
            $table->foreignId('prepared_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('paid_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_record_id')->nullable()->constrained('staff_records')->nullOnDelete();
            $table->string('name', 150);
            $table->string('email', 160)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->string('department', 90)->nullable();
            $table->decimal('basic_ksh', 12, 2)->default(0);
            $table->decimal('house_ksh', 12, 2)->default(0);
            $table->decimal('transport_ksh', 12, 2)->default(0);
            $table->decimal('other_allowance_ksh', 12, 2)->default(0);
            $table->string('other_allowance_note', 90)->nullable();
            $table->decimal('paye_ksh', 12, 2)->default(0);
            $table->decimal('nssf_ksh', 12, 2)->default(0);
            $table->decimal('shif_ksh', 12, 2)->default(0);
            $table->decimal('levy_ksh', 12, 2)->default(0);
            $table->decimal('other_deduction_ksh', 12, 2)->default(0);
            $table->string('other_deduction_note', 90)->nullable();
            $table->decimal('gross_ksh', 12, 2)->default(0);
            $table->decimal('deductions_ksh', 12, 2)->default(0);
            $table->decimal('net_ksh', 12, 2)->default(0);
            $table->enum('status', ['unpaid', 'paid', 'held'])->default('unpaid');
            $table->string('hold_reason', 300)->nullable();
            $table->string('method', 20)->nullable();
            $table->string('payee_account', 120)->nullable();
            $table->string('provider_ref', 60)->nullable();
            $table->date('paid_on')->nullable();
            $table->foreignId('paid_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A person appears once in a month's run and is corrected in place.
            $table->unique(['payroll_run_id', 'staff_record_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
    }
};
