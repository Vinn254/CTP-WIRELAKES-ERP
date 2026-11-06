<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A trainer the Registrar named on one unit of one intake. A unit can carry several hands. */
class UnitTrainer extends Model
{
    protected $table = 'cohort_unit_trainers';

    protected $fillable = ['cohort_id', 'training_module_id', 'trainer_id'];

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }
}
