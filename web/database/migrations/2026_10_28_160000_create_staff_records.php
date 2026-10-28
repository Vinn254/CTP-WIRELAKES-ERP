<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The staff directory Human Resources keeps: everything the office knows about a person who works in the
 * organisation — next of kin, the C14 on file and the salary — held beside the login account, and filled in by
 * the onboarding desk when an applicant is given the job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_records', function (Blueprint $table) {
            $table->id();
            // The account is not there yet while an invitation is still waiting to be opened, so it is nullable:
            // a person onboards first and appears in the directory straight away.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('job_application_id')->nullable()->constrained('job_applications')->nullOnDelete();
            $table->string('name', 150);
            $table->string('email', 160)->index();
            $table->string('phone', 30)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->date('started_on')->nullable();
            $table->decimal('salary', 12, 2)->nullable();
            $table->string('id_number', 40)->nullable();
            $table->string('kra_pin', 40)->nullable();
            $table->string('nssf_number', 40)->nullable();
            $table->string('nhif_number', 40)->nullable();
            $table->string('bank_name', 90)->nullable();
            $table->string('bank_branch', 90)->nullable();
            $table->string('bank_account', 40)->nullable();
            $table->string('address', 180)->nullable();
            $table->string('next_of_kin_name', 150)->nullable();
            $table->string('next_of_kin_relation', 60)->nullable();
            $table->string('next_of_kin_phone', 30)->nullable();
            $table->string('c14_path', 260)->nullable();
            $table->string('c14_name', 180)->nullable();
            $table->unsignedInteger('c14_size')->nullable();
            $table->foreignId('recorded_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('job_applications', function (Blueprint $table) {
            $table->timestamp('onboarded_at')->nullable();
            $table->foreignId('onboarded_by_user')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('onboarded_by_user');
            $table->dropColumn('onboarded_at');
        });
        Schema::dropIfExists('staff_records');
    }
};
