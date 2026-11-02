<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reading a student pays to keep. A title on the shelf is always read on the platform; saving it to the phone
 * costs KSh 50 for a past paper or KSh 100 for a book or notes, sent to the programme's Paybill. One row per student
 * per title: the student says they have paid, the Academic Registrar counts the money and marks it paid, and only
 * then does the download open. A declined row can be asked for again, so the row is written over rather than
 * duplicated — and a trainer, who is handed the same shelf to teach from, never has a row at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ctp_id')->nullable()->constrained('ctp_registrations')->nullOnDelete();
            $table->foreignId('library_item_id')->nullable()->constrained('library_items')->nullOnDelete();
            // The amount as it was asked for, so a price changed later does not rewrite what was paid.
            $table->unsignedInteger('amount');
            $table->enum('status', ['requested', 'paid', 'declined'])->default('requested');
            $table->string('decision_note', 500)->nullable();
            $table->foreignId('requested_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['ctp_id', 'library_item_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_purchases');
    }
};
