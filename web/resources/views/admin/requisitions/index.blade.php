@extends('layouts.admin')
@section('title', 'Requisitions')

@php
    use App\Models\Requisition;
    use App\Support\Finance;
@endphp

@section('content')
@include('partials.errors')
<div class="head-row">
    <div>
        <h1>Requisitions</h1>
        <p class="page-lede">What each desk needs, approved by Procurement, processed by Finance.</p>
    </div>
    <div class="head-acts">
        <button class="btn btn-sm btn-lime" type="button" data-sheet="raise">+ Raise a requisition</button>
    </div>
</div>

<div class="stat-row">
    <div class="stat"><small>Waiting on Procurement</small><b>{{ $totals['waiting'] }}</b></div>
    <div class="stat"><small>Waiting on Finance</small><b>{{ $totals['withFinance'] }}</b></div>
    <div class="stat"><small>Estimated value in play</small><b>{{ Finance::ksh($totals['value']) }}</b></div>
    <div class="stat"><small>Disbursed so far</small><b>{{ Finance::ksh($totals['paid']) }}</b></div>
</div>

<form method="GET" action="{{ route('admin.requisitions.index') }}" class="filter-bar">
    <label class="field">
        <span>Status</span>
        <select name="f" onchange="this.form.submit()">
            @foreach ($filters as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>
            @endforeach
        </select>
    </label>
    @if ($seesAll)
        <label class="field">
            <span>Desk</span>
            <select name="desk" onchange="this.form.submit()">
                <option value="all">Every desk</option>
                @foreach ($desks as $slug => $name)
                    <option value="{{ $slug }}" @selected($desk === $slug)>{{ $name }}</option>
                @endforeach
            </select>
        </label>
    @endif
    <input type="search" name="q" value="{{ $search }}" placeholder="Search reference, item or what it is for"
           aria-label="Search reference, item or what it is for">
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($status !== 'all' || $search !== '' || $desk !== 'all')
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.requisitions.index') }}">Clear</a>
    @endif
</form>

<table class="data">
    <thead>
        <tr>
            <th>Requisition</th><th>What is wanted</th><th>Quantity</th><th>Quality asked for</th>
            <th>Estimated price</th><th>Desk</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($requisitions as $rq)
            <tr>
                <td><b>{{ $rq->reference }}</b>
                    @if ($rq->needed_by)<div class="hint">needed by {{ $rq->needed_by->format('d M Y') }}</div>@endif
                </td>
                <td>{{ $rq->item_name }}
                    <div class="hint">for: {{ $rq->purpose }}</div>
                </td>
                <td>{{ $rq->quantityLabel() }}</td>
                <td><span class="hint">{{ $rq->quality_note ?: '—' }}</span></td>
                <td>{{ $rq->priceLabel() }}</td>
                <td><span class="hint">{{ $rq->departmentLabel() }}</span>
                    <div class="hint">{{ $rq->raiser->exists ? $rq->raiser->name : 'Raised in the office' }}</div>
                </td>
                <td>
                    <span class="status {{ $rq->isClosed() ? ($rq->status === 'rejected' ? 'closed' : 'active') : 'pending_review' }}">
                        {{ $rq->shortLabel() }}
                    </span>
                </td>
                <td class="actions">
                    <a class="btn btn-sm btn-outline" href="{{ route('admin.requisitions.show', $rq) }}">Open</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty-cell">
                Nothing on this filter. Any desk raises one with the button above, and it lands here on the same register.
            </td></tr>
        @endforelse
    </tbody>
</table>

{{ $requisitions->links() }}

@include('partials.sheet-start', ['name' => 'raise', 'title' => 'Raise a requisition',
    'sub' => 'Say what is wanted, how much, the quality it must meet and an estimated price. Procurement decides, Finance pays out or hands over.'])
    <form method="POST" action="{{ route('admin.requisitions.store') }}" class="fee-form"
          data-confirm="Raise this requisition and send it to Procurement?" data-title="Raise requisition">
        @csrf
        <input type="hidden" name="_sheet" value="raise">
        <div class="field">
            <label>Which desk is asking <span class="req">*</span></label>
            <select name="department" required>
                @foreach ($myDepts as $slug => $name)
                    <option value="{{ $slug }}" @selected(old('department') === $slug)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>What is wanted <span class="req">*</span></label>
            <input type="text" name="item_name" required maxlength="190" value="{{ old('item_name') }}"
                   placeholder="e.g. A4 reams, marker pens, a laptop charger, safety boots">
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Quantity <span class="req">*</span></label>
                <input type="number" name="quantity" required min="0.01" step="0.01" value="{{ old('quantity') }}">
            </div>
            <div class="field">
                <label>Unit <span class="req">*</span></label>
                <select name="unit" required>
                    @foreach ($units as $key => $label)
                        <option value="{{ $key }}" @selected(old('unit') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>Quality or standard wanted</label>
            <input type="text" name="quality_note" maxlength="1000" value="{{ old('quality_note') }}"
                   placeholder="e.g. 80 gsm, brand new, size 44 steel-toe.">
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Estimated price (KSH)</label>
                <input type="number" name="estimated_price_ksh" min="0" step="0.01" value="{{ old('estimated_price_ksh') }}"
                       placeholder="An honest estimate.">
            </div>
            <div class="field">
                <label>Charge to budget</label>
                <select name="budget_category">
                    <option value="">Finance will place it</option>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected(old('budget_category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Needed by</label>
                <input type="date" name="needed_by" value="{{ old('needed_by') }}">
            </div>
            <div class="field">
                <label>For which project</label>
                <select name="project_id">
                    <option value="">General desk need</option>
                    @foreach ($projects as $id => $title)
                        <option value="{{ $id }}" @selected((int) old('project_id') === $id)>{{ $title }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>What it is for <span class="req">*</span></label>
            <textarea name="purpose" required rows="3" maxlength="1000"
                      placeholder="e.g. The training desk has 24 trainees starting Monday and no way to print the term's worksheets."></textarea>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-sm btn-lime" type="submit">Raise the requisition</button>
        </div>
    </form>
@include('partials.sheet-end')
@endsection
