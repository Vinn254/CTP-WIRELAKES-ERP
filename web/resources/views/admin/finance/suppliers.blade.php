@extends('layouts.admin')
@section('title', 'Suppliers')

@section('content')
@include('partials.errors')
@php use App\Models\Supplier; @endphp
<p class="back"><a href="{{ route('admin.department', 'finance') }}">&larr; Finance, Procurement &amp; Inventory</a></p>
<h1>Approved suppliers</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    Quotations only count when they come from a name on this list (§14.4 step 3), so a supplier is added with their
    contact and payment details and, when they stop supplying, taken <b>off the list rather than deleted</b> — the
    purchase orders they already delivered have to stay readable and payable.
</p>

<div class="stat-row">
    <div class="stat"><small>On the list</small><b>{{ $counts['all'] }}</b></div>
    <div class="stat"><small>Approved to quote</small><b>{{ $counts['approved'] }}</b></div>
    <div class="stat"><small>Off the list</small><b>{{ $counts['inactive'] }}</b></div>
</div>

<div class="tabs">
    @foreach ($filters as $key => $label)
        <a class="tab {{ $status === $key ? 'on' : '' }}"
           href="{{ route('admin.suppliers.index', ['f' => $key, 'q' => $search]) }}">
            {{ $label }}<span class="n">{{ $counts[$key] ?? 0 }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.suppliers.index') }}" class="filter-bar">
    <input type="hidden" name="f" value="{{ $status }}">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search name, code or contact">
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($search !== '')
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.suppliers.index', ['f' => $status]) }}">Clear</a>
    @endif
</form>

<table class="data">
    <thead>
        <tr>
            <th>Code</th><th>Supplier</th><th>Contact</th><th>County</th><th>Supplies</th>
            <th>Paid to</th><th>Quotes</th><th>Orders</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($suppliers as $s)
            <tr>
                <td><b>{{ $s->code }}</b></td>
                <td>{{ $s->name }}
                    @if ($s->note)<div class="hint">{{ $s->note }}</div>@endif
                </td>
                <td><span class="hint">{{ $s->contactLabel() }}</span></td>
                <td><span class="hint">{{ $s->county ?: '—' }}</span></td>
                <td><span class="tag">{{ $s->suppliesLabel() }}</span></td>
                <td><span class="hint">{{ $s->payLabel() }}</span></td>
                <td>{{ $s->quotations_count }}</td>
                <td>{{ $s->purchase_orders_count }}
                    <div class="hint">added by {{ $s->adder->exists ? $s->adder->name : 'the office' }}</div></td>
                <td><span class="status {{ $s->status === 'approved' ? 'active' : 'closed' }}">{{ $s->statusLabel() }}</span></td>
                <td style="text-align:right; white-space:nowrap;">
                    <details class="need-form">
                        <summary>Correct / change list status</summary>

                        <form method="POST" action="{{ route('admin.suppliers.update', $s) }}" class="fee-form"
                              data-confirm="Save these details for {{ $s->code }}?" data-title="Correct supplier">
                            @csrf @method('PATCH')
                            <div class="form-grid-2">
                                <div class="field">
                                    <label>Name <span class="req">*</span></label>
                                    <input type="text" name="name" required maxlength="190" value="{{ $s->name }}">
                                </div>
                                <div class="field">
                                    <label>What they supply</label>
                                    <select name="supplies">
                                        <option value="">Any goods</option>
                                        @foreach ($categories as $key => $label)
                                            <option value="{{ $key }}" @selected($s->supplies === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-grid-2" style="margin-top:10px;">
                                <div class="field">
                                    <label>Contact person</label>
                                    <input type="text" name="contact_name" maxlength="120" value="{{ $s->contact_name }}">
                                </div>
                                <div class="field">
                                    <label>Phone</label>
                                    <input type="text" name="phone" maxlength="40" value="{{ $s->phone }}">
                                </div>
                            </div>
                            <div class="form-grid-2" style="margin-top:10px;">
                                <div class="field">
                                    <label>Email</label>
                                    <input type="email" name="email" maxlength="190" value="{{ $s->email }}">
                                </div>
                                <div class="field">
                                    <label>County</label>
                                    <input type="text" name="county" maxlength="100" value="{{ $s->county }}">
                                </div>
                            </div>
                            <div class="form-grid-2" style="margin-top:10px;">
                                <div class="field">
                                    <label>M-Pesa paybill</label>
                                    <input type="text" name="mpesa_paybill" maxlength="60" value="{{ $s->mpesa_paybill }}">
                                </div>
                                <div class="field">
                                    <label>Bank account</label>
                                    <input type="text" name="bank_account" maxlength="120" value="{{ $s->bank_account }}">
                                </div>
                            </div>
                            <div class="field" style="margin-top:10px;">
                                <label>Note</label>
                                <input type="text" name="note" maxlength="1000" value="{{ $s->note }}">
                            </div>
                            <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Save details</button>
                        </form>

                        <form method="POST" action="{{ route('admin.suppliers.status', $s) }}" class="fee-form"
                              data-confirm="Change {{ $s->code }}'s place on the list?" data-title="List status">
                            @csrf
                            <div class="form-grid-2">
                                <div class="field">
                                    <label>On the approved list?</label>
                                    <select name="status">
                                        @foreach (Supplier::STATUSES as $key => $label)
                                            <option value="{{ $key }}" @selected($s->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Why</label>
                                    <input type="text" name="note" maxlength="1000" value="{{ $s->note }}"
                                           placeholder="e.g. Off the list pending a new warranty on their boards.">
                                </div>
                            </div>
                            <button class="btn btn-outline btn-block" type="submit" style="margin-top:12px;">
                                {{ $s->status === 'approved' ? 'Take off the list' : 'Put back on the list' }}
                            </button>
                        </form>
                    </details>
                </td>
            </tr>
        @empty
            <tr><td colspan="10" style="text-align:center;color:var(--muted);padding:22px;">
                No supplier on this filter. Add one below — a request cannot be quoted until there is a name here to
                quote it (§14.4 step 3).
            </td></tr>
        @endforelse
    </tbody>
</table>

<div class="card">
    <h3>Add a supplier to the approved list</h3>
    <p class="hint">A supplier is paid against paperwork, so they need a real name, a way to be reached and an
        account or paybill the Finance Officer can pay into.</p>
    <form method="POST" action="{{ route('admin.suppliers.store') }}" class="fee-form"
          data-confirm="Add this supplier to the approved list?" data-title="Add supplier">
        @csrf
        <div class="form-grid-2">
            <div class="field">
                <label>Supplier name <span class="req">*</span></label>
                <input type="text" name="name" required maxlength="190" placeholder="e.g. Lakeview Electrical Supplies Ltd">
            </div>
            <div class="field">
                <label>What they supply</label>
                <select name="supplies">
                    <option value="">Any goods</option>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Contact person</label>
                <input type="text" name="contact_name" maxlength="120">
            </div>
            <div class="field">
                <label>Phone</label>
                <input type="text" name="phone" maxlength="40" placeholder="07xx xxx xxx">
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" maxlength="190">
            </div>
            <div class="field">
                <label>County</label>
                <input type="text" name="county" maxlength="100">
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>M-Pesa paybill</label>
                <input type="text" name="mpesa_paybill" maxlength="60">
            </div>
            <div class="field">
                <label>Bank account</label>
                <input type="text" name="bank_account" maxlength="120">
            </div>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>Note</label>
            <input type="text" name="note" maxlength="1000" placeholder="Terms, lead time, who introduced them.">
        </div>
        <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Add to the approved list</button>
    </form>
</div>
@endsection
