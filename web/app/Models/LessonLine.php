<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * One lesson on a teaching plan: the unit, the day and time it sits, and everything the trainer means to cover.
 */
class LessonLine extends Model
{
    protected $fillable = [
        'lesson_plan_id', 'position', 'training_module_id', 'planned_on', 'starts_at', 'ends_at', 'topic', 'summary',
        'objectives', 'activities', 'resources', 'assessment_note',
    ];

    protected $casts = ['planned_on' => 'date', 'position' => 'integer'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(LessonPlan::class, 'lesson_plan_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(TrainingMaterial::class, 'lesson_line_id');
    }

    /** The lesson form, the same fields wherever a lesson is written. */
    public static function dataFrom(Request $request): array
    {
        $data = $request->validate([
            'training_module_id' => 'nullable|exists:training_modules,id',
            'planned_on' => 'nullable|date',
            'starts_at' => 'nullable|date_format:H:i|required_with:ends_at',
            'ends_at' => 'nullable|date_format:H:i|after:starts_at',
            'topic' => 'required|string|max:190',
            'summary' => 'required|string|max:20000',
            'objectives' => 'nullable|string|max:5000',
            'activities' => 'nullable|string|max:5000',
            'resources' => 'nullable|string|max:5000',
            'assessment_note' => 'nullable|string|max:5000',
        ], [
            'ends_at.after' => 'A lesson cannot end before it starts.',
            'summary.required' => 'Write what the lesson covers — the summary and the content.',
        ], [
            'training_module_id' => 'Unit', 'planned_on' => 'Day', 'starts_at' => 'Starts', 'ends_at' => 'Ends',
            'topic' => 'What is taught', 'summary' => 'Summary and content', 'objectives' => 'Objectives',
            'activities' => 'Activities', 'resources' => 'Resources', 'assessment_note' => 'How the class is assessed',
        ]);

        return $data;
    }

    public function dateLabel(): string
    {
        return $this->planned_on?->format('D d M Y') ?: 'day not fixed';
    }

    public function timeLabel(): string
    {
        if (! $this->starts_at) {
            return '';
        }

        return substr($this->starts_at, 0, 5).' – '.substr((string) $this->ends_at, 0, 5);
    }

    /** The first free lesson number on the plan, so lessons run 1, 2, 3 in the order they are written. */
    public static function nextPosition(int $planId): int
    {
        return (int) static::where('lesson_plan_id', $planId)->max('position') + 1;
    }
}
