<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 9 — Technology Services (§12). One asset register, one project register and one fault-ticket line, each cut
 * by the five sub-areas (ICT, Networking, Robotics, Programming, AI). The five share the same fundis, the same finance
 * and the same M&E, so they share these three tables and differ only by their category — not by five near-empty copies.
 */
return new class extends Migration
{
    public function up(): void
    {
        // §12.2–12.6 — a device, licence or kit. It carries its own location, warranty and licence dates so the
        // alerts (§12.7) can be read off the register, and it can be assigned to a community or organisation.
        Schema::create('technology_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 40)->unique();
            $table->string('category', 20);
            $table->string('name', 200);
            $table->string('kind', 60)->nullable();
            $table->text('description')->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->string('status', 20)->default('operational');
            // §12.7 — the QR payload printed on the asset tag; it is scanned back to this record.
            $table->string('qr_payload', 400)->nullable();
            // An asset handed to a community, an organisation or a named holder (§12.7).
            $table->string('assigned_type', 20)->nullable();
            $table->unsignedBigInteger('assigned_id')->nullable();
            $table->string('assigned_name', 200)->nullable();
            $table->string('location', 200)->nullable();
            $table->string('county', 80)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost_ksh', 14, 2)->default(0);
            $table->string('supplier', 200)->nullable();
            // §12.2 — warranty and software licence tracking, each with its own renewal date.
            $table->date('warranty_expires_on')->nullable();
            $table->string('licence_key', 200)->nullable();
            $table->unsignedSmallInteger('licence_seats')->nullable();
            $table->date('licence_renews_on')->nullable();
            // §12.7 — a refurbished device comes in from Module 10; a dead one is decommissioned and routed back.
            $table->string('source', 20)->default('purchase');
            $table->foreignId('ewaste_collection_id')->nullable()->constrained('ewaste_collections')->nullOnDelete();
            $table->date('decommissioned_on')->nullable();
            $table->string('decommission_note', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'status']);
            $table->index('warranty_expires_on');
        });

        // §12.4–12.6 — the robotics, programming and AI work the programme delivers. A project runs through the AI
        // lifecycle (problem → data → design → develop → test → deploy → monitor); the same status column carries it.
        Schema::create('technology_projects', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('category', 20);
            $table->string('title', 200);
            $table->text('summary')->nullable();
            $table->text('problem')->nullable();
            $table->string('technology', 300)->nullable();
            $table->unsignedSmallInteger('team_size')->default(0);
            // A Robotics / AI E-Fundi mentors the team (§12.4); the community the work belongs to feeds Module 12.
            $table->foreignId('mentor_fundi_id')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->foreignId('community_id')->nullable()->constrained('communities')->nullOnDelete();
            // A technology project that also runs as a job comes through Module 6; keep the thread.
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('status', 30)->default('identified');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'status']);
        });

        // §12.2, §12.7 — a repair or fault ticket on an asset, opened in the field, assigned to a fundi, closed out.
        Schema::create('technology_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('asset_id')->constrained('technology_assets')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('fault');
            $table->string('opened_by', 160)->nullable();
            $table->string('opened_by_type', 20)->nullable();
            $table->foreignId('assigned_fundi_id')->nullable()->constrained('fundi_registrations')->nullOnDelete();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('open');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_on')->nullable();
            $table->timestamp('closed_on')->nullable();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['assigned_fundi_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technology_tickets');
        Schema::dropIfExists('technology_projects');
        Schema::dropIfExists('technology_assets');
    }
};
