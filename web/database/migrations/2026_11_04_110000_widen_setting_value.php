<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The payroll deduction rates are one JSON value with five PAYE bands in it, which a 255-character column cannot
 * hold. Programme switches are kept on the same table, so the column is widened rather than the rates split up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->text('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('value', 255)->nullable()->change();
        });
    }
};
