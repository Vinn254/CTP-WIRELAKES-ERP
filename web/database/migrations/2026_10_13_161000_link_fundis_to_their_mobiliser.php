<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->string('county', 100)->nullable()->after('tier');
            $table->string('sub_county', 100)->nullable()->after('county');
            $table->string('ward', 100)->nullable()->after('sub_county');
            $table->foreignId('mobilised_by_ctp')->nullable()->after('ward')
                ->constrained('ctp_registrations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fundi_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mobilised_by_ctp');
            $table->dropColumn(['county', 'sub_county', 'ward']);
        });
    }
};
