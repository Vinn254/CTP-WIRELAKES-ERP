<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The units handed to one student on one seat.
 *
 * An intake ticks the units it teaches and a trainee reads the units of their own programme — but a class of thirty
 * is rarely one block. One student arrives late, another is already strong on the wiring unit, a third is being
 * finished off from an earlier term, and what the office wants to do at the moment it gives the seat is say which
 * units that person is sitting.
 *
 * The list is optional and additive: a seat with nothing ticked keeps exactly the units it reads today, so every
 * intake already running behaves as it did before this table existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_module_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['enrollment_id', 'training_module_id'], 'enrollment_unit_seat_units_unique');
            $table->index('training_module_id', 'enrollment_unit_module_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_unit');
    }
};
