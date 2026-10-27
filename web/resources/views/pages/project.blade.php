@extends('layouts.app')
@section('title', $project->title)

@php $delivered = (bool) $order; @endphp

@section('content')
<section class="section">
    <p class="back"><a href="{{ route('projects') }}">&larr; Back to projects</a></p>

    <span class="tag gray">{{ $project->categoryLabel() }}</span>
    <h1>{{ $project->title }}</h1>
    <p class="hint" style="margin:4px 0 14px;">{{ $project->reference }} · posted {{ $project->created_at->format('d M Y') }}
        · {{ \App\Models\Project::STATUSES[$project->status] }}</p>

    <span class="window{{ $project->status === 'open' && ! $project->deadline?->isPast() ? '' : ' closed' }}">
        {{ $project->applicationWindow() }}
    </span>

    <div class="proj-grid" style="margin-top:18px;">
        <div class="card">
            <h3>What the work is</h3>
            <p style="white-space:pre-line;margin-top:0;">{{ $project->description }}</p>
            @if ($project->materials)
                <h3 style="margin:18px 0 6px;">Materials required</h3>
                <p style="white-space:pre-line;margin:0;">{{ $project->materials }}</p>
            @endif
            @if ($delivered && $order->deliverables)
                <h3 style="margin:18px 0 6px;">What was delivered</h3>
                <p style="white-space:pre-line;margin:0;">{{ $order->deliverables }}</p>
            @endif
            @if ($project->hirer->organisation)
                <p class="hint">Client: <b>{{ $project->hirer->organisation }}</b>
                    @if ($project->hirer->county) · {{ $project->hirer->county }}@endif</p>
            @endif
        </div>

        <div class="card">
            <h3>At a glance</h3>
            <dl class="kv">
                <div><dt>Trade</dt><dd>{{ $project->trade ?: 'Any trade' }}</dd></div>
                <div><dt>Place</dt><dd>{{ collect([$project->county, $project->ward])->filter()->implode(', ') ?: 'To be confirmed' }}
                    @if ($project->community->exists)<div class="hint">Community: {{ $project->community->name }}</div>@endif</dd></div>
                <div><dt>Budget</dt><dd>{{ $project->budget !== null ? 'KSh '.number_format((float) $project->budget, 2) : 'Discussed on award' }}</dd></div>
                <div><dt>Duration</dt><dd>{{ $project->duration ?: '—' }}</dd></div>
                <div><dt>Applications</dt><dd>{{ $project->applicationWindow() }}</dd></div>
                @if ($delivered)
                    <div><dt>Delivered</dt><dd>{{ ($order->confirmed_on ?: $order->closed_on)?->format('d M Y') ?: 'Signed off' }}
                        by {{ $order->assignee_name ?: 'an E-Fundi' }}</dd></div>
                @endif
            </dl>

            @if ($project->status === 'open' && ! $project->deadline?->isPast())
                <div class="apply-state">
                    <p class="hint" style="margin:0 0 10px;">Applying is done from the member portal, so the office can
                        verify the skills behind each application.</p>
                    <a class="btn btn-primary" href="{{ route('register.fundi') }}">Register as a Fundi</a>
                    &nbsp;
                    <a class="btn btn-ghost btn-sm" href="{{ route('login') }}">Log in to apply</a>
                </div>
            @elseif ($project->status === 'open')
                <div class="apply-state">
                    <p class="hint" style="margin:0;">The application window for this project has closed. The office is
                        reviewing what came in.</p>
                </div>
            @else
                <div class="apply-state">
                    <p class="hint" style="margin:0;">This project is no longer taking applications. New work appears on
                        the <a href="{{ route('projects') }}">projects board</a> as hirers post it.</p>
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
