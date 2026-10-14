<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every member who joins uploads the photo that goes on their profile. A CTP application already
 * carried one (passport_photo_path); this gives the E-Fundi register the same column so both
 * member types are identified by the same picture the office captured at the counter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->string('passport_photo_path')->nullable()->after('id_scan_path');
        });
    }

    public function down(): void
    {
        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->dropColumn('passport_photo_path');
        });
    }
};
