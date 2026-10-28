<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Module 11 — an E-Fundi payout (§14.6). Project income and training income are two different paths on one
 * register: a project payout waits for the hirer's money and is calculated out of the agreed amount after the
 * deductions; a training payout waits for the end-of-cohort report and is calculated from certified trainees.
 * Keeping them on separate rows with separate formulas is what stops the training budget paying for project work.
 */
class Payout extends Model
{
    public const STREAMS = [
        'project' => 'Project income (Module 7)',
        'training' => 'Training income (Module 4)',
    ];

    public const STAGES = [
        'awaiting_hirer_payment' => 'Waiting for the hirer to pay',
        'awaiting_report' => 'Waiting for the end-of-cohort report',
        'queued' => 'Calculated — waiting for approval',
        'approved' => 'Approved — waiting for release',
        'released' => 'Released to the fundi',
        'held' => 'On hold',
        'rejected' => 'Rejected',
    ];

    public const METHODS = ['mpesa' => 'M-Pesa B2C', 'bank' => 'Bank transfer'];

    protected $fillable = [
        'reference', 'stream', 'stage', 'budget_category', 'fundi_id', 'trainer_id', 'project_id', 'cohort_id',
        'agreed_amount_ksh', 'materials_ksh', 'commission_ksh', 'mobilization_ksh', 'penalty_ksh',
        'hirer_paid_on', 'hirer_payment_ref',
        'certified_count', 'per_trainee_fee_ksh', 'hosting_fee_ksh', 'report_submitted_on', 'report_note',
        'gross_ksh', 'net_ksh', 'calculation',
        'method', 'payee_phone', 'payee_account', 'provider_ref', 'note',
        'raised_by_user', 'approved_by_user', 'approved_on', 'released_by_user', 'released_on',
        'held_reason', 'rejection_reason',
    ];

    protected $casts = [
        'agreed_amount_ksh' => 'decimal:2', 'materials_ksh' => 'decimal:2', 'commission_ksh' => 'decimal:2',
        'mobilization_ksh' => 'decimal:2', 'penalty_ksh' => 'decimal:2', 'gross_ksh' => 'decimal:2',
        'net_ksh' => 'decimal:2', 'per_trainee_fee_ksh' => 'decimal:2', 'hosting_fee_ksh' => 'decimal:2',
        'certified_count' => 'integer', 'hirer_paid_on' => 'date', 'report_submitted_on' => 'date',
        'approved_on' => 'datetime', 'released_on' => 'datetime',
    ];

    public static function dataFrom(Request $request): array
    {
        $data = $request->validate([
            'stream' => 'required|in:project,training',
            'fundi_id' => 'required|integer|exists:fundi_registrations,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'cohort_id' => 'nullable|integer|exists:cohorts,id',
            'budget_category' => 'nullable|in:'.implode(',', array_keys(BudgetLine::CATEGORIES)),
            'agreed_amount_ksh' => 'nullable|numeric|min:0|max:1000000000',
            'materials_ksh' => 'nullable|numeric|min:0|max:1000000000',
            'commission_ksh' => 'nullable|numeric|min:0|max:1000000000',
            'mobilization_ksh' => 'nullable|numeric|min:0|max:1000000000',
            'penalty_ksh' => 'nullable|numeric|min:0|max:1000000000',
            'certified_count' => 'nullable|integer|min:0|max:10000',
            'per_trainee_fee_ksh' => 'nullable|numeric|min:0|max:100000000',
            'hosting_fee_ksh' => 'nullable|numeric|min:0|max:100000000',
            'method' => 'required|in:mpesa,bank',
            'payee_phone' => 'nullable|string|max:40',
            'payee_account' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:1000',
        ], [
            'stream.required' => 'Say which income stream this is — project work and training delivery are paid through different paths (§14.6).',
            'fundi_id.required' => 'A payout names the E-Fundi it is released to.',
        ], [
            'agreed_amount_ksh' => 'Agreed amount for the project',
            'budget_category' => 'Charged to budget',
        ]);

        $isProject = $data['stream'] === 'project';

        $request->validate($isProject
            ? ['project_id' => 'required|integer|exists:projects,id', 'agreed_amount_ksh' => 'required|numeric|min:0.01|max:1000000000']
            : ['cohort_id' => 'required|integer|exists:cohorts,id', 'certified_count' => 'required|integer|min:0|max:10000',
                'per_trainee_fee_ksh' => 'required|numeric|min:0|max:100000000'],
            [], $isProject
                ? ['project_id' => 'Project', 'agreed_amount_ksh' => 'Agreed amount for the project']
                : ['cohort_id' => 'Cohort', 'certified_count' => 'Certified trainees', 'per_trainee_fee_ksh' => 'Fee per trainee']);

        $data['budget_category'] = $isProject
            ? ($data['budget_category'] ?? 'operations')
            : 'training';

        foreach (['materials_ksh', 'commission_ksh', 'mobilization_ksh', 'penalty_ksh', 'hosting_fee_ksh'] as $k) {
            $data[$k] = (float) ($data[$k] ?? 0);
        }

        return $data;
    }

    public function fundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'fundi_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class)->withDefault();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withDefault();
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class)->withDefault();
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user')->withDefault();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user')->withDefault();
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user')->withDefault();
    }

    /** §14.6 — the two formulas, kept in one place so the register, the fundi's earnings and the ledger agree. */
    public function calculate(): array
    {
        if ($this->stream === 'training') {
            if ($this->certified_count === null || $this->per_trainee_fee_ksh === null) {
                return ['gross' => 0.0, 'net' => 0.0, 'calculation' =>
                    'Not calculated yet — the payout is worked out once the end-of-cohort report is in and the number of certified trainees is confirmed (§14.6 step 2).'];
            }

            $fee = (float) $this->per_trainee_fee_ksh * (int) $this->certified_count;
            $host = (float) $this->hosting_fee_ksh;
            $gross = $fee + $host;

            return [
                'gross' => $gross,
                'net' => $gross,
                'calculation' => $this->certified_count.' certified trainees × '.Finance::ksh((float) $this->per_trainee_fee_ksh)
                    .' = '.Finance::ksh($fee)
                    .($host > 0 ? ' + workshop-hosting fee '.Finance::ksh($host) : ' · no workshop-hosting fee')
                    .' → '.Finance::ksh($gross),
            ];
        }

        if ($this->agreed_amount_ksh === null) {
            return ['gross' => 0.0, 'net' => 0.0, 'calculation' =>
                'Not calculated yet — the agreed amount for the project is what the payout is built from.'];
        }

        $agreed = (float) $this->agreed_amount_ksh;
        $net = $agreed - (float) $this->materials_ksh - (float) $this->commission_ksh
            - (float) $this->mobilization_ksh - (float) $this->penalty_ksh;

        abort_if($net < 0, 422,
            'The deductions on this payout come to more than the agreed amount ('.Finance::ksh($agreed)
            .'). Record what was actually spent — a fundi is never taken below zero.');

        $parts = collect([
            $this->materials_ksh > 0 ? '− materials '.Finance::ksh((float) $this->materials_ksh) : null,
            $this->commission_ksh > 0 ? '− commission '.Finance::ksh((float) $this->commission_ksh) : null,
            $this->mobilization_ksh > 0 ? '− mobilization credit '.Finance::ksh((float) $this->mobilization_ksh) : null,
            $this->penalty_ksh > 0 ? '− deductions '.Finance::ksh((float) $this->penalty_ksh) : null,
        ])->filter()->join(' ');

        return [
            'gross' => $agreed,
            'net' => $net,
            'calculation' => 'Agreed '.Finance::ksh($agreed).($parts ? ' '.$parts : ' · no deductions').' → '.Finance::ksh($net),
        ];
    }

    public function streamLabel(): string
    {
        return self::STREAMS[$this->stream] ?? $this->stream;
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? ucfirst($this->stage);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }

    public function netLabel(): string
    {
        return Finance::ksh((float) $this->net_ksh);
    }

    public function grossLabel(): string
    {
        return Finance::ksh((float) $this->gross_ksh);
    }

    public function budgetLabel(): string
    {
        return BudgetLine::categoryLabel($this->budget_category);
    }

    public function sourceLabel(): string
    {
        return $this->stream === 'training'
            ? ($this->cohort->exists ? 'Cohort '.$this->cohort->reference.' — '.$this->cohort->courseLabel() : 'Training delivery')
            : ($this->project->exists ? 'Project '.$this->project->title : 'Project work');
    }

    /** Who signs each step of this stream's path — the reason the two streams are kept apart. */
    public function approverLabel(): string
    {
        return $this->stream === 'training' ? 'Programme Manager' : 'Finance Officer';
    }

    public function path(): array
    {
        return $this->stream === 'project' ? [
            ['stage' => 'awaiting_hirer_payment', 'actor' => 'Fundi / hirer', 'action' => 'Project completed, hirer confirms and pays the organisation'],
            ['stage' => 'queued', 'actor' => 'Finance Officer', 'action' => 'Receipt of the hirer payment confirmed; the payout is calculated and queued'],
            ['stage' => 'approved', 'actor' => 'Finance Officer', 'action' => 'Payout approved for release'],
            ['stage' => 'released', 'actor' => 'Finance Officer', 'action' => 'Sent by M-Pesa B2C or bank and logged on the fundi\'s earnings and the project account'],
        ] : [
            ['stage' => 'awaiting_report', 'actor' => 'Trainer', 'action' => 'Cohort ended, final attendance and grades marked, end-of-cohort report uploaded'],
            ['stage' => 'queued', 'actor' => 'Programme Manager', 'action' => 'Report reviewed and the number of certified trainees confirmed; payout calculated'],
            ['stage' => 'approved', 'actor' => 'Programme Manager', 'action' => 'Payout approved'],
            ['stage' => 'released', 'actor' => 'Finance Officer', 'action' => 'Released from the training budget and logged on the trainer\'s earnings and the cohort record'],
        ];
    }

    public function stepIndex(): int
    {
        $position = array_search($this->stage, array_column($this->path(), 'stage'), true);

        return $position === false ? count($this->path()) - 1 : (int) $position;
    }

    public function isClosed(): bool
    {
        return in_array($this->stage, ['released', 'rejected'], true);
    }

    public function mustBe(string ...$stages): void
    {
        if (in_array($this->stage, $stages, true)) {
            return;
        }

        $wanted = collect($stages)->map(fn ($s) => self::STAGES[$s] ?? $s)->join(' or ');

        abort(422, $this->reference.' is '.$this->stageLabel().', so that action is not open on it yet. '
            .'It has to be: '.$wanted.'.');
    }

    /** The stage a held or rejected row goes back to — never a fresh record. */
    public function resumableStage(): string
    {
        if ($this->stream === 'training') {
            return $this->certified_count !== null ? 'queued' : 'awaiting_report';
        }

        return $this->hirer_paid_on !== null ? 'queued' : 'awaiting_hirer_payment';
    }

    public static function ledgerKind(string $stream): string
    {
        return $stream === 'training' ? 'trainer_payout' : 'fundi_payout';
    }

    /** PAY-2026-00003 — the payout ID the fundi is told, so a payment can always be traced (§14.6 step 7). */
    public static function nextReference(): string
    {
        $year = now()->year;
        $prefix = 'PAY-'.$year.'-';

        $highest = static::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) ((int) $highest + 1), 5, '0', STR_PAD_LEFT);
    }
}
