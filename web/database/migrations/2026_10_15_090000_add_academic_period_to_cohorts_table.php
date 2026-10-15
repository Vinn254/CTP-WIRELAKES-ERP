<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A course runs more than once — the same training in another term, and again next year. A cohort needs to say
 * which academic year and which term or semester it was, so a run is placed in history rather than only having
 * a start date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->unsignedSmallInteger('academic_year')->nullable()->after('ends_on');
            $table->string('term', 32)->nullable()->after('academic_year');

            $table->index(['academic_year', 'term']);
        });

        // A run that already happened belongs to the year and term its own dates fall in.
        DB::statement('update cohorts set academic_year = year(starts_on) where academic_year is null');
        DB::statement("update cohorts set term = case
            when month(starts_on) <= 4 then 'term_1'
            when month(starts_on) <= 8 then 'term_2'
            else 'term_3' end where term is null");
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropIndex(['academic_year', 'term']);
            $table->dropColumn(['academic_year', 'term']);
        });
    }
};
