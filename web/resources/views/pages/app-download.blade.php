@extends('layouts.app')
@section('title', 'Get the app')

@section('content')
<section class="section">
    <span class="tag">Mobile app</span>
    <h1>The WireLakes app for CTPs and E-Fundis</h1>
    <p class="sub">One app for every member of the programme. You sign in with the same email and password you use on
        this website, and the app opens on your own page — what you are studying, what you owe and what you have been
        given to do.</p>

    <div class="card">
        <h3>Take it with you</h3>
        <p style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
            <a class="btn btn-primary" href="{{ \App\Support\MobileApp::webUrl() }}">Open the app</a>
            @if ($download)
                <a class="btn btn-lime" href="{{ route('get-the-app.download') }}" download>Install on Android</a>
            @endif
            @if ($playUrl)
                <a class="btn btn-outline" href="{{ \App\Support\SafeUrl::href($playUrl) }}"
                   target="_blank" rel="noopener">Get it on Google Play</a>
            @endif
        </p>
        <ul class="delivered-meta">
            <li>Sign in with the email and password you registered with</li>
            @if ($download)
                <li>Android file · {{ $size ?: 'size not recorded' }}@if ($version) · version {{ $version }}@endif</li>
                @if ($publishedAt)<li>Put up {{ $publishedAt }}</li>@endif
                <li>Works on Android 5.0 and above</li>
            @endif
        </ul>
        @if ($notes)
            <p style="margin-top:14px;"><b>What is new</b><br>{{ $notes }}</p>
        @endif
    </div>

    <div class="grid-3" style="margin-top:22px;">
        <div class="card">
            <h3>Who it is for</h3>
            <p>CTPs, E-Fundis of every grade and trainers — staff and fundi trainers alike. Everyone signs in with the
                account this website already holds for them.</p>
        </div>
    </div>

    @if ($download)
        <section class="section">
            <h2>Installing the file</h2>
            <ol class="sub">
                <li>Tap <b>Install on Android</b> and open the file when it finishes.</li>
                <li>Your phone will say the source is unknown — allow this one install from that browser.</li>
                <li>Sign in with the email and password you registered here with.</li>
            </ol>
        </section>
    @endif
</section>
@endsection
