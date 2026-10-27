@php
    use App\Models\CtpRegistration;

    $term = CtpRegistration::currentTerm();
    $thisTerm = $member->peopleBroughtThisTerm();
    $lifetime = $member->peopleBrought();
    $next = $member->nextMilestone() ?? 30;
    $areas = $communities->where('status', '!=', 'rejected');
    $share = $member->referralLink();
    $whats = 'https://wa.me/?text='.urlencode('Join WireLakes Technologies as a CTP — use my referral link '.$share);
@endphp

<div class="card">
    <h3>Bring young people in — your referral code</h3>
    <div class="ref-code-box">
        <div>
            <small>Your code</small>
            <b>{{ $member->ctp_code ?: 'Issued on approval' }}</b>
        </div>
        <div class="ref-actions">
            <button class="btn btn-sm btn-outline" type="button"
                    onclick="copyText('{{ $share }}', this)">Copy link</button>
            <a class="btn btn-sm btn-lime" href="{{ $whats }}" target="_blank" rel="noopener">Share on WhatsApp</a>
        </div>
    </div>
    <div class="link-box">
        <input type="text" readonly value="{{ $share }}" onclick="this.select()">
    </div>
    <p class="hint">Drop the link in a WhatsApp or community chat. Anyone who joins the programme through it counts
        for you — whether they come in as a <b>trainee CTP</b> or register as an <b>E-Fundi</b>.</p>

    <div class="milestone">
        <div class="ms-top"><b>{{ $thisTerm }} of {{ $next }}</b><span class="hint">people you brought this term ({{ $term['label'] }})</span></div>
        <div class="ms-bar"><i style="width: {{ $next ? min(100, round($thisTerm / $next * 100)) : 0 }}%"></i></div>
        @if ($thisTerm >= 15)
            <p class="reward">You have earned <b>{{ $member->discountLabel() }}</b> on this term's training fee.</p>
        @else
            <p class="hint">{{ max(0, 15 - $thisTerm) }} more to unlock 50% off this term’s fee, {{ max(0, 30 - $thisTerm) }} more for the full sponsorship credit.</p>
        @endif
    </div>
</div>

<div class="card">
    <h3>The areas you mobilise <span class="tag gray">{{ $areas->count() }}</span></h3>
    <p class="hint">More than one CTP works an area. Everyone on the roster mobilises it and is credited for the
        people <b>they</b> brought — the lead only owns the record.</p>
    @forelse ($areas as $community)
        <div class="row-line">
            <b>{{ $community->name }}</b>
            <span class="tag">{{ $community->community_code ?: 'awaiting COM ID' }}</span>
            @php $mine = $community->ctps->firstWhere('id', $member->id); @endphp
            <span class="fee-tag {{ $mine && $mine->pivot->role === 'lead' ? 'paid' : '' }}">
                {{ $mine && $mine->pivot->role === 'lead' ? 'Lead CTP' : 'Mobilizer' }}
            </span>
            <span class="hint">
                {{ $community->ctps->count() }} CTP{{ $community->ctps->count() === 1 ? '' : 's' }} on this area ·
                {{ $community->needs->count() }} needs logged
            </span>
        </div>
    @empty
        <p class="hint">You are not on a community roster yet. Propose one under <b>My communities</b> and the office
            will confirm it.</p>
    @endforelse
</div>

<div class="card">
    <h3>People you have brought in <span class="tag gray">{{ $brought->count() }} brought · {{ $lifetime }} counting</span></h3>
    @forelse ($brought as $p)
        <div class="row-line">
            <b>{{ $p['name'] }}</b>
            <span class="tag">{{ $p['kind'] }}</span>
            <span class="status {{ $p['status'] }}">{{ $p['label'] }}</span>
            <span class="hint">{{ $p['place'] ?: 'area not recorded' }} · {{ $p['on'] ? $p['on']->format('d M Y') : '—' }}</span>
        </div>
    @empty
        <p class="hint">Nobody yet. Share your link — a person counts once they have paid and the programme office
            has approved them as a trainee CTP or an E-Fundi.</p>
    @endforelse
</div>

<div class="card">
    <h3>How counting works</h3>
    <ul class="rules">
        <li>A <b>trainee CTP</b> you brought counts once they are active, graduated or deployed.</li>
        <li>An <b>E-Fundi</b> you brought counts once the office approves their registration.</li>
        <li>Only people brought <b>inside the current term</b> count toward the discount.</li>
        <li>The discount is a <b>credit on this term’s fee</b> — never a refund.</li>
        <li>You cannot bring yourself, and each person counts for only one mobiliser.</li>
        <li>If someone you brought is later rejected, they drop off your count.</li>
    </ul>
</div>
