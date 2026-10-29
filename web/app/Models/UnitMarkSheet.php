<?php

namespace App\Models;

use App\Models\Concerns\ForwardedPaper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The marks one unit of a cohort was taken through — the sheet a trainer fills in and forwards to the Registrar.
 *
 * A unit is marked on a day, once: the sheet is the paper, and each line on it is one CTP's mark for one kind.
 * Attendance is not on this sheet — it is counted on the day's register, which is a separate paper.
 */
class UnitMarkSheet extends Model
{
    use ForwardedPaper;

    /** The four marks a unit carries, in the order they are written across the sheet. */
    public const KINDS = [
        'cat' => 'CAT',
        'assignment' => 'Assignment',
        'theory' => 'Theory',
        'practical' => 'Practical',
    ];

    public const OUT_OF = 100;

    protected $fillable = [
        'cohort_id', 'training_module_id', 'trainer_id', 'covered_on', 'note',
        'status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note', 'taken_by_user', 'taken_by_fundi',
    ];

    protected $casts = ['covered_on' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class, 'trainer_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(UnitMark::class, 'sheet_id');
    }

    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'covered_on' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:500',
        ], [], ['covered_on' => 'Day the unit was taken']);
    }

    public function dateLabel(): string
    {
        return $this->covered_on?->format('D d M Y') ?: 'date not set';
    }

    public function unitLabel(): string
    {
        return $this->module?->title ?? Str::headline((string) $this->training_module_id);
    }
}
