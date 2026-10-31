<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An E-Fundi who comes as a Trainer states the field they teach, taken from the programme's own training list and
 * deliberately not tied to their trade skills. A CV replaces the business certificate, the trade certificates and
 * the portfolio photos on the form — the columns those used stay, so a record already holding them keeps them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fundi_registrations', function (Blueprint $table) {
            // The grade column is an enum: a Trainer cannot be saved until the list carries it.
            $table->enum('tier', ['fundi_intern', 'attachment', 'junior_fundi', 'senior_fundi', 'trainer'])->change();
            $table->json('training_field_ids')->nullable()->after('service_counties');
            $table->string('cv_path')->nullable()->after('passport_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->enum('tier', ['fundi_intern', 'attachment', 'junior_fundi', 'senior_fundi'])->change();
            $table->dropColumn(['training_field_ids', 'cv_path']);
        });
    }
};
