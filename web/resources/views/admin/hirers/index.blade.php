@extends('layouts.admin')
@section('title', 'Hirers & organisations')

@section('content')
<p class="back"><a href="{{ route('admin.department', 'membership') }}">&larr; Public Site &amp; Membership</a></p>
<h1>Hirers &amp; organisations</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 18px;">
 Organisations bring projects to the programme, but they no longer post them themselves — record the deal here,
 then post the project against this organisation. <a href="{{ route('admin.projects.create') }}">Post a project →</a>
</p>

<datalist id="county-names">
 @foreach ($counties as $c)
 <option value="{{ $c }}"></option>
 @endforeach
</datalist>

<div class="card" style="margin-bottom:22px;">
 <h3>New hirer</h3>
 <form method="POST" action="{{ route('admin.hirers.store') }}" class="inline-form" style="align-items:flex-end;">
 @csrf
 <span class="fld"><label>Organisation <span class="req">*</span></label><input type="text" name="organisation" maxlength="120" value="{{ old('organisation') }}" required></span>
 <span class="fld"><label>Contact person <span class="req">*</span></label><input type="text" name="contact_name" maxlength="120" value="{{ old('contact_name') }}" required></span>
 <span class="fld"><label>Email (optional)</label><input type="email" name="email" maxlength="120" value="{{ old('email') }}"></span>
 <span class="fld"><label>Phone (optional)</label><input type="text" name="phone" maxlength="20" value="{{ old('phone') }}" placeholder="07XX XXX XXX"></span>
 <span class="fld"><label>County <span class="req">*</span></label><input type="text" name="county" list="county-names" maxlength="80" value="{{ old('county') }}" required placeholder="Type county"></span>
 <span class="fld"><label>Sub-county <span class="req">*</span></label><input type="text" name="sub_county" maxlength="100" value="{{ old('sub_county') }}" required placeholder="e.g. Nyando"></span>
 <span class="fld"><label>Ward <span class="req">*</span></label><input type="text" name="ward" maxlength="100" value="{{ old('ward') }}" required placeholder="e.g. Chemelil/Sango"></span>
 <span class="fld"><label>Type <span class="req">*</span></label>
 <select name="org_type" required>
 @foreach ($orgTypes as $k => $lbl)
 <option value="{{ $k }}" @selected(old('org_type') === $k)>{{ $lbl }}</option>
 @endforeach
 </select>
 </span>
 <button class="btn btn-primary" type="submit">Add hirer</button>
 </form>
 <div class="hint" style="margin-top:10px;">A Hirer ID (HIR-0001 style) is issued automatically.</div>
</div>

@forelse ($hirers as $h)
 <div class="invite-row {{ $h->status === 'active' ? '' : 'revoked' }}">
 <div class="invite-main">
 <b>{{ $h->organisation }}</b>
 <span class="tag gray">{{ $h->hirer_code ?? 'HIRER' }}</span>
 <span class="status {{ $h->status === 'active' ? 'active' : 'rejected' }}">{{ $h->status === 'active' ? 'Active' : 'Inactive' }}</span>
 <div class="hint">
 {{ $h->contact_name }}
 @if ($h->email) · <a href="mailto:{{ $h->email }}">{{ $h->email }}</a>@endif
 @if ($h->phone) · {{ $h->phone }}@endif
 @if (!$h->email && !$h->phone) · <em>no contact details recorded</em>@endif
 </div>
 <div class="hint">{{ $orgTypes[$h->org_type] ?? $h->org_type }} · {{ $h->locationLabel() }} · {{ $h->projects_count }} project(s)</div>
 </div>
 <div class="invite-actions">
 <form method="POST" action="{{ route('admin.hirers.update', $h) }}" class="inline-form mini-edit">
 @csrf @method('PATCH')
 <input type="text" name="organisation" maxlength="120" value="{{ $h->organisation }}" required placeholder="Organisation">
 <input type="text" name="contact_name" maxlength="120" value="{{ $h->contact_name }}" required placeholder="Contact person">
 <input type="email" name="email" maxlength="120" value="{{ $h->email }}" placeholder="Email (optional)">
 <input type="text" name="phone" maxlength="20" value="{{ $h->phone }}" placeholder="Phone (optional)">
 <input type="text" name="county" list="county-names" maxlength="80" value="{{ $h->county }}" required placeholder="County">
 <input type="text" name="sub_county" maxlength="100" value="{{ $h->sub_county }}" required placeholder="Sub-county">
 <input type="text" name="ward" maxlength="100" value="{{ $h->ward }}" required placeholder="Ward">
 <select name="org_type">
 @foreach ($orgTypes as $k => $lbl)
 <option value="{{ $k }}" @selected($h->org_type === $k)>{{ $lbl }}</option>
 @endforeach
 </select>
 <button class="btn btn-sm btn-outline" type="submit">Save</button>
 </form>
 <form method="POST" action="{{ route('admin.hirers.toggle', $h) }}">
 @csrf
 <button class="btn btn-sm btn-ghost" type="submit">{{ $h->status === 'active' ? 'Deactivate' : 'Reactivate' }}</button>
 </form>
 <form method="POST" action="{{ route('admin.hirers.delete', $h) }}" data-danger data-confirm="Remove {{ $h->organisation }} from the hirer list?">
 @csrf @method('DELETE')
 <button class="btn btn-sm btn-danger" type="submit">Remove</button>
 </form>
 </div>
 </div>
@empty
 <div class="card"><p style="margin:0;color:var(--muted);">No hirers recorded yet.</p></div>
@endforelse

<div class="pagination">{{ $hirers->links() }}</div>
@endsection
