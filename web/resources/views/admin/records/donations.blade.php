@extends('layouts.admin')
@section('title', 'Donations')

@php
 $freq = ['one_time' => 'One-off', 'monthly' => 'Monthly', 'yearly' => 'Yearly'];
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.dashboard') }}">&larr; Departments</a></p>
<h1>Donations</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 18px;">
 Every pledge made through the public Donate page lands here the moment it is submitted.
 <span class="hint">M-Pesa / bank checkout integration arrives with the payments phase, so amounts are self-reported for now.</span>
</p>

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
 <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:28px;">No donations recorded yet.</td></tr>
 @endforelse
 </tbody>
</table>

<div class="pagination">{{ $donations->links() }}</div>
@endsection
