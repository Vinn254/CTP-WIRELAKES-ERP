@extends('layouts.admin')
@section('title', 'E-Fundi payouts')

@section('content')
@include('partials.errors')
@php
    use App\Models\Payout;
    use App\Support\Finance;
@endphp
<h1>Payouts to E-Fundis</h1>
<p class="page-lede">Payouts owed to E-Fundis for project work and training.</p>

<div class="stat-row">
    <div class="stat"><small>Calculated, waiting</small><b>{{ Finance::ksh($totals['queued']) }}</b></div>
    <div class="stat"><small>Approved, not released</small><b>{{ Finance::ksh($totals['approved']) }}</b></div>
    <div class="stat"><small>Released — project work</small><b>{{ Finance::ksh($totals['project']) }}</b></div>
    <div class="stat"><small>Released — training</small><b>{{ Finance::ksh($totals['training']) }}</b></div>
    <div class="stat"><small>On hold</small><b class="{{ $totals['held'] ? 'flag-bad' : '' }}">{{ $totals['held'] }}</b></div>
</div>

<div class="tabs">
    <a class="tab {{ $stream === 'all' ? 'on' : '' }}" href="{{ route('admin.payouts.index', ['q' => $search]) }}">
        Both streams<span class="n">{{ $totals['lines'] }}</span></a>
    @foreach (Payout::STREAMS as $key => $label)
        <a class="tab {{ $stream === $key ? 'on' : '' }}"
           href="{{ route('admin.payouts.index', ['f' => $key, 'stage' => $stage, 'q' => $search]) }}">
            {{ $label }}<span class="n"></span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.payouts.index') }}" class="filter-bar">
    <input type="hidden" name="f" value="{{ $stream }}">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search payout reference or note" aria-label="Search payout reference or note">
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    <a class="btn btn-sm btn-ghost" href="{{ route('admin.payouts.index') }}">Clear</a>
    <div class="grow">
        <label for="stage">Stage</label>
        <select name="stage" id="stage">
            <option value="all">Any stage</option>
            @foreach ($stages as $key => $label)
                <option value="{{ $key }}" @selected($stage === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>

<table class="data">
    <thead>
        <tr>
            <th>Payout</th><th>Stream</th><th>Paid to</th><th>For</th><th>Stage</th>
            <th>Calculated</th><th>Approved by</th><th>Released</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($payouts as $p)
            <tr>
                <td><b>{{ $p->reference }}</b>
                    @if ($p->stream === 'training' && $p->trainer?->fundi)
                        <div class="hint">trainer {{ $p->trainer->fundi->full_name }}</div>
                    @endif
                </td>
                <td><span class="tag {{ $p->stream === 'training' ? 'gold' : '' }}">{{ $p->stream === 'training' ? 'Training' : 'Project' }}</span>
                    <div class="hint">{{ $p->budgetLabel() }}</div></td>
                <td>{{ $p->fundi->full_name ?? '—' }}
                    <div class="hint">{{ $p->fundi->ef_code ?? '' }}</div></td>
                <td><span class="hint">{{ $p->sourceLabel() }}</span></td>
                <td>
                    <span class="status {{ $p->stage === 'released' ? 'active' : ($p->stage === 'rejected' ? 'closed' : 'pending_review') }}">
                        {{ $p->stageLabel() }}
                    </span>
                    @if ($p->held_reason)<div class="hint">{{ $p->held_reason }}</div>@endif
                    @if ($p->rejection_reason)<div class="hint">{{ $p->rejection_reason }}</div>@endif
                </td>
                <td>{{ $p->net_ksh !== null && (float) $p->net_ksh > 0 ? $p->netLabel() : '—' }}
                    @if ($p->gross_ksh && $p->gross_ksh != $p->net_ksh)
                        <div class="hint">of {{ $p->grossLabel() }}</div>
                    @endif</td>
                <td><span class="hint">{{ $p->approver->exists ? $p->approver->name : '—' }}
                    @if ($p->approved_on)<div>{{ $p->approved_on->format('d M Y') }}</div>@endif</span></td>
                <td><span class="hint">{{ $p->releaser->exists ? $p->releaser->name : '—' }}
                    @if ($p->released_on)<div>{{ $p->released_on->format('d M Y') }} · {{ $p->provider_ref }}</div>@endif</span></td>
                <td class="actions"><a class="btn btn-sm btn-outline"
                    href="{{ route('admin.payouts.show', $p) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty-cell">
                No payout on this filter yet. Open one below when a fundi finishes a project or a cohort run —
                the fundi sees the same money on their own <b>My earnings</b> screen.
            </td></tr>
        @endforelse
    </tbody>
</table>

{{ $payouts->links() }}

<div class="two-col">
    <div class="card">
        <h3>Open a project payout (§14.6)</h3>
        <p class="hint">Agreed amount minus materials, commission, the mobilization credit and any deductions. It
            cannot be approved until Finance confirms the hirer paid the organisation.</p>
        <form method="POST" action="{{ route('admin.payouts.store') }}" class="fee-form"
              data-confirm="Open this payout on the register?" data-title="Project payout">
            @csrf
            <input type="hidden" name="stream" value="project">
            <div class="form-grid-2">
                <div class="field">
                    <label>E-Fundi <span class="req">*</span></label>
                    <select name="fundi_id" required>
                        @foreach ($fundis as $f)
                            <option value="{{ $f->id }}">{{ $f->full_name }} · {{ $f->ef_code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Project <span class="req">*</span></label>
                    <select name="project_id" required>
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}">{{ $p->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-grid-2" style="margin-top:10px;">
                <div class="field">
                    <label>Agreed amount for the project (KSH) <span class="req">*</span></label>
                    <input type="number" name="agreed_amount_ksh" required min="0.01" step="0.01">
                </div>
                <div class="field">
                    <label>Charged to budget</label>
                    <select name="budget_category">
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-grid-2" style="margin-top:10px;">
                <div class="field">
                    <label>Materials the programme supplied</label>
                    <input type="number" name="materials_ksh" min="0" step="0.01" placeholder="Leave empty to use the project">
                    <div class="help">Leave this empty and the system deducts what that project's material lines record as handed over — the figure cannot disagree with the store (§14.6 step 6).</div>
                </div>
                <div class="field">
                    <label>Service commission</label>
                    <input type="number" name="commission_ksh" min="0" step="0.01" value="0">
                </div>
            </div>
            <div class="form-grid-2" style="margin-top:10px;">
                <div class="field">
                    <label>Mobilization credit</label>
                    <input type="number" name="mobilization_ksh" min="0" step="0.01" value="0">
                </div>
                <div class="field">
                    <label>Deductions / penalties</label>
                    <input type="number" name="penalty_ksh" min="0" step="0.01" value="0">
                </div>
            </div>
            <div class="form-grid-2" style="margin-top:10px;">
                <div class="field">
                    <label>Released by</label>
                    <select name="method">
                        @foreach (Payout::METHODS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Phone or account</label>
                    <input type="text" name="payee_phone" maxlength="40" placeholder="The number the payout goes to.">
                </div>
            </div>
            <div class="field" style="margin-top:10px;">
                <label>Note</label>
                <input type="text" name="note" maxlength="1000" placeholder="What the job was and any dispute on file.">
            </div>
            <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Open the payout</button>
        </form>
    </div>

    <div class="card">
        <h3>Open a training payout (§14.6)</h3>
        <p class="hint">Paid to the fundi-trainer who ran the cohort, out of the training budget: fee per certified
            trainee, plus the workshop-hosting fee where they hosted it.</p>
        <form method="POST" action="{{ route('admin.payouts.store') }}" class="fee-form"
              data-confirm="Open this training payout?" data-title="Training payout">
            @csrf
            <input type="hidden" name="stream" value="training">
            <div class="form-grid-2">
                <div class="field">
                    <label>Cohort <span class="req">*</span></label>
                    <select name="cohort_id" required>
                        @foreach ($cohorts as $c)
                            <option value="{{ $c->id }}">{{ $c->reference }} — {{ $c->training?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Fundi-trainer <span class="req">*</span></label>
                    <select name="fundi_id" required>
                        @foreach ($fundis as $f)
                            <option value="{{ $f->id }}">{{ $f->full_name }} · {{ $f->ef_code }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-grid-2" style="margin-top:10px;">
                <div class="field">
                    <label>Certified trainees <span class="req">*</span></label>
                    <input type="number" name="certified_count" required min="0" step="1"
                           placeholder="Confirmed again when the report is filed.">
                </div>
                <div class="field">
                    <label>Fee per trainee (KSH) <span class="req">*</span></label>
                    <input type="number" name="per_trainee_fee_ksh" required min="0" step="0.01">
                </div>
            </div>
            <div class="form-grid-2" style="margin-top:10px;">
                <div class="field">
                    <label>Workshop-hosting fee (KSH)</label>
                    <input type="number" name="hosting_fee_ksh" min="0" step="0.01" value="0">
                </div>
                <div class="field">
                    <label>Released by</label>
                    <select name="method">
                        @foreach (Payout::METHODS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="field" style="margin-top:10px;">
                <label>Note</label>
                <input type="text" name="note" maxlength="1000" placeholder="Where it ran, how the assessment went.">
            </div>
            <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Open the payout</button>
            <p class="hint" style="margin-top:10px;">The trainer on the intake's record is the payee: opening one for
                someone else is refused until the report says who taught it.</p>
        </form>
    </div>
</div>
@endsection
