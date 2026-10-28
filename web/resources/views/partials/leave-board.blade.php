@php
    use App\Models\LeaveRequest;
    use App\Support\Leave;

    $num = fn (float $d) => Leave::format($d);
    $statusClass = ['pending' => 'pending_review', 'approved' => 'active', 'rejected' => 'rejected', 'cancelled' => 'closed'];
@endphp

<div class="leave-grid">
    @foreach ($balances as $b)
        <div class="leave-type {{ $b['left'] <= 0 ? 'spent' : '' }}">
            <b>{{ $b['name'] }}</b>
            <div class="nums">
                <span><i>{{ $num($b['left']) }}</i> left</span>
                <span><i>{{ $num($b['taken']) }}</i> taken</span>
                <span><i>{{ $num($b['pending']) }}</i> pending</span>
                <span><i>{{ $num($b['entitled']) }}</i> entitled</span>
            </div>
            @if ($b['accrual'])
                <div class="accrual">Monthly accrual: {{ $num($b['accrual']) }} days/month
                    <small>{{ $b['months'] }} {{ Str::plural('month', $b['months']) }} worked since
                        {{ $joined->format('d M Y') }} · up to 21 a year</small>
                </div>
            @else
                <div class="accrual">{{ $num($b['entitled']) }} days a year</div>
            @endif
        </div>
    @endforeach
</div>

<div class="head-acts" style="margin-bottom:22px;">
    <button class="btn btn-sm btn-lime" type="button" data-sheet="book">Book leave</button>
</div>

@include('partials.sheet-start', ['name' => 'book', 'title' => 'Book leave',
    'sub' => 'Only the days you have left are booked. Human Resources answers every application.'])
    <form method="POST" action="{{ route($applyRoute) }}" class="fee-form"
          data-confirm="Send this application to Human Resources?" data-title="Book leave">
        @csrf
        <input type="hidden" name="_sheet" value="book">
        @include('partials.errors')
        <div class="form-grid-3">
            <div class="field">
                <label>Leave type <span class="req">*</span></label>
                <select name="type" required>
                    @foreach ($balances as $b)
                        <option value="{{ $b['type'] }}" @selected(old('type') === $b['type'])>
                            {{ $b['name'] }} — {{ $num($b['left']) }} left
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>First day <span class="req">*</span></label>
                <input type="date" name="starts_on" required value="{{ old('starts_on') }}">
            </div>
            <div class="field">
                <label>Last day <span class="req">*</span></label>
                <input type="date" name="ends_on" required value="{{ old('ends_on') }}">
                <div class="help">Days are counted from the first to the last, both included.</div>
            </div>
        </div>
        <div class="form-grid-2">
            <div class="field">
                <label>Who covers your work</label>
                <input type="text" name="cover_for" maxlength="160" value="{{ old('cover_for') }}"
                       placeholder="A colleague, or nothing booked yet">
            </div>
            <div class="field">
                <label>Reachable on</label>
                <input type="text" name="phone" maxlength="30" value="{{ old('phone') }}" placeholder="07********">
            </div>
        </div>
        <div class="field">
            <label>Reason</label>
            <textarea name="reason" rows="2" maxlength="1000"
                      placeholder="Anything the desk should know">{{ old('reason') }}</textarea>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-sm btn-lime" type="submit">Send to Human Resources</button>
        </div>
    </form>
    <p class="hint">Annual leave builds by {{ Leave::ANNUAL_ACCRUAL }} days for every month worked; the other types
        are given in full for the year.</p>
@include('partials.sheet-end')

<h2 class="sec-title">Your applications</h2>
@if ($history->isEmpty())
    <div class="card"><p class="page-lede">No leave has been booked from this account.</p></div>
@else
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr><th>Type</th><th>Dates</th><th class="num">Days</th><th>Status</th><th>Applied</th>
                    <th>Human Resources</th>@if ($cancelRoute ?? null)<th></th>@endif</tr>
            </thead>
            <tbody>
                @foreach ($history as $row)
                    <tr>
                        <td><b>{{ $row->typeLabel() }}</b></td>
                        <td class="nowrap">{{ $row->periodLabel() }}</td>
                        <td class="num">{{ $num($row->days) }}</td>
                        <td><span class="status {{ $statusClass[$row->status] ?? 'closed' }}">{{ $row->statusLabel() }}</span></td>
                        <td class="nowrap">{{ $row->created_at->format('d M Y') }}</td>
                        <td>
                            @if ($row->decision_note)<div class="hint">{{ $row->decision_note }}</div>@endif
                            @if ($row->decidedBy)
                                <div class="hint">{{ $row->decidedBy->name }} · {{ $row->decided_at?->format('d M Y') }}</div>
                            @endif
                        </td>
                        @if ($cancelRoute ?? null)
                            <td class="actions">
                                @if ($row->isPending())
                                    <form method="POST" action="{{ route($cancelRoute, $row->id) }}"
                                          data-confirm="Withdraw the application for {{ $row->periodLabel() }}?"
                                          data-title="Withdraw application">
                                        @csrf
                                        <button class="btn btn-sm btn-outline" type="submit">Withdraw</button>
                                    </form>
                                @else
                                    <a class="btn btn-sm btn-outline" href="{{ route($applyRoute) }}">Book again</a>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
