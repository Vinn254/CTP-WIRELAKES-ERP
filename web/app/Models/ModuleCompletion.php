<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Proof that one trainee got through one module. The trainer marks it on their own roll, the office marks it
 * for a partner school — both write the same row, so the trainee sees one line either way.
 */
class ModuleCompletion extends Model
{
    protected $fillable = [
        'enrollment_id', 'training_module_id', 'done_on', 'note', 'recorded_by_user', 'recorded_by_fundi',
    ];

    protected $casts = ['done_on' => 'date'];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user');
    }

    public function trainerFundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'recorded_by_fundi');
    }

    /** Only a note is optional input — the date and who marked it are never typed. */
    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'note' => 'nullable|string|max:1000',
        ], [], ['note' => 'What they showed']);
    }

    public function recorderLabel(): string
    {
        if ($this->trainerFundi) {
            return 'Trainer '.$this->trainerFundi->full_name;
        }

        return $this->staffUser ? $this->staffUser->name.' (office)' : 'Recorded by the system';
    }
}
