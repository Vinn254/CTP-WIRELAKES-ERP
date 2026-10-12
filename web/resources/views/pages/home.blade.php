@extends('layouts.app')
@section('title', 'Home')

@section('content')
<section class="hero" style="margin:0 -20px;">
    <div class="container">
        <h1>From a community need, we create trained people, certified fundis, completed projects and lasting jobs.</h1>
        <p class="lede">CTP-WireLakes connects Kenyan communities with technical training, skilled E-Fundis, employment, enterprise development and green-economy projects — across all 47 counties.</p>
        <div class="hero-ctas">
            <a class="btn btn-lime btn-lg" href="{{ route('register.ctp') }}">Become a CTP</a>
            <a class="btn btn-outline btn-lg" href="{{ route('register.fundi') }}">Become a Fundi</a>
            <a class="btn btn-outline btn-lg" href="{{ route('register.hirer') }}">Post a Project</a>
        </div>
    </div>
</section>

@php
    $tickerItems = [
        number_format($counters['ctps']).' Active CTPs',
        number_format($counters['fundis']).' Verified E-Fundis',
        number_format($counters['hirers']).' Registered Hirers',
        number_format($counters['partners']).' Registered Partners',
    ];
@endphp
<div class="ticker" aria-label="Live programme stats">
    <div class="ticker-track">
        @foreach ([$tickerItems, $tickerItems] as $copy)
            @foreach ($copy as $item)
                <span class="ticker-item">{{ $item }}</span>
            @endforeach
        @endforeach
    </div>
</div>

<section class="section">
    <h2>What the programme does</h2>
    <p class="sub">Six connected areas of work, run as one ecosystem.</p>
    <div class="grid-3">
        @foreach ([
            ['icon' => '🎓', 'title' => 'Training', 'desc' => 'Community-based technical training with certification recognised across the industry.'],
            ['icon' => '🛠️', 'title' => 'E-Fundi Marketplace', 'desc' => 'Verified fundis — intern, junior and senior tiers — matched to real projects.'],
            ['icon' => '🏗️', 'title' => 'Projects', 'desc' => 'Schools, businesses and communities post projects; the right team is assigned.'],
            ['icon' => '♻️', 'title' => 'E-Waste', 'desc' => 'Collection, repair, refurbishment and safe disposal — turning waste into work.'],
            ['icon' => '💼', 'title' => 'Entrepreneurship', 'desc' => 'Business support for fundis and CTPs so skills become enterprises.'],
            ['icon' => '🌱', 'title' => 'Green Economy', 'desc' => 'Solar, green tech and community technology serving a sustainable Kenya.'],
        ] as $c)
            <div class="card"><div class="icon">{{ $c['icon'] }}</div><h3>{{ $c['title'] }}</h3><p>{{ $c['desc'] }}</p></div>
        @endforeach
    </div>
    <p><a class="btn btn-primary" href="{{ route('what-we-do') }}">See all six areas of work</a></p>
</section>

<section class="section">
    <div class="donate-strip">
        <div>
            <h3>Every shilling has a visible impact</h3>
            <p>KSh 5,000 trains 1 CTP. KSh 50,000 equips a community computer corner.</p>
        </div>
        <a class="btn btn-lime btn-lg" href="{{ route('donate') }}">Donate now</a>
    </div>
</section>

@if ($partners->isNotEmpty())
<section class="section">
    <h2>Our partners</h2>
    <div class="grid-4">
        @foreach ($partners as $p)
            <div class="card" style="text-align:center;">{{ $p->name }}</div>
        @endforeach
    </div>
</section>
@endif
@endsection
