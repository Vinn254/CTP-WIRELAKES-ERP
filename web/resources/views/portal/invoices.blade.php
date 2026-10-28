@extends('layouts.member')
@section('title', 'My invoices')

@php
    use App\Support\Finance;
    $paybill = Finance::paybill();
@endphp

@section('content')
<div class="page-intro">
    <p class="sub">These are the invoices the programme office has raised against your name. Settle the balance shown
        and the office marks the invoice paid when the money is seen.</p>
    <div class="member-stats">
        <div class="stat"><small>Total billed</small><b>{{ Finance::ksh($totals['billed']) }}</b></div>
        <div class="stat"><small>Received so far</small><b>{{ Finance::ksh($totals['paid']) }}</b></div>
        <div class="stat"><small>Balance you owe</small><b>{{ Finance::ksh($totals['owing']) }}</b></div>
        <div class="stat"><small>Invoices to settle</small><b>{{ $totals['open'] }}</b></div>
    </div>
</div>

<div class="card paybill-card">
    <div class="section-head"><h3>How to pay</h3></div>
    <p class="paybill">For any payment use this Paybill <b>{{ $paybill['number'] }}</b>,
        account number <b>{{ $paybill['account'] }}</b>.</p>
    <p class="hint">Put the invoice number (INV-…) as the reason for each payment — that is what lets the office match
        your money to the right invoice. If you paid and it is still showing here, contact the office with the amount,
        date and phone number you paid from.</p>
</div>

@foreach ($invoices as $i)
    <div class="card">
        <div class="section-head">
            <h3>{{ $i->reference }} — {{ $i->title }}</h3>
            <a class="btn btn-sm btn-outline" href="{{ route('portal.invoice.document', $i) }}">Open the invoice</a>
        </div>
        <table class="data money-table">
            <tbody>
                <tr>
                    <td>Raised for</td><td colspan="3">{{ $i->details ?: $i->title }}</td>
                </tr>
                <tr>
                    <td>Issued</td><td>{{ $i->issued_on->format('d M Y') }}</td>
                    <td>Due by</td>
                    <td>{{ $i->due_on?->format('d M Y') ?: 'on receipt' }}
                        @if ($i->status === 'open' && $i->isOverdue())<span class="flag-bad"> · overdue</span>@endif</td>
                </tr>
                <tr>
                    <td>Billed</td><td>{{ $i->amountLabel() }}</td>
                    <td>Received</td><td>{{ $i->paidLabel() }}</td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td><span class="status {{ $i->status === 'settled' ? 'active' : ($i->status === 'cancelled' ? 'closed' : 'pending_review') }}">
                        {{ $i->statusLabel() }}</span></td>
                    <td><b>Balance</b></td>
                    <td><b class="{{ $i->status === 'cancelled' ? '' : ($i->balanceKsh() > 0.005 ? 'flag-bad' : 'flag-good') }}">
                        {{ $i->status === 'cancelled' ? '—' : $i->balanceLabel() }}</b></td>
                </tr>
                @if ($i->note)
                    <tr><td colspan="4" class="hint">{{ $i->note }}</td></tr>
                @endif
                @if ($i->status === 'cancelled')
                    <tr><td colspan="4" class="hint">Withdrawn by {{ $i->canceller->name ?? 'the office' }}
                        {{ $i->cancel_reason ? ' — '.$i->cancel_reason : '' }}. You do not owe this one.</td></tr>
                @endif
            </tbody>
        </table>

        @php $paid = $i->payments->whereNull('voided_at'); @endphp
        @if ($paid->isNotEmpty())
            <h4 class="dl-group">What the office has received against this invoice</h4>
            <table class="data">
                <thead><tr><th>Date</th><th>Amount</th><th>How</th><th>Confirmation</th></tr></thead>
                <tbody>
                    @foreach ($paid as $p)
                        <tr>
                            <td>{{ $p->happened_on->format('d M Y') }}</td>
                            <td>{{ $p->amountLabel() }}</td>
                            <td>{{ $p->methodLabel() }}</td>
                            <td>{{ $p->reference ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endforeach

<p class="hint">If a figure here is wrong, or a payment you made is missing, the correction is made by the programme
    office on the same invoice — the invoice is amended, never re-raised under a new number.</p>
@endsection
