@extends('layouts.member')
@section('title', 'Overview')

@php
    use App\Models\CtpRegistration;
    use App\Models\FundiRegistration;
    use Illuminate\Support\Str;

    $isCtp = $member instanceof CtpRegistration;
    $code = $isCtp ? $member->ctp_code : $member->ef_code;
    $level = $isCtp
        ? ($member->fundi_tier ? $member->poolLabel() : Str::headline($member->track ?? '').' CTP')
        : FundiRegistration::tierLabel($member->tier);
    $waiting = $applications->where('status', 'pending_review')->count();
    $won = $applications->where('status', 'approved')->count();
    $term = CtpRegistration::currentTerm();
    $thisTerm = $isCtp ? $member->verifiedReferralsThisTerm() : 0;
    $next = $isCtp ? ($member->nextMilestone() ?? 30) : 0;
    $openLabel = $isCtp ? 'Open for you' : 'Available projects';
    $myCommunities = $communities;
    $openNeeds = $myCommunities->sum(fn ($c) => $c->needs->whereIn('status', ['proposed', 'prioritised'])->count());
@endphp

@section('content')
<div class="member-hero ctp-hero">
    <div>
        <span class="tag">{{ $isCtp ? 'CTP' : 'E-Fundi' }}@if ($code) · {{ $code }}@endif</span>
        <h1>Hello, {{ Str::before($member->full_name, ' ') }}.</h1>
        <p class="sub">{{ $isCtp ? $member->stageLabel() : 'Registered as '.$level }}</p>
        <p class="sub ctp-term">
            @if ($isCtp)
                {{ $member->trackLabel() }} · current term <b>{{ $term['label'] }}</b>
            @else
                Projects at your level are shown under <b>My projects</b>
            @endif
        </p>
    </div>
    <div class="member-stats">
        <div class="stat"><small>{{ $openLabel }}</small><b>{{ $projects->count() }}</b></div>
        <div class="stat"><small>Applications</small><b>{{ $applications->count() }}</b></div>
        @if ($isCtp)
            <div class="stat"><small>Communities</small><b>{{ $myCommunities->count() }}</b></div>
        @else
            <div class="stat"><small>Waiting review</small><b>{{ $waiting }}</b></div>
        @endif
        <div class="stat"><small>Awarded</small><b>{{ $won }}</b></div>
    </div>
</div>

@if ($isCtp && $myCommunities->isNotEmpty())
    <div class="card">
        <h3>My communities</h3>
        @foreach ($myCommunities->take(3) as $c)
            <div class="row-line">
                <b>{{ $c->name }}</b>
                <span class="status {{ $c->status }}">{{ $c->statusLabel() }}</span>
                <span class="hint">{{ $c->needs->count() }} needs · {{ $c->beneficiaries->count() }} reached</span>
            </div>
        @endforeach
        <p style="margin:12px 0 0;"><a class="btn btn-sm btn-outline" href="{{ route('portal.communities') }}">Open my communities</a>
            <span class="hint">{{ $openNeeds }} need{{ $openNeeds === 1 ? '' : 's' }} still open for the office to review.</span></p>
    </div>
@endif

@if ($isCtp && $member->status === 'pending_payment')
    <div class="card callout">
        <h3>Your membership fee is not recorded yet</h3>
        <p class="help" style="margin:0;">Your CTP ID and portal access are issued once the programme office confirms the
            fee. Pay through the checkout when it opens, or bring your receipt to your County Coordinator.</p>
    </div>
@elseif ($isCtp)
    <div class="card">
        <h3>Where you are</h3>
        <p class="help">{{ $member->stageLabel() }} — your training path is under <b>My training</b>, and what you have
            mobilised this term is under <b>My mobilisation</b>.</p>
        <div class="milestone">
            <div class="ms-top"><b>{{ $thisTerm }} of {{ $next }}</b><span class="hint">verified referrals this term</span></div>
            <div class="ms-bar"><i style="width: {{ $next ? min(100, round($thisTerm / $next * 100)) : 0 }}%"></i></div>
        </div>
        <p style="margin:14px 0 0;"><a class="btn btn-sm btn-outline" href="{{ route('portal.mobilisation') }}">Open my mobilisation</a></p>
    </div>
@endif

<h2 class="section-head">Open for you <span class="tag">{{ $projects->count() }}</span></h2>
@include('portal.partials.project-list', ['limit' => 3])

<h2 class="section-head" style="margin-top:28px;">My latest applications <span class="tag gray">{{ $applications->count() }}</span></h2>
@include('portal.partials.applications', ['limit' => 3])
@endsection
