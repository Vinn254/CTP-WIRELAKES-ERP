@extends('layouts.app')
@section('title', 'What We Do')

@section('content')
<section class="section">
    <span class="tag">{{ content('what-we-do','intro','tag','What we do') }}</span>
    <h1>{{ content('what-we-do','intro','title','Six areas of work, one connected ecosystem') }}</h1>
    <p class="sub">{!! nl2br(e(content('what-we-do','intro','sub',''))) !!}</p>

    <div class="grid-3">
        @foreach (content_rows('what-we-do', 'cards') as $c)
            @php $area = \App\Support\ProgrammeAreas::slugForTitle($c['title'] ?? null); @endphp
            <div class="card link-card">
                <div class="icon">@include('partials.icon', ['n' => $c['icon'] ?? ''])</div>
                <h3>{{ $c['title'] ?? '' }}</h3>
                <p>{{ $c['desc'] ?? '' }}</p>
                @if ($area)
                    <a href="{{ route($area) }}">Open the {{ \App\Support\ProgrammeAreas::nav($area) }} page →</a>
                @endif
                @if (! empty($c['cta_url']))
                    <a href="{{ safe_href($c['cta_url']) }}">{{ $c['cta_label'] ?? 'Learn more' }} →</a>
                @endif
            </div>
        @endforeach
    </div>
</section>

@include('partials.delivered-projects')

@include('partials.content-extra', ['page' => 'what-we-do', 'used' => ['intro', 'cards']])
@endsection
