<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 11 — stock movements. Every change of quantity on a stock card is one of these: goods received on a
 * purchase order, e-waste brought to a collection point, materials issued to a project or a cohort, a disposal, a
 * transfer, or a correction after a count. A mistake is corrected with another movement rather than an edit, so
 * both the error and the fix stay visible on the card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost_ksh', 14, 2)->nullable();
            $table->string('reason', 40);
            $table->string('note', 1000)->nullable();
            // What the movement belongs to — a purchase order that brought goods in, a project that drew them out.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained('cohorts')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('moved_on');
            $table->foreignId('recorded_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['inventory_item_id', 'moved_on']);
            $table->index(['direction', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
