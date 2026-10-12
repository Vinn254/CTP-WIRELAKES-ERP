@extends('layouts.admin')
@section('title', 'Staff & roles')

@php
    $roleLabels = ['admin' => 'Admin', 'superadmin' => 'Super admin'];
@endphp

@section('content')
<a class="back" href="{{ route('admin.dashboard') }}">Back to Departments</a>
<h1>Staff &amp; roles</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 20px;">
    @if ($isSuper)
        Invite colleagues, assign their role and deactivate staff who leave. Only a super admin can change roles.
    @else
        You are signed in as an <b>Admin</b> — only a super admin can invite staff or change roles.
    @endif
</p>

<div class="card" style="margin-bottom:24px;">
    <h3>Invite a staff member</h3>
    @unless ($isSuper)
        <p class="hint">Ask a super admin to send invitations.</p>
    @else
        <form method="POST" action="{{ route('admin.staff.invite') }}" class="inline-form" style="align-items:flex-end;">
            @csrf
            <span class="fld"><label>Full name</label><input type="text" name="name" maxlength="120" value="{{ old('name') }}" required></span>
            <span class="fld"><label>Email</label><input type="email" name="email" maxlength="160" value="{{ old('email') }}" required></span>
            <span class="fld"><label>Role</label>
                <select name="role">
                    @foreach (\App\Models\StaffInvite::ROLES as $k => $lbl)
                        <option value="{{ $k }}" @selected(old('role') === $k)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </span>
            <button class="btn btn-primary" type="submit">Create invitation</button>
        </form>
        <div class="hint" style="margin-top:10px;">
            The invitee opens the activation link (valid 14 days) and sets their own password. Copy the link below and send it by email or WhatsApp.
        </div>
    @endunless
</div>

<h2 class="sec-title">Staff accounts</h2>
<table class="data">
    <thead>
        <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th>@if ($isSuper)<th></th>@endif</tr>
    </thead>
    <tbody>
        @foreach ($users as $u)
            <tr>
                <td><b>{{ $u->name }}</b>@if ($u->id === auth()->id()) <span class="tag">you</span>@endif</td>
                <td>{{ $u->email }}</td>
                <td><span class="status {{ $u->role === 'superadmin' ? 'active' : 'pending_review' }}">{{ $roleLabels[$u->role] ?? $u->role }}</span></td>
                <td><span class="status {{ $u->active ? 'active' : 'rejected' }}">{{ $u->active ? 'Active' : 'Deactivated' }}</span></td>
                <td>{{ $u->created_at->format('d M Y') }}</td>
                @if ($isSuper)
                    <td style="text-align:right;">
                        @if ($u->id === auth()->id())
                            <span class="hint">your account</span>
                        @else
                            <form method="POST" action="{{ route('admin.staff.users.update', $u->id) }}" class="inline-form mini-edit">
                                @csrf
                                <select name="role">
                                    @foreach ($roleLabels as $k => $lbl)
                                        <option value="{{ $k }}" @selected($u->role === $k)>{{ $lbl }}</option>
                                    @endforeach
                                </select>
                                <label class="chk"><input type="checkbox" name="active" value="1" @checked($u->active)> Active</label>
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
            <span class="tag gray">{{ $roleLabels[$inv->role] ?? $inv->role }}</span>
            <span class="status {{ $inv->status === 'pending' ? 'pending_review' : ($inv->status === 'accepted' ? 'active' : 'rejected') }}">{{ ucfirst($inv->status) }}</span>
            <div class="hint">Sent {{ $inv->created_at->format('d M Y') }}@if ($inv->expires_at) · expires {{ $inv->expires_at->format('d M Y') }}@endif</div>
            @if ($inv->status === 'pending')
                <div class="invite-link">{{ request()->getSchemeAndHttpHost().route('invite.show', $inv->token) }}</div>
            @endif
        </div>
        <div class="invite-actions">
            @if ($inv->status === 'pending' && $isSuper)
                <a class="btn btn-sm btn-lime" href="{{ route('invite.show', $inv->token) }}" target="_blank">Open activation link</a>
                <button class="btn btn-sm btn-outline" type="button"
                        onclick="copyInvite('{{ request()->getSchemeAndHttpHost().route('invite.show', $inv->token) }}', this)">Copy link</button>
                <form method="POST" action="{{ route('admin.staff.invite.revoke', $inv->id) }}"
                      data-danger data-confirm="Revoke the invitation for {{ $inv->email }}? The link will stop working.">
                    @csrf
                    <button class="btn btn-sm btn-danger" type="submit">Revoke</button>
                </form>
            @endif
        </div>
    </div>
@empty
    <div class="card"><p style="margin:0;color:var(--muted);">No invitations sent yet.</p></div>
@endforelse

<script>
function copyInvite(url, btn) {
    navigator.clipboard.writeText(url).then(function () {
        btn.textContent = 'Copied';
        setTimeout(function () { btn.textContent = 'Copy link'; }, 1800);
    });
}
</script>
@endsection
