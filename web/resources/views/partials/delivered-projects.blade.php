@if ($delivered->isNotEmpty())
    <section class="section">
        <span class="tag">Projects delivered</span>
        <h2>Work the programme has finished</h2>
        <p class="sub">Every project below was completed by an E-Fundi or a CTP on attachment, then signed off by the
            organisation that hired them. New work joins this list as each order closes.</p>

        <div class="grid-3">
            @foreach ($delivered as $order)
                @php $project = $order->project; @endphp
                <div class="card delivered-card">
                    <div class="delivered-when">
                        <span class="status active">Delivered</span>
                        <small>{{ ($order->confirmed_on ?: $order->closed_on)?->format('d M Y') ?: 'Signed off' }}</small>
                    </div>
                    <h3>{{ $project->title }}</h3>
                    <p>{{ \Illuminate\Support\Str::limit($order->deliverables ?: $project->description, 140) }}</p>
                    <ul class="delivered-meta">
                        <li>{{ $project->categoryLabel() }}</li>
                        @if ($project->county)
                            <li>{{ $project->county }}@if ($project->ward) · {{ $project->ward }}@endif</li>
                        @endif
                        @if ($project->trade)<li>{{ $project->trade }}</li>@endif
                        <li>Done by {{ $order->assignee_name ?: 'an E-Fundi' }}</li>
                        @if ($project->hirer->organisation)<li>For {{ $project->hirer->organisation }}</li>@endif
                    </ul>
                    <a class="btn btn-outline btn-sm" href="{{ route('portal.projects') }}">
                        {{ ($canApply ?? false) ? 'Apply for a project like this' : 'Log in as an E-Fundi to apply' }}
                    </a>
                </div>
            @endforeach
        </div>

        @unless ($canApply ?? false)
            <p class="delivered-note">
                Applying takes an account on the member portal.
                <a href="{{ route('login') }}">Log in as an E-Fundi</a>
                &nbsp;·&nbsp; no account yet? <a href="{{ route('register.fundi') }}">Become a Fundi</a>
            </p>
        @endunless
    </section>
@endif
