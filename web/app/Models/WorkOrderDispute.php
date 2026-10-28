<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Module 7  — a job the hirer would not confirm. The issue with its photos, the worker's answer inside 48
 * hours, what the Programme Manager did to mediate and what a Director finally decided. The five outcomes are the
 * blueprint's, kept closed: an outcome that is not on the list cannot be recorded.
 */
class WorkOrderDispute extends Model
{
    /**  — the worker has two days to answer before mediation proceeds without them. */
    public const WINDOW_HOURS = 48;

    public const OUTCOMES = [
        'rework' => 'Rework required — the job goes back to the worker',
        'partial_payment' => 'Partial payment — the work is accepted as far as it stands',
        'full_payment' => 'Full payment — the work is accepted as done',
        'refund' => 'Refund — the hirer is paid back and no payout is opened',
        'penalty' => 'Fundi penalised — a deduction is carried onto the payout',
    ];

    protected $fillable = [
        'work_order_id', 'issue', 'issue_path', 'issue_name', 'issue_size',
        'raised_by_user', 'raised_on', 'responded_on', 'response', 'responded_by_fundi',
        'mediated_by_user', 'mediation_note', 'outcome', 'penalty_ksh', 'outcome_note', 'decided_by_user', 'decided_on',
    ];

    protected $casts = [
        'raised_on' => 'datetime',
        'responded_on' => 'datetime',
        'decided_on' => 'datetime',
        'penalty_ksh' => 'decimal:2',
        'issue_size' => 'integer',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user')->withDefault();
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'responded_by_fundi')->withDefault();
    }

    public function mediator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mediated_by_user')->withDefault();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user')->withDefault();
    }

    /** The point the 48 hours run out, whatever clock the sheet is read on. */
    public function dueOn(): ?\Illuminate\Support\Carbon
    {
        return $this->raised_on?->copy()->addHours(self::WINDOW_HOURS);
    }

    public function isAwaitingWorker(): bool
    {
        return $this->responded_on === null && $this->decided_on === null;
    }

    public function isOverdue(): bool
    {
        return $this->isAwaitingWorker() && $this->dueOn()?->isPast();
    }

    public function isDecided(): bool
    {
        return $this->decided_on !== null;
    }

    public function outcomeLabel(): string
    {
        return self::OUTCOMES[$this->outcome] ?? ucfirst((string) $this->outcome);
    }

    public function penaltyLabel(): string
    {
        return Finance::ksh((float) $this->penalty_ksh);
    }

    /** How long the worker has left, in the words the sheet shows. */
    public function timeLabel(): string
    {
        if (! $this->isAwaitingWorker()) {
            return $this->responded_on
                ? 'Answered '.$this->responded_on->diffForHumans()
                : 'Decided '.$this->decided_on?->diffForHumans();
        }

        $due = $this->dueOn();

        return $due === null ? 'no window recorded'
            : ($due->isPast()
                ? 'Answer window ran out '.$due->diffForHumans(null, true).' ago'
                : $due->diffForHumans());
    }

    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'issue' => 'required|string|max:5000',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:8192',
        ], [
            'issue.required' => 'The hirer\'s issue has to be written out — a dispute with no statement cannot be answered.',
        ], [
            'issue' => 'The issue',
            'photo' => 'Photos or documents',
        ]);
    }

    public function storeEvidence(Request $request): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        $file = $request->file('photo');

        $this->update([
            'issue_path' => $file->store('work-orders/'.$this->workOrder->reference.'/disputes', 'local'),
            'issue_name' => $file->getClientOriginalName(),
            'issue_size' => $file->getSize(),
        ]);
    }
}
