<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which counties an intake is offered in.
 *
 * A cohort held one county name, so an intake could only ever be put in front of CTPs from that county — and the form
 * would not save unless a venue or a community was tied to it as well. In practice a mobilisation covers a whole
 * region, an intake is often run for every county at once, and plenty of intakes belong to no community at all.
 *
 * So the intake states its reach: every county, or the counties ticked here. The county written on the cohort row
 * stays as where the training physically happens (a printed place, a map tile), and a community is never compulsory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->string('county_scope', 12)->default('listed')->after('county');
        });

        Schema::create('cohort_county', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('county_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['cohort_id', 'county_id']);
        });

        // Each intake already on the site keeps the county it was filed under, so nothing has to be ticked again. An
        // intake with no county at all was out of every CTP's area before and would be shut out again — that is now
        // an intake offered everywhere.
        DB::statement('UPDATE `cohorts` SET `county_scope` = IF(`county` IS NULL OR `county` = "", "all", "listed")');

        DB::statement('INSERT INTO `cohort_county` (`cohort_id`, `county_id`, `created_at`, `updated_at`)
            SELECT c.`id`, co.`id`, NOW(), NOW() FROM `cohorts` c
            JOIN `counties` co ON co.`name` = c.`county`
            WHERE c.`county` IS NOT NULL AND c.`county` <> ""
              AND NOT EXISTS (SELECT 1 FROM `cohort_county` cc WHERE cc.`cohort_id` = c.`id`
                AND cc.`county_id` = co.`id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_county');
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropColumn('county_scope');
        });
    }
};
