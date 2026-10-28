@extends('layouts.admin')
@section('title', $invoice->reference)

@php
    use App\Support\Finance;

    $isExec = Finance::isExecutive();
    $paybill = Finance::paybill();
@endphp

@section('content')
@include('partials.errors')
<p class="back"><a href="{{ route('admin.invoices.index') }}">&larr; Invoices</a></p>

<div class="applicant-head card">
    <div class="ah-main">
        <h1>{{ $invoice->memberName() }}
            <span class="status {{ $invoice->status === 'settled' ? 'active' : ($invoice->status === 'cancelled' ? 'closed' : 'pending_review') }}">
                {{ $invoice->statusLabel() }}@if ($invoice->isOverdue()) · overdue @endif
            </span>
        </h1>
        <p class="ah-meta">
            <span class="tag">{{ $invoice->reference }}</span>
            <span>{{ $invoice->billedToLabel() }}</span>
            @if ($code = $invoice->ctp?->ctp_code ?: $invoice->fundi?->ef_code)<span>{{ $code }}</span>@endif
            <span>issued {{ $invoice->issued_on->format('d M Y') }}</span>
            <span>due {{ $invoice->due_on?->format('d M Y') ?: 'on receipt' }}</span>
            @if ($email = $invoice->email())<span>{{ $email }}</span>@endif
            @if ($invoice->emailed_at)
                <span>sent {{ $invoice->emailed_at->format('d M Y H:i') }}</span>
            @endif
        </p>
    </div>
    <div class="ah-contact">
        <span class="hint">{{ $invoice->status === 'settled' ? 'Settled in full' : 'Still outstanding' }}</span>
        <b>{{ $invoice->status === 'cancelled' ? '—' : $invoice->balanceLabel() }}</b>
    </div>
</div>

<div class="stat-row">
    <div class="stat"><small>Billed</small><b>{{ $invoice->amountLabel() }}</b></div>
    <div class="stat"><small>Received</small><b class="flag-good">{{ $invoice->paidLabel() }}</b></div>
    <div class="stat"><small>Balance</small><b class="{{ $invoice->balanceKsh() > 0 ? 'flag-bad' : '' }}">
        {{ $invoice->status === 'cancelled' ? '—' : $invoice->balanceLabel() }}</b></div>
    <div class="stat"><small>Payments recorded</small><b>{{ $invoice->payments->count() }}</b></div>
</div>

<p class="head-acts" style="display:flex;gap:8px;flex-wrap:wrap;">
    <a class="btn btn-sm btn-lime" href="{{ route('admin.invoices.document', $invoice) }}" target="_blank"
       rel="noopener">Open the document</a>
    @if ($invoice->status !== 'cancelled')
        <button class="btn btn-sm btn-outline" type="button" data-sheet="send">Send it by email</button>
        @if ($invoice->status === 'open')
            <button class="btn btn-sm btn-outline" type="button" data-sheet="pay">Record a payment</button>
        @endif
    @endif
</p>

<div class="card">
    <h3>The invoice as {{ $invoice->memberName() }} receives it</h3>
    @include('partials.invoice-body', ['invoice' => $invoice])
</div>

@if ($invoice->payments->isNotEmpty())
    <div class="card">
        <h3>What has been received on it</h3>
        <table class="data">
            <thead>
                <tr><th>Date</th><th>Amount</th><th>How</th><th>Reference</th><th>Recorded by</th><th>Note</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($invoice->payments as $p)
                    <tr>
                        <td>{{ $p->happened_on->format('d M Y') }}</td>
                        <td class="{{ $p->isVoid() ? 'hint' : 'flag-good' }}">{{ $p->amountLabel() }}</td>
                        <td>{{ $p->methodLabel() }}</td>
                        <td><span class="hint">{{ $p->reference ?: '—' }}</span></td>
                        <td><span class="hint">{{ $p->recorder->exists ? $p->recorder->name : 'the office' }}</span></td>
                        <td>
                            <span class="hint">{{ $p->note ?: '—' }}</span>
                            @if ($p->isVoid())
                                <div class="hint flag-bad">Voided {{ $p->voided_at->format('d M Y') }} — {{ $p->void_reason }}</div>
                            @endif
                        </td>
                        <td class="actions">
                            @if (! $p->isVoid() && $invoice->status !== 'cancelled')
                                @include('partials.sheet-start', ['name' => 'void-'.$p->id,
                                    'title' => 'Void this payment',
                                    'sub' => 'The payment keeps its row and its reason. An opposite line goes on the money register.'])
                                    <form method="POST" action="{{ route('admin.invoices.payment.void', $p) }}"
                                          class="fee-form" data-danger
                                          data-confirm="Void the {{ $p->amountLabel() }} payment of {{ $invoice->reference }}? An opposite line goes on the money register."
                                          data-title="Void payment">
                                        @csrf
                                        <input type="hidden" name="_sheet" value="void-{{ $p->id }}">
                                        <div class="field">
                                            <label>What was wrong <span class="req">*</span></label>
                                            <input type="text" name="void_reason" required maxlength="500"
                                                   placeholder="e.g. Recorded twice — the same M-Pesa code came in once.">
                                        </div>
                                        <div class="sheet-actions">
                                            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                                            <button class="btn btn-sm btn-danger" type="submit">Void this payment</button>
                                        </div>
                                    </form>
                                @include('partials.sheet-end')
                                <button class="btn btn-sm btn-outline" type="button"
                                        data-sheet="void-{{ $p->id }}">Payment was wrong</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="hint">A voided payment keeps its row and its reason. The money register gets an opposite line the same
            day, so the two still add up to the same balance.</p>
    </div>
@endif

@if ($ledger->isNotEmpty())
    <div class="card">
        <h3>Its lines on the money register</h3>
        <table class="data">
            <thead>
                <tr><th>Reference</th><th>Date</th><th>Money in</th><th>What it is</th><th>Method</th><th>Posted from</th></tr>
            </thead>
            <tbody>
                @foreach ($ledger as $e)
                    <tr>
                        <td><b>{{ $e->reference }}</b></td>
                        <td>{{ $e->happened_on->format('d M Y') }}</td>
                        <td class="{{ $e->amount_ksh < 0 ? 'flag-bad' : 'flag-good' }}">{{ Finance::ksh((float) $e->amount_ksh) }}</td>
                        <td>{{ $e->title }}</td>
                        <td><span class="hint">{{ $e->methodLabel() }}</span></td>
                        <td><span class="hint">{{ $e->note }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="hint">These lines are written by the payments above — never typed in.
            <a href="{{ route('admin.ledger.index', ['f' => 'income', 'kind' => 'invoice_settlement']) }}">
                See every invoice payment on the register</a>.</p>
    </div>
@endif

<div class="two-col">
    @if ($isExec && $invoice->status !== 'cancelled' && $invoice->paidKsh() < 0.005)
        <div class="card entry-card">
            <h3>Cancel this invoice</h3>
            <p class="hint">A programme executive's action. The invoice keeps its row and its reason.</p>
            <button class="btn btn-sm btn-danger" type="button" data-sheet="cancel">Cancel it</button>
        </div>
    @elseif ($isExec && $invoice->status !== 'cancelled')
        <div class="card gaps">
            {{ $invoice->reference }} has {{ $invoice->paidLabel() }} paid against it. Void those payments first if this
            invoice should not stand — the register and the invoice must change together.
        </div>
    @endif

    @if ($isExec && $invoice->status === 'cancelled')
        <div class="card entry-card">
            <h3>Reopen it</h3>
            <p class="hint">Cancelled by {{ $invoice->canceller->name ?? 'an executive' }}
                {{ $invoice->cancel_reason ? ' — '.$invoice->cancel_reason : '' }}.</p>
            <form method="POST" action="{{ route('admin.invoices.reopen', $invoice) }}" class="fee-form"
                  data-confirm="Reopen {{ $invoice->reference }}?" data-title="Reopen invoice">
                @csrf
                <button class="btn btn-outline btn-block" type="submit">Reopen the invoice</button>
            </form>
        </div>
    @endif
</div>

@if ($invoice->status !== 'cancelled')
    @include('partials.sheet-start', ['name' => 'send', 'title' => 'Send '.$invoice->reference,
        'sub' => 'To hand over a copy instead, open the document and print it or save it as a PDF.'])
        <form method="POST" action="{{ route('admin.invoices.email', $invoice) }}" class="fee-form"
              data-confirm="Send {{ $invoice->reference }} to this address?" data-title="Send invoice">
            @csrf
            <input type="hidden" name="_sheet" value="send">
            <div class="field">
                <label>Send to <span class="req">*</span></label>
                <input type="email" name="to" required maxlength="150" value="{{ $invoice->email() }}"
                       placeholder="who receives it">
            </div>
            <div class="sheet-actions">
                <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                <button class="btn btn-sm btn-lime" type="submit">Send by email</button>
            </div>
        </form>
    @include('partials.sheet-end')
@endif

@if ($invoice->status === 'open')
    @include('partials.sheet-start', ['name' => 'pay', 'title' => 'Record money received',
        'sub' => 'Recording what came in is what settles an invoice.'])
        <form method="POST" action="{{ route('admin.invoices.pay', $invoice) }}" class="fee-form"
              data-confirm="Record {{ $invoice->reference }}'s payment on the money register?" data-title="Record payment">
            @csrf
            <input type="hidden" name="_sheet" value="pay">
            <div class="form-grid-2">
                <div class="field">
                    <label>Amount received (KSH) <span class="req">*</span></label>
                    <input type="number" name="amount_ksh" required min="0.01" step="0.01" max="{{ $invoice->balanceKsh() }}">
                </div>
                <div class="field">
                    <label>Date received <span class="req">*</span></label>
                    <input type="date" name="happened_on" required value="{{ now()->toDateString() }}">
                </div>
                <div class="field">
                    <label>How it was paid <span class="req">*</span></label>
                    <select name="method" required>
                        @foreach ($methods as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>M-Pesa or bank reference</label>
                    <input type="text" name="reference" maxlength="60" placeholder="e.g. SLK4H29PQX">
                </div>
                <div class="field full">
                    <label>Note</label>
                    <input type="text" name="note" maxlength="500">
                </div>
            </div>
            <div class="sheet-actions">
                <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                <button class="btn btn-sm btn-lime" type="submit">Record the payment</button>
            </div>
        </form>
    @include('partials.sheet-end')
@endif

@if ($isExec && $invoice->status !== 'cancelled' && $invoice->paidKsh() < 0.005)
    @include('partials.sheet-start', ['name' => 'cancel', 'title' => 'Cancel '.$invoice->reference,
        'sub' => 'It is never deleted, and it is reopened here if the decision was wrong.'])
        <form method="POST" action="{{ route('admin.invoices.cancel', $invoice) }}" class="fee-form"
              data-confirm="Cancel {{ $invoice->reference }} for {{ $invoice->memberName() }}?" data-title="Cancel invoice">
            @csrf
            <input type="hidden" name="_sheet" value="cancel">
            <div class="field">
                <label>Why it is cancelled <span class="req">*</span></label>
                <input type="text" name="cancel_reason" required maxlength="500"
                       placeholder="e.g. Raised twice — the same work is billed on INV-2026-00004.">
            </div>
            <div class="sheet-actions">
                <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Keep it</button>
                <button class="btn btn-sm btn-danger" type="submit">Cancel the invoice</button>
            </div>
        </form>
    @include('partials.sheet-end')
@endif

<p class="hint">Paybill {{ $paybill['number'] }}, account {{ $paybill['account'] }} — set under Programme settings and
    printed on the document. {{ $invoice->billedToLabel() }}
    @if ($invoice->ctp_id || $invoice->fundi_id)sees this invoice and its balance on their own portal @else
    is sent the document by email @endif.</p>
@endsection
