@php
    use App\Models\Beneficiary;
    use App\Models\CtpRegistration;
    use App\Models\Community;
    use App\Models\Need;

    $canWork = in_array($member->status, CtpRegistration::WORKING, true);
    $openNeeds = $communities->sum(fn (Community $c) => $c->needs->whereIn('status', ['proposed', 'prioritised'])->count());
    $reached = $communities->sum(fn (Community $c) => $c->beneficiaries->count());
    $confirmed = $communities->where('status', 'active')->count();
    // A CTP only opens a project that is published and matches their level — anything else is shown without a link.
    $canOpen = fn ($project) => $project && $project->isOpen() && $project->accepts($member);
@endphp

<div class="page-intro">
    <p class="sub">You are the programme’s person on the ground in these places. What you log here is what the
        programme office uses to book training, match projects and count impact — nothing you record is changed
        without you seeing it.</p>
    <div class="member-stats">
        <div class="stat"><small>Communities</small><b>{{ $communities->count() }}</b></div>
        <div class="stat"><small>Confirmed</small><b>{{ $confirmed }}</b></div>
        <div class="stat"><small>Needs open</small><b>{{ $openNeeds }}</b></div>
        <div class="stat"><small>People reached</small><b>{{ $reached }}</b></div>
    </div>
</div>

@if ($communities->isEmpty())
    <div class="card">
        <h3>You are not on a community roster yet</h3>
        <p class="help">Your County Coordinator assigns communities after approval. You can still put one forward
            yourself — describe the place and the office confirms it and issues its COM ID.</p>
    </div>
@endif

@foreach ($communities as $community)
    @php
        $myRole = $community->ctps->firstWhere('id', $member->id)->pivot->role ?? null;
        $pending = $community->status === 'pending_review';
        $rejected = $community->status === 'rejected';
        $communityNeeds = $community->needs;
        $communityProjects = $community->projects;
    @endphp

    <div class="card community-card">
        <div class="section-head">
            <h3>{{ $community->name }}</h3>
            <span class="status {{ $community->status }}">{{ $community->statusLabel() }}</span>
            <span class="tag">{{ $community->community_code ?: 'COM ID on approval' }}</span>
            @if ($myRole)<span class="fee-tag {{ $myRole === 'lead' ? 'paid' : '' }}">{{ $myRole === 'lead' ? 'You are the lead CTP' : 'You support this one' }}</span>@endif
        </div>
        <p class="hint">{{ collect([$community->village, $community->ward, $community->sub_county, $community->county])->filter()->join(' · ') }}
            @if ($community->hasPin())
                · <a href="https://www.google.com/maps?q={{ $community->gps_lat }},{{ $community->gps_lng }}" target="_blank" rel="noopener">map pin ↗</a>
            @endif
            @if ($community->population) · {{ number_format($community->population) }} people @endif
            @if ($community->households) · {{ number_format($community->households) }} households @endif
        </p>

        @if ($pending)
            <p class="help">Waiting on the programme office to confirm this proposal. Keep logging what you find —
                the needs you add here are read while they decide.</p>
        @endif
        @if ($rejected)
            <p class="help"><b>Not confirmed:</b> {{ $community->rejection_reason ?: 'the office gave no reason — ask your County Coordinator.' }}</p>
        @endif

        <h4 class="dl-group">What needs work here ({{ $communityNeeds->count() }})</h4>
        @forelse ($communityNeeds as $need)
            <div class="row-line">
                <b>{{ $need->title }}</b>
                <span class="tag">{{ Need::typeLabel($need->type) }}</span>
                <span class="status {{ $need->status }}">{{ $need->statusLabel() }}</span>
                <span class="hint">{{ $need->priorityLabel() }} priority · {{ $need->created_at->format('d M Y') }}</span>
            </div>
            @if ($need->project && $canOpen($need->project))
                <p class="hint">Became <a href="{{ route('portal.project', $need->project_id) }}">{{ $need->project->title }}</a>.</p>
            @elseif ($need->project)
                <p class="hint">{{ $need->project->isOpen() ? 'Became a project: '.$need->project->title : 'The office drafted it as a project ('.$need->project->title.') — it is not published yet.' }}</p>
            @endif
        @empty
            <p class="hint">Nothing logged yet.</p>
        @endforelse

        <details class="need-form">
            <summary>Flag a need in this community</summary>
            <form method="POST" action="{{ route('portal.need.store') }}" enctype="multipart/form-data"
                  data-confirm="Send this need to the programme office?" data-title="Flag a need">
                @csrf
                <input type="hidden" name="community_id" value="{{ $community->id }}">
                <div class="form-grid-2">
                    <div class="field">
                        <label>What kind of need <span class="req">*</span></label>
                        <select name="type" required>
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Short title <span class="req">*</span></label>
                        <input type="text" name="title" required maxlength="160" placeholder="e.g. Library hall has no power">
                    </div>
                </div>
                <div class="field">
                    <label>What you saw</label>
                    <textarea name="detail" rows="3" maxlength="2000" placeholder="How many people it affects, what they do without it, who already tried to fix it."></textarea>
                </div>
                <div class="form-grid-2">
                    <div class="field">
                        <label>GPS latitude</label>
                        <input type="text" name="gps_lat" data-lat placeholder="-0.0918020">
                    </div>
                    <div class="field">
                        <label>GPS longitude</label>
                        <input type="text" name="gps_lng" data-lng placeholder="34.7680000">
                    </div>
                </div>
                <div class="field">
                    <label>Photo of the need</label>
                    <input type="file" name="photo" accept="image/jpeg,image/png">
                    <div class="hint">JPG or PNG up to 3 MB. Take it on the spot — a pinned photo is what gets a need funded.</div>
                </div>
                <p class="hint"><button class="btn btn-sm btn-ghost" type="button" data-pin>Use my location</button>
                    <span data-pin-note>Coordinates are what put this need on the impact map.</span></p>
                <button class="btn btn-primary btn-sm" type="submit">Send to the programme office</button>
            </form>
        </details>

        <h4 class="dl-group">People reached ({{ $community->beneficiaries->count() }})</h4>
        @forelse ($community->beneficiaries->take(8) as $b)
            <div class="row-line">
                <b>{{ $b->name }}</b>
                <span class="tag">{{ $b->typeLabel() }}</span>
                <span class="hint">{{ $b->benefited_on->format('d M Y') }}</span>
                @if ($b->project && $canOpen($b->project))
                    <span class="hint">via <a href="{{ route('portal.project', $b->project_id) }}">{{ $b->project->title }}</a></span>
                @elseif ($b->project)
                    <span class="hint">via {{ $b->project->title }}</span>
                @endif
            </div>
        @empty
            <p class="hint">Nobody registered yet.</p>
        @endforelse
        @if ($community->beneficiaries->count() > 8)
            <p class="hint">+{{ $community->beneficiaries->count() - 8 }} more on the community record.</p>
        @endif

        @if ($community->status === 'active')
            <details class="need-form">
                <summary>Register a beneficiary</summary>
                <form method="POST" action="{{ route('portal.beneficiary.store') }}"
                      data-confirm="Add this person or household to the count?" data-title="Register beneficiary">
                    @csrf
                    <input type="hidden" name="community_id" value="{{ $community->id }}">
                    <div class="form-grid-2">
                        <div class="field">
                            <label>Name <span class="req">*</span></label>
                            <input type="text" name="name" required maxlength="150">
                        </div>
                        <div class="field">
                            <label>Who they are <span class="req">*</span></label>
                            <select name="type" required>
                                @foreach (Beneficiary::TYPES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Age</label>
                            <input type="number" name="age" min="1" max="120">
                        </div>
                        <div class="field">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Not recorded</option>
                                <option value="female">Female</option>
                                <option value="male">Male</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Date they benefited <span class="req">*</span></label>
                            <input type="date" name="benefited_on" required value="{{ now()->toDateString() }}">
                        </div>
                        <div class="field">
                            <label>Which project reached them</label>
                            <select name="project_id">
                                <option value="">Not tied to a project</option>
                                @foreach ($projectOptions as $p)
                                    <option value="{{ $p->id }}">{{ $p->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <label class="check-row">
                        <input type="checkbox" name="id_consent" value="1">
                        <span>They agreed to give their ID number for follow-up programmes</span>
                    </label>
                    <div class="field">
                        <label>ID number (only with consent)</label>
                        <input type="text" name="id_number" maxlength="20">
                    </div>
                    <button class="btn btn-lime btn-sm" type="submit">Record beneficiary</button>
                </form>
            </details>
        @else
            <p class="hint">Beneficiaries are counted once the office confirms this community.</p>
        @endif

        <h4 class="dl-group">Projects here ({{ $communityProjects->count() }})</h4>
        @forelse ($communityProjects as $p)
            <div class="row-line">
                @if ($canOpen($p))
                    <a href="{{ route('portal.project', $p->id) }}"><b>{{ $p->title }}</b></a>
                @else
                    <b>{{ $p->title }}</b>
                @endif
                <span class="status {{ $p->status }}">{{ str_replace('_', ' ', $p->status) }}</span>
            </div>
        @empty
            <p class="hint">None yet. Needs you flag here become project candidates.</p>
        @endforelse
    </div>
@endforeach

@if ($canWork)
    <div class="card">
        <h3>Propose a community</h3>
        <p class="help">Use the ward and village the people themselves name. The office checks it against the register —
            a duplicate is sent back with the reason, and nothing you log is lost.</p>
        <form method="POST" action="{{ route('portal.community.store') }}"
              data-confirm="Send this community to the programme office for confirmation?" data-title="Propose community">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label>Community name <span class="req">*</span></label>
                    <input type="text" name="name" required maxlength="160" placeholder="e.g. Nyanda youth cluster">
                </div>
                <div class="field">
                    <label>County <span class="req">*</span></label>
                    <input type="text" name="county" list="county-names" required value="{{ old('county', $member->county) }}">
                </div>
                <div class="field">
                    <label>Sub-county <span class="req">*</span></label>
                    <input type="text" name="sub_county" required value="{{ old('sub_county', $member->sub_county) }}">
                </div>
                <div class="field">
                    <label>Ward <span class="req">*</span></label>
                    <input type="text" name="ward" required value="{{ old('ward', $member->ward) }}">
                </div>
                <div class="field">
                    <label>Village / location</label>
                    <input type="text" name="village" value="{{ old('village', $member->village) }}">
                </div>
                <div class="field">
                    <label>Households you counted</label>
                    <input type="number" name="households" min="1" placeholder="e.g. 980">
                </div>
                <div class="field">
                    <label>Population</label>
                    <input type="number" name="population" min="1" placeholder="e.g. 4200">
                </div>
            </div>
            <div class="field">
                <label>What this community is like</label>
                <textarea name="summary" rows="3" maxlength="4000" placeholder="Who lives here, what the young people do, what already exists."></textarea>
            </div>
            <div class="form-grid-2">
                <div class="field">
                    <label>GPS latitude</label>
                    <input type="text" name="gps_lat" data-lat placeholder="-0.0918020">
                </div>
                <div class="field">
                    <label>GPS longitude</label>
                    <input type="text" name="gps_lng" data-lng placeholder="34.7680000">
                </div>
            </div>
            <p class="hint"><button class="btn btn-sm btn-ghost" type="button" data-pin>Use my location</button>
                <span data-pin-note>A pin is what lets a cohort or a drive be planned for this place.</span></p>
            <datalist id="county-names">@foreach ($counties as $name)<option value="{{ $name }}"></option>@endforeach</datalist>
            <button class="btn btn-primary" type="submit">Send proposal</button>
        </form>
    </div>
@endif

<div class="card">
    <h3>Field work you have logged</h3>
    <dl class="kv">
        <dt>Needs flagged by you</dt><dd>{{ $member->needsRecorded()->count() }}</dd>
        <dt>Beneficiaries registered by you</dt><dd>{{ $member->beneficiariesRegistered()->count() }}</dd>
    </dl>
    @forelse ($myNeeds as $need)
        <div class="row-line">
            <b>{{ $need->title }}</b>
            <span class="tag">{{ Need::typeLabel($need->type) }}</span>
            <span class="hint">{{ $need->community->name ?? 'community removed' }}</span>
            <span class="status {{ $need->status }}">{{ $need->statusLabel() }}</span>
        </div>
    @empty
        <p class="hint">You have not flagged a need yet.</p>
    @endforelse
    <p class="help">Rules the office applies: a need is counted once per community, a beneficiary once per project,
        and only confirmed communities carry impact numbers. Priority and conversion decisions are made by the
        programme office — you always see the result here.</p>
</div>

<script>
    document.querySelectorAll('form [data-pin]').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = button.closest('form');
            const note = form.querySelector('[data-pin-note]');
            if (! navigator.geolocation) {
                note.textContent = 'This device cannot read a location — type the coordinates instead.';
                return;
            }
            note.textContent = 'Reading GPS…';
            navigator.geolocation.getCurrentPosition(
                (p) => {
                    form.querySelector('[data-lat]').value = p.coords.latitude.toFixed(7);
                    form.querySelector('[data-lng]').value = p.coords.longitude.toFixed(7);
                    note.textContent = 'Pin captured — save to place it on the map.';
                },
                () => { note.textContent = 'Location permission refused — type the coordinates instead.'; }
            );
        });
    });
</script>
