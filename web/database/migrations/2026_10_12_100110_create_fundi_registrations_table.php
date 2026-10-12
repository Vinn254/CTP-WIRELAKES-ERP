<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fundi_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('full_name');
            $table->string('national_id', 20)->unique();
            $table->string('phone', 20)->unique();
            $table->string('email')->unique();
            $table->date('dob');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->enum('tier', ['fundi_intern', 'attachment', 'junior_fundi', 'senior_fundi']);
            $table->string('business_name')->nullable();
            $table->string('business_reg_no')->nullable();
            $table->unsignedTinyInteger('years_in_business')->nullable();
            $table->json('specialisations');
            $table->json('service_counties');
            $table->string('referee_name');
            $table->string('referee_phone', 20);
            $table->string('referee_role');
            $table->string('id_scan_path')->nullable();
            $table->string('business_cert_path')->nullable();
            $table->json('trade_cert_paths')->nullable();
            $table->json('portfolio_paths')->nullable();
            $table->enum('status', ['pending_review', 'active', 'rejected'])->default('pending_review');
            $table->text('rejection_reason')->nullable();
            $table->string('ef_code', 20)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fundi_registrations');
    }
};
