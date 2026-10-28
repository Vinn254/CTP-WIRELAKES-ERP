<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave. One line per application: who asks, which of the seven types, the dates, how many days come off their
 * balance, and what Human Resources answers. The person applies from their own page — a staff desk or an E-Fundi's
 * portal — and only the HR desk decides. A cancelled or refused request never counts against the balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('days', 6, 2);
            $table->string('cover_for', 160)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->string('decision_note', 500)->nullable();
            $table->foreignId('decided_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
