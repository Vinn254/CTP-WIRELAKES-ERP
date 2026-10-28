@extends('layouts.admin')
@section('title', 'Invoices')

@section('content')
@include('partials.errors')
@php use App\Models\Invoice; use App\Support\Finance; @endphp
<div class="head-row">
    <div>
        <h1>Invoices</h1>
        <p class="page-lede">Money the programme asks for — billed here, settled on the money register.</p>
    </div>
    <div class="head-acts">
        <button class="btn btn-sm btn-lime" type="button" data-sheet="raise">+ Raise invoice</button>
        <a class="btn btn-sm btn-outline" href="{{ route('admin.invoices.index', ['f' => 'open']) }}">What is unpaid</a>
    </div>
</div>

<div class="stat-row">
    <div class="stat"><small>Billed</small><b>{{ Finance::ksh($totals['billed']) }}</b></div>
    <div class="stat"><small>Still outstanding</small><b class="flag-bad">{{ Finance::ksh($totals['outstanding']) }}</b></div>
    <div class="stat"><small>Past their due date</small><b>{{ $totals['overdue'] }}</b></div>
    <div class="stat"><small>Invoices on the desk</small><b>{{ $totals['count'] }}</b></div>
</div>

<form method="GET" action="{{ route('admin.invoices.index') }}" class="filter-bar">
    <label class="field">
        <span>Invoice status</span>
        <select name="f" onchange="this.form.submit()">
            @foreach (['all' => 'Every invoice', 'open' => 'Not paid', 'settled' => 'Settled', 'cancelled' => 'Cancelled'] as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }} ({{ $counts[$key] }})</option>
            @endforeach
        </select>
    </label>
    <input type="search" name="q" value="{{ $search }}" placeholder="Search reference, what it is for or who it is billed to"
           aria-label="Search reference, title or who it is billed to">
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($who !== 'all' || $search !== '')
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.invoices.index', ['f' => $status]) }}">Clear</a>
    @endif
    <div class="form-grid-2">
        <div>
            <label for="who">Billed to</label>
            <select name="who" id="who">
                <option value="all">Anyone</option>
                @foreach ($billedTo as $key => $label)
                    <option value="{{ $key }}" @selected($who === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</form>

<table class="data">
    <thead>
        <tr>
            <th>Reference</th><th>Billed to</th><th>What is invoiced</th><th>Billed</th><th>Paid</th>
            <th>Balance</th><th>Issued</th><th>Due by</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($invoices as $i)
            <tr>
                <td><b>{{ $i->reference }}</b></td>
                <td>{{ $i->memberName() }}
                    <div class="hint">{{ $i->billedToLabel() }}
                        @if ($code = $i->ctp?->ctp_code ?: $i->fundi?->ef_code) · {{ $code }}@endif</div>
                </td>
                <td>{{ $i->title }}
                    @if ($i->project)<div class="hint">{{ $i->project->title }}</div>@endif
                </td>
                <td>{{ $i->amountLabel() }}</td>
                <td class="{{ $i->paidKsh() > 0 ? 'flag-good' : 'hint' }}">{{ $i->paidLabel() }}</td>
                <td class="{{ $i->status === 'cancelled' ? 'hint' : 'flag-bad' }}">
                    {{ $i->status === 'cancelled' ? '—' : $i->balanceLabel() }}</td>
                <td>{{ $i->issued_on->format('d M Y') }}</td>
                <td>
                    {{ $i->due_on?->format('d M Y') ?: 'on receipt' }}
                    @if ($i->isOverdue())<div class="hint flag-bad">overdue</div>@endif
                </td>
                <td><span class="status {{ $i->status === 'settled' ? 'active' : ($i->status === 'cancelled' ? 'closed' : 'pending_review') }}">
                    {{ $i->statusLabel() }}</span></td>
                <td class="actions">
                    <a class="btn btn-sm btn-outline" href="{{ route('admin.invoices.show', $i) }}">Open</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty-cell">
                No invoice on this filter. Raise one below — a donor, a CTP or an E-Fundi is billed here, and the money
                they send is recorded against it on the register.
            </td></tr>
        @endforelse
    </tbody>
</table>

{{ $invoices->links() }}

@include('partials.sheet-start', ['name' => 'raise', 'title' => 'Raise an invoice',
    'sub' => 'The document carries the programme logo and the Paybill the payer must use.'])
    <form method="POST" action="{{ route('admin.invoices.store') }}" class="fee-form"
          data-confirm="Raise this invoice?" data-title="Raise invoice">
        @csrf
        <input type="hidden" name="_sheet" value="raise">
        <div class="form-grid-2">
            <div class="field">
                <label>Billed to <span class="req">*</span></label>
                <select name="billed_to" id="billed_to" data-choices required>
                    @foreach ($billedTo as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" data-for-choice="ctp">
                <label>Which CTP</label>
                <select name="ctp_id">
                    <option value="">— pick the CTP —</option>
                    @foreach ($ctps as $c)
                        <option value="{{ $c->id }}">{{ $c->full_name }} ({{ $c->ctp_code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field" data-for-choice="fundi">
                <label>Which E-Fundi</label>
                <select name="fundi_id">
                    <option value="">— pick the E-Fundi —</option>
                    @foreach ($fundis as $f)
                        <option value="{{ $f->id }}">{{ $f->full_name }} ({{ $f->ef_code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field" data-for-choice="donor">
                <label>Which donation it follows</label>
                <select name="donation_id">
                    <option value="">Not tied to a pledge</option>
                    @foreach ($donations as $d)
                        <option value="{{ $d->id }}">{{ $d->donor_name }} · {{ Finance::ksh((float) $d->amount) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field full">
                <label>Name on the invoice</label>
                <input type="text" name="party_name" maxlength="150"
                       placeholder="e.g. Lake County Water Board — blank bills the member picked above by their registered name">
            </div>
            <div class="field" data-for-choice="donor|other">
                <label>Email the invoice goes to</label>
                <input type="email" name="party_email" maxlength="150" placeholder="for a donor or another party">
            </div>
            <div class="field" data-for-choice="donor|other">
                <label>Phone</label>
                <input type="text" name="party_phone" maxlength="30">
            </div>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>What is being invoiced <span class="req">*</span></label>
            <input type="text" name="title" required maxlength="190"
                   placeholder="e.g. Second disbursement — ICT lab equipment, Kisumu">
        </div>
        <div class="field" style="margin-top:10px;">
            <label>Details on the document</label>
            <textarea name="details" rows="3" maxlength="1000"
                      placeholder="What the money covers, the period, any reference the payer quotes."></textarea>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Amount (KSH) <span class="req">*</span></label>
                <input type="number" name="amount_ksh" required min="0.01" step="0.01">
            </div>
            <div class="field">
                <label>Project it belongs to</label>
                <select name="project_id">
                    <option value="">None</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Issued on <span class="req">*</span></label>
                <input type="date" name="issued_on" required value="{{ now()->toDateString() }}">
            </div>
            <div class="field">
                <label>Due by</label>
                <input type="date" name="due_on" value="{{ now()->addDays(30)->toDateString() }}">
            </div>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>Note on the document</label>
            <input type="text" name="note" maxlength="1000" placeholder="Anything the payer should read under the total.">
        </div>
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-sm btn-lime" type="submit">Raise the invoice</button>
        </div>
    </form>
@include('partials.sheet-end')

<p class="hint">{{ Finance::paybillLine() }} It is set under Programme settings and printed on every invoice.
    An invoice is a request for money, not a movement of it — recording what came in is what
    puts the income line on the register. Cancelling one is signed by a programme executive.</p>
@endsection
