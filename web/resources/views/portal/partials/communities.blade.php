<div class="card">
    <h3>Where you work</h3>
    <dl class="kv">
        <dt>Home location</dt><dd>{{ $member->locationLabel() ?: '—' }}</dd>
        <dt>GPS pin</dt>
        <dd>@if ($member->gps_lat && $member->gps_lng){{ number_format($member->gps_lat, 5) }}, {{ number_format($member->gps_lng, 5) }}@else<span class="hint">not captured yet</span>@endif</dd>
        <dt>Counties you serve</dt>
        <dd>@if (count((array) $member->service_counties))<span class="chips">@foreach ($member->service_counties as $sc)<span class="chip">{{ $sc }}</span>@endforeach</span>@else<span class="hint">Home county ({{ $member->county }})</span>@endif</dd>
        <dt>Available for mobilisation drives</dt><dd>{{ $member->available_for_mobilisation ? 'Yes' : 'No' }}</dd>
        <dt>Trainer opt-in</dt><dd>{{ $member->trainer_opt_in ? 'Yes — once rated 3.5 or better' : 'Not opted in' }}</dd>
    </dl>
    @if ($member->profileGaps())
        <p class="help">Still to capture: {{ collect($member->profileGaps())->join(', ') }}. Ask your County Coordinator to add these —
            the GPS pin is what puts your field work on the impact map.</p>
    @endif
</div>

<div class="card">
    <h3>Community field work</h3>
    <p class="help">As a community agent you run needs assessments, register beneficiaries, spot projects and
        lead e-waste collection drives. Every visit is logged with a photo and GPS pin.</p>
    <ul class="rules">
        <li>Community assignments — <span class="soon">opens with Module 3</span></li>
        <li>Needs assessment and beneficiary forms — <span class="soon">opens with Module 3</span></li>
        <li>E-waste collection drives — <span class="soon">opens with Module 10</span></li>
        <li>Beneficiaries and visits counted for your county — <span class="soon">opens with Module 12</span></li>
    </ul>
</div>
