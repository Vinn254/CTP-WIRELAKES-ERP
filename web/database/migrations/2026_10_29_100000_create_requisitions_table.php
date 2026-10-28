<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A requisition is how a desk inside the organisation asks for something: the item, how much of it, the quality
 * wanted and an estimated price. It goes to Procurement to be checked and approved, then to Finance to be
 * processed — and the row stays on the register after that, reading Disbursed when money went out or Distributed
 * when goods were handed over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('department', 60);
            $table->string('item_name', 190);
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 20);
            $table->string('quality_note', 1000)->nullable();
            $table->decimal('estimated_price_ksh', 14, 2)->nullable();
            $table->string('budget_category', 60)->nullable();
            $table->string('purpose', 1000);
            $table->date('needed_by')->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('raised_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->foreignId('processed_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->decimal('paid_amount_ksh', 14, 2)->nullable();
            $table->string('paid_method', 30)->nullable();
            $table->string('given_to', 190)->nullable();
            $table->string('finance_note', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitions');
    }
};
