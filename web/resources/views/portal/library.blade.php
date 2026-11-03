@extends('layouts.member')
@section('title', 'My library')

@section('content')
@php
    use App\Support\Library;

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

<div class="card paybill-card">@include('partials.paybill')</div>

<form method="GET" action="{{ route('portal.library') }}" class="filter-bar inline-filter">
    <label class="field">
        <span>Programme</span>
        <select name="training_id" onchange="this.form.submit()">
            <option value="">Everything on my shelf</option>
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
        @php
            // Reading is free; only saving the file to the phone is charged, and a trainer is never charged at all.
            $row = $kept->get($item->id);
            $price = Library::price($item);
            $open = ! $charges || $row?->isPaid();
        @endphp
        <div class="card">
            <div class="section-head">
                <h3>{{ $item->title }}</h3>
                <span class="tag">{{ $item->kindLabel() }}</span>
                <span class="tag">{{ $item->courseLabel() }}</span>
                @if ($row?->isPaid())
                    <span class="tag ok">Paid {{ $row->dateLabel() }}</span>
                @endif
            </div>
            <p class="ah-meta">
                <span>posted {{ $item->dateLabel() }}</span>
                <span>{{ $item->sizeLabel() }}</span>
                @if ($charges && ! $open)<span>keep it for {{ Library::priceLabel($price) }}</span>@endif
            </p>
            @if ($item->note)<p class="hint">{{ $item->note }}</p>@endif
            @if ($row?->isWaiting())
                <p class="help">{{ $row->statusLabel() }}. Send {{ Library::priceLabel($price) }} to Paybill
                    <b>{{ $paybill['number'] }}</b>, account <b>{{ $paybill['account'] }}</b>, then tell the office below.</p>
            @elseif ($row && $row->status === 'declined')
                <p class="help">The office did not confirm this payment.
                    @if ($row->decision_note)<span>{{ $row->decision_note }}</span>@endif
                    Send it again and tell them.</p>
            @endif
            <div class="module-foot">
                <a class="btn btn-sm btn-lime" href="{{ route('portal.library.open', $item) }}" target="_blank"
                   rel="noopener">Read it</a>
                @if ($open)
                    <a class="btn btn-sm btn-ghost"
                       href="{{ download_href(route('portal.library.open', $item)) }}">Download</a>
                @else
                    <button class="btn btn-sm btn-ghost" type="button"
                            data-sheet="pay-{{ $item->id }}">Download · {{ Library::priceLabel($price) }}</button>
                @endif
            </div>
        </div>
    @endforeach
@empty
    <div class="card">
        <h3>Nothing on your shelf yet</h3>
        <p class="help">This page carries the reading the Academic Registrar filed under your own programme, plus
            anything filed for every course. Nothing they file for another programme appears here. If you were told to
            read something and it is not on the shelf, tell the Training office — they either file it for your course or
            place you on the course it was filed under.</p>
    </div>
@endforelse

<p class="hint">You read your own programme's filing and the reading filed for every course. The library opens only from
    your signed-in account — a link to a title will not show it to anyone else.</p>

@if ($charges)
    @foreach ($items as $item)
        @php $row = $kept->get($item->id); @endphp
        @continue($row?->isPaid())
        @include('partials.sheet-start', ['name' => 'pay-'.$item->id,
            'title' => 'Save this title to your phone',
            'sub' => 'Reading it here costs nothing. Keeping the file costs money, and the Academic Registrar confirms it.'])
        <p class="help"><b>{{ $item->title }}</b> — {{ $item->kindLabel() }} ·
            <b>{{ Library::priceLabel(Library::price($item)) }}</b>.</p>
        <div class="card paybill-card">@include('partials.paybill')</div>
        @if ($row?->isWaiting())
            <p class="help">You have already told the office about this payment. They check the Paybill and open the
                download — you do not have to send it twice.</p>
        @else
            <p class="help">Send the amount first, then tell the office here. Nothing is deleted; the download opens
                when they confirm.</p>
        @endif
        <form method="POST" action="{{ route('portal.library.pay', $item) }}">
            @csrf
            <input type="hidden" name="_sheet" value="pay-{{ $item->id }}">
            <div class="form-foot">
                <button class="btn btn-lime" type="submit">I have paid — tell the office</button>
                <button class="btn btn-outline" type="button" data-close-sheet>Not now</button>
            </div>
        </form>
        @include('partials.sheet-end')
    @endforeach
@endif
@endsection
