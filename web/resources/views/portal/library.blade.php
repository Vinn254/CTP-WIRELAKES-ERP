@extends('layouts.member')
@section('title', 'My library')

@section('content')
@php
    // The reading is filed the way the Registrar filed it: one block per programme.
    $blocks = $items->groupBy(fn ($item) => $item->courseLabel())
        ->sortKeys();
@endphp
<div class="head-row">
    <div>
        <h1>My library</h1>
        <p class="ah-meta">
            <span>{{ $items->count() }} {{ Str::plural('title', $items->count()) }} for you to read</span>
        </p>
    </div>
</div>

<form method="GET" action="{{ route('portal.library') }}" class="filter-bar inline-filter">
    <label class="field">
        <span>Programme</span>
        <select name="training_id" onchange="this.form.submit()">
            <option value="">Every programme</option>
            <option value="general" @selected($course === 'general')>Reading for every course</option>
            @foreach ($courses as $c)
                <option value="{{ $c->id }}" @selected($course === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="field">
        <span>Type</span>
        <select name="kind" onchange="this.form.submit()">
            <option value="all" @selected($kind === null)>Every type</option>
            @foreach ($kinds as $key => $label)
                <option value="{{ $key }}" @selected($kind === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <noscript><button class="btn btn-sm btn-outline" type="submit">Apply</button></noscript>
</form>

@forelse ($blocks as $label => $block)
    <h4 class="dl-group">{{ $label }} — {{ $block->count() }} {{ Str::plural('title', $block->count()) }}</h4>
    @foreach ($block as $item)
        <div class="card">
            <div class="section-head">
                <h3>{{ $item->title }}</h3>
                <span class="tag">{{ $item->kindLabel() }}</span>
                <span class="tag">{{ $item->courseLabel() }}</span>
            </div>
            <p class="ah-meta">
                <span>posted {{ $item->dateLabel() }}</span>
                <span>{{ $item->sizeLabel() }}</span>
            </p>
            @if ($item->note)<p class="hint">{{ $item->note }}</p>@endif
            <div class="module-foot">
                <a class="btn btn-sm btn-lime" href="{{ route('portal.library.open', $item) }}" target="_blank"
                   rel="noopener">Read it</a>
                <a class="btn btn-sm btn-ghost"
                   href="{{ download_href(route('portal.library.open', $item)) }}">Download</a>
            </div>
        </div>
    @endforeach
@empty
    <div class="card">
        <h3>Nothing on the shelf for you yet</h3>
        <p class="help">The Academic Registrar puts books, notes, slides and PDFs here for each course. When a title is
            filed under your training, or for every student, it appears on this page — read it in the browser or save
            it to your phone.</p>
        @if ($courses->isEmpty())
            <p class="hint">You are not on a class roll yet, so you only see reading filed for every course.
                <a href="{{ route('portal.training') }}">My training</a> lists what you can apply for.</p>
        @endif
    </div>
@endforelse

<p class="hint">A title stays private to this programme — it opens from your record and nowhere else.</p>
@endsection
