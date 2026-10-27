@extends('layouts.app')
@section('title', 'About')

@section('content')
<section class="section">
    <span class="tag">{{ content('about','intro','tag','About the programme') }}</span>
    <h1>{{ content('about','intro','title','Our origin') }}</h1>
    <p>{!! nl2br(e(content('about','intro','body1','WireLakes Technologies started in Kisumu, Kenya.'))) !!}</p>
    <p>{!! nl2br(e(content('about','intro','body2',''))) !!}</p>
</section>

@php $pillars = content_rows('about', 'pillars'); @endphp
@if ($pillars)
<section class="section grid-3">
    @foreach ($pillars as $p)
        <div class="card">
            <h3>{{ $p['title'] ?? '' }}</h3>
            <p>{{ $p['desc'] ?? '' }}</p>
        </div>
    @endforeach
</section>
@endif

<section class="section">
    <h2>{{ content('about','operate','title','Where we operate') }}</h2>
    <p class="sub">{!! nl2br(e(content('about','operate','sub',''))) !!}</p>
    <div class="card" style="text-align:center;padding:40px;">
        <div class="icon icon-lg">@include('partials.icon', ['n' => content('about','operate','card_icon','🗺️')])</div>
        <h3>{{ content('about','operate','card_title','Kisumu · Kenya') }}</h3>
        <p>{{ content('about','operate','card_text','') }}</p>
    </div>
</section>

@include('partials.content-extra', ['page' => 'about', 'used' => ['intro', 'pillars', 'operate']])
@endsection
