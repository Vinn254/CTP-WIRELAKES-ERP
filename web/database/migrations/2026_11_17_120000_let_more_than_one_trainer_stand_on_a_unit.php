<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * More than one trainer on a unit.
 *
 * A unit could only ever name one hand, so a course shared between a theory lecturer and a practical supervisor — or a
 * unit handed over mid-run — had to be left unassigned on the papers. The intake's units now carry a list of trainers:
 * the first name stays on `cohort_module.trainer_id` as the lead, because every paper already written reads that one
 * column, and the whole list lives here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cohort_unit_trainers')) {
            Schema::create('cohort_unit_trainers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cohort_id')->constrained()->cascadeOnDelete();
                $table->foreignId('training_module_id')->constrained()->cascadeOnDelete();
                $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                // Named by hand: MySQL cuts an automatic key name for these three columns over its 64-character limit.
                $table->unique(['cohort_id', 'training_module_id', 'trainer_id'], 'cohort_unit_hands_unique');
                $table->index('trainer_id', 'cohort_unit_hands_trainer_index');
            });
        } else {
            // A run that stopped halfway leaves the table without its keys, so put the missing ones on rather than fail.
            $keys = collect(DB::select('SHOW INDEX FROM `cohort_unit_trainers`'))->pluck('Key_name')->unique();

            Schema::table('cohort_unit_trainers', function (Blueprint $table) use ($keys) {
                if (! $keys->contains('cohort_unit_hands_unique')) {
                    $table->unique(['cohort_id', 'training_module_id', 'trainer_id'], 'cohort_unit_hands_unique');
                }

                if (! $keys->contains('cohort_unit_hands_trainer_index')) {
                    $table->index('trainer_id', 'cohort_unit_hands_trainer_index');
                }
            });
        }

        DB::statement('INSERT INTO `cohort_unit_trainers` (`cohort_id`, `training_module_id`, `trainer_id`,
            `created_at`, `updated_at`)
            SELECT cm.`cohort_id`, cm.`training_module_id`, cm.`trainer_id`, NOW(), NOW() FROM `cohort_module` cm
            WHERE cm.`trainer_id` IS NOT NULL
              AND NOT EXISTS (SELECT 1 FROM `cohort_unit_trainers` cut WHERE cut.`cohort_id` = cm.`cohort_id`
                AND cut.`training_module_id` = cm.`training_module_id` AND cut.`trainer_id` = cm.`trainer_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_unit_trainers');
    }
};
