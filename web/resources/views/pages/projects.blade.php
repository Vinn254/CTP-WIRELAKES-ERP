@extends('layouts.app')
@section('title', 'Projects')

@section('content')
<section class="section">
    <span class="tag">Projects board</span>
    <h1>Projects open for application</h1>
    <p class="sub">Hirers post their work here — computer labs, roofs, fences, solar and ICT installations. The office
        assesses each one, opens it for applications, then awards it to an E-Fundi or a CTP on attachment. Every project
        states the date applications close.</p>

    <p>
        <a class="btn btn-outline btn-sm" href="{{ route('register.fundi') }}">Register as a Fundi to apply</a>
        &nbsp;
        <a class="btn btn-ghost btn-sm" href="/register/hirer">Post a project as a hirer →</a>
    </p>

    @if ($open->isEmpty())
        <div class="card">
            <h3>No project is taking applications right now</h3>
            <p>Work posted by a hirer appears on this board the day the office opens it. Check back soon, or register so
                we can reach you when your trade comes up.</p>
        </div>
    @else
        <div class="grid-3">
            @foreach ($open as $p)
                <div class="card link-card">
                    <span class="tag gray">{{ $p->categoryLabel() }}</span>
                    <h3>{{ $p->title }}</h3>
                    <p>{{ \Illuminate\Support\Str::limit($p->description, 130) }}</p>
                    <ul class="delivered-meta">
                        @if ($p->county)
                            <li>{{ $p->county }}@if ($p->ward) · {{ $p->ward }}@endif</li>
                        @endif
                        @if ($p->trade)<li>{{ $p->trade }}</li>@endif
                        @if ($p->hirer->organisation)<li>For {{ $p->hirer->organisation }}</li>@endif
                    </ul>
                    <span class="window">{{ $p->applicationWindow() }}</span>
                    <p style="margin:12px 0 0;"><a href="{{ route('projects.show', $p) }}">View project →</a></p>
                </div>
            @endforeach
        </div>
    @endif
</section>

@if ($delivered->isNotEmpty())
    <section class="section">
        <span class="tag">Completed</span>
        <h2>Projects done successfully</h2>
        <p class="sub">Each project below was completed by an E-Fundi or a CTP on attachment and signed off by the
            organisation that hired them.</p>

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
                        <li>Done by {{ $order->assignee_name ?: 'an E-Fundi' }}</li>
                        @if ($project->hirer->organisation)<li>For {{ $project->hirer->organisation }}</li>@endif
                    </ul>
                    <p style="margin:0;"><a href="{{ route('projects.show', $project) }}">View project →</a></p>
                </div>
            @endforeach
        </div>
    </section>
@endif
@endsection
