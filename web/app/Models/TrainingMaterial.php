<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Learning material a trainer hands to a cohort — a note, an assignment, a slide deck for one unit or one lesson.
 *
 * It sits on the cohort and the unit rather than on a trainee, so every CTP in the class opens the same handout and
 * nothing is uploaded once per person. The file is stored privately and only ever served through this row.
 */
class TrainingMaterial extends Model
{
    public const KINDS = [
        'note' => 'Topic note',
        'slide' => 'Slides',
        'assignment' => 'Assignment',
        'practical' => 'Practical exercise',
        'past_paper' => 'Past paper',
        'other' => 'Other material',
    ];

    protected $fillable = [
        'cohort_id', 'training_module_id', 'lesson_line_id', 'given_on', 'title', 'kind', 'note',
        'file_path', 'file_name', 'file_size', 'uploaded_by_user', 'uploaded_by_fundi',
    ];

    protected $casts = ['given_on' => 'date', 'file_size' => 'integer'];

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(LessonLine::class, 'lesson_line_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user');
    }

    public function uploaderFundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'uploaded_by_fundi');
    }

    /** The handout form. A file is needed to post it and only needed again to replace the one on record. */
    public static function dataFrom(Request $request, bool $fileRequired = true): array
    {
        return $request->validate([
            'training_module_id' => 'nullable|exists:training_modules,id',
            'lesson_line_id' => 'nullable|exists:lesson_lines,id',
            'given_on' => 'nullable|date',
            'title' => 'required|string|max:190',
            'kind' => 'required|in:'.implode(',', array_keys(self::KINDS)),
            'file' => ($fileRequired ? 'required' : 'nullable').'|file|mimes:pdf,jpg,jpeg,png,doc,docx,ppt,pptx,xls,xlsx,zip|max:20480',
            'note' => 'nullable|string|max:500',
        ], [
            'training_module_id.required' => 'Say which unit the material belongs to.',
        ], [
            'training_module_id' => 'Unit', 'lesson_line_id' => 'Lesson', 'given_on' => 'Handed out on',
            'title' => 'Title', 'kind' => 'Type', 'file' => 'The file',
        ]);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? Str::headline((string) $this->kind);
    }

    public function sizeLabel(): string
    {
        if (! $this->file_size) {
            return '';
        }

        return $this->file_size > 1048576
            ? round($this->file_size / 1048576, 1).' MB'
            : max(1, (int) round($this->file_size / 1024)).' KB';
    }

    public function dateLabel(): string
    {
        return $this->given_on?->format('d M Y') ?: 'no date';
    }
}
