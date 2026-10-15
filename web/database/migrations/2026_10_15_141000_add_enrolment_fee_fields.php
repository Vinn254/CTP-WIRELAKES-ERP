<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 4 / Module 11 — the trainee's course fee is a seat-level fact, and it is money in.
 * A cohort carries the fee per trainee; each seat records whether that trainee paid, by what method and
 * under which reference, and the payment posts a line on the ledger the same way a membership fee does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('fee_status', 20)->default('awaiting_payment')->after('stage');
            $table->decimal('fee_amount', 10, 2)->nullable()->after('fee_status');
            $table->string('fee_method', 20)->nullable()->after('fee_amount');
            $table->string('fee_reference', 60)->nullable()->after('fee_method');
            $table->timestamp('fee_paid_at')->nullable()->after('fee_reference');
            $table->foreignId('fee_recorded_by')->nullable()->after('fee_paid_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fee_recorded_by');
            $table->dropColumn(['fee_status', 'fee_amount', 'fee_method', 'fee_reference', 'fee_paid_at']);
        });
    }
};
