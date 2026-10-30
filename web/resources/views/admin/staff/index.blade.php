@extends('layouts.admin')
@section('title', 'Staff & roles')

@php
    $accountLabels = ['admin' => 'Admin', 'superadmin' => 'Super admin'];
    $deptLabels = collect($liveDepartments)->mapWithKeys(fn ($d) => [$d['slug'] => $d['name']]);
    $heldRoles = fn ($slugs) => \App\Support\Roles::namesFor(is_array($slugs) ? $slugs : []);
    $manageRow = fn ($u) => $canTicks && $u->id !== auth()->id() && $u->role !== 'superadmin';
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>Staff &amp; roles</h1>
        <p class="ah-meta"><span>{{ $users->count() }} accounts</span><span>{{ $invites->where('status', 'pending')->count() }} invitations waiting</span></p>
    </div>
    @if ($canManage)
        <div class="head-actions">
            <button class="btn btn-sm btn-lime" type="button" data-sheet="invite">Invite a staff member</button>
        </div>
    @endif
</div>

<p class="page-lede">@if ($canManage)
    Every account made here is an <b>Admin</b> that opens only the modules its roles grant.
@else
    You are signed in without the Human Resources desk, so this list is read-only for you.
@endif</p>

@include('partials.errors')

@if ($canManage)
    @include('partials.sheet-start', ['name' => 'invite', 'title' => 'Invite a staff member',
        'sub' => 'They set their own password on the activation link, which is valid 14 days.'])
        <form method="POST" action="{{ route('admin.staff.invite') }}" class="invite-form">
            @csrf
            <input type="hidden" name="_sheet" value="invite">
            <div class="inline-form field-grid">
                <span class="fld"><label>Full name <span class="req">*</span></label><input type="text" name="name" maxlength="120" value="{{ old('name') }}" required></span>
                <span class="fld"><label>Email <span class="req">*</span></label><input type="email" name="email" maxlength="160" value="{{ old('email') }}" required></span>
                <span class="fld"><label>Account</label>
                    <input type="text" value="Admin" readonly
                        title="Accounts made here are always Admin. Only the super admin account already held opens every module.">
                </span>
            </div>

            <div class="fld">
                <label>Roles <span class="hint">— tick every one this person holds</span></label>
                <div class="role-grid">
                    @foreach ($roles as $slug => $r)
                        <label class="role-pick">
                            <input type="checkbox" name="roles[]" value="{{ $slug }}" data-depts="{{ implode(' ', $r['departments']) }}"
                                   @checked(in_array($slug, (array) old('roles', []), true))>
                            <b>{{ $r['name'] }}</b>
                            <small>{{ $r['desc'] }}</small>
                        </label>
                    @endforeach
                </div>
            </div>

            @include('admin.staff.partials.field', ['selected' => old('trainer_training_id'),
                'open' => in_array('trainer', (array) old('roles', []), true)])

            <details class="dept-pick">
                <summary>Departments this opens</summary>
                <div class="dept-box">
                    @foreach ($liveDepartments as $d)
                        <label class="chk"><input type="checkbox" name="departments[]" value="{{ $d['slug'] }}"
                            @checked(in_array($d['slug'], \App\Support\Roles::departmentsFor((array) old('roles', [])), true))>
                            {{ $deptLabels[$d['slug']] }}</label>
                    @endforeach
                    <div class="hint">Ticking a role fills this in. Add a department here to open one extra module
                        without giving the whole role; a super admin opens every one regardless.</div>
                </div>
            </details>

            <div class="sheet-actions">
                <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                <button class="btn btn-sm btn-lime" type="submit">Create invitation</button>
            </div>
        </form>
    @include('partials.sheet-end')
@endif

<h2 class="sec-title">Staff accounts</h2>
<table class="data">
    <thead>
        <tr><th>Name</th><th>Email</th><th>Account</th><th>Roles</th><th>Modules opened</th><th>Status</th><th>Joined</th>@if ($canTicks)<th></th>@endif</tr>
    </thead>
    <tbody>
        @foreach ($users as $u)
            <tr>
                <td><b>{{ $u->name }}</b>@if ($u->id === auth()->id()) <span class="tag">you</span>@endif</td>
                <td>{{ $u->email }}</td>
                <td><span class="status {{ $u->role === 'superadmin' ? 'active' : 'pending_review' }}">{{ $accountLabels[$u->role] ?? $u->role }}</span></td>
                <td>
                    @if ($u->role === 'superadmin')
                        <span class="tag">Every module</span>
                    @elseif (! $heldRoles($u->roleSlugs()))
                        <span class="tag gray">No role</span>
                    @else
                        @foreach ($heldRoles($u->roleSlugs()) as $label)
                            <span class="tag">{{ $label }}</span>
                        @endforeach
                        @if (in_array('trainer', $u->roleSlugs(), true))
                            <span class="tag gray">{{ $u->trainerFieldLabel() ?: 'No field yet' }}</span>
                        @endif
                    @endif
                </td>
                <td>
                    @if ($u->role !== 'superadmin')
                        <div class="hint">{{ count($u->departmentSlugs()) }} of {{ count($liveDepartments) }} —
                            {{ $deptLabels->only($u->departmentSlugs())->values()->implode(', ') ?: 'nothing yet' }}</div>
                    @endif
                </td>
                <td><span class="status {{ $u->active ? 'active' : 'rejected' }}">{{ $u->active ? 'Active' : 'Deactivated' }}</span></td>
                <td>{{ $u->created_at->format('d M Y') }}</td>
                @if ($canTicks)
                    <td class="actions">
                        @unless ($manageRow($u))
                            <span class="hint">{{ $u->id === auth()->id() ? 'your account' : 'not edited here' }}</span>
                        @else
                            <button class="btn btn-sm btn-outline" type="button" data-sheet="edit-{{ $u->id }}">Edit</button>
                            <form method="POST" action="{{ route('admin.staff.users.active', $u->id) }}"
                                  data-confirm="{{ $u->active ? 'Deactivate '.$u->name.'? Their sign-in closes at once; what they hold is kept.' : 'Let '.$u->name.' sign in again? The roles they held come back as they were.' }}"
                                  data-title="{{ $u->active ? 'Deactivate account' : 'Reinstate account' }}">
                                @csrf
                                <button class="btn btn-sm {{ $u->active ? 'btn-danger' : 'btn-lime' }}" type="submit">
                                    {{ $u->active ? 'Deactivate' : 'Reinstate' }}
                                </button>
                            </form>
                        @endunless
                    </td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>

@if ($canTicks)
    @foreach ($users as $u)
        @if ($manageRow($u))
            @include('partials.sheet-start', ['name' => 'edit-'.$u->id,
                'title' => 'Edit access — '.$u->name,
                'sub' => 'The job titles they hold and the modules those open. Their sign-in is switched on their row.'])
            <form method="POST" action="{{ route('admin.staff.users.update', $u->id) }}" class="fee-form"
                  data-confirm="Save the access on {{ $u->name }}?" data-title="Save access">
                @csrf
                <input type="hidden" name="_sheet" value="edit-{{ $u->id }}">

                <div class="field full">
                    <label>Roles <span class="hint">— tick every one this person holds</span></label>
                    <div class="role-grid">
                        @foreach ($roles as $slug => $r)
                            <label class="role-pick">
                                <input type="checkbox" name="roles[]" value="{{ $slug }}"
                                       data-depts="{{ implode(' ', $r['departments']) }}"
                                       @checked(in_array($slug, $u->roleSlugs(), true))>
                                <b>{{ $r['name'] }}</b>
                                <small>{{ $r['desc'] }}</small>
                            </label>
                        @endforeach
                    </div>
                </div>

                @include('admin.staff.partials.field', ['selected' => $u->trainer_training_id,
                    'open' => in_array('trainer', $u->roleSlugs(), true)])

                <details class="dept-pick" @if ($errors->any() && old('_sheet') === 'edit-'.$u->id) open @endif>
                    <summary>Departments this opens</summary>
                    <div class="dept-box">
                        @foreach ($liveDepartments as $d)
                            <label class="chk"><input type="checkbox" name="departments[]" value="{{ $d['slug'] }}"
                                @checked(in_array($d['slug'], $u->departmentSlugs(), true))> {{ $d['name'] }}</label>
                        @endforeach
                        <div class="hint">A role opens all of its modules. To drop one module a role grants,
                            untick the role instead.</div>
                    </div>
                </details>

                <div class="hint">This account is <b>{{ $u->active ? 'live' : 'deactivated' }}</b> — Deactivate and
                    Reinstate on their row close or open the sign-in without touching anything set here.</div>

                <div class="sheet-actions">
                    <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                    <button class="btn btn-sm btn-lime" type="submit">Save access</button>
                </div>
            </form>
            @include('partials.sheet-end')
        @endif
    @endforeach
@endif

<h2 class="sec-title">Invitations <span class="tag">{{ $invites->count() }}</span></h2>
@if ($invites->isEmpty())
    <div class="card"><p class="page-lede">No invitations sent yet.</p></div>
@else
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr><th>Who</th><th>Account</th><th>Roles</th><th>Status</th><th>Sent</th><th>Expires</th>@if ($canManage)<th></th>@endif</tr>
            </thead>
            <tbody>
                @foreach ($invites as $inv)
                    <tr>
                        <td><b>{{ $inv->name }}</b><small>{{ $inv->email }}</small></td>
                        <td><span class="status {{ $inv->role === 'superadmin' ? 'active' : 'pending_review' }}">{{ $accountLabels[$inv->role] ?? $inv->role }}</span></td>
                        <td>@if ($inv->roleSlugs())<span class="tag">{{ $inv->roleLabels() }}</span>@else<span class="tag gray">No role</span>@endif</td>
                        <td><span class="status {{ $inv->status === 'pending' ? 'pending_review' : ($inv->status === 'accepted' ? 'active' : 'rejected') }}">{{ ucfirst($inv->status) }}</span></td>
                        <td class="nowrap">{{ $inv->created_at->format('d M Y') }}</td>
                        <td class="nowrap">{{ $inv->expires_at?->format('d M Y') ?: '—' }}</td>
                        @if ($canManage)
                            <td class="actions">
                                @if ($inv->status === 'pending')
                                    <a class="btn btn-sm btn-lime" href="{{ route('invite.show', $inv->token) }}" target="_blank">Open link</a>
                                    <button class="btn btn-sm btn-outline" type="button"
                                            onclick="copyInvite('{{ route('invite.show', $inv->token) }}', this)">Copy link</button>
                                    <form method="POST" action="{{ route('admin.staff.invite.revoke', $inv->id) }}"
                                          data-danger data-confirm="Revoke the invitation for {{ $inv->email }}? The link will stop working.">
                                        @csrf
                                        <button class="btn btn-sm btn-danger" type="submit">Revoke</button>
                                    </form>
                                @else
                                    <span class="hint">{{ $inv->status === 'accepted' ? 'They set their password already' : 'Link withdrawn' }}</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<script>
function copyInvite(url, btn) {
    navigator.clipboard.writeText(url).then(function () {
        btn.textContent = 'Copied';
        setTimeout(function () { btn.textContent = 'Copy link'; }, 1800);
    });
}

// The Trainer title is a field of training as well as a desk, so the pick only appears when that box is ticked.
document.querySelectorAll('input[name="roles[]"][value="trainer"]').forEach(function (box) {
    box.addEventListener('change', function () {
        const pick = box.form.querySelector('.trainer-field');
        if (pick) {
            pick.hidden = !box.checked;
            pick.querySelector('select').required = box.checked;
        }
    });
});

// Ticking a role shows which modules it will open. The server works the same list out on its own; this only
// keeps the boxes on screen honest while the form is being filled in.
document.querySelectorAll('input[name="roles[]"]').forEach(function (box) {
    box.addEventListener('change', function () {
        const form = box.form;
        const granted = {};
        form.querySelectorAll('input[name="roles[]"]:checked').forEach(function (on) {
            on.dataset.depts.split(' ').forEach(function (d) { granted[d] = true; });
        });
        form.querySelectorAll('input[name="departments[]"]').forEach(function (dept) {
            if (granted[dept.value] || dept.dataset.manual === '1') { dept.checked = !!granted[dept.value]; }
        });
    });
});
</script>
@endsection
