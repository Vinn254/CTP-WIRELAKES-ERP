<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The C14 is a next-of-kin declaration, so the office types the people in rather than uploading a form.
 * Two are kept: the first is required, the second is optional. A copy already filed stays on the private disk
 * and still opens from the record, so no document is lost by the change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_records', function (Blueprint $table) {
            $table->string('next_of_kin_2_name', 150)->nullable()->after('next_of_kin_phone');
            $table->string('next_of_kin_2_relation', 60)->nullable()->after('next_of_kin_2_name');
            $table->string('next_of_kin_2_phone', 30)->nullable()->after('next_of_kin_2_relation');
        });
    }

    public function down(): void
    {
        Schema::table('staff_records', function (Blueprint $table) {
            $table->dropColumn(['next_of_kin_2_name', 'next_of_kin_2_relation', 'next_of_kin_2_phone']);
        });
    }
};
