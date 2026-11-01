<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student says which training they are applying for on the application, and the Academic Registrar or the System
 * Administrator places them on one when they chose nothing. It is a pointer into the programme's own catalogue, so
 * switching a training off leaves the student's record standing with no programme rather than a broken one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ctp_registrations', function (Blueprint $table) {
            $table->foreignId('training_id')->nullable()->after('track')
                ->constrained('trainings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ctp_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('training_id');
        });
    }
};
