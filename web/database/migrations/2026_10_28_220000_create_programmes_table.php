<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The programmes the office runs money for beyond the plan the budget was first drawn on. A programme carries no
 * money of its own — an executive sets that on its budget line — so adding one only puts its name on the dropdowns
 * every money line is filled from: the plan, the register, a purchase, a payout, stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32)->unique();
            $table->string('label', 60);
            $table->string('note', 1000)->nullable();
            $table->foreignId('added_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};
