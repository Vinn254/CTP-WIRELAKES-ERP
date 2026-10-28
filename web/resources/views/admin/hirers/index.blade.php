@extends('layouts.admin')
@section('title', 'Hirers & organisations')

@section('content')
<div class="head-row">
 <div>
 <h1>Hirers &amp; organisations</h1>
 <p class="page-lede">Organisations that bring projects to the programme.</p>
 </div>
 <div class="head-actions">
 <button class="btn btn-sm btn-primary" type="button" data-sheet="add-hirer">+ Add a hirer</button>
 </div>
</div>

<datalist id="county-names">
 @foreach ($counties as $c)
 <option value="{{ $c }}"></option>
 @endforeach
</datalist>

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
 <form method="POST" action="{{ route('admin.hirers.update', $h) }}" class="inline-form mini-edit field-grid">
 @csrf @method('PATCH')
 <span class="fld"><label>Organisation <span class="req">*</span></label><input type="text" name="organisation" maxlength="120" value="{{ $h->organisation }}" required></span>
 <span class="fld"><label>Contact person <span class="req">*</span></label><input type="text" name="contact_name" maxlength="120" value="{{ $h->contact_name }}" required></span>
 <span class="fld"><label>Email (optional)</label><input type="email" name="email" maxlength="120" value="{{ $h->email }}"></span>
 <span class="fld"><label>Phone (optional)</label><input type="text" name="phone" maxlength="20" value="{{ $h->phone }}" placeholder="07XX XXX XXX"></span>
 <span class="fld"><label>County <span class="req">*</span></label><input type="text" name="county" list="county-names" maxlength="80" value="{{ $h->county }}" required></span>
 <span class="fld"><label>Sub-county <span class="req">*</span></label><input type="text" name="sub_county" maxlength="100" value="{{ $h->sub_county }}" required></span>
 <span class="fld"><label>Ward <span class="req">*</span></label><input type="text" name="ward" maxlength="100" value="{{ $h->ward }}" required></span>
 <span class="fld"><label>Type <span class="req">*</span></label>
 <select name="org_type">
 @foreach ($orgTypes as $k => $lbl)
 <option value="{{ $k }}" @selected($h->org_type === $k)>{{ $lbl }}</option>
 @endforeach
 </select>
 </span>
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
 <div class="card"><p class="page-lede">No hirers recorded yet.</p></div>
@endforelse

<div class="pagination">{{ $hirers->links() }}</div>

@include('partials.sheet-start', ['name' => 'add-hirer', 'title' => 'New hirer',
    'sub' => 'Organisations that bring projects to the programme.'])
 <form method="POST" action="{{ route('admin.hirers.store') }}" class="inline-form field-grid">
 @csrf
 <input type="hidden" name="_sheet" value="add-hirer">
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
 <div class="sheet-actions">
 <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
 <button class="btn btn-primary" type="submit">Add hirer</button>
 </div>
 </form>
 <div class="hint" style="margin-top:10px;">A Hirer ID (HIR-0001 style) is issued automatically.</div>
@include('partials.sheet-end')
@endsection
