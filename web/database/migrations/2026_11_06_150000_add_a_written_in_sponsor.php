<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sponsor is not always one of our partners. An applicant may name anybody who backs them, so the name and
 * the contact details are written in by hand and kept beside the partner pick.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ctp_registrations', function (Blueprint $table) {
            $table->string('sponsor_name')->nullable()->after('sponsor_id');
            $table->string('sponsor_contact')->nullable()->after('sponsor_name');
        });

        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('sponsor_id')->nullable()->after('referee_role');
            $table->string('sponsor_name')->nullable()->after('sponsor_id');
            $table->string('sponsor_contact')->nullable()->after('sponsor_name');
        });
    }

    public function down(): void
    {
        Schema::table('ctp_registrations', function (Blueprint $table) {
            $table->dropColumn(['sponsor_name', 'sponsor_contact']);
        });

        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->dropColumn(['sponsor_id', 'sponsor_name', 'sponsor_contact']);
        });
    }
};
