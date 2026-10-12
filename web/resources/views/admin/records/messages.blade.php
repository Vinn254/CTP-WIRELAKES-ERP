@extends('layouts.admin')
@section('title', 'Messages')

@section('content')
<p class="back"><a href="{{ route('admin.department', 'membership') }}">&larr; Public Site &amp; Membership</a></p>
<h1>Messages from the public site</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 18px;">
 Every enquiry sent through the Contact page. {{ number_format($messages->total()) }} in total — newest first.
</p>

<table class="data">
 <thead>
 <tr><th>From</th><th>Contact</th><th>Message</th><th>Received</th><th></th></tr>
 </thead>
 <tbody>
 @forelse ($messages as $m)
 <tr>
 <td><b>{{ $m->name }}</b></td>
 <td>
 <a href="mailto:{{ $m->email }}">{{ $m->email }}</a>
 @if ($m->phone)<div class="hint">{{ $m->phone }}</div>@endif
 </td>
 <td style="max-width:420px;">{!! nl2br(e($m->message)) !!}</td>
 <td>{{ $m->created_at->format('d M Y, H:i') }}</td>
 <td><a class="btn btn-sm btn-outline" href="mailto:{{ $m->email }}?subject=Re:%20your%20enquiry%20—%20CTP-WireLakes">Reply</a></td>
 </tr>
 @empty
 <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:28px;">No messages yet.</td></tr>
 @endforelse
 </tbody>
</table>

<div class="pagination">{{ $messages->links() }}</div>
@endsection
