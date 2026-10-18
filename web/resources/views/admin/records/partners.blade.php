@extends('layouts.admin')
@section('title', 'Partners')

@php
 $tiers = ['platinum' => 'Platinum', 'gold' => 'Gold', 'silver' => 'Silver'];
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.department', 'membership') }}">&larr; Public Site &amp; Membership</a></p>
<h1>Partners</h1>
<p class="sub page-lede">
 Post your own partners straight onto the public wall, and review every partnership request sent from the public Partners page.
 <a href="{{ route('partners') }}" target="_blank">View partner wall &#8599;</a>
</p>

<div class="card" style="margin-bottom:22px;">
 <h3>Post a partner</h3>
 <form method="POST" action="{{ route('admin.partners.store') }}" enctype="multipart/form-data" class="inline-form field-grid">
 @csrf
 <span class="fld"><label>Organisation name <span class="req">*</span></label><input type="text" name="name" maxlength="120" value="{{ old('name') }}" required></span>
 <span class="fld"><label>Caption (shown under the name on the wall)</label><input type="text" name="caption" maxlength="180" value="{{ old('caption') }}" placeholder="e.g. Funding the Kisumu tech hubs"></span>
 <span class="fld"><label>Logo (PNG / JPG / SVG / WebP) <span class="req">*</span></label><input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp" required></span>
 <button class="btn btn-primary" type="submit">Post on wall</button>
 </form>
 <div class="hint" style="margin-top:10px;">Posted partners are listed immediately. Max 2 MB.</div>
</div>

<h2>On the public wall <span class="tag">{{ $wall->count() }}</span></h2>
@forelse ($wall as $p)
 <div class="invite-row">
 <div class="wall-logo">
 @if ($p->logo_path)
 <img src="{{ asset('uploads/partners/'.$p->logo_path) }}" alt="">
 @else
 <span class="hint">no logo</span>
 @endif
 </div>
 <div class="invite-main">
 <b>{{ $p->name }}</b>
 @if ($p->source === 'request')<span class="tag gray">from a public request</span>@endif
 @if ($p->caption)<div class="hint">{{ $p->caption }}</div>@endif
 </div>
 <div class="invite-actions">
 <form method="POST" action="{{ route('admin.partners.update', $p->id) }}" enctype="multipart/form-data" class="inline-form mini-edit">
 @csrf @method('PATCH')
 <input type="text" name="caption" maxlength="180" value="{{ $p->caption }}" placeholder="Caption" aria-label="Caption shown under the name">
 <label class="chk">Logo <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp"></label>
 <button class="btn btn-sm btn-outline" type="submit">Save</button>
 </form>
 <form method="POST" action="{{ route('admin.partners.toggle', $p->id) }}">
 @csrf
 <button class="btn btn-sm btn-ghost" type="submit">Hide from wall</button>
 </form>
 <form method="POST" action="{{ route('admin.partners.delete', $p->id) }}" data-danger data-confirm="Remove {{ $p->name }} from the partner wall?">
 @csrf @method('DELETE')
 <button class="btn btn-sm btn-danger" type="submit">Remove</button>
 </form>
 </div>
 </div>
@empty
 <div class="card"><p class="page-lede">Nothing is on the wall yet — post a partner above or list one of the requests below.</p></div>
@endforelse

<h2 style="margin-top:30px;">Partnership requests <span class="tag gray">{{ $requests->count() }}</span></h2>
<p class="hint">Sent from the public Partners page. Listing one puts its logo and caption on the wall.</p>
@forelse ($requests as $p)
 <div class="invite-row">
 <div class="wall-logo">
 @if ($p->logo_path)
 <img src="{{ asset('uploads/partners/'.$p->logo_path) }}" alt="">
 @else
 <span class="hint">no logo</span>
 @endif
 </div>
 <div class="invite-main">
 <b>{{ $p->name }}</b>
 <span class="tag gray">{{ $tiers[$p->tier] ?? 'No tier' }}</span>
 <div class="hint"><a href="mailto:{{ $p->contact_email }}">{{ $p->contact_email }}</a>@if ($p->website) · <a href="{{ $p->website }}" target="_blank">website &#8599;</a>@endif</div>
 <div class="hint">Requested {{ $p->created_at->format('d M Y') }}</div>
 </div>
 <div class="invite-actions">
 <form method="POST" action="{{ route('admin.partners.toggle', $p->id) }}">
 @csrf
 <button class="btn btn-sm btn-lime" type="submit">List it</button>
 </form>
 <form method="POST" action="{{ route('admin.partners.delete', $p->id) }}" data-danger data-confirm="Discard the partnership request from {{ $p->name }}?">
 @csrf @method('DELETE')
 <button class="btn btn-sm btn-danger" type="submit">Discard</button>
 </form>
 </div>
 </div>
@empty
 <div class="card"><p class="page-lede">No pending partnership requests.</p></div>
@endforelse
@endsection
