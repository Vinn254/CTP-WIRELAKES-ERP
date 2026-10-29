<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the public area pages take in: a person asking the programme to do a piece of work for them. Nobody needs an
 * account to fill it in and nobody is asked for a document — the contact details are the whole record until the
 * office answers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('area', 60);
            $table->string('category', 90)->nullable();
            $table->string('name', 150);
            $table->string('email', 150);
            $table->string('phone', 30);
            $table->string('whatsapp', 30)->nullable();
            $table->string('town', 90)->nullable();
            $table->text('details')->nullable();
            $table->string('status', 30)->default('received');
            $table->string('review_note', 1000)->nullable();
            $table->date('contacted_on')->nullable();
            $table->foreignId('reviewed_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['area', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_applications');
    }
};
