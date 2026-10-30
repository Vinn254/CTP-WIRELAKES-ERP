<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An attaché or a junior fundi fresh from training often has nobody to name as a referee, so the three
     * referee columns take null rather than forcing a made-up name onto the application.
     */
    public function up(): void
    {
        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->string('referee_name')->nullable()->change();
            $table->string('referee_phone', 20)->nullable()->change();
            $table->string('referee_role')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('fundi_registrations')->whereNull('referee_name')->update(['referee_name' => '']);
        DB::table('fundi_registrations')->whereNull('referee_phone')->update(['referee_phone' => '']);
        DB::table('fundi_registrations')->whereNull('referee_role')->update(['referee_role' => '']);

        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->string('referee_name')->nullable(false)->change();
            $table->string('referee_phone', 20)->nullable(false)->change();
            $table->string('referee_role')->nullable(false)->change();
        });
    }
};
