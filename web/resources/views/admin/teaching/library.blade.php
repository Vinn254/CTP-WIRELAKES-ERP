@extends('layouts.admin')
@section('title', 'Library')

@php
    // The Registrar's shelf, read the way the students see it: one block per programme.
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>Library</h1>
        <p class="page-lede">Books, notes, slides and past papers the Registrar put up for the classes.</p>
        <p class="ah-meta">
            <span>{{ $items->count() }} {{ Str::plural('title', $items->count()) }} on the shelf</span>
            <span>free for a trainer — read and save</span>
        </p>
    </div>
</div>

<form method="GET" action="{{ route('admin.teaching.library') }}" class="filter-bar inline-filter">
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
            </div>
            <p class="ah-meta">
                <span>posted {{ $item->dateLabel() }}</span>
                <span>{{ $item->sizeLabel() }}</span>
                <span>{{ $item->poster?->name ?: 'the office' }}</span>
            </p>
            @if ($item->note)<p class="hint">{{ $item->note }}</p>@endif
            <div class="module-foot">
                <a class="btn btn-sm btn-lime" href="{{ route('admin.teaching.library.open', $item) }}"
                   target="_blank" rel="noopener">Read it</a>
                <a class="btn btn-sm btn-ghost"
                   href="{{ download_href(route('admin.teaching.library.open', $item)) }}">Download</a>
            </div>
        </div>
    @endforeach
@empty
    <div class="card">
        <h3>Nothing on the shelf yet</h3>
        <p class="help">The Academic Registrar posts books, notes, slides and past papers here. A trainer keeps any of
            them for their class without paying — the charge falls on a student's own account alone.</p>
    </div>
@endforelse
@endsection
