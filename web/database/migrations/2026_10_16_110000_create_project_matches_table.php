<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 6 — the matching desk (§9.4, §9.6). One row is one E-Fundi shortlisted, invited or hand-picked for one
 * project in one round. A re-run writes round 2 rather than overwriting round 1, so the desk can show that the first
 * search found nobody and what was changed for the second. Nothing here is a duplicate of the project or the member
 * record: it points at both and only holds the match itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('fundi_id')->constrained('fundi_registrations')->cascadeOnDelete();
            $table->unsignedTinyInteger('round')->default(1);
            $table->decimal('score', 5, 2)->default(0);
            $table->json('matched')->nullable();
            $table->string('status', 20)->default('shortlisted');
            $table->timestamp('invited_on')->nullable();
            $table->timestamp('responded_on')->nullable();
            $table->boolean('widened')->default(false);
            $table->boolean('below_bar')->default(false);
            $table->string('note', 1000)->nullable();
            $table->foreignId('awarded_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('awarded_on')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'fundi_id', 'round']);
            $table->index(['project_id', 'round']);
            $table->index(['fundi_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_matches');
    }
};
