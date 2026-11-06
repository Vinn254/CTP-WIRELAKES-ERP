@extends('layouts.app')
@section('title', 'Get the app')

@section('content')
<section class="section">
    <span class="tag">Mobile app</span>
    <h1>The WireLakes app for CTPs and E-Fundis</h1>
    <p class="sub">One app for every member of the programme. You sign in with the same email and password you use on
        this website, and the app opens on your own page — what you are studying, what you owe and what you have been
        given to do.</p>

    @if ($download || $playUrl)
        <div class="card">
            <h3>Version {{ $version ?: '1.0' }}</h3>
            @if ($download)
                <p>
                    <a class="btn btn-lime" href="{{ route('get-the-app.download') }}" download>Download the app (Android)</a>
                </p>
                <ul class="delivered-meta">
                    <li>Android package · {{ $size ?: 'size not recorded' }}</li>
                    @if ($publishedAt)<li>Put up {{ $publishedAt }}</li>@endif
                    <li>Works on Android 5.0 and above</li>
                </ul>
            @endif
            @if ($playUrl)
                <p style="margin-top:12px;">
                    <a class="btn btn-outline btn-sm" href="{{ \App\Support\SafeUrl::href($playUrl) }}"
                       target="_blank" rel="noopener">Open it on Google Play →</a>
                </p>
            @endif
            @if ($notes)
                <p style="margin-top:14px;"><b>What is new</b><br>{{ $notes }}</p>
            @endif
        </div>
    @else
        <div class="card">
            <h3>The app is not out for download yet</h3>
            <p>The office has not put up a package for this page. Everything the app will show is already on the
                website — <a href="{{ route('login') }}">sign in</a> and use it from your phone's browser in the meantime.</p>
        </div>
    @endif

    <div class="grid-3" style="margin-top:22px;">
        <div class="card">
            <h3>Your class</h3>
            <p>The units handed to you, the lecturer on each, your marks out of 100, the week's timetable and the
                handouts your trainer filed.</p>
        </div>
        <div class="card">
            <h3>Your record</h3>
            <p>Your track percentage, attendance, certificate once the office signs it, and the intakes you have been on.</p>
        </div>
        <div class="card">
            <h3>Work and money</h3>
            <p>Project invitations, work orders and what you must buy for them, plus every invoice you owe.</p>
        </div>
    </div>

    @if ($download)
        <section class="section">
            <h2>Installing the file</h2>
            <ol class="sub">
                <li>Tap the download button above and open the file when it finishes.</li>
                <li>Your phone will say the source is unknown — allow this one install from that browser.</li>
                <li>Sign in with the email and password you registered here with.</li>
            </ol>
            <p class="sub">iPhone: the app runs on the same website data; the office will post the Apple build here
                when it is signed. Until then use the browser version from <a href="{{ route('login') }}">Log in</a>.</p>
        </section>
    @endif
</section>
@endsection
