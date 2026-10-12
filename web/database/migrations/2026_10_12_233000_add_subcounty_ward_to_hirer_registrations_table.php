<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hirer_registrations', function (Blueprint $table) {
            $table->string('sub_county', 100)->nullable()->after('county');
            $table->string('ward', 100)->nullable()->after('sub_county');
        });
    }

    public function down(): void
    {
        Schema::table('hirer_registrations', function (Blueprint $table) {
            $table->dropColumn(['sub_county', 'ward']);
        });
    }
};
