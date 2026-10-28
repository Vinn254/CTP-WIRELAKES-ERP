@extends('layouts.admin')
@section('title', 'Payroll '.$run->periodLabel())

@php
    use App\Models\PayrollLine;
    use App\Support\Finance;

    $money = fn ($v) => number_format((float) $v, 2, '.', '');
    $index = str_replace('.show', '.index', $self);
    $paying = isset($canPay) && $canPay;
    $tracked = $self === 'admin.payroll.show' && ! ($canEdit ?? false);

    $sentBy = in_array($run->status, ['sent', 'paid'])
        ? 'Sent by '.($run->sender->name ?: 'the office').($run->sent_at ? ' on '.$run->sent_at->format('d M Y') : '').'.'
        : '';

    $trail = collect([
        $run->statusLabel(),
        'prepared by '.($run->preparer->name ?: 'the office').($run->created_at ? ' on '.$run->created_at->format('d M Y') : ''),
        $run->sent_at ? 'sent by '.($run->sender->name ?: 'the office').' on '.$run->sent_at->format('d M Y') : null,
        $run->paid_at ? 'settled by '.($run->payer->name ?: 'the office').' on '.$run->paid_at->format('d M Y') : null,
    ])->filter()->implode(' · ').'.';

    $settled = collect([
        $totals['paid_count'].' of '.$totals['people'].' people paid ('.Finance::ksh($totals['paid']).')',
        $totals['unpaid_count'] ? $totals['unpaid_count'].' still with Finance ('.Finance::ksh($totals['unpaid']).')' : null,
        $totals['held_count'] ? $totals['held_count'].' held back' : null,
    ])->filter()->implode(' · ').'.';
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>Payroll — {{ $run->periodLabel() }}</h1>
        <p class="ah-meta">
            <span><b>{{ $run->reference }}</b></span>
            <span>{{ $totals['people'] }} staff</span>
            <span>Gross {{ Finance::ksh($totals['gross']) }}</span>
            <span>Deductions {{ Finance::ksh($totals['deductions']) }}</span>
            <span><b>{{ Finance::ksh($totals['net']) }}</b> net</span>
            <span>{{ $run->statusLabel() }}</span>
        </p>
    </div>
    <div class="head-actions">
        <a class="btn btn-sm btn-outline" href="{{ route($index) }}">{{ $paying ? 'All months' : 'Payroll register' }}</a>
        <button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
</div>

@if ($run->status === 'returned')
    <p class="flash-error" style="margin:0 0 18px"><b>Returned by Finance.</b> {{ $run->return_reason }}</p>
@endif

@if ($run->note)
    <p class="hint">Note on this month: {{ $run->note }}</p>
@endif

<div class="table-scroll">
    <table class="data pay-sheet">
        <thead>
            <tr>
                <th>Person</th><th>Dept</th>
                @foreach ($columns as $label)
                    <th class="num">{{ $label }}</th>
                @endforeach
                <th class="num">Gross</th><th class="num">Total deductions</th><th class="num">Net pay</th>
                <th>Status</th><th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td><b>{{ $line->name }}</b>
                        <small>{{ $line->job_title ?: $line->email }}</small></td>
                    <td>{{ $line->department ?: '—' }}</td>
                    @foreach (array_keys($columns) as $column)
                        <td class="num">{{ (float) $line->{$column} > 0 ? Finance::ksh((float) $line->{$column}) : '—' }}</td>
                    @endforeach
                    <td class="num">{{ Finance::ksh((float) $line->gross_ksh) }}</td>
                    <td class="num">{{ Finance::ksh((float) $line->deductions_ksh) }}</td>
                    <td class="num"><b>{{ $line->netLabel() }}</b></td>
                    <td><span class="status {{ ['unpaid' => 'pending_payment', 'paid' => 'active',
                            'held' => 'suspended'][$line->status] ?? 'closed' }}">{{ $line->statusLabel() }}
                            @if ($line->status === 'paid')
                                <small>{{ $line->paid_on?->format('d M Y') }} · {{ $line->methodLabel() }}</small>
                            @elseif ($line->status === 'held')
                                <small>{{ $line->hold_reason }}</small>
                            @endif</span></td>
                    <td class="actions">
                        @if ($canEdit ?? false)
                            <button class="btn btn-sm btn-outline" type="button"
                                data-sheet="pay-{{ $line->id }}">{{ (float) $line->gross_ksh > 0 ? 'Correct' : 'Set pay' }}</button>
                            @if ($line->status === 'held')
                                <form method="POST" action="{{ route('admin.payroll.line.release', $line) }}" class="inline-form">
                                    @csrf
                                    <button class="btn btn-sm btn-ghost" type="submit">Put back</button>
                                </form>
                            @elseif ($line->status === 'unpaid')
                                <button class="btn btn-sm btn-ghost" type="button"
                                    data-sheet="hold-{{ $line->id }}">Hold</button>
                            @endif
                        @elseif ($paying && $line->status === 'unpaid')
                            <button class="btn btn-sm btn-primary" type="button"
                                data-sheet="settle-{{ $line->id }}">Pay</button>
                        @else
                            <span class="hint">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 9 }}" class="span-note">Nobody is on this sheet yet.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td><b>Total</b></td><td></td>
                @foreach (array_keys($columns) as $column)
                    <td class="num">{{ Finance::ksh($lines->sum($column)) }}</td>
                @endforeach
                <td class="num">{{ Finance::ksh($totals['gross']) }}</td>
                <td class="num">{{ Finance::ksh($totals['deductions']) }}</td>
                <td class="num"><b>{{ Finance::ksh($totals['net']) }}</b></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</div>

@if ($canEdit ?? false)
    <div class="card">
        <h3>Send {{ $run->periodLabel() }} to Finance</h3>
        <p class="hint">{{ $totals['unpaid_count'] }} {{ Str::plural('person', $totals['unpaid_count']) }} to be paid
            {{ Finance::ksh($totals['unpaid']) }}
            @if ($totals['held_count']) · {{ $totals['held_count'] }} held back @endif
            · {{ $approvedWeeks }} {{ Str::plural('approved time-log week', $approvedWeeks) }} signed in this month.</p>
        <form method="POST" action="{{ route('admin.payroll.send', $run) }}" class="inline-form"
              data-confirm="Send this sheet to Finance for payment?" data-title="Send to Finance">
            @csrf
            <button class="btn btn-primary" type="submit" @disabled($totals['unpaid_count'] === 0)>Send to Finance</button>
        </form>
        @if ($sentBy)
            <p class="hint">{{ $sentBy }}</p>
        @endif
    </div>

    @if ($people->isNotEmpty())
        <div class="head-actions" style="margin:0 0 14px">
            <button class="btn btn-sm btn-outline" type="button" data-sheet="person">Add a person to this sheet</button>
        </div>
        @include('partials.sheet-start', ['name' => 'person', 'title' => 'Add a person to this month'])
        <form method="POST" action="{{ route('admin.payroll.add', $run) }}" class="fee-form"
              data-confirm="Add this person to the sheet?" data-title="Add person">
            @csrf
            <input type="hidden" name="_sheet" value="person">
            <div class="field">
                <label>On the staff file <span class="req">*</span></label>
                <select name="staff_record_id" required>
                    <option value="">Choose the person</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}" @selected((int) old('staff_record_id') === $person->id)>
                            {{ $person->name }}{{ $person->job_title ? ' — '.$person->job_title : '' }}
                        </option>
                    @endforeach
                </select>
                <div class="help">They come in at the pay on their file; the allowances and deductions are set next.</div>
            </div>
            <div class="sheet-actions">
                <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
                <button class="btn btn-primary" type="submit">Add to the sheet</button>
            </div>
        </form>
        @include('partials.sheet-end')
    @endif
@elseif ($paying)
    <div class="card">
        <h3>Is this sheet right?</h3>
        <p class="hint">Pay each person from the row above, or send the whole month back to Human Resources with what
            must be corrected. Nothing here is deleted.</p>
        <button class="btn btn-sm btn-outline" type="button" data-sheet="return">Return it to Human Resources</button>
        @include('partials.sheet-start', ['name' => 'return', 'title' => 'Return '.$run->periodLabel().' payroll'])
        <form method="POST" action="{{ route('admin.staffpay.return', $run) }}" class="fee-form"
              data-confirm="Send this month back to Human Resources?" data-title="Return payroll">
            @csrf
            <input type="hidden" name="_sheet" value="return">
            <div class="field">
                <label>What must be corrected <span class="req">*</span></label>
                <textarea name="reason" rows="3" maxlength="500" required
                          placeholder="e.g. Two lines carry no PAYE"></textarea>
            </div>
            <div class="sheet-actions">
                <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
                <button class="btn btn-primary" type="submit">Return the sheet</button>
            </div>
        </form>
        @include('partials.sheet-end')
    </div>
@endif

@if ($tracked)
    <div class="card">
        <h3>Where this sheet is</h3>
        <p class="hint">{{ $trail }}</p>
        <p class="hint">{{ $settled }}</p>
    </div>
@endif

@if ($ledger->isNotEmpty())
    <div class="card">
        <h3>On the money register</h3>
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th>Register line</th><th>Detail</th><th class="num">Amount</th><th>Date</th></tr></thead>
                <tbody>
                    @foreach ($ledger as $entry)
                        <tr>
                            <td class="nowrap">{{ $entry->reference }}</td>
                            <td>{{ $entry->title }}<small>{{ $entry->note }}</small></td>
                            <td class="num">{{ $entry->amountLabel() }}</td>
                            <td class="nowrap">{{ $entry->happened_on?->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@foreach ($lines as $line)
    @include('admin.payroll.partials.line', ['line' => $line])
    @include('admin.payroll.partials.settle', ['line' => $line])
@endforeach
@endsection
