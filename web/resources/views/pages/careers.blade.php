@extends('layouts.app')
@section('title', 'Careers')

@section('content')
<section class="section">
    <span class="tag">Careers</span>
    <h1>Work and roles the programme is hiring for</h1>
    <p class="sub">These are openings the office has put out — a job on the staff, or project work it is taking people
        on for. They are not the trainings on the programme catalogue; those run through the CTP and E-Fundi
        registration. Anyone can apply here, with or without an account.</p>

    @if ($open->isEmpty())
        <div class="card">
            <h3>No opening is taking applications right now</h3>
            <p>Register as a student or an E-Fundi and we will reach you when your trade comes up.</p>
            <p>
                <a class="btn btn-outline btn-sm" href="{{ route('register.ctp') }}">Become a student (CTP)</a>
                &nbsp;
                <a class="btn btn-lime btn-sm" href="{{ route('register.fundi') }}">Become a Fundi</a>
            </p>
        </div>
    @else
        <div class="grid-3">
            @foreach ($open as $o)
                <div class="card link-card">
                    <span class="tag gray">{{ $o->kindLabel() }}</span>
                    @if ($o->area)<span class="tag">{{ $o->area }}</span>@endif
                    <h3>{{ $o->title }}</h3>
                    <p>{{ \Illuminate\Support\Str::limit($o->description, 150) }}</p>
                    <ul class="delivered-meta">
                        @if ($o->county)<li>{{ $o->county }}</li>@endif
                        <li>{{ $o->positions }} position{{ $o->positions == 1 ? '' : 's' }}</li>
                        <li>Posted {{ $o->created_at->format('d M Y') }}</li>
                    </ul>
                    <span class="window">{{ $o->closingLabel() }}</span>
                    <p style="margin:12px 0 0;">
                        <a class="btn btn-lime btn-sm" href="{{ route('careers.opening', $o) }}#apply">Apply</a>
                        &nbsp;
                        <a href="{{ route('careers.opening', $o) }}">Read the opening →</a>
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</section>

@if ($closed->isNotEmpty())
    <section class="section">
        <span class="tag gray">Closed</span>
        <h2>Openings that have shut</h2>
        <p class="sub">Applications for these are with the office. A person who applied is contacted back directly —
            the list is kept here so you can see what the programme hires for.</p>
        <div class="grid-3">
            @foreach ($closed as $o)
                <div class="card link-card">
                    <span class="tag gray">{{ $o->kindLabel() }}</span>
                    @if ($o->area)<span class="tag gray">{{ $o->area }}</span>@endif
                    <h3>{{ $o->title }}</h3>
                    <p>{{ \Illuminate\Support\Str::limit($o->description, 150) }}</p>
                    <ul class="delivered-meta">
                        <li>Where: {{ $o->county ?: '—' }}</li>
                        <li>Closed: {{ $o->closing_on ? $o->closing_on->format('d M Y') : ($o->status === 'closed' ? 'taken down' : '—') }}</li>
                    </ul>
                    <p style="margin:12px 0 0;"><a href="{{ route('careers.opening', $o) }}">Read the opening →</a></p>
                </div>
            @endforeach
        </div>
    </section>
@endif
@endsection
