<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctp_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('full_name');
            $table->string('national_id', 20)->unique();
            $table->string('phone', 20)->unique();
            $table->string('email')->unique();
            $table->date('dob');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->string('county');
            $table->string('sub_county');
            $table->string('ward');
            $table->string('village');
            $table->string('education_level');
            $table->enum('track', ['school_leaver', 'junior']);
            $table->string('referral_code', 20)->nullable()->index();
            $table->string('id_scan_path')->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('passport_photo_path')->nullable();
            $table->enum('status', ['pending_review', 'active', 'rejected'])->default('pending_review');
            $table->text('rejection_reason')->nullable();
            $table->string('ctp_code', 20)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ctp_registrations');
    }
};
