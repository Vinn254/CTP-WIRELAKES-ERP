@extends('layouts.app')
@section('title', 'What We Do')

@section('content')
<section class="section">
    <span class="tag">{{ content('what-we-do','intro','tag','What we do') }}</span>
    <h1>{{ content('what-we-do','intro','title','Six areas of work, one connected ecosystem') }}</h1>
    <p class="sub">{!! nl2br(e(content('what-we-do','intro','sub',''))) !!}</p>

    <div class="grid-3">
        @foreach (content_rows('what-we-do', 'cards') as $c)
            <div class="card link-card">
                <div class="icon">{{ $c['icon'] ?? '' }}</div>
                <h3>{{ $c['title'] ?? '' }}</h3>
                <p>{{ $c['desc'] ?? '' }}</p>
                @if (! empty($c['cta_url']))
                    <a href="{{ $c['cta_url'] }}">{{ $c['cta_label'] ?? 'Learn more' }} →</a>
                @endif
            </div>
        @endforeach
    </div>
</section>

@include('partials.content-extra', ['page' => 'what-we-do', 'used' => ['intro', 'cards']])
@endsection
