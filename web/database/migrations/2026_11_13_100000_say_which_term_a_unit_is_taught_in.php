<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which term a unit is taught in.
 *
 * A programme's units are planned against the academic year: the Registrar says whether a unit runs in Term 1, Term 2
 * or Term 3, or leaves it <b>general</b> — taught in every term, so an intake that starts at any point still gets it.
 * Nothing existing changes: every unit already on the list is put at general, which is exactly what it has been doing.
 *
 * The term is the office's planning note. It is the Registrar's own screen that sets it — the System Administrator
 * keeps the power to put a new field of training on the programme list, not to write the units underneath it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_modules', function (Blueprint $table) {
            $table->string('term', 16)->default('general')->after('hours');
        });
    }

    public function down(): void
    {
        Schema::table('training_modules', function (Blueprint $table) {
            $table->dropColumn('term');
        });
    }
};
