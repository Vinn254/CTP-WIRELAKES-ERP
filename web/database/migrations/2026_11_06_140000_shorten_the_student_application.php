<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public student application asks for the short set only, so these four are recorded by the office while
 * it reviews the person. Nothing is lost: the columns keep every value an application already carried.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ctp_registrations', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->change();
            $table->string('village')->nullable()->change();
            $table->string('education_level')->nullable()->change();
            $table->enum('track', ['school_leaver', 'junior'])->nullable()->change();
        });
    }

    public function down(): void
    {
        // A short application has no value to put back, so rolling this by hand would delete real records.
    }
};
