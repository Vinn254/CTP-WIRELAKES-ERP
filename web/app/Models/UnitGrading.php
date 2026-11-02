<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * The Registrar's grading for one unit: what each mark is worth, and the mark a student has to reach to pass it.
 *
 * One row per unit. A unit the Registrar has not set yet is graded on [Grading::DEFAULTS], so a cohort can be marked
 * from the first day and the office can put the weights right whenever it gets to them.
 */
class UnitGrading extends Model
{
    protected $table = 'unit_grading';

    /** The five weighed parts, in the order a screen lists them. */
    public const PARTS = [
        'cat' => 'CAT',
        'assignment' => 'Assignment',
        'theory' => 'Theory',
        'practical' => 'Practical',
        'attendance' => 'Attendance',
    ];

    protected $fillable = [
        'training_module_id', 'cat_pct', 'assignment_pct', 'theory_pct', 'practical_pct',
        'attendance_pct', 'pass_pct', 'note', 'set_by_user',
    ];

    protected $casts = [
        'cat_pct' => 'integer',
        'assignment_pct' => 'integer',
        'theory_pct' => 'integer',
        'practical_pct' => 'integer',
        'attendance_pct' => 'integer',
        'pass_pct' => 'decimal:1',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user');
    }

    /** The weights as one map, keyed the same way as a mark's kind. */
    public function weights(): array
    {
        return collect(self::PARTS)
            ->mapWithKeys(fn (string $label, string $part) => [$part => (int) $this->{"{$part}_pct"}])
            ->all();
    }

    /** The form every grading screen uses. The parts must add to 100, or a grade would not be out of 100. */
    public static function dataFrom(Request $request): array
    {
        $rules = ['pass_pct' => 'required|numeric|min:0|max:100', 'note' => 'nullable|string|max:500'];
        $messages = ['pass_pct.required' => 'Say the mark a student has to reach to pass this unit.'];
        $names = [];

        foreach (self::PARTS as $part => $label) {
            $rules["{$part}_pct"] = 'required|integer|min:0|max:100';
            $messages["{$part}_pct.required"] = 'Say what the '.$label.' part is worth, or 0 if this unit does not use it.';
            $names["{$part}_pct"] = $label;
        }

        $data = $request->validate($rules, $messages, $names);

        $sum = collect(self::PARTS)->sum(fn (string $label, string $part) => (int) $data["{$part}_pct"]);

        abort_unless($sum === 100, 422,
            'The parts add to '.$sum.'%. They must add to 100% — otherwise a grade would not be out of 100.');

        return $data;
    }
}
