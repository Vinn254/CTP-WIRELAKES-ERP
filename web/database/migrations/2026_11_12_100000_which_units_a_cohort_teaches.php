<?php

use App\Models\Cohort;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The units a cohort actually teaches.
 *
 * A training lists every unit the programme expects; a single intake rarely runs all of them. The office (or the
 * Academic Registrar) ticks, on the cohort itself, which of <b>that programme's own</b> units this run teaches — the
 * tick list is built from the programme's units, so no screen can invent a unit or borrow one from another course.
 * What is ticked is exactly what the students in that cohort see on their units page and what its trainer marks.
 *
 * Every cohort already running is given the whole list of its programme, so a run that has been graded keeps its
 * marks: nothing is dropped from a record by this table arriving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohort_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained('cohorts')->cascadeOnDelete();
            $table->foreignId('training_module_id')->constrained('training_modules')->cascadeOnDelete();
            $table->unique(['cohort_id', 'training_module_id']);
            $table->timestamps();
        });

        foreach (Cohort::whereNotNull('training_id')->cursor() as $cohort) {
            DB::table('cohort_module')->insert(
                DB::table('training_modules')->where('training_id', $cohort->training_id)
                    ->orderBy('position')->orderBy('id')->pluck('id')
                    ->map(fn (int $id) => [
                        'cohort_id' => $cohort->id,
                        'training_module_id' => $id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_module');
    }
};
