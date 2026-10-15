<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 11 — the stock cards (§14.5): SKU, description, unit, quantity on hand, reorder level, where the item
 * sits and what it cost. The quantity is not history — it is the running total of the movements on the card, so a
 * shelf is never re-typed to match what somebody thinks is there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 40)->unique();
            $table->string('name', 190);
            $table->string('description', 1000)->nullable();
            $table->string('category', 40);
            $table->string('unit', 20)->default('pcs');
            $table->decimal('qty_on_hand', 12, 2)->default(0);
            $table->decimal('reorder_level', 12, 2)->default(0);
            $table->string('location', 190)->nullable();
            $table->decimal('unit_cost_ksh', 14, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['category', 'status']);
            $table->index(['qty_on_hand', 'reorder_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
