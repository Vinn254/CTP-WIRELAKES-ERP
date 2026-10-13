<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL only accepts enum values that already exist, so widen, move the rows, then narrow.
        Schema::table('community_ctp', function (Blueprint $table) {
            $table->enum('role', ['lead', 'support', 'mobilizer'])->default('support')->change();
        });

        DB::table('community_ctp')->where('role', 'support')->update(['role' => 'mobilizer']);

        Schema::table('community_ctp', function (Blueprint $table) {
            $table->enum('role', ['lead', 'mobilizer'])->default('mobilizer')->change();
        });
    }

    public function down(): void
    {
        Schema::table('community_ctp', function (Blueprint $table) {
            $table->enum('role', ['lead', 'support', 'mobilizer'])->default('support')->change();
        });

        DB::table('community_ctp')->where('role', 'mobilizer')->update(['role' => 'support']);

        Schema::table('community_ctp', function (Blueprint $table) {
            $table->enum('role', ['lead', 'support'])->default('support')->change();
        });
    }
};
