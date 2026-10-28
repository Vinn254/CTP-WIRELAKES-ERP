@extends('layouts.member')
@section('title', 'My earnings')

@php
    use App\Models\Payout;
    use App\Support\Finance;
@endphp

@section('content')
<div class="page-intro">
    <p class="sub">Two kinds of money come to an E-Fundi and they are kept apart on purpose.
        <b>Project income</b> is your share of a job: the amount agreed for the work, <i>minus</i> materials the
        programme supplied, the service commission, the mobilization credit and any deduction — and it is only
        released once the hirer has paid the organisation. <b>Training income</b> is what you are paid for running a
        cohort as a trainer: the fee per trainee who passed the assessment, plus a workshop-hosting fee where you
        hosted it, out of the training budget. Every payout carries its own ID, which is what a payment is traced by.</p>
    <div class="member-stats">
        <div class="stat"><small>Project income paid</small><b>{{ Finance::ksh($totals['project']) }}</b></div>
        <div class="stat"><small>Training income paid</small><b>{{ Finance::ksh($totals['training']) }}</b></div>
        <div class="stat"><small>Calculated, not yet released</small><b>{{ Finance::ksh($totals['waiting']) }}</b></div>
        <div class="stat"><small>Payouts on record</small><b>{{ $totals['lines'] }}</b></div>
    </div>
</div>

<div class="card">
    <div class="section-head"><h3>How a payout reaches you</h3></div>
    <p class="hint">Nothing here is released by a member — staff prepare it and a programme executive pays it out.
        If a payout is stuck, the reason is written on the row.</p>
    <div class="two-col">
        <div>
            <h4 class="dl-group">Project income</h4>
            <ol class="steps">
                @foreach ((new Payout(['stream' => 'project']))->path() as $step)
                    <li><b>{{ $step['action'] }}</b><div class="hint">{{ $step['actor'] }}</div></li>
                @endforeach
            </ol>
        </div>
        <div>
            <h4 class="dl-group">Training income</h4>
            <ol class="steps">
                @foreach ((new Payout(['stream' => 'training']))->path() as $step)
                    <li><b>{{ $step['action'] }}</b><div class="hint">{{ $step['actor'] }}</div></li>
                @endforeach
            </ol>
            @unless ($isTrainer)
                <p class="hint">You are not registered as a trainer yet, so no training payout can come to your
                    name. A senior fundi trains CTPs — ask the programme office about the next intake.</p>
            @endunless
        </div>
    </div>
</div>

<div class="card">
    <div class="section-head"><h3>Your payouts ({{ $payouts->count() }})</h3></div>
    @if ($payouts->isEmpty())
        <p class="help">No payout is open in your name yet. One is opened by the office when a project you were
            awarded is completed, or when a cohort you trained reaches its report.</p>
    @else
        @foreach ($payouts as $p)
            <div class="dl-group">{{ $p->streamLabel() }} — {{ $p->sourceLabel() }}</div>
            <table class="data money-table">
                <tbody>
                    <tr>
                        <td>Payout</td><td><b>{{ $p->reference }}</b></td>
                        <td>Stage</td>
                        <td><span class="status {{ $p->stage === 'released' ? 'active' : ($p->stage === 'rejected' ? 'closed' : 'pending_review') }}">
                            {{ $p->stageLabel() }}</span></td>
                    </tr>
                    <tr><td>Calculated as</td><td colspan="3">{{ $p->calculation }}</td></tr>
                    <tr>
                        <td>{{ $p->stream === 'training' ? 'Payable' : 'Net to you' }}</td>
                        <td><b>{{ $p->netLabel() }}</b></td>
                        <td>Charged to</td><td>{{ $p->budgetLabel() }}</td>
                    </tr>
                    @if ($p->stream === 'project')
                        <tr>
                            <td>Hirer paid</td>
                            <td>{{ $p->hirer_paid_on ? $p->hirer_paid_on->format('d M Y').' ('.$p->hirer_payment_ref.')' : 'not confirmed yet' }}</td>
                            <td>Agreed for the job</td><td>{{ Finance::ksh((float) $p->agreed_amount_ksh) }}</td>
                        </tr>
                        <tr>
                            <td>Deducted</td>
                            <td colspan="3" class="hint">
                                {{ collect([
                                    $p->materials_ksh > 0 ? 'materials '.Finance::ksh((float) $p->materials_ksh) : null,
                                    $p->commission_ksh > 0 ? 'commission '.Finance::ksh((float) $p->commission_ksh) : null,
                                    $p->mobilization_ksh > 0 ? 'mobilization '.Finance::ksh((float) $p->mobilization_ksh) : null,
                                    $p->penalty_ksh > 0 ? 'deductions '.Finance::ksh((float) $p->penalty_ksh) : null,
                                ])->filter()->join(' · ') ?: 'nothing deducted' }}
                                @php $given = $p->project?->providedMaterials?->where('supplied_on', '!=', null) ?? collect(); @endphp
                                @if ($given->count())
                                    <table class="data money-table" style="margin-top:8px;">
                                        <thead><tr><th>Materials the programme handed over</th><th>Quantity</th><th>Value</th></tr></thead>
                                        <tbody>
                                            @foreach ($given as $m)
                                                <tr>
                                                    <td>{{ $m->name }}<div class="hint">given out {{ $m->supplied_on->format('d M Y') }}</div></td>
                                                    <td>{{ $m->quantityLabel() }}</td>
                                                    <td>{{ $m->totalLabel() }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot><tr><td colspan="2">Total taken off the payout</td>
                                            <td><b>{{ Finance::ksh((float) $p->materials_ksh) }}</b></td></tr></tfoot>
                                    </table>
                                    <div class="hint">You are paid the agreed amount less what you were given for the
                                        job — the same list is on the project page.</div>
                                @endif
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td>Certified trainees</td><td>{{ $p->certified_count ?? 'not confirmed yet' }}</td>
                            <td>Fee per trainee</td><td>{{ Finance::ksh((float) $p->per_trainee_fee_ksh) }}</td>
                        </tr>
                        <tr>
                            <td>Report</td>
                            <td>{{ $p->report_submitted_on ? $p->report_submitted_on->format('d M Y') : 'not filed yet' }}</td>
                            <td>Hosting fee</td><td>{{ Finance::ksh((float) $p->hosting_fee_ksh) }}</td>
                        </tr>
                    @endif
                    @if ($p->stage === 'released')
                        <tr>
                            <td>Released</td>
                            <td>{{ $p->released_on?->format('d M Y') }} by {{ $p->methodLabel() }}</td>
                            <td>Confirmation</td>
                            <td>{{ $p->provider_ref ?: '—' }}
                                <div class="hint">{{ $p->payee_phone ?: $p->payee_account }}</div></td>
                        </tr>
                    @endif
                    @if ($p->approved_on)
                        <tr>
                            <td>Approved</td>
                            <td>{{ $p->approved_on->format('d M Y') }}</td>
                            <td>By</td><td>{{ $p->approver->exists ? $p->approver->name : 'the office' }}</td>
                        </tr>
                    @endif
                    @if ($p->held_reason)
                        <tr><td colspan="4" class="hint flag-bad">On hold: {{ $p->held_reason }}</td></tr>
                    @endif
                    @if ($p->rejection_reason)
                        <tr><td colspan="4" class="hint">Not paid out: {{ $p->rejection_reason }}</td></tr>
                    @endif
                    @if ($p->note)
                        <tr><td colspan="4" class="hint">{{ $p->note }}</td></tr>
                    @endif
                </tbody>
            </table>
        @endforeach
    @endif
</div>

@if ($ledger->isNotEmpty())
    <div class="card">
        <div class="section-head"><h3>The same money on the programme register</h3></div>
        <p class="hint">Every payout posts its own line when the Finance Officer releases it — that is the programme's
            record of what it paid you, and it should always agree with the table above.</p>
        <table class="data">
            <thead><tr><th>Reference</th><th>Date</th><th>What it is</th><th>Amount</th></tr></thead>
            <tbody>
                @foreach ($ledger as $e)
                    <tr>
                        <td>{{ $e->reference }}</td>
                        <td>{{ $e->happened_on->format('d M Y') }}</td>
                        <td>{{ $e->kindLabel() }}<div class="hint">{{ $e->title }}</div></td>
                        <td class="{{ $e->direction === 'income' ? 'flag-good' : 'flag-bad' }}">{{ $e->amountLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<p class="hint">If a figure here is wrong — an agreed amount, a material charge, a certified count that should be
    yours — the correction is made by the programme office on the same payout row, never by deleting it. Ask them to
    reopen it with the evidence.</p>
@endsection
