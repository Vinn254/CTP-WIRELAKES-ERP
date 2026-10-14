@php
    $parts = $enrollment->trackParts();
    $pct = $enrollment->trackPct();
    $closed = in_array($enrollment->stage, ['completed', 'failed'], true);
@endphp

<div class="milestone">
    <div class="ms-top">
        <b>{{ $pct }}%</b>
        <span class="hint">of the track earned{{ $enrollment->state !== 'approved' ? ' — no seat held, so nothing counts yet' : '' }}</span>
    </div>
    <div class="ms-bar"><i style="width: {{ min(100, $pct) }}%"></i></div>
</div>

<table class="data track-parts">
    <thead>
        <tr>
            <th>Part of the track</th>
            <th title="What this part has earned out of what it is worth">Earned</th>
            <th>What is on file</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($parts as $p)
            <tr>
                <td><b>{{ $p['label'] }}</b></td>
                <td><span class="fee-tag {{ $p['done'] ? 'paid' : 'waived' }}">{{ $p['got'] }} / {{ $p['weight'] }}</span></td>
                <td class="hint">{{ $p['detail'] }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th>Track so far</th>
            <th>{{ $pct }} / 100</th>
            <th class="hint">
                @if ($pct >= 100)
                    <b>Track complete.</b>
                    @if ($enrollment->certification)
                        They are in the certification queue as <b>{{ $enrollment->certification->statusLabel() }}</b> —
                        an executive signs it and the code is issued.
                    @else
                        Nothing was recorded that blocks them: they go into the certification queue for the office to
                        verify and an executive to sign.
                    @endif
                @elseif ($closed)
                    This run is closed, so the track is left as it was recorded.
                @else
                    Still short of 100%:
                    {{ collect($parts)->reject(fn ($p) => $p['done'])->pluck('label')->join(', ') }}.
                @endif
            </th>
        </tr>
    </tfoot>
</table>
