@extends('layouts.admin')
@section('title', 'Money register')

@section('content')
@include('partials.errors')
@php use App\Models\FinanceEntry; use App\Support\Finance; @endphp
<p class="back"><a href="{{ route('admin.department', 'finance') }}">&larr; Finance, Procurement &amp; Inventory</a></p>
<h1>Money register — {{ $year }}</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    Every shilling in and every shilling out is one line here (§14.3). Money the system already knows about —
    a supplier paid on a purchase order, a payout released to an E-Fundi, materials issued from the store —
    <b>posts its own line and is not retyped</b>. What is typed in is membership fees, donor and partner funds,
    transport, accommodation and the odd operating cost. A line that was wrong is corrected with an opposite line,
    so the mistake stays visible on the register.
</p>

<div class="stat-row">
    <div class="stat"><small>Money in {{ $year }}</small><b>{{ Finance::ksh($income) }}</b></div>
    <div class="stat"><small>Money out {{ $year }}</small><b>{{ Finance::ksh($expense) }}</b></div>
    <div class="stat"><small>Balance</small><b class="{{ $balance < 0 ? 'flag-bad' : 'flag-good' }}">{{ Finance::ksh($balance) }}</b></div>
    <div class="stat"><small>Budgeted {{ $year }}</small><b>{{ Finance::ksh($planned) }}</b></div>
    <div class="stat"><small>Lines on the register</small><b>{{ $counts['all'] }}</b></div>
</div>

<div class="tabs">
    @foreach (['all' => 'Every line', 'income' => 'Money in', 'expense' => 'Money out'] as $key => $label)
        <a class="tab {{ $direction === $key ? 'on' : '' }}"
           href="{{ route('admin.ledger.index', ['f' => $key, 'kind' => $kind, 'budget' => $budget, 'year' => $year, 'q' => $search]) }}">
            {{ $label }}<span class="n">{{ $counts[$key] }}</span>
        </a>
    @endforeach
    @foreach ($years as $y)
        <a class="tab {{ (int) $y === $year ? 'on' : '' }}"
           href="{{ route('admin.ledger.index', ['f' => $direction, 'kind' => $kind, 'budget' => $budget, 'year' => $y, 'q' => $search]) }}">
            {{ $y }}<span class="n">year</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.ledger.index') }}" class="filter-bar">
    <input type="hidden" name="f" value="{{ $direction }}">
    <input type="hidden" name="year" value="{{ $year }}">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search reference, title or note">
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($kind !== 'all' || $budget !== 'all' || $search !== '')
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.ledger.index', ['f' => $direction, 'year' => $year]) }}">Clear</a>
    @endif

    <div class="form-grid-2" style="width:100%; gap:12px 18px;">
        <div>
            <label for="kind">What the money is</label>
            <select name="kind" id="kind">
                <option value="all">Every kind</option>
                @foreach (FinanceEntry::INCOME_KINDS as $key => $label)
                    <option value="{{ $key }}" @selected($kind === $key)>{{ $label }} — in</option>
                @endforeach
                @foreach (FinanceEntry::EXPENSE_KINDS as $key => $label)
                    <option value="{{ $key }}" @selected($kind === $key)>{{ $label }} — out</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="budget">Budget line</label>
            <select name="budget" id="budget">
                <option value="all">Any budget line</option>
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}" @selected($budget === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</form>

<table class="data">
    <thead>
        <tr>
            <th>Reference</th><th>Date</th><th>Money in</th><th>Money out</th>
            <th>What it is</th><th>Charged to</th><th>Tied to</th><th>Recorded by</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($entries as $e)
            <tr>
                <td><b>{{ $e->reference }}</b></td>
                <td>{{ $e->happened_on->format('d M Y') }}</td>
                <td class="flag-good">{{ $e->direction === 'income' ? Finance::ksh((float) $e->amount_ksh) : '—' }}</td>
                <td class="{{ $e->budget_category ? '' : 'hint' }}">
                    {{ $e->direction === 'expense' ? Finance::ksh((float) $e->amount_ksh) : '—' }}
                </td>
                <td>{{ $e->kindLabel() }}
                    <div class="hint">{{ $e->title }} · {{ $e->methodLabel() }}</div>
                </td>
                <td><span class="hint">{{ $e->budgetLabel() }}</span></td>
                <td>
                    @php
                        $tied = collect([
                            $e->project->exists ? 'Project '.$e->project->title : null,
                            $e->cohort->exists ? 'Cohort '.$e->cohort->reference : null,
                            $e->fundi->exists ? $e->fundi->full_name : null,
                        ])->filter()->join(' · ');
                    @endphp
                    <span class="hint">{{ $tied ?: 'The programme itself' }}</span>
                    @if ($e->source_type)
                        <div class="hint">posted by the system from {{ class_basename($e->source_type) }} #{{ $e->source_id }}</div>
                    @endif
                </td>
                <td><span class="hint">{{ $e->recorder->exists ? $e->recorder->name : 'the system' }}</span></td>
            </tr>
            @if ($e->note)
                <tr><td colspan="8" class="hint">{{ $e->note }}</td></tr>
            @endif
        @empty
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:22px;">
                No lines on the register with these filters for {{ $year }}. Paid purchase orders and released
                payouts land here on their own; the two forms below record the money the office holds.
            </td></tr>
        @endforelse
    </tbody>
</table>

{{ $entries->links() }}

<div class="two-col">
    <div class="card">
        <h3>Record money in</h3>
        <p class="hint">Funding is not charged to a budget line — it arrives. Say what it is so the budget page can
            count it for the year.</p>
        <form method="POST" action="{{ route('admin.ledger.store') }}" class="fee-form"
              data-confirm="Record this money in on the register?" data-title="Record money in">
            @csrf
            <input type="hidden" name="direction" value="income">
            <div class="field">
                <label>What came in <span class="req">*</span></label>
                <select name="kind" required>
                    @foreach (FinanceEntry::INCOME_KINDS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-grid-2" style="margin-top:10px;">
                <div class="field">
                    <label>Amount (KSH) <span class="req">*</span></label>
                    <input type="number" name="amount_ksh" required min="0.01" step="0.01">
                </div>
                <div class="field">
                    <label>Date received <span class="req">*</span></label>
                    <input type="date" name="happened_on" required value="{{ now()->toDateString() }}">
                </div>
            </div>
            <div class="field" style="margin-top:10px;">
                <label>How it was paid</label>
                <select name="method">
                    @foreach ($methods as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-top:10px;">
                <label>Line on the register <span class="req">*</span></label>
                <input type="text" name="title" required maxlength="190"
                       placeholder="e.g. Second disbursement — Wire Lakes Foundation, ICT lab equipment">
            </div>
            <div class="field" style="margin-top:10px;">
                <label>Against which project</label>
                <select name="project_id">
                    <option value="">Not a project payment</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-top:10px;">
                <label>Note</label>
                <input type="text" name="note" maxlength="1000" placeholder="Receipt, cheque or M-Pesa reference.">
            </div>
            <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Record money in</button>
        </form>
    </div>

    <div class="card">
        <h3>Record money out</h3>
        <p class="hint">Every expense sits on a budget line (§14.2) — training money does not pay for a build. Pick
            the line, and the budget page will show it charged.</p>
        <form method="POST" action="{{ route('admin.ledger.store') }}" class="fee-form"
              data-confirm="Record this expense on the register?" data-title="Record money out">
            @csrf
            <input type="hidden" name="direction" value="expense">
            <div class="field">
                <label>What went out <span class="req">*</span></label>
                <select name="kind" required>
                    @foreach (FinanceEntry::EXPENSE_KINDS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-grid-2" style="margin-top:10px;">
                <div class="field">
                    <label>Amount (KSH) <span class="req">*</span></label>
                    <input type="number" name="amount_ksh" required min="0.01" step="0.01">
                </div>
                <div class="field">
                    <label>Date paid <span class="req">*</span></label>
                    <input type="date" name="happened_on" required value="{{ now()->toDateString() }}">
                </div>
            </div>
            <div class="form-grid-2" style="margin-top:10px;">
                <div class="field">
                    <label>Charged to <span class="req">*</span></label>
                    <select name="budget_category" required>
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>How it was paid</label>
                    <select name="method">
                        @foreach ($methods as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="field" style="margin-top:10px;">
                <label>Line on the register <span class="req">*</span></label>
                <input type="text" name="title" required maxlength="190"
                       placeholder="e.g. Transport for the Kisumu cohort assessment">
            </div>
            <div class="form-grid-2" style="margin-top:10px;">
                <div class="field">
                    <label>Against which project</label>
                    <select name="project_id">
                        <option value="">None</option>
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}">{{ $p->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Against which cohort</label>
                    <select name="cohort_id">
                        <option value="">None</option>
                        @foreach ($cohorts as $c)
                            <option value="{{ $c->id }}">{{ $c->reference }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="field" style="margin-top:10px;">
                <label>Note</label>
                <input type="text" name="note" maxlength="1000" placeholder="Invoice number, who authorised it.">
            </div>
            <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Record money out</button>
        </form>
    </div>
</div>

<p class="hint">Recording a line on the register is a bookkeeping action and staff do it; setting a budget and
    releasing money out of the programme are signed by a programme executive (§14.2, §14.4 step 8, §14.6 steps 5–7).</p>
@endsection
