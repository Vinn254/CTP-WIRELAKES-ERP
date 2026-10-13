<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communities', function (Blueprint $table) {
            $table->id();
            $table->string('community_code', 12)->nullable()->unique();
            $table->string('name', 160);
            $table->string('county', 100);
            $table->string('sub_county', 100);
            $table->string('ward', 100);
            $table->string('village', 100)->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->unsignedInteger('population')->nullable();
            $table->unsignedInteger('households')->nullable();
            $table->text('summary')->nullable();
            $table->enum('status', ['pending_review', 'active', 'rejected'])->default('pending_review');
            $table->string('rejection_reason', 2000)->nullable();
            $table->foreignId('created_by_ctp')->nullable()->constrained('ctp_registrations')->nullOnDelete();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // One CTP looks after a community (lead); others may support it.
        Schema::create('community_ctp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ctp_id')->constrained('ctp_registrations')->cascadeOnDelete();
            $table->enum('role', ['lead', 'support'])->default('support');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->unique(['community_id', 'ctp_id']);
        });

        Schema::create('needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title', 160);
            $table->text('detail')->nullable();
            $table->enum('priority', ['low', 'standard', 'high', 'urgent'])->default('standard');
            $table->enum('status', ['proposed', 'prioritised', 'converted', 'closed'])->default('proposed');
            $table->string('photo_path')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->foreignId('assessed_by_ctp')->nullable()->constrained('ctp_registrations')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->index(['community_id', 'status']);
        });

        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->unsignedTinyInteger('age')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('type', ['student', 'youth', 'household', 'business', 'organisation'])->default('youth');
            $table->date('benefited_on');
            $table->string('id_number', 20)->nullable();
            $table->boolean('id_consent')->default(false);
            $table->foreignId('created_by_ctp')->nullable()->constrained('ctp_registrations')->nullOnDelete();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['community_id', 'type']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('community_id')->nullable()->after('ward')
                ->constrained('communities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('community_id');
        });
        Schema::dropIfExists('beneficiaries');
        Schema::dropIfExists('needs');
        Schema::dropIfExists('community_ctp');
        Schema::dropIfExists('communities');
    }
};
