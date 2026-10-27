@extends('layouts.admin')
@section('title', 'Donations')

@php
 $freq = ['one_time' => 'One-off', 'monthly' => 'Monthly', 'yearly' => 'Yearly'];
 $methods = ['mpesa' => 'M-Pesa', 'card' => 'Card', 'bank' => 'Bank transfer'];
@endphp

@section('content')
<h1>Donations</h1>
<p class="page-lede">Donation pledges from the public Donate page and gifts recorded here.</p>

@include('partials.errors')

<div class="card" style="margin-bottom:22px;">
 <h3>Record a donation</h3>
 <form method="POST" action="{{ route('admin.donations.store') }}" class="form-grid-3">
 @csrf
 <div class="field"><label>Donor name</label><input type="text" name="donor_name" maxlength="120" value="{{ old('donor_name') }}" placeholder="e.g. Jane Awino"></div>
 <div class="field"><label>Email (optional)</label><input type="email" name="email" maxlength="120" value="{{ old('email') }}"></div>
 <div class="field"><label>Phone (optional)</label><input type="text" name="phone" maxlength="20" value="{{ old('phone') }}" placeholder="07XX XXX XXX"></div>
 <div class="field"><label>Amount (KSh) <span class="req">*</span></label><input type="number" name="amount" min="1" step="1" value="{{ old('amount') }}" required></div>
 <div class="field"><label>Received as <span class="req">*</span></label>
  <select name="method" required>
   @foreach ($methods as $key => $label)<option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>@endforeach
  </select></div>
 <div class="field"><label>Frequency <span class="req">*</span></label>
  <select name="frequency" required>
   @foreach ($freq as $key => $label)<option value="{{ $key }}" @selected(old('frequency', 'one_time') === $key)>{{ $label }}</option>@endforeach
  </select></div>
 <div class="field"><label>What it supports</label><input type="text" name="impact_label" maxlength="190" value="{{ old('impact_label') }}" placeholder="e.g. Tools for 5 CTPs"></div>
 <div class="field full"><label>Note (optional)</label><input type="text" name="message" maxlength="1000" value="{{ old('message') }}" placeholder="Reference number, who collected it, the event it came from"></div>
 <div class="field"><label class="chk"><input type="checkbox" name="anonymous" value="1" @checked(old('anonymous'))> Give no public name</label></div>
 <div class="field"><button class="btn btn-primary" type="submit">Record donation</button></div>
 </form>
 <div class="hint" style="margin-top:10px;">Recorded here, in the register below. Money actually banked is entered on the <a href="{{ dept_link('admin.ledger.index') }}">Finance ledger</a>.</div>
</div>

<div class="stat-row">
 <div class="stat"><small>All-time pledges</small><b>KSh {{ number_format($total, 2) }}</b></div>
 <div class="stat"><small>This month</small><b>KSh {{ number_format($monthTotal, 2) }}</b></div>
 <div class="stat"><small>Gifts recorded</small><b>{{ number_format($donations->total()) }}</b></div>
</div>

<table class="data">
 <thead>
 <tr><th>Donor</th><th>Amount</th><th>Method</th><th>Frequency</th><th>Supporting</th><th>Message</th><th>Date</th></tr>
 </thead>
 <tbody>
 @forelse ($donations as $d)
 <tr>
 <td>
 <b>{{ $d->anonymous ? 'Anonymous' : ($d->donor_name ?: '—') }}</b>
 <div class="hint">{{ $d->email }}@if ($d->phone) · {{ $d->phone }}@endif</div>
 </td>
 <td><b>{{ $d->currency }} {{ number_format((float) $d->amount, 2) }}</b></td>
 <td>{{ strtoupper($d->method) }}</td>
 <td>{{ $freq[$d->frequency] ?? $d->frequency }}</td>
 <td>{{ $d->impact_label ?: '—' }}</td>
 <td style="max-width:280px;">{{ $d->message ? \Illuminate\Support\Str::limit($d->message, 90) : '—' }}</td>
 <td>{{ $d->created_at->format('d M Y') }}</td>
 </tr>
 @empty
 <tr><td colspan="7" class="empty-cell">No donations recorded yet.</td></tr>
 @endforelse
 </tbody>
</table>

<div class="pagination">{{ $donations->links() }}</div>
@endsection
