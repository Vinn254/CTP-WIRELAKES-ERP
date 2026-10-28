<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 11 / Projects — the materials the programme hands over for a project.
 * The office lists them when it posts the project, the fundi sees the same list and its cost, and what is
 * actually handed over is the figure deducted from the payout. A line is never deleted: a material that turns
 * out not to be needed is left un-supplied, so the plan stays visible next to the payout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()
                ->constrained('inventory_items')->nullOnDelete();
            $table->string('name', 190);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 12)->default('pcs');
            $table->decimal('unit_cost_ksh', 12, 2)->default(0);
            $table->date('supplied_on')->nullable();
            $table->foreignId('supplied_by_user')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('note', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_materials');
    }
};
