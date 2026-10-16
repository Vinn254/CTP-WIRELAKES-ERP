<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Module 7 — one line of what actually happened on the job (§10.4 steps 3, 4, 5). The worker's day, a supervisor's
 * site visit, and the final photos that come with a completion request. A line is never edited into another: a
 * wrong day is answered by the next line, so the log keeps reading as the sequence it was written in.
 */
class WorkOrderUpdate extends Model
{
    public const KINDS = [
        'progress' => 'Worker\'s day',
        'site_visit' => 'Site visit',
        'completion' => 'Final photos',
    ];

    protected $fillable = [
        'work_order_id', 'kind', 'entry_date', 'percent', 'note',
        'photo_path', 'photo_name', 'photo_size', 'recorded_by_user', 'recorded_by_fundi',
    ];

    protected $casts = ['entry_date' => 'date', 'percent' => 'integer'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user')->withDefault();
    }

    public function fundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'recorded_by_fundi')->withDefault();
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst(str_replace('_', ' ', $this->kind));
    }

    public function hasPhoto(): bool
    {
        return filled($this->photo_path);
    }

    /** @param  string[]  $kinds  what this form is allowed to record */
    public static function dataFrom(Request $request, array $kinds = ['progress']): array
    {
        return $request->validate([
            'kind' => 'required|in:'.implode(',', $kinds),
            'entry_date' => 'required|date',
            'percent' => 'nullable|integer|min:0|max:100',
            'note' => 'required|string|max:5000',
            'photo' => 'nullable|image|max:8192',
        ], [
            'note.required' => 'Say what was done — a percentage with no words behind it is not a progress report (§10.4 step 3).',
            'entry_date.required' => 'Which day is this for? The log is read as a sequence of days.',
        ], [
            'kind' => 'What kind of entry',
            'entry_date' => 'Date',
            'percent' => 'Percentage complete',
            'note' => 'What happened',
            'photo' => 'Photo',
        ]);
    }

    public function storePhoto(Request $request): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        $file = $request->file('photo');

        $this->update([
            'photo_path' => $file->store('work-orders/'.$this->workOrder->reference.'/'.$this->kind, 'local'),
            'photo_name' => $file->getClientOriginalName(),
            'photo_size' => $file->getSize(),
        ]);
    }
}
