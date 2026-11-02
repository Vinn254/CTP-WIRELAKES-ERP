<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The Registrar's library — a book, a set of notes, slides or any other reading a CTP student is told to work
 * through. The course it is filed under says who the reading was written for and groups it on the shelf; it is not a
 * lock, so a trainee not yet put on that course's roll still reads what the Registrar posted.
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

    /** A whole book is not a few pages of notes: the shelf takes files up to 300 MB. */
    public const MAX_KB = 307200;

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
            'file' => ($fileRequired ? 'required' : 'nullable').'|file|mimes:pdf,epub,jpg,jpeg,png,doc,docx,ppt,pptx,xls,xlsx,txt|max:'.self::MAX_KB,
        ], [
            'title.required' => 'Give the title as the students will read it.',
            'kind.required' => 'Say what kind of reading it is.',
            'file.required' => 'Attach the file the students open.',
            'file.mimes' => 'A book, note or slide deck goes in as a PDF, Word, PowerPoint, Excel, text file or a picture.',
            'file.max' => 'The library takes a file up to '.self::uploadLabel().' — a bigger book goes to the office as a link.',
        ], [
            'title' => 'Title', 'kind' => 'Type', 'training_id' => 'Course', 'note' => 'What it covers', 'file' => 'The file',
        ]);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? Str::headline((string) $this->kind);
    }

    /** The words the upload field shows for its own ceiling. */
    public static function uploadLabel(): string
    {
        return (int) round(self::MAX_KB / 1024).' MB';
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
