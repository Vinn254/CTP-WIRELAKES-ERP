@php
    /** @var array $sheet */
    $trainer = $sheet['trainer'];
    $editing = $editing ?? false;
    $editLink = $editLink ?? fn ($date) => '#';
@endphp
<div class="table-scroll">
    <table class="data sheet">
        <caption>{{ $trainer->personName() }} — week of {{ $sheet['label'] }}</caption>
        <thead>
            <tr>
                <th>DAY/DATE</th>
                <th>TIME IN</th>
                <th>TIME OUT</th>
                <th>TASKS ACCOMPLISHED</th>
                <th>INITIALS</th>
                <th>DEPT</th>
                <th>APPROVAL SIGN</th>
                <th class="num">TOTAL HOURS</th>
                <th class="num">TOTAL UNITS</th>
                @if ($editing)
                    <th></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($sheet['days'] as $day)
                @php $row = $day['row']; @endphp
                <tr>
                    <td class="nowrap"><b>{{ $day['day'] }}</b><small>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d/m/Y') }}</small></td>
                    <td class="num nowrap">{{ $row ? $row->clockIn() : '-' }}</td>
                    <td class="num nowrap">{{ $row ? $row->clockOut() : '-' }}</td>
                    <td class="text">
                        {{ $row && $row->task ? $row->task : '-' }}
                        @if ($row && $row->training)
                            <small>{{ $row->training->name }}</small>
                        @endif
                        @if ($row && $row->cohort)
                            <small>{{ $row->cohort->reference }}</small>
                        @endif
                        @if ($row && $row->approval_note && $row->status === 'returned')
                            <small class="warn-note">Back to you: {{ $row->approval_note }}</small>
                        @endif
                    </td>
                    <td class="nowrap">{{ $row && $row->initials ? $row->initials : '-' }}</td>
                    <td class="nowrap">{{ $row && $row->department ? $row->department : '-' }}</td>
                    <td class="nowrap">
                        @if ($row && $row->status === 'approved')
                            <b>{{ $row->approvedBy?->name ?? 'Approved' }}</b>
                        @elseif ($row && $row->status === 'submitted')
                            <span class="hint">Signed by {{ $row->signedBy?->name ?? 'the trainer' }}</span>
                        @elseif ($row)
                            <span class="sign-line">&nbsp;</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="num nowrap">{{ $row && $row->hours !== null ? number_format($row->hours, 2) : '-' }}</td>
                    <td class="num nowrap">{{ $row ? (int) $row->units : '-' }}</td>
                    @if ($editing)
                        <td class="actions">
                            @if ($row)
                                <a class="btn btn-sm btn-outline"
                                   href="{{ $editLink($day['date']) }}">Correct</a>
                            @else
                                <a class="btn btn-sm btn-outline"
                                   href="{{ $editLink($day['date']) }}">Write</a>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7"><b>TOTALS</b></td>
                <td class="num nowrap"><b>{{ number_format($sheet['hours'], 2) }}</b></td>
                <td class="num nowrap"><b>{{ (int) $sheet['units'] }}</b></td>
                @if ($editing)
                    <td></td>
                @endif
            </tr>
        </tfoot>
    </table>
</div>
