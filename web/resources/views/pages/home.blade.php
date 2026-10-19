@extends('layouts.app')
@section('title', 'Home')
@section('bodyClass', 'has-hero')

@php
    $slides = array_values(array_filter(array_column(content_rows('home', 'hero_slides'), 'image')));
    if (! $slides) $slides = [asset('images/hero-1.jpg'), asset('images/hero-2.jpg'), asset('images/hero-3.jpg')];
@endphp

@section('content')
<section class="hero">
    <div class="container hero-inner">
        <div class="hero-text">
            <span class="kicker">{{ content('home','hero','kicker','Community Technology Promoters · Kenya') }}</span>
            <h1>{{ content('home','hero','title','We train and certify people — then give the projects to our own Fundis.') }}</h1>
            <p class="lede">{{ content('home','hero','lede','CTP-WireLakes connects Kenyan communities with technical training, skilled E-Fundis, employment, enterprise development and green-economy projects — across all 47 counties.') }}</p>
            @include('partials.extra-fields', ['page' => 'home', 'section' => 'hero', 'used' => ['kicker', 'title', 'lede', 'cta1_label', 'cta1_url', 'cta2_label', 'cta2_url']])
            <div class="hero-ctas">
                <a class="btn btn-lime btn-lg" href="{{ content('home','hero','cta1_url','/register/ctp') }}">{{ content('home','hero','cta1_label','Become a CTP') }}</a>
                <a class="btn btn-outline btn-lg" href="{{ content('home','hero','cta2_url','/register/fundi') }}">{{ content('home','hero','cta2_label','Become a Fundi') }}</a>
            </div>
        </div>
        <div class="hero-slider" aria-label="Highlights">
            <div class="slides">
                @foreach ($slides as $src)
                    <div class="slide" style="background-image:url('{{ $src }}')"></div>
                @endforeach
            </div>
        </div>
    </div>
</section>

@php $cards = content_rows('home', 'programme_cards'); @endphp
@if ($cards)
<section class="section">
    <h2>{{ content('home','programme','title','What the programme does') }}</h2>
    <p class="sub">{{ content('home','programme','sub','Six connected areas of work, run as one ecosystem.') }}</p>
    <div class="grid-3">
        @foreach ($cards as $c)
            <div class="card"><div class="icon">{{ $c['icon'] ?? '' }}</div><h3>{{ $c['title'] ?? '' }}</h3><p>{{ $c['desc'] ?? '' }}</p></div>
        @endforeach
    </div>
    <p><a class="btn btn-primary" href="{{ content('home','programme','cta_url','/what-we-do') }}">{{ content('home','programme','cta_label','See all six areas of work') }}</a></p>
</section>
@endif

<section class="section">
    <div class="donate-strip">
        <div>
            <h3>{{ content('home','donate_strip','title','Every donation goes directly to programme work') }}</h3>
            <p>{{ content('home','donate_strip','text','Training fundis, certifying skills and equipping communities across Kenya.') }}</p>
        </div>
        <a class="btn btn-lime btn-lg" href="{{ route('donate') }}">{{ content('home','donate_strip','cta_label','Donate now') }}</a>
    </div>
</section>

@if ($partners->isNotEmpty())
<section class="section">
    <h2>{{ content('home','partners_heading','title','Our partners') }}</h2>
    <div class="grid-4">
        @foreach ($partners as $p)
            <div class="card partner-card" style="text-align:center;">
                @if ($p->logo_path)<img class="partner-logo" src="{{ asset('uploads/partners/'.$p->logo_path) }}" alt="{{ $p->name }} logo">@endif
                {{ $p->name }}
            </div>
        @endforeach
    </div>
</section>
@endif

@include('partials.content-extra', ['page' => 'home', 'used' => ['hero', 'hero_slides', 'programme', 'programme_cards', 'donate_strip', 'partners_heading']])
@endsection
