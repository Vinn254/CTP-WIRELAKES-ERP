@extends('layouts.admin')
@section('title', 'Staff & roles')

@php
    $accountLabels = ['admin' => 'Admin', 'superadmin' => 'Super admin'];
    $deptLabels = collect($liveDepartments)->mapWithKeys(fn ($d) => [$d['slug'] => $d['name']]);
    $heldRoles = fn ($slugs) => \App\Support\Roles::namesFor(is_array($slugs) ? $slugs : []);
@endphp

@section('content')
<h1>Staff &amp; roles</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 20px;">
    @if ($isSuper)
        Invite colleagues, hand them one or more roles, and deactivate staff who leave. A person sees only the
        modules their roles open — nothing else on this list.
    @else
        You are signed in as an <b>Admin</b> — only a super admin can invite staff or change roles.
    @endif
</p>

<div class="card" style="margin-bottom:24px;">
    <h3>Invite a staff member</h3>
    @unless ($isSuper)
        <p class="hint">Ask a super admin to send invitations.</p>
    @else
        <form method="POST" action="{{ route('admin.staff.invite') }}" class="invite-form">
            @csrf
            <div class="inline-form field-grid">
                <span class="fld"><label>Full name <span class="req">*</span></label><input type="text" name="name" maxlength="120" value="{{ old('name') }}" required></span>
                <span class="fld"><label>Email <span class="req">*</span></label><input type="email" name="email" maxlength="160" value="{{ old('email') }}" required></span>
                <span class="fld"><label>Account type</label>
                    <select name="role">
                        @foreach (\App\Models\StaffInvite::ROLES as $k => $lbl)
                            <option value="{{ $k }}" @selected(old('role') === $k)>{{ $lbl }}</option>
                        @endforeach
                    </select>
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

            <details class="dept-pick">
                <summary>Departments this opens</summary>
                <div class="dept-box">
                    @foreach ($liveDepartments as $d)
                        <label class="chk"><input type="checkbox" name="departments[]" value="{{ $d['slug'] }}"
                            @checked(in_array($d['slug'], \App\Support\Roles::departmentsFor((array) old('roles', [])), true))>
                            {{ $d['tag'] }} · {{ $deptLabels[$d['slug']] }}</label>
                    @endforeach
                    <div class="hint">Ticking a role fills this in. Add a department here to open one extra module
                        without giving the whole role; a super admin opens every one regardless.</div>
                </div>
            </details>

            <button class="btn btn-primary" type="submit">Create invitation</button>
        </form>
        <div class="hint" style="margin-top:10px;">
            The invitee opens the activation link (valid 14 days) and sets their own password. It is emailed to them
            when the invitation is created; use <b>Copy link</b> below to send it another way.
        </div>
    @endunless
</div>

<h2 class="sec-title">Staff accounts</h2>
<table class="data">
    <thead>
        <tr><th>Name</th><th>Email</th><th>Account</th><th>Roles</th><th>Modules opened</th><th>Status</th><th>Joined</th>@if ($isSuper)<th></th>@endif</tr>
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
                @if ($isSuper)
                    <td class="actions">
                        @if ($u->id === auth()->id())
                            <span class="hint">your account</span>
                        @else
                            <form method="POST" action="{{ route('admin.staff.users.update', $u->id) }}" class="inline-form mini-edit">
                                @csrf
                                <select name="role" aria-label="Account type">
                                    @foreach ($accountLabels as $k => $lbl)
                                        <option value="{{ $k }}" @selected($u->role === $k)>{{ $lbl }}</option>
                                    @endforeach
                                </select>
                                <label class="chk"><input type="checkbox" name="active" value="1" @checked($u->active)> Active</label>
                                <details class="dept-pick">
                                    <summary>Roles &amp; departments</summary>
                                    <div class="dept-box">
                                        @foreach ($roles as $slug => $r)
                                            <label class="chk"><input type="checkbox" name="roles[]" value="{{ $slug }}" data-depts="{{ implode(' ', $r['departments']) }}"
                                                @checked(in_array($slug, $u->roleSlugs(), true))> {{ $r['name'] }}</label>
                                        @endforeach
                                        <hr>
                                        @foreach ($liveDepartments as $d)
                                            <label class="chk"><input type="checkbox" name="departments[]" value="{{ $d['slug'] }}"
                                                @checked($u->role !== 'superadmin' && in_array($d['slug'], $u->departmentSlugs(), true))>
                                                {{ $d['tag'] }}</label>
                                        @endforeach
                                        <div class="hint">A role opens all of its modules. To drop one module a role
                                            grants, untick the role instead.</div>
                                    </div>
                                </details>
                                <button class="btn btn-sm btn-outline" type="submit">Save</button>
                            </form>
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>

<h2 class="sec-title">Invitations</h2>
@forelse ($invites as $inv)
    <div class="invite-row {{ $inv->status }}">
        <div>
            <b>{{ $inv->name }}</b> &lt;{{ $inv->email }}&gt;
            <span class="tag gray">{{ $accountLabels[$inv->role] ?? $inv->role }}</span>
            @if ($inv->roleSlugs())<span class="tag">{{ $inv->roleLabels() }}</span>@endif
            <span class="status {{ $inv->status === 'pending' ? 'pending_review' : ($inv->status === 'accepted' ? 'active' : 'rejected') }}">{{ ucfirst($inv->status) }}</span>
            <div class="hint">Sent {{ $inv->created_at->format('d M Y') }}@if ($inv->expires_at) · expires {{ $inv->expires_at->format('d M Y') }}@endif</div>
        </div>
        <div class="invite-actions">
            @if ($inv->status === 'pending' && $isSuper)
                <a class="btn btn-sm btn-lime" href="{{ route('invite.show', $inv->token) }}" target="_blank">Open activation link</a>
                <button class="btn btn-sm btn-outline" type="button"
                        onclick="copyInvite('{{ route('invite.show', $inv->token) }}', this)">Copy link</button>
                <form method="POST" action="{{ route('admin.staff.invite.revoke', $inv->id) }}"
                      data-danger data-confirm="Revoke the invitation for {{ $inv->email }}? The link will stop working.">
                    @csrf
                    <button class="btn btn-sm btn-danger" type="submit">Revoke</button>
                </form>
            @endif
        </div>
    </div>
@empty
    <div class="card"><p class="page-lede">No invitations sent yet.</p></div>
@endforelse

<script>
function copyInvite(url, btn) {
    navigator.clipboard.writeText(url).then(function () {
        btn.textContent = 'Copied';
        setTimeout(function () { btn.textContent = 'Copy link'; }, 1800);
    });
}

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
