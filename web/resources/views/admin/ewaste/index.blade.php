@extends('layouts.admin')
@section('title', 'E-Waste register')

@section('content')
<p class="back"><a href="{{ route('admin.department', 'ewaste') }}">&larr; E-Waste Management</a></p>
<h1>E-Waste register</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    Every line the field collected, from the first photo to the weight on the scale. A CTP or an E-Fundi registers a
    collection, an E-Fundi carries it to a collection point and forwards it, and <b>weighing it in is done here</b> —
    that is what turns an estimate into programme stock. The route each item takes (repair, refurbish, recycle,
    certified disposal) is recorded on the same line; nothing is deleted or registered twice.
</p>

<div class="stat-row">
    <div class="stat"><small>Lines on the register</small><b>{{ $counts['all'] }}</b></div>
    <div class="stat"><small>In the field</small><b>{{ $counts['submitted'] }}</b></div>
    <div class="stat"><small>Forwarded, not weighed</small><b>{{ $counts['forwarded'] }}</b></div>
    <div class="stat"><small>Weighed in</small><b>{{ $counts['received'] }}</b></div>
    <div class="stat"><small>Items collected</small><b>{{ number_format($totals['items']) }}</b></div>
    <div class="stat"><small>Weighed</small><b>{{ $totals['weighed'] }} kg</b></div>
    <div class="stat"><small>Still estimated</small><b>{{ $totals['estimated'] }} kg</b></div>
    <div class="stat"><small>Hazardous</small><b>{{ $totals['hazardous'] }}</b></div>
</div>

<div class="tabs">
    @foreach ($filters as $key => $label)
        <a class="tab {{ $status === $key ? 'on' : '' }}"
           href="{{ route('admin.ewaste.index', ['f' => $key, 'item' => $item, 'county' => $county, 'q' => $search]) }}">
            {{ $label }}<span class="n">{{ $key === 'all' ? $counts['all'] : $counts[$key] }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.ewaste.index') }}" class="filter-bar">
    <input type="hidden" name="f" value="{{ $status }}">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search collection ID, note or who collected it">
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($item !== 'all' || $county !== 'all' || $search !== '')
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.ewaste.index', ['f' => $status]) }}">Clear</a>
    @endif

    <div class="form-grid-2" style="width:100%; gap:12px 18px;">
        <div>
            <label for="item">Item type</label>
            <select name="item" id="item">
                <option value="all">Every item</option>
                @foreach ($itemTypes as $key => $label)
                    <option value="{{ $key }}" @selected($item === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="county">County</label>
            <select name="county" id="county">
                <option value="all">Any county</option>
                @foreach ($counties as $name)
                    <option value="{{ $name }}" @selected($county === $name)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</form>

<table class="data">
    <thead>
        <tr>
            <th>Collection ID</th><th>Date</th><th>Item</th><th>Qty</th><th>Condition</th><th>Place</th>
            <th>Registered by</th><th>Carried by</th><th>Weight</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($collections as $col)
            <tr>
                <td><b>{{ $col->reference }}</b>
                    @if ($col->photo_path)
                        <a class="photo-thumb" href="{{ route('admin.ewaste.photo', $col) }}" data-zoom
                            data-zoom-label="{{ $col->reference }} — {{ $col->quantity }} × {{ $col->itemTypeLabel() }}, {{ $col->conditionLabel() }}"
                            aria-label="Zoom the photo on {{ $col->reference }}"><img src="{{ route('admin.ewaste.photo', $col) }}" alt="{{ $col->reference }} photo"></a>
                    @endif
                </td>
                <td>{{ $col->collected_on->format('d M Y') }}</td>
                <td>{{ $col->itemTypeLabel() }}</td>
                <td>{{ $col->quantity }}</td>
                <td><span class="tag {{ $col->condition === 'hazardous' ? 'gold' : 'gray' }}">{{ $col->conditionLabel() }}</span></td>
                <td class="hint">{{ $col->placeLabel() }}</td>
                <td><span class="hint">{{ $col->collectorLabel() }}</span></td>
                <td><span class="hint">{{ $col->forwarder?->full_name ?? '—' }}
                    @if ($col->forwarded_on)<div>{{ $col->forwarded_on->format('d M Y') }}</div>@endif</span></td>
                <td>{{ $col->weighed_weight_kg !== null ? $col->weighed_weight_kg.' kg' : ($col->estimated_weight_kg !== null ? '~'.$col->estimated_weight_kg.' kg' : '—') }}</td>
                <td><span class="status {{ $col->status === 'received' ? 'active' : 'pending_review' }}">{{ $col->statusLabel() }}</span>
                    @if ($col->receiver)<div class="hint">by {{ $col->receiver->name }}</div>@endif</td>
                <td>
                    @if ($col->status !== 'received')
                        <details class="need-form">
                            <summary>Weigh it in</summary>
                            <form method="POST" action="{{ route('admin.ewaste.receive', $col) }}" class="fee-form"
                                  data-confirm="Weigh {{ $col->reference }} in on the register?" data-title="Weigh in">
                                @csrf
                                <div class="form-grid-2">
                                    <div class="field">
                                        <label>Weight on the scale (kg) <span class="req">*</span></label>
                                        <input type="number" name="weighed_weight_kg" required min="0.01" step="0.01"
                                               value="{{ $col->estimated_weight_kg }}">
                                    </div>
                                    <div class="field">
                                        <label>Condition once checked <span class="req">*</span></label>
                                        <select name="condition" required>
                                            @foreach ($conditions as $key => $label)
                                                <option value="{{ $key }}" @selected($col->condition === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:10px;">
                                    <label>What the collection point saw</label>
                                    <input type="text" name="note" maxlength="1000"
                                           placeholder="e.g. 9 of 12 desktops power on; boards wiped; 3 to be stripped for parts.">
                                </div>
                                <button class="btn btn-lime btn-block" type="submit" style="margin-top:10px;">Weigh in</button>
                            </form>
                        </details>
                    @else
                        <span class="hint">weighed in {{ $col->received_on?->format('d M Y') }}</span>
                    @endif
                </td>
            </tr>
            @if ($col->note || $col->forward_note)
                <tr>
                    <td colspan="11" class="hint">
                        @if ($col->note){{ $col->collectorLabel() }} wrote: {{ $col->note }}. @endif
                        @if ($col->forward_note){{ $col->forwarder?->full_name }} reported: {{ $col->forward_note }}. @endif
                    </td>
                </tr>
            @endif
        @empty
            <tr><td colspan="11" style="text-align:center;color:var(--muted);padding:22px;">
                Nothing on the register with those filters. CTPs and E-Fundids add lines from their own portal —
                <b>E-Waste collection</b> in the member sidebar.
            </td></tr>
        @endforelse
    </tbody>
</table>

{{ $collections->links() }}

<p class="hint">The next steps on this module — sorting, testing and routing a line to repair, refurbishment,
    recycling or a certified disposal partner — are recorded here on the same row, so the lifetime of every item
    stays on one record.</p>
@endsection
