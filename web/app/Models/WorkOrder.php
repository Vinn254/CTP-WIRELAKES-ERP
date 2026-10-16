<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * Module 7 — the work order (§10.2). What an award turns into on paper: who does the work, what they deliver,
 * when it starts and ends, what the store gives them and how the agreed amount is released. Money is never
 * worked out here — the payout register in Module 11 is the only place a figure is calculated, and this row hands
 * over to it once the hirer has confirmed the work.
 */
class WorkOrder extends Model
{
    /** §10.4 — the delivery states. A row is closed or cancelled with its reason, never deleted. */
    public const STATUSES = [
        'issued' => 'Issued — waiting for the E-Fundi to accept',
        'accepted' => 'Accepted and signed — work starting',
        'in_progress' => 'On site',
        'completion_submitted' => 'Completion asked — waiting on the hirer to inspect',
        'confirmed' => 'Confirmed by the hirer — handed to Finance',
        'disputed' => 'In dispute (§10.6)',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ];

    /** §10.5 — the three schedules an amount can be released on. */
    public const SCHEDULES = [
        'fixed' => 'Fixed price — one total amount, paid on completion',
        'milestone' => 'Milestone based — released against signed-off milestones',
        'time_materials' => 'Time and materials — paid as the hours and materials go',
    ];

    /** §10.2 — the schedule an order starts with unless the office changes it: 30 on mobilization, 40 on milestone, 30 on completion. */
    public const DEFAULT_SPLIT = [30, 40, 30];

    protected $fillable = [
        'reference', 'project_id', 'assignee_type', 'assignee_id', 'assignee_name', 'status',
        'scope', 'deliverables', 'start_on', 'deadline', 'schedule', 'split', 'agreed_amount_ksh',
        'materials_note', 'accepted_on', 'signature', 'accept_note', 'percent_complete',
        'completion_requested_on', 'confirmed_on', 'confirmed_by_name', 'confirmation_note',
        'confirmation_path', 'confirmation_name', 'completed_on', 'closed_on', 'closure_note',
        'raised_by_user',
    ];

    protected $casts = [
        'split' => 'array',
        'agreed_amount_ksh' => 'decimal:2',
        'percent_complete' => 'integer',
        'start_on' => 'date',
        'deadline' => 'date',
        'accepted_on' => 'datetime',
        'completion_requested_on' => 'datetime',
        'confirmed_on' => 'datetime',
        'completed_on' => 'datetime',
        'closed_on' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** The E-Fundi the award named. An attachment order goes to a CTP instead, which is why the name is kept on the row. */
    public function fundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'assignee_id')->withDefault();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(WorkOrderMilestone::class)->orderBy('id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(WorkOrderUpdate::class)->orderByDesc('entry_date')->orderByDesc('id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(WorkOrderDispute::class)->orderByDesc('id');
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user')->withDefault();
    }

    /** The site visit that goes with each worker type (§10.4 step 4). */
    public function assigneeLabel(): string
    {
        return $this->assignee_type === 'ctp' ? 'CTP attachment worker' : 'E-Fundi';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function scheduleLabel(): string
    {
        return self::SCHEDULES[$this->schedule] ?? $this->schedule;
    }

    public function agreedLabel(): string
    {
        return Finance::ksh((float) $this->agreed_amount_ksh);
    }

    /** The split as it was written, so a custom 50/20/30 reads exactly as the office set it. */
    public function splitLabel(): string
    {
        $parts = collect($this->split ?: self::DEFAULT_SPLIT)->map(fn ($p) => (int) $p.'%');

        return $parts->isEmpty() ? 'no split recorded' : $parts->join(' / ');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['closed', 'cancelled'], true);
    }

    /** Does this member hold the order? Resolved from their own record, never from the url. */
    public function isHeldBy(Model $member): bool
    {
        $type = $member instanceof CtpRegistration ? 'ctp' : 'fundi';

        return $this->assignee_type === $type && (int) $this->assignee_id === (int) $member->id;
    }

    /** The dispute a worker has to answer — the sheet only offers a response box while one is open. */
    public function openDispute(): ?WorkOrderDispute
    {
        return $this->disputes()->whereNull('decided_on')->latest('id')->first();
    }

    public function materialsPlannedKsh(): float
    {
        return $this->project->materialsPlannedKsh();
    }

    public function materialsIssuedKsh(): float
    {
        return $this->project->materialsSuppliedKsh();
    }

    /** §10.4 step 4 — the order is only fully signed off when every stage the split lists is. */
    public function milestonesOutstanding(): int
    {
        return $this->milestones()->where('status', WorkOrderMilestone::PENDING)->count();
    }

    public function mustBe(string ...$statuses): void
    {
        if (in_array($this->status, $statuses, true)) {
            return;
        }

        $wanted = collect($statuses)->map(fn ($s) => self::STATUSES[$s] ?? $s)->join(' or ');

        abort(422, $this->reference.' is "'.$this->statusLabel().'", so that step is not open on it yet. '
            .'It has to be: '.$wanted.'.');
    }

    /** What the office types when it records the hirer's inspection (§10.4 step 6). */
    public static function confirmationFrom(Request $request): array
    {
        return $request->validate([
            'confirmed_by_name' => 'required|string|max:160',
            'confirmation_note' => 'required|string|max:1000',
            'confirmed_on' => 'required|date',
            'sheet' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:8192',
        ], [
            'confirmed_by_name.required' => 'Say who inspected the work for the hirer — that is the name the confirmation is recorded under (§10.4 step 6).',
            'confirmation_note.required' => 'Write what they said. A confirmation without the hirer\'s words on it is not a record.',
        ], [
            'confirmed_by_name' => 'Confirmed by (for the hirer)',
            'confirmation_note' => 'What they said',
            'confirmed_on' => 'Date of inspection',
            'sheet' => 'Signed sheet',
        ]);
    }

    public function storeConfirmationSheet(Request $request): void
    {
        if (! $request->hasFile('sheet')) {
            return;
        }

        $file = $request->file('sheet');

        $this->update([
            'confirmation_path' => $file->store('work-orders/'.$this->reference, 'local'),
            'confirmation_name' => $file->getClientOriginalName(),
        ]);
    }

    /** WO-2026-00004 — the order ID the worker is told, so a job can be quoted anywhere in the programme. */
    public static function nextReference(): string
    {
        $year = now()->year;
        $prefix = 'WO-'.$year.'-';

        $highest = static::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) ((int) $highest + 1), 5, '0', STR_PAD_LEFT);
    }
}
