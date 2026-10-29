@extends('layouts.admin')
@section('title', $payout->reference)

@php
    use App\Models\Payout;
    use App\Support\Finance;

    $here = $payout->stepIndex();
    $isExec = Finance::isExecutive();
    $isProject = $payout->stream === 'project';
    $stage = $payout->stage;
@endphp

@section('content')
@include('partials.errors')
<p class="back"><a href="{{ route('admin.payouts.index', ['f' => $payout->stream]) }}">&larr; Payouts</a></p>

<div class="applicant-head card">
    <div class="ah-main">
        <h1>{{ $payout->fundi->full_name ?? 'E-Fundi' }}
            <span class="status {{ $stage === 'released' ? 'active' : ($stage === 'rejected' ? 'closed' : 'pending_review') }}">
                {{ $payout->stageLabel() }}
            </span>
        </h1>
        <p class="ah-meta">
            <span class="tag">{{ $payout->reference }}</span>
            <span>{{ $payout->streamLabel() }}</span>
            <span>{{ $payout->sourceLabel() }}</span>
            <span>charged to {{ $payout->budgetLabel() }}</span>
            @if ($payout->fundi->exists && $payout->fundi->phone)<span>{{ $payout->fundi->phone }}</span>@endif
        </p>
    </div>
    <div class="ah-contact">
        <span class="hint">{{ $isProject ? 'Net to the fundi' : 'Payable to the trainer' }}</span>
        <b>{{ $payout->netLabel() }}</b>
    </div>
</div>

<div class="calc-line">{{ $payout->calculation }}</div>

@if ($payout->held_reason)
    <div class="card gaps"><b>On hold:</b> {{ $payout->held_reason }}</div>
@endif
@if ($payout->rejection_reason)
    <div class="card gaps"><b>Not paid out:</b> {{ $payout->rejection_reason }}
        <span class="hint">— the decision stays on the register; this row is not deleted and the work is not paid twice.</span>
    </div>
@endif

<div class="grid-2 review-grid">
    <div class="stack">
        <div class="card">
            <h3>{{ $isProject ? 'The project payout path' : 'The training payout path' }}</h3>
            <p class="hint">Each step is signed by the officer named under it. {{ $payout->approverLabel() }}
                approves this stream; only the Finance Officer releases the money.</p>
            <ol class="steps">
                @foreach ($payout->path() as $i => $step)
                    <li class="{{ $stage === $step['stage'] ? 'current' : ($i < $here ? 'done' : '') }}">
                        <b>{{ $step['action'] }}</b>
                        <div class="hint">{{ $step['actor'] }}</div>
                        @if ($stage === $step['stage'])<span class="tag gold">This payout is here</span>@endif
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="card">
            <h3>The figures</h3>
            <dl class="kv">
                <dt>Stream</dt><dd>{{ $payout->streamLabel() }}</dd>
                <dt>Paid to</dt><dd>{{ $payout->fundi->full_name ?? '—' }}
                    <span class="hint">{{ $payout->fundi->ef_code ?? '' }}</span></dd>
                @if (! $isProject)
                    <dt>Trainer on the record</dt>
                    <dd>{{ $payout->trainer?->personName() ?? 'the intake has no trainer recorded' }}</dd>
                @endif
                <dt>For</dt><dd>{{ $payout->sourceLabel() }}</dd>
                <dt>Charged to</dt><dd>{{ $payout->budgetLabel() }}</dd>
                @if ($isProject)
                    <dt>Agreed for the project</dt><dd>{{ Finance::ksh((float) $payout->agreed_amount_ksh) }}</dd>
                    <dt>Materials supplied</dt><dd>{{ Finance::ksh((float) $payout->materials_ksh) }}
                        @php $handed = $payout->project?->providedMaterials?->where('supplied_on', '!=', null) ?? collect(); @endphp
                        @if ($handed->count())
                            <table class="data money-table" style="margin-top:6px;">
                                <thead><tr><th>Handed over from the project</th><th>Quantity</th><th>Value</th></tr></thead>
                                <tbody>
                                    @foreach ($handed as $m)
                                        <tr>
                                            <td>{{ $m->name }}<div class="hint">{{ $m->supplied_on->format('d M Y') }}</div></td>
                                            <td>{{ $m->quantityLabel() }}</td>
                                            <td>{{ $m->totalLabel() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="hint">These are the project's own material lines marked as handed over — the
                                deduction is their total, so the office and the store cannot disagree.</div>
                        @elseif ((float) $payout->materials_ksh)
                            <div class="hint">Entered by the office; the project has no material lines marked as handed over.</div>
                        @endif
                    </dd>
                    <dt>Service commission</dt><dd>{{ Finance::ksh((float) $payout->commission_ksh) }}</dd>
                    <dt>Mobilization credit</dt><dd>{{ Finance::ksh((float) $payout->mobilization_ksh) }}</dd>
                    <dt>Deductions</dt><dd>{{ Finance::ksh((float) $payout->penalty_ksh) }}</dd>
                    <dt>Hirer paid</dt><dd>
                        {{ $payout->hirer_paid_on ? $payout->hirer_paid_on->format('d M Y').' · ref '.$payout->hirer_payment_ref : 'not confirmed in the system yet' }}
                    </dd>
                @else
                    <dt>Certified trainees</dt><dd>{{ $payout->certified_count ?? 'not confirmed yet' }}</dd>
                    <dt>Fee per trainee</dt><dd>{{ Finance::ksh((float) $payout->per_trainee_fee_ksh) }}</dd>
                    <dt>Workshop-hosting fee</dt><dd>{{ Finance::ksh((float) $payout->hosting_fee_ksh) }}</dd>
                    <dt>End-of-cohort report</dt><dd>
                        {{ $payout->report_submitted_on ? $payout->report_submitted_on->format('d M Y') : 'not filed yet' }}
                        @if ($payout->report_note)<div class="hint">{{ $payout->report_note }}</div>@endif
                    </dd>
                @endif
                <dt>Gross</dt><dd>{{ $payout->grossLabel() }}</dd>
                <dt>Net payable</dt><dd><b>{{ $payout->netLabel() }}</b></dd>
                <dt>Release route</dt><dd>{{ $payout->methodLabel() }}
                    {{ $payout->payee_phone ?: ($payout->payee_account ?: '') }}
                    @if ($payout->provider_ref)<div class="hint">confirmation {{ $payout->provider_ref }}</div>@endif</dd>
                <dt>Paper trail</dt><dd><span class="hint">
                    opened by {{ $payout->raiser->exists ? $payout->raiser->name : 'the office' }}
                    {{ $payout->created_at->format('d M Y') }}
                    @if ($payout->approved_on) · approved {{ $payout->approved_on->format('d M Y') }} by {{ $payout->approver->exists ? $payout->approver->name : '—' }}@endif
                    @if ($payout->released_on) · released {{ $payout->released_on->format('d M Y') }} by {{ $payout->releaser->exists ? $payout->releaser->name : '—' }}@endif
                </span></dd>
                @if ($payout->note)<dt>Note</dt><dd>{{ $payout->note }}</dd>@endif
            </dl>
        </div>
    </div>

    <div class="stack">
        @if ($isProject && in_array($stage, ['awaiting_hirer_payment', 'held'], true))
            <div class="card">
                <h3>Confirm the hirer has paid</h3>
                <p class="hint">A fundi is paid out of what the organisation actually received. The amount the hirer
                    paid cannot be below the agreed amount on the payout.</p>
                <form method="POST" action="{{ route('admin.payouts.hirer', $payout) }}" class="fee-form"
                      data-confirm="Confirm this payment and queue the payout?" data-title="Hirer payment">
                    @csrf
                    <div class="form-grid-2">
                        <div class="field">
                            <label>Date the payment was received <span class="req">*</span></label>
                            <input type="date" name="hirer_paid_on" required value="{{ now()->toDateString() }}">
                        </div>
                        <div class="field">
                            <label>Payment reference <span class="req">*</span></label>
                            <input type="text" name="hirer_payment_ref" required maxlength="80" placeholder="M-Pesa or bank receipt.">
                        </div>
                    </div>
                    <div class="form-grid-2" style="margin-top:10px;">
                        <div class="field">
                            <label>Amount the hirer paid (KSH) <span class="req">*</span></label>
                            <input type="number" name="amount_received_ksh" required min="0" step="0.01"
                                   value="{{ (float) $payout->agreed_amount_ksh }}">
                        </div>
                        <div class="field">
                            <label>&nbsp;</label>
                            <label class="hint"><input type="checkbox" name="record_on_ledger" value="1" checked>
                                also post this receipt on the money register</label>
                        </div>
                    </div>
                    <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">
                        Confirm and calculate the payout
                    </button>
                </form>
            </div>
        @endif

        @if (! $isProject && in_array($stage, ['awaiting_report', 'held'], true))
            <div class="card">
                <h3>File the report and confirm the certified count</h3>
                <p class="hint">The trainer is paid for the trainees who passed the assessment. Confirm that number
                    from the end-of-cohort report; the payout is then calculated and queued.</p>
                <form method="POST" action="{{ route('admin.payouts.report', $payout) }}" class="fee-form"
                      data-confirm="Confirm the report and calculate this payout?" data-title="Cohort report">
                    @csrf
                    <div class="form-grid-2">
                        <div class="field">
                            <label>Report submitted on <span class="req">*</span></label>
                            <input type="date" name="report_submitted_on" required value="{{ now()->toDateString() }}">
                        </div>
                        <div class="field">
                            <label>Certified trainees <span class="req">*</span></label>
                            <input type="number" name="certified_count" required min="0" step="1"
                                   value="{{ $payout->certified_count }}">
                        </div>
                    </div>
                    <div class="form-grid-2" style="margin-top:10px;">
                        <div class="field">
                            <label>Fee per trainee (KSH) <span class="req">*</span></label>
                            <input type="number" name="per_trainee_fee_ksh" required min="0" step="0.01"
                                   value="{{ $payout->per_trainee_fee_ksh }}">
                        </div>
                        <div class="field">
                            <label>Workshop-hosting fee (KSH)</label>
                            <input type="number" name="hosting_fee_ksh" min="0" step="0.01"
                                   value="{{ (float) $payout->hosting_fee_ksh }}">
                        </div>
                    </div>
                    <div class="field" style="margin-top:10px;">
                        <label>On the report</label>
                        <input type="text" name="report_note" maxlength="1000"
                               placeholder="e.g. 9 of 12 sat the assessment, all passed; venue hosted by the trainer.">
                    </div>
                    <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Confirm and queue</button>
                </form>
            </div>
        @endif

        @if ($stage === 'queued')
            <div class="card">
                <h3>{{ $payout->approverLabel() }} approves the payout</h3>
                <p class="hint">
                    @if ($isProject)
                        A project payout is approved by the Finance Officer — money has already come in from the
                        hirer and this releases the fundi's share.
                    @else
                        The Programme Manager reviews the report and confirms the certified count, then Finance
                        releases it from the training budget.
                    @endif
                </p>
                <form method="POST" action="{{ route('admin.payouts.approve', $payout) }}" class="fee-form"
                      data-confirm="Approve {{ $payout->reference }} at {{ $payout->netLabel() }}?" data-title="Approve payout">
                    @csrf
                    <button class="btn btn-lime btn-block" type="submit" @if ($isProject && ! $isExec) disabled @endif>
                        {{ $isProject && ! $isExec ? 'Only a programme executive approves a project payout' : 'Approve for release' }}
                    </button>
                </form>
            </div>
        @endif

        @if ($stage === 'approved')
            <div class="card">
                <h3>Release the money</h3>
                <p class="hint">Money leaves the programme here, so it is a Finance Officer (programme executive)
                    action. The confirmation code is what makes the payout traceable, and releasing it posts its own
                    line on the money register against {{ $payout->budgetLabel() }}.</p>
                <form method="POST" action="{{ route('admin.payouts.release', $payout) }}" class="fee-form"
                      data-confirm="Release {{ $payout->netLabel() }} to {{ $payout->fundi->full_name ?? 'the fundi' }}?"
                      data-title="Release payout">
                    @csrf
                    <div class="form-grid-2">
                        @include('partials.mpesa-line', ['cls' => 'full'])
                        <div class="field">
                            <label>Paid by <span class="req">*</span></label>
                            <select name="method" required>
                                @foreach (Payout::METHODS as $key => $label)
                                    <option value="{{ $key }}" @selected($payout->method === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Date released <span class="req">*</span></label>
                            <input type="date" name="released_on" required value="{{ now()->toDateString() }}">
                        </div>
                    </div>
                    <div class="form-grid-2" style="margin-top:10px;">
                        <div class="field">
                            <label>M-Pesa number paid</label>
                            <input type="text" name="payee_phone" maxlength="40" value="{{ $payout->fundi->phone ?? $payout->payee_phone }}">
                        </div>
                        <div class="field">
                            <label>Bank account paid</label>
                            <input type="text" name="payee_account" maxlength="120" value="{{ $payout->payee_account }}">
                        </div>
                    </div>
                    <div class="field" style="margin-top:10px;">
                        <label>Confirmation code from the provider <span class="req">*</span></label>
                        <input type="text" name="provider_ref" required maxlength="80" placeholder="e.g. QGH7XK2LM1">
                    </div>
                    <div class="field" style="margin-top:10px;">
                        <label>Note</label>
                        <input type="text" name="note" maxlength="1000">
                    </div>
                    <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;"
                        @if (! $isExec) disabled @endif>
                        {{ $isExec ? 'Release '.$payout->netLabel() : 'Only a programme executive releases money' }}
                    </button>
                    @if (! $isExec)
                        <div class="hint" style="margin-top:8px;">The payout is approved and waiting — the release
                            itself is signed by the Finance Officer.</div>
                    @endif
                </form>
            </div>
        @endif

        @if (in_array($stage, ['awaiting_hirer_payment', 'awaiting_report', 'queued', 'approved'], true))
            <div class="card">
                <h3>Hold this payout</h3>
                <p class="hint">Something not settled — a dispute, a balance owed, a report still out. The row stays
                    on the register with the reason and returns to the same step when it is released from hold.</p>
                <form method="POST" action="{{ route('admin.payouts.hold', $payout) }}" class="fee-form"
                      data-confirm="Put {{ $payout->reference }} on hold?" data-title="Hold payout">
                    @csrf
                    <div class="field">
                        <label>What is holding it <span class="req">*</span></label>
                        <input type="text" name="held_reason" required maxlength="1000" minlength="10"
                               placeholder="e.g. The hirer has paid 60% of the agreed amount; balance chased.">
                    </div>
                    <button class="btn btn-outline btn-block" type="submit" style="margin-top:12px;">Put on hold</button>
                </form>
            </div>
        @endif

        @if ($stage === 'held')
            <div class="card">
                <h3>Back on its path</h3>
                <form method="POST" action="{{ route('admin.payouts.resume', $payout) }}" class="mini-form"
                      data-confirm="Take {{ $payout->reference }} off hold?" data-title="Resume payout">
                    @csrf
                    <button class="btn btn-sm btn-primary" type="submit">
                        Resume at {{ Payout::STAGES[$payout->resumableStage()] }}
                    </button>
                </form>
            </div>
        @endif

        @if (in_array($stage, ['queued', 'approved', 'held'], true))
            <div class="card">
                <h3>Do not pay this out</h3>
                <form method="POST" action="{{ route('admin.payouts.reject', $payout) }}" class="fee-form"
                      data-confirm="Reject {{ $payout->reference }}?" data-title="Reject payout">
                    @csrf
                    <div class="field">
                        <label>Reason the fundi can read <span class="req">*</span></label>
                        <input type="text" name="rejection_reason" required minlength="10" maxlength="1000"
                               placeholder="e.g. Work was not completed; the job was reassigned on the delivery sheet.">
                    </div>
                    <button class="btn btn-outline btn-block" type="submit" style="margin-top:12px;"
                        @if ($isProject && ! $isExec) disabled @endif>
                        {{ $isProject && ! $isExec ? 'Only a programme executive refuses a project payout' : 'Record the refusal' }}
                    </button>
                </form>
            </div>
        @endif

        <div class="card">
            <h3>On the money register</h3>
            <p class="hint">The receipt that unlocked this payout and the release itself, as the ledger carries them.</p>
            <table class="data">
                <thead><tr><th>Reference</th><th>Date</th><th>What it is</th><th>Amount</th></tr></thead>
                <tbody>
                    @forelse ($ledger as $e)
                        <tr>
                            <td>{{ $e->reference }}</td>
                            <td>{{ $e->happened_on->format('d M Y') }}</td>
                            <td>{{ $e->kindLabel() }}<div class="hint">{{ $e->title }}</div></td>
                            <td class="{{ $e->direction === 'income' ? 'flag-good' : 'flag-bad' }}">{{ $e->amountLabel() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="hint" style="text-align:center;padding:16px;">
                            Nothing on the register for this fundi yet.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
