<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ctp_registrations', function (Blueprint $table) {
            $table->string('fundi_tier', 30)->nullable()->after('track');
        });
    }

    public function down(): void
    {
        Schema::table('ctp_registrations', function (Blueprint $table) {
            $table->dropColumn('fundi_tier');
        });
    }
};
