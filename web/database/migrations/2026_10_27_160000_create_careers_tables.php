<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Careers. The office posts an opening — a job, or project work that is not one of the trainings the programme
 * runs — and anyone can apply from the public page without an account. What comes in is reviewed here and the
 * person is contacted back; an application is never edited into somebody else's record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('title', 190);
            // A job the programme is hiring for, or project work it is opening to people.
            $table->string('kind', 12)->default('job');
            // What the work is in — training, robotics, construction, mobilisation. Free words, not a closed list.
            $table->string('area', 90)->nullable();
            $table->string('county', 90)->nullable();
            $table->unsignedSmallInteger('positions')->default(1);
            $table->string('description', 1000);
            $table->string('requirements', 1000)->nullable();
            $table->date('closing_on')->nullable();
            // draft stays inside the office, open is on the public page, closed takes the form down.
            $table->string('status', 12)->default('draft');
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'kind']);
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_opening_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email', 150);
            $table->string('phone', 30);
            $table->string('id_number', 40)->nullable();
            $table->string('town', 90)->nullable();
            $table->string('experience', 90)->nullable();
            // Their own CV or a certificate — kept private and served to the office only.
            $table->string('cv_path', 255)->nullable();
            $table->string('cv_name', 150)->nullable();
            $table->unsignedInteger('cv_size')->nullable();
            $table->string('cover_note', 1000)->nullable();
            $table->string('status', 16)->default('received');
            $table->string('review_note', 1000)->nullable();
            $table->date('contacted_on')->nullable();
            $table->foreignId('reviewed_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['job_opening_id', 'status']);
            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_openings');
    }
};
