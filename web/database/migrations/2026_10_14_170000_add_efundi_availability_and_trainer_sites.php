<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 5 — E-Fundi Management. An E-Fundi is working labour, so the register has to say whether they
 * can be taken (§8.3 availability) and whether the programme has blocked them (§8.4 suspended /
 * blacklisted). A fundi-trainer is approved for a SITE (§7.8 "delivers training cohorts in their
 * region, own workshop or community site"), so one fundi can hold the seat at Nyanda and be declined
 * at another — the trainers row stops being unique on the person and becomes one row per site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->enum('status', [
                'pending_payment', 'pending_review', 'active', 'rejected', 'suspended', 'blacklisted',
            ])->default('pending_payment')->change();

            $table->enum('availability', ['available', 'on_job', 'on_leave'])
                ->default('available')->after('status');
            $table->string('availability_note', 300)->nullable()->after('availability');
        });

        // MySQL keeps a foreign key tied to an index, so the replacement is laid down before the
        // one-row-per-fundi index it takes over from is dropped.
        Schema::table('trainers', function (Blueprint $table) {
            $table->unique(['fundi_id', 'venue_id']);
        });

        Schema::table('trainers', function (Blueprint $table) {
            $table->dropUnique(['fundi_id']);
        });
    }

    public function down(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->dropUnique(['fundi_id', 'venue_id']);
            $table->unique('fundi_id');
        });

        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->dropColumn(['availability', 'availability_note']);
            $table->enum('status', ['pending_payment', 'pending_review', 'active', 'rejected'])
                ->default('pending_payment')->change();
        });
    }
};
