@extends('layouts.admin')
@section('title', 'Match '.$project->reference)

@php
    use App\Models\FundiRegistration;
    use App\Models\Project;
    use App\Models\ProjectMatch;
    use App\Support\Finance;
    use App\Support\Marketplace;

    $pill = ['draft' => 'pending_review', 'open' => 'active', 'awarded' => 'awarded', 'closed' => 'rejected'];
    $statusPill = ['shortlisted' => 'pending_review', 'invited' => 'pending_review', 'accepted' => 'active',
        'declined' => 'rejected', 'no_reply' => 'rejected', 'awarded' => 'awarded'];
    $award = $project->applications()->where('status', 'approved')->first();
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.marketplace.index') }}">&larr; Matching desk</a></p>

<div class="proj-head">
    <div>
        <span class="status {{ $pill[$project->status] ?? 'pending_review' }}">{{ Project::STATUSES[$project->status] ?? $project->status }}</span>
        <span class="tag {{ $project->category === 'senior_fundi' ? 'gold' : '' }}">{{ $project->categoryLabel() }}</span>
        <h1 style="margin:8px 0 4px;">{{ $project->title }}</h1>
        <div class="hint">
            {{ $project->reference }} · {{ $project->trade ?: 'any trade' }} ·
            {{ collect([$project->county, $project->ward])->filter()->implode(', ') ?: 'place not set' }} ·
            {{ $project->deadline ? 'must be done by '.$project->deadline->format('d M Y') : 'no deadline' }} ·
            {{ $project->budget !== null ? Finance::ksh((float) $project->budget) : 'budget not set' }}
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-sm btn-outline" href="{{ route('admin.projects.show', $project) }}">Posting, materials &amp; applications</a>
    </div>
</div>

@if ($award)
    <div class="card" style="border-left:4px solid var(--green);">
        <h3 style="margin:0 0 6px;">This project is already awarded</h3>
        <p style="margin:0;">
            <b>{{ $award->full_name }}</b> <span class="tag gray">{{ $award->memberCode() }}</span>
            took it on {{ $project->awarded_at?->format('d M Y') ?? 'a date on the record' }}.
            The work order is opened under
            <a href="{{ route('admin.department', 'execution') }}">Project Execution &amp; Payment</a> and the money under
            <a href="{{ route('admin.department', 'finance') }}">Finance</a> — this desk keeps the rounds below as the
            record of how the fundi was found. To change the worker, release the award on the project page first.
        </p>
    </div>
@endif

<div class="card">
    <h2 class="section-head">What the search looks for</h2>
    <p class="help">
        §9.4 matches a project to the register the way the register is kept: grade against the category, the skills the
        trade actually names, the counties the fundi said they cover, whether they say they are free, and what they have
        already done. The programme holds no review scores, so nothing here is a star rating — the standing bar is built
        from those facts and every factor is written out on the card below.
    </p>
    <dl class="kv">
        <div><dt>Skills the trade names</dt>
            <dd>{{ $needs ? implode(', ', $needs) : 'None — the typed trade names no skill on the register, so the search scores on place, availability and standing alone' }}</dd></div>
        <div><dt>Grade it needs</dt><dd>{{ Project::CATEGORIES[$project->category]['hint'] ?? '—' }}</dd></div>
        <div><dt>County</dt><dd>{{ $project->county ?: 'Not set — the search cannot score place' }}</dd></div>
        <div><dt>Must say they are free</dt><dd>Yes. A fundi marked on a job or unavailable is not put forward.</dd></div>
        <div><dt>Standing bar</dt><dd>{{ $bar }}% of the points available on this project</dd></div>
        <div><dt>Asked when the round is run</dt>
            <dd>The top {{ Marketplace::INVITE_TOP }}. Each gets {{ ProjectMatch::WINDOW_HOURS }} hours to answer, from the moment the office sends the ask.</dd></div>
    </dl>
</div>

<div class="card">
    <h2 class="section-head">Run the search</h2>
    <p class="help">
        A run writes a new round underneath the ones already there (§9.6) — round 1 is never overwritten, so the desk
        can show what changed. Widening brings in fundis from neighbouring counties and says so on every card it brought.
    </p>
    <form method="POST" action="{{ route('admin.marketplace.run', $project) }}" class="fee-form"
          data-confirm="Run the search for {{ $project->reference }} as a new round?" data-title="Run the search">
        @csrf
        <div class="form-grid-2">
            <div class="field">
                <label for="widen">Reach</label>
                <select name="widen" id="widen">
                    <option value="0">{{ $project->county }} only</option>
                    <option value="1">Widen to counties neighbouring {{ $project->county ?: 'the project' }}</option>
                </select>
            </div>
            <div class="field">
                <label for="bar">Standing bar (%)</label>
                @if ($isExecutive)
                    <input type="number" name="bar" id="bar" min="{{ Marketplace::MIN_BAR }}" max="100" value="{{ $bar }}">
                    <div class="hint">Taking it below {{ $bar }}% is your decision and is marked on every card it lets in.</div>
                @else
                    <input type="number" id="bar" value="{{ $bar }}" disabled>
                    <div class="hint">Fixed at {{ $bar }}% for staff. Lowering it is a programme executive's decision (§9.6).</div>
                @endif
            </div>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Run the search as round {{ ($latest ?: 0) + 1 }}</button>
    </form>

    @if ($rounds->isNotEmpty())
        <form method="POST" action="{{ route('admin.marketplace.invite', $project) }}" class="fee-form" style="margin-top:12px;"
              data-confirm="Put {{ $project->reference }} to the top of this round? The 48 hours start now." data-title="Ask the shortlist">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label for="round">Round to ask</label>
                    <select name="round" id="round">
                        @foreach ($rounds as $round => $rows)
                            <option value="{{ $round }}">Round {{ $round }} — {{ $rows->count() }} shortlisted,
                                {{ $rows->where('status', 'shortlisted')->count() }} not yet asked</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="top">How many at the top</label>
                    <input type="number" name="top" id="top" min="1" max="20" value="{{ Marketplace::INVITE_TOP }}">
                </div>
            </div>
            <button class="btn btn-lime btn-block" type="submit">Send the invite</button>
        </form>
    @endif
</div>

@if ($isExecutive)
    <div class="card">
        <h2 class="section-head">Bring a fundi forward yourself</h2>
        <p class="help">
            §9.6 — a Director can put the project to someone the search passed over. The reason is typed and stays on the
            card, and the same {{ ProjectMatch::WINDOW_HOURS }} hours apply. Hand-picks are added to the current round.
        </p>
        <form method="POST" action="{{ route('admin.marketplace.handpick', $project) }}" class="fee-form" data-title="Hand-pick a fundi">
            @csrf
            <div class="field">
                <label for="fundi_id">E-Fundi <span class="req">*</span></label>
                <select name="fundi_id" id="fundi_id" required>
                    <option value="">Select from the register</option>
                    @foreach ($pool as $m)
                        <option value="{{ $m['id'] }}">
                            {{ $m['code'] }} · {{ $m['name'] }} — {{ $m['level'] }}
                            @unless ($m['fits'])· grade does not cover this category @endunless
                            @if ($m['asked'])· already on a round @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="reason">Why them <span class="req">*</span></label>
                <input type="text" name="reason" id="reason" maxlength="190" required
                       placeholder="e.g. Has done the same fit-out two wards away and can start this week.">
            </div>
            <button class="btn btn-outline btn-block" type="submit">Add to the round and ask</button>
        </form>
    </div>
@else
    <p class="hint">
        Staff run the search, widen it and ask the shortlist. Bringing a fundi forward outside the search, and lowering
        the standing bar, are decisions a programme executive makes on this page (§9.6).
    </p>
@endif

<h2 style="margin-top:26px;">Rounds <span class="tag">{{ $rounds->count() }}</span></h2>

@forelse ($rounds as $round => $rows)
    <div class="card">
        <h3 class="section-head">Round {{ $round }}
            <span class="tag gray">{{ $rows->count() }} shortlisted</span>
            @if ($rows->first()->widened)<span class="tag">widened to neighbours</span>@endif
            @if ($rows->first()->below_bar)<span class="tag gold">bar was lowered</span>@endif
        </h3>
        <p class="hint">Run {{ $rows->first()->created_at->format('d M Y, H:i') }}
            @if ($rows->first()->updated_at->ne($rows->first()->created_at)) · last touched {{ $rows->first()->updated_at->diffForHumans(null, true) }}@endif.</p>

        @foreach ($rows as $match)
            @php $fundi = $match->fundi; @endphp
            <div class="invite-row {{ in_array($match->status, ['declined', 'no_reply'], true) ? 'revoked' : '' }}">
                <div class="invite-main">
                    <b>{{ $match->score }}%</b>
                    <a href="{{ route('admin.fundis.show', $fundi) }}">{{ $fundi->full_name }}</a>
                    <span class="tag gray">{{ $fundi->ef_code ?: 'no E-Fundi ID yet' }}</span>
                    <span class="status {{ $statusPill[$match->status] ?? 'pending_review' }}">{{ $match->statusLabel() }}</span>
                    <div class="hint">{{ FundiRegistration::tierLabel($fundi->tier) }} ·
                        {{ collect([$fundi->county])->merge((array) $fundi->service_counties)->filter()->unique()->implode(', ') ?: 'counties not stated' }}</div>
                    <div class="hint">{{ $fundi->availabilityLabel() }} · {{ $match->timeLabel() }}</div>
                    <ul class="hint" style="margin:6px 0 0;padding-left:18px;">
                        @foreach ($match->matchedLines() as $line)<li>{{ $line }}</li>@endforeach
                    </ul>
                    @if ($match->note)<div class="hint" style="margin-top:4px;">“{{ $match->note }}”</div>@endif
                    @if ($match->awarded_on)
                        <div class="hint">Awarded {{ $match->awarded_on->format('d M Y, H:i') }}
                            @if ($match->awardee) by {{ $match->awardee->name }}@endif.</div>
                    @endif
                </div>
                <div class="invite-actions">
                    @if ($match->status === 'awarded')
                        <span class="hint">The fundi holding this project.</span>
                    @elseif ($award)
                        <span class="hint">Released — the project has since gone to {{ $award->full_name }}.</span>
                    @elseif (in_array($match->status, ['accepted', 'invited'], true))
                        <form method="POST" action="{{ route('admin.marketplace.award', [$project, $match]) }}" class="inline-form"
                              data-confirm="Award {{ $project->reference }} to {{ $fundi->full_name }}? Every other fundi on this project is told they were not selected."
                              data-title="Award to this fundi">
                            @csrf
                            <button class="btn btn-sm btn-lime" type="submit">Award to this fundi</button>
                        </form>
                    @else
                        <span class="hint">{{ $match->status === 'shortlisted' ? 'On the shortlist — send the invite to put them in the 48 hours.' : 'Closed.' }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@empty
    <div class="card">
        <p style="margin:0;color:var(--muted);">
            No round has been run for this project yet. Run the search above to build the shortlist; members who applied
            on their own are listed underneath and can be awarded from the project page.
        </p>
    </div>
@endforelse

@if ($rounds->isNotEmpty() && $project->status === 'open' && $rounds->flatten()->where('status', 'accepted')->isNotEmpty())
    <div class="card">
        <h3 class="section-head">Nobody chosen yet</h3>
        <p class="help">Or leave it to the rule: the project goes to the highest-scoring fundi who took it (§9.4 step 5).</p>
        <form method="POST" action="{{ route('admin.marketplace.autoaward', $project) }}"
              data-confirm="Award {{ $project->reference }} to the best-scoring fundi who accepted?" data-title="Auto-pick the best">
            @csrf
            <button class="btn btn-outline btn-sm" type="submit">Award to the best of those who accepted</button>
        </form>
    </div>
@endif

<h2 style="margin-top:26px;">Applications on the project <span class="tag">{{ $applicants->count() }}</span></h2>
<div class="card">
    <p class="help">
        Members put these forward themselves on the project page — they are shown here so the desk can see both halves of
        §9.4 at once. Awarding an applicant is done on the project page, and the award rule is the same one either screen
        uses.
    </p>
    @if ($applicants->isEmpty())
        <p class="hint">Nobody has applied. {{ $project->applications->count() }} — the marketplace is how this one gets worked.</p>
    @else
        <table class="data">
            <thead><tr><th>Member</th><th>Level</th><th>On the marketplace?</th><th>Status</th><th>Sent</th></tr></thead>
            <tbody>
                @foreach ($applicants as $app)
                    <tr>
                        <td><b>{{ $app->full_name }}</b><div class="hint">{{ $app->memberCode() }}</div></td>
                        <td>{{ $app->levelLabel() }}</td>
                        <td>
                            @php $row = $app->applicant_type === 'fundi'
                                ? $rounds->flatten()->first(fn ($m) => (int) $m->fundi_id === (int) $app->applicant_id)
                                : null; @endphp
                            {{ $row ? 'Round '.$row->round.' — '.$row->statusLabel() : 'came on their own' }}
                        </td>
                        <td>{{ $app->status === 'approved' ? 'Awarded' : ($app->status === 'rejected' ? 'Not selected' : 'Waiting') }}</td>
                        <td>{{ $app->created_at->format('d M Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<p class="hint">
    Materials the programme supplies, the work order and the payout are not this department's screens: the list stays on
    <a href="{{ route('admin.projects.show', $project) }}">the project</a>, delivery is Module 7 and money is Module 11.
</p>
@endsection
