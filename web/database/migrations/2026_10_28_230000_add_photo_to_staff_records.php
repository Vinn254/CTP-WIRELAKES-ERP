<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Human Resources puts a face on a staff file, the same way every member registers with one. The picture is kept on
 * the private disk beside the C14 and is served to this desk only — it is never a public column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('staff_records', 'photo_path')) {
            Schema::table('staff_records', function (Blueprint $table) {
                $table->string('photo_path', 260)->nullable()->after('c14_size');
            });
        }
    }

    public function down(): void
    {
        Schema::table('staff_records', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
