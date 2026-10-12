<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hirer_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('contact_name');
            $table->string('organisation');
            $table->string('phone', 20)->unique();
            $table->string('email')->unique();
            $table->string('county');
            $table->string('org_type')->nullable();
            $table->string('org_doc_path')->nullable();
            $table->enum('status', ['pending_review', 'active', 'rejected'])->default('pending_review');
            $table->text('rejection_reason')->nullable();
            $table->string('hirer_code', 20)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('website')->nullable();
            $table->string('contact_email')->nullable();
            $table->enum('tier', ['platinum', 'gold', 'silver'])->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_listed')->default(false);
            $table->timestamps();
        });

        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('donor_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('KES');
            $table->enum('method', ['mpesa', 'card', 'bank']);
            $table->enum('frequency', ['one_time', 'monthly', 'yearly'])->default('one_time');
            $table->string('impact_label')->nullable();
            $table->text('message')->nullable();
            $table->boolean('anonymous')->default(false);
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('donations');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('hirer_registrations');
    }
};
