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

    @foreach ($parts as $key => $p)
        <div class="row-line">
            <b>{{ $p['label'] }}</b>
            <span class="fee-tag {{ $p['done'] ? 'paid' : 'waived' }}">{{ $p['got'] }} / {{ $p['weight'] }}</span>
            <span class="hint">{{ $p['detail'] }}</span>
        </div>
    @endforeach

    @if ($pct >= 100)
        <p class="help" style="margin:10px 0 0;"><b>Track complete.</b>
            @if ($enrollment->certification)
                They are in the certification queue as <b>{{ $enrollment->certification->statusLabel() }}</b> —
                an executive signs it and the code is issued.
            @else
                Nothing was recorded that blocks them: they go into the certification queue for the office to
                verify and an executive to sign.
            @endif
        </p>
    @elseif ($closed)
        <p class="hint" style="margin:8px 0 0;">This run is closed, so the track is left as it was recorded.</p>
    @else
        <p class="hint" style="margin:8px 0 0;">Still short of 100%:
            {{ collect($parts)->reject(fn ($p) => $p['done'])->pluck('label')->join(', ') }}.</p>
    @endif
</div>
