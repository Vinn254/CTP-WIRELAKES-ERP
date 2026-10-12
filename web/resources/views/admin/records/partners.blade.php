@extends('layouts.admin')
@section('title', 'Partners')

@php
 $tiers = ['platinum' => 'Platinum', 'gold' => 'Gold', 'silver' => 'Silver'];
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.department', 'membership') }}">&larr; Public Site &amp; Membership</a></p>
<h1>Partners</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 18px;">
 Post your own partners straight onto the public wall, and review every partnership request sent from the public Partners page.
 <a href="{{ route('partners') }}" target="_blank">View partner wall &#8599;</a>
</p>

<div class="card" style="margin-bottom:22px;">
 <h3>Post a partner</h3>
 <form method="POST" action="{{ route('admin.partners.store') }}" enctype="multipart/form-data" class="inline-form" style="align-items:flex-end;">
 @csrf
 <span class="fld"><label>Organisation name</label><input type="text" name="name" maxlength="120" value="{{ old('name') }}" required></span>
 <span class="fld"><label>Tier</label>
 @include('admin.records.partials.tier', ['selected' => old('tier')])
 </span>
 <span class="fld grow"><label>Caption (shown under the name on the wall)</label><input type="text" name="caption" maxlength="180" value="{{ old('caption') }}" placeholder="e.g. Funding the Kisumu tech hubs"></span>
 <span class="fld"><label>Website</label><input type="url" name="website" maxlength="200" value="{{ old('website') }}" placeholder="https://&#8230;"></span>
 <span class="fld"><label>Logo (PNG / JPG / SVG / WebP)</label><input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp" required></span>
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
 <span class="tag {{ $p->tier === 'gold' || $p->tier === 'platinum' ? 'gold' : 'gray' }}">{{ $tiers[$p->tier] ?? 'Partner' }}</span>
 @if ($p->source === 'request')<span class="tag gray">from a public request</span>@endif
 @if ($p->caption)<div class="hint">{{ $p->caption }}</div>@endif
 @if ($p->website)<a href="{{ $p->website }}" target="_blank">{{ \Illuminate\Support\Str::limit(parse_url($p->website, PHP_URL_HOST) ?: $p->website, 40) }} &#8599;</a>@endif
 </div>
 <div class="invite-actions">
 <form method="POST" action="{{ route('admin.partners.update', $p->id) }}" enctype="multipart/form-data" class="inline-form mini-edit">
 @csrf @method('PATCH')
 @include('admin.records.partials.tier', ['selected' => $p->tier])
 <input type="text" name="caption" maxlength="180" value="{{ $p->caption }}" placeholder="Caption">
 <input type="url" name="website" maxlength="200" value="{{ $p->website }}" placeholder="https://&#8230;">
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
 <div class="card"><p style="margin:0;color:var(--muted);">Nothing is on the wall yet — post a partner above or list one of the requests below.</p></div>
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
 <div class="card"><p style="margin:0;color:var(--muted);">No pending partnership requests.</p></div>
@endforelse
@endsection
