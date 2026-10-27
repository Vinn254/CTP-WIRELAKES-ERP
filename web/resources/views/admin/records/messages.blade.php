@extends('layouts.admin')
@section('title', 'Messages')

@section('content')
<h1>Messages from the public site</h1>
<p class="page-lede">Enquiries sent through the public Contact page, newest first.</p>

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
 <td><a class="btn btn-sm btn-outline" href="mailto:{{ $m->email }}?subject=Re:%20your%20enquiry%20—%20WireLakes Technologies">Reply</a></td>
 </tr>
 @empty
 <tr><td colspan="5" class="empty-cell">No messages yet.</td></tr>
 @endforelse
 </tbody>
</table>

<div class="pagination">{{ $messages->links() }}</div>
@endsection
