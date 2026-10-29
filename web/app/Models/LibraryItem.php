<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The Registrar's library — a book, a set of notes, slides or any other reading a CTP student is told to work
 * through. It belongs to a course rather than to one cohort, so the Registrar posts a title once and every student
 * taking that training reads the same copy; a title with no course belongs to the whole student body.
 *
 * The file sits on the private disk and is only ever served through this row, so nothing reaches a student who is
 * not on the programme. Taking a title off the shelf hides it from students but keeps the record.
 */
class LibraryItem extends Model
{
    public const KINDS = [
        'book' => 'Book',
        'notes' => 'Notes',
        'slides' => 'Slides',
        'pdf' => 'PDF reading',
        'worksheet' => 'Worksheet',
        'past_paper' => 'Past paper',
        'other' => 'Other reading',
    ];

    public const SHELF = ['on' => 'On the shelf', 'off' => 'Off the shelf'];

    protected $fillable = [
        'title', 'kind', 'training_id', 'note', 'file_path', 'file_name', 'file_size', 'status',
        'posted_by_user', 'posted_at',
    ];

    protected $casts = ['file_size' => 'integer', 'posted_at' => 'datetime'];

    protected $attributes = ['status' => 'on'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Training::class, 'training_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user');
    }

    /** What students may read: a title the Registrar has left on the shelf. */
    public function scopeOnShelf(Builder $query): Builder
    {
        return $query->where('status', 'on');
    }

    /** The library form. A file is needed to post a title and only needed again to replace the one on record. */
    public static function dataFrom(Request $request, bool $fileRequired = true): array
    {
        return $request->validate([
            'title' => 'required|string|max:190',
            'kind' => 'required|in:'.implode(',', array_keys(self::KINDS)),
            'training_id' => 'nullable|exists:trainings,id',
            'note' => 'nullable|string|max:1000',
            'file' => ($fileRequired ? 'required' : 'nullable').'|file|mimes:pdf,epub,jpg,jpeg,png,doc,docx,ppt,pptx,xls,xlsx,txt|max:30720',
        ], [
            'title.required' => 'Give the title as the students will read it.',
            'kind.required' => 'Say what kind of reading it is.',
            'file.required' => 'Attach the file the students open.',
            'file.mimes' => 'A book, note or slide deck goes in as a PDF, Word, PowerPoint, Excel, text file or a picture.',
            'file.max' => 'The library takes a file up to 30 MB — a bigger book goes to the office as a link.',
        ], [
            'title' => 'Title', 'kind' => 'Type', 'training_id' => 'Course', 'note' => 'What it covers', 'file' => 'The file',
        ]);
    }

    /** Whether this title is one the student in front of us reads: theirs by course, or for every student. */
    public function readsFor(?int $courseId): bool
    {
        return $this->training_id === null || $this->training_id === $courseId;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? Str::headline((string) $this->kind);
    }

    public function courseLabel(): string
    {
        return $this->course?->name ?: 'Every course';
    }

    public function shelfLabel(): string
    {
        return self::SHELF[$this->status] ?? Str::headline((string) $this->status);
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
        return ($this->posted_at ?? $this->created_at)?->format('d M Y') ?: 'today';
    }
}
