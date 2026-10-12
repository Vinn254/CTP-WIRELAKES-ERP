<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('hirer_id')->nullable()->constrained('hirer_registrations')->nullOnDelete();
            $table->string('title', 160);
            $table->text('description');
            $table->enum('category', ['attachment', 'internship', 'junior_fundi', 'senior_fundi']);
            $table->string('trade', 80)->nullable();
            $table->string('county', 80)->nullable();
            $table->string('ward', 80)->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('duration', 60)->nullable();
            $table->date('deadline')->nullable();
            $table->enum('status', ['draft', 'open', 'awarded', 'closed'])->default('draft');
            $table->timestamp('awarded_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('applicant_type', ['ctp', 'fundi']);
            $table->unsignedBigInteger('applicant_id');
            $table->string('full_name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['pending_review', 'approved', 'rejected'])->default('pending_review');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'applicant_type', 'applicant_id'], 'project_applications_member_unique');
            $table->index(['applicant_type', 'applicant_id'], 'project_applications_member_index');
        });

        Schema::create('account_invites', function (Blueprint $table) {
            $table->id();
            $table->enum('member_type', ['ctp', 'fundi']);
            $table->unsignedBigInteger('member_id');
            $table->string('name');
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->enum('status', ['pending', 'accepted', 'revoked'])->default('pending');
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['member_type', 'member_id']);
        });

        foreach (['ctp_registrations', 'fundi_registrations'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->after('status');
            });
        }
    }

    public function down(): void
    {
        foreach (['ctp_registrations', 'fundi_registrations'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
        Schema::dropIfExists('account_invites');
        Schema::dropIfExists('project_applications');
        Schema::dropIfExists('projects');
    }
};
