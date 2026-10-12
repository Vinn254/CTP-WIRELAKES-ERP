@extends('layouts.app')
@section('title', 'FAQ')

@section('content')
<section class="section" style="max-width:820px;">
    <span class="tag">{{ content('faq','intro','tag','FAQ') }}</span>
    <h1>{{ content('faq','intro','title','Frequently asked questions') }}</h1>
    @foreach (content_rows('faq', 'items') as $f)
        <div class="card" style="margin-bottom:14px;">
            <h3 style="margin-bottom:6px;">{{ $f['question'] ?? '' }}</h3>
            <p style="margin:0;color:var(--muted);">{{ $f['answer'] ?? '' }}</p>
        </div>
    @endforeach
</section>

@include('partials.content-extra', ['page' => 'faq', 'used' => ['intro', 'items']])
@endsection
