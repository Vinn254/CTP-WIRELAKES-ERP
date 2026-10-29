@extends('layouts.app')
@section('title', $meta['nav'])

@section('content')
<section class="section">
    <span class="tag">{{ $tag }}</span>
    <h1>{{ $heading }}</h1>
    <p class="sub">{!! nl2br(e($lede)) !!}</p>
</section>

@if ($offers)
    <section class="section">
        <span class="tag">What we do</span>
        <h2>{{ $work_title }}</h2>
        <p class="sub">{{ $work_note }}</p>

        @foreach ($offers as $o)
            @continue(trim((string) ($o['title'] ?? '').($o['desc'] ?? '')) === '')
            <article class="offer-row{{ $loop->odd ? ' is-flipped' : '' }}">
                <div class="offer-text">
                    <h3>{{ $o['title'] ?? '' }}</h3>
                    <p>{!! nl2br(e($o['desc'] ?? '')) !!}</p>
                </div>
                <div class="offer-picture">
                    @if (! empty($o['photo']))
                        <a class="offer-shot" href="{{ $o['photo'] }}" data-zoom
                           data-zoom-label="{{ $o['title'] ?? '' }}">
                            <img src="{{ $o['photo'] }}" alt="{{ $o['title'] ?? '' }}" loading="lazy">
                        </a>
                    @else
                        <span class="icon">
                            @include('partials.icon', ['n' => \App\Support\ProgrammeAreas::icon($area)])
                        </span>
                    @endif
                </div>
            </article>
        @endforeach
    </section>
@endif

@include('partials.area-gallery')
@include('partials.area-voices')
@include('partials.area-apply', ['area' => $area])

@include('partials.content-extra', ['page' => $area, 'used' => ['intro', 'offers', 'gallery', 'testimonials']])
@endsection
