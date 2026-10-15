<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 11 — procurement (§14.4). A request can come from anyone (a CTP in the field, a manager, a fundi),
 * is decided by the Programme Manager, quoted by the Procurement Officer from approved suppliers, ordered on a
 * purchase order, received against a delivery note, matched to the invoice and paid. Each table holds one step
 * of that path and a row never moves to a new record — the same purchase request carries the whole story.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 190);
            $table->string('contact_name', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('county', 100)->nullable();
            // What they supply, from the inventory categories — a supplier is approved for a kind of goods.
            $table->string('supplies', 40)->nullable();
            $table->string('bank_account', 120)->nullable();
            $table->string('mpesa_paybill', 60)->nullable();
            $table->string('status', 20)->default('approved');
            $table->string('note', 1000)->nullable();
            $table->foreignId('added_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'supplies']);
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('item_name', 190);
            $table->string('description', 1000)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit', 20)->default('pcs');
            $table->decimal('estimated_cost_ksh', 14, 2)->nullable();
            // The budget the money will come out of, and what it is being bought for.
            $table->string('budget_category', 32);
            $table->string('justification', 1000);
            $table->date('needed_by')->nullable();
            $table->string('county', 100)->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained('cohorts')->nullOnDelete();
            // When the goods are programme stock the request can point straight at the stock card.
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            // Who raised it — a staff member, a CTP or an E-Fundi. Exactly one of the three.
            $table->foreignId('raised_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ctp_id')->nullable()->constrained('ctp_registrations')->nullOnDelete();
            $table->foreignId('fundi_id')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->string('status', 20)->default('submitted');
            $table->foreignId('decided_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_on')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['status', 'needed_by']);
            $table->index('budget_category');
        });

        Schema::create('purchase_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_ksh', 14, 2);
            $table->string('quotation_ref', 80)->nullable();
            $table->date('quoted_on');
            $table->string('note', 1000)->nullable();
            // The quote the Procurement Officer bought against — one per request.
            $table->boolean('is_selected')->default(false);
            $table->foreignId('added_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['purchase_request_id', 'amount_ksh']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost_ksh', 14, 2);
            $table->decimal('amount_ksh', 14, 2);
            $table->string('note', 1000)->nullable();
            $table->foreignId('raised_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_on')->nullable();
            // Step 6 — the storekeeper signs the delivery note and the goods become stock.
            $table->string('delivery_note_ref', 80)->nullable();
            $table->decimal('received_qty', 12, 2)->nullable();
            $table->string('receipt_note', 1000)->nullable();
            $table->foreignId('received_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_on')->nullable();
            // Step 7 — PO, delivery note and supplier invoice matched before a payment request is raised.
            $table->string('invoice_ref', 80)->nullable();
            $table->decimal('invoice_amount_ksh', 14, 2)->nullable();
            $table->foreignId('matched_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index(['status', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_quotations');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('suppliers');
    }
};
