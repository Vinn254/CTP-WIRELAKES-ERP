<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 4 — Training & Certification (§7). A training runs as a cohort at a venue (a partner school,
 * a community site or a programme centre) or under a fundi-trainer in their own area; CTPs take seats,
 * progress is recorded through the cohort until it is certified, and an executive approves the certificate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_venues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('type', ['partner_school', 'community_site', 'programme_centre'])->default('community_site');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignId('community_id')->nullable()->constrained('communities')->nullOnDelete();
            $table->string('county', 100);
            $table->string('sub_county', 100)->nullable();
            $table->string('ward', 120)->nullable();
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->text('summary')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // The trainer role on a fundi. Module 5 owns the person; this row is only their licence to teach.
        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fundi_id')->constrained('fundi_registrations')->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('training_venues')->nullOnDelete();
            $table->string('county', 100)->nullable();
            $table->string('sub_county', 100)->nullable();
            $table->string('ward', 120)->nullable();
            $table->json('teaches')->nullable();
            $table->unsignedTinyInteger('weekly_hours')->default(0);
            $table->decimal('rate_ksh', 10, 2)->nullable();
            $table->enum('status', ['applied', 'approved', 'declined', 'suspended'])->default('applied');
            $table->string('note', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('fundi_id');
        });

        Schema::create('cohorts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('course', 150);
            $table->enum('family', ['ICT', 'Networking', 'Programming', 'Robotics', 'AI', 'Electrical', 'Construction', 'E-Waste']);
            $table->foreignId('venue_id')->nullable()->constrained('training_venues')->nullOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('trainers')->nullOnDelete();
            $table->foreignId('community_id')->nullable()->constrained('communities')->nullOnDelete();
            $table->string('county', 100)->nullable();
            $table->string('sub_county', 100)->nullable();
            $table->string('ward', 120)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('enrol_closes_on')->nullable();
            $table->unsignedSmallInteger('seats')->default(0);
            $table->unsignedSmallInteger('theory_hours')->default(0);
            $table->unsignedSmallInteger('practical_hours')->default(0);
            $table->decimal('fee_ksh', 10, 2)->default(0);
            $table->text('summary')->nullable();
            $table->enum('status', ['draft', 'open', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained('cohorts')->cascadeOnDelete();
            $table->foreignId('ctp_id')->constrained('ctp_registrations')->cascadeOnDelete();
            $table->enum('state', ['pending', 'approved', 'declined', 'withdrawn'])->default('pending');
            $table->enum('stage', ['enrolled', 'theory', 'practical', 'assessment', 'completed', 'failed'])->default('enrolled');
            $table->enum('source', ['self', 'office'])->default('self');
            $table->string('note', 1000)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('approved_on')->nullable();
            $table->timestamps();
            $table->unique(['cohort_id', 'ctp_id']);
        });

        Schema::create('progress_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->date('entry_date');
            $table->enum('kind', ['attendance', 'theory', 'practical', 'assessment', 'note'])->default('note');
            $table->boolean('present')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->unsignedSmallInteger('hours')->nullable();
            $table->string('note', 1000)->nullable();
            $table->foreignId('recorded_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by_fundi')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->timestamps();
            $table->index(['enrollment_id', 'entry_date']);
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained('enrollments')->cascadeOnDelete();
            $table->string('certificate_code', 60)->nullable()->unique();
            $table->decimal('theory_score', 5, 2)->nullable();
            $table->decimal('practical_score', 5, 2)->nullable();
            $table->decimal('attendance_pct', 5, 2)->nullable();
            $table->enum('status', ['recommended', 'approved', 'declined', 'revoked'])->default('recommended');
            $table->text('reason')->nullable();
            $table->foreignId('recommended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('approved_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('progress_entries');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('cohorts');
        Schema::dropIfExists('trainers');
        Schema::dropIfExists('training_venues');
    }
};
