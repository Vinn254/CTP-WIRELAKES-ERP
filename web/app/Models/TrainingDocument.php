<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One document on a trainee's record. The office uploads the report a partner school sends; a trainer uploads
 * what they covered in the field. The file is stored privately and only ever opened through the seat it
 * belongs to, so another CTP's paperwork cannot be reached by guessing an id.
 */
class TrainingDocument extends Model
{
    public const KINDS = [
        'school_report' => 'Partner school report',
        'assessment_paper' => 'Assessment paper',
        'competency_sheet' => 'Competency sheet',
        'photo' => 'Photo of the work',
        'other' => 'Other evidence',
    ];

    protected $fillable = [
        'enrollment_id', 'training_module_id', 'title', 'kind', 'dated_on',
        'file_path', 'file_name', 'file_size', 'note', 'uploaded_by_user', 'uploaded_by_fundi',
    ];

    protected $casts = ['dated_on' => 'date', 'file_size' => 'integer'];

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
        return $this->belongsTo(User::class, 'uploaded_by_user');
    }

    public function trainerFundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'uploaded_by_fundi');
    }

    public static function dataFrom(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'kind' => 'required|in:'.implode(',', array_keys(self::KINDS)),
            'training_module_id' => 'nullable|integer',
            'dated_on' => 'required|date|before_or_equal:today',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:8192',
            'note' => 'nullable|string|max:1000',
        ], [], [
            'title' => 'What it is', 'training_module_id' => 'Module', 'dated_on' => 'Date',
            'file' => 'The document', 'kind' => 'Type',
        ]);

        return $data;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? Str::headline($this->kind);
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

    public function uploaderLabel(): string
    {
        if ($this->trainerFundi) {
            return 'Uploaded by trainer '.$this->trainerFundi->full_name;
        }

        return $this->staffUser ? 'Uploaded by '.$this->staffUser->name.' (office)' : 'Uploaded to the record';
    }
}
