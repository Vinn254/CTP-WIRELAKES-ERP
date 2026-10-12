@extends('layouts.app')
@section('title', 'Partners')

@section('content')
<section class="section">
    <span class="tag">{{ content('partners','intro','tag','Partners') }}</span>
    <h1>{{ content('partners','intro','title','Organisations that power the programme') }}</h1>

    @if ($partners->isEmpty())
        <div class="card" style="text-align:center;padding:36px;">
            <div class="icon" style="font-size:2.4rem;">{{ content('partners','empty_state','icon','🤝') }}</div>
            <h3>{{ content('partners','empty_state','title','The partner wall is being filled') }}</h3>
            <p>{{ content('partners','empty_state','text','Our first partner logos will appear here. Want to be one of them? Use the form below.') }}</p>
        </div>
    @else
        <div class="grid-4">
            @foreach ($partners as $p)
                <div class="card partner-card" style="text-align:center;">
                    @if ($p->logo_path)
                        <img class="partner-logo" src="{{ asset('uploads/partners/'.$p->logo_path) }}" alt="{{ $p->name }} logo">
                    @endif
                    <span class="tag {{ $p->tier === 'gold' ? 'gold' : '' }}">{{ $p->tier ?? 'Partner' }}</span>
                    <h3>{{ $p->name }}</h3>
                    @if ($p->caption)<p class="partner-caption">{{ $p->caption }}</p>@endif
                    @if ($p->website)<a href="{{ $p->website }}">Website</a>@endif
                </div>
            @endforeach
        </div>
    @endif
</section>

<section class="section">
    <div class="card" style="max-width:640px;margin:0 auto;">
        <h2>{{ content('partners','form','title','Become a Partner') }}</h2>
        <p class="sub">{{ content('partners','form','sub','Tiers: Platinum · Gold · Silver. Listed partners appear on the logo wall and home page.') }}</p>
        @include('partials.errors')
        <form method="POST" action="{{ route('partners.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label>Organisation name <span class="req">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="120">
            </div>
            <div class="form-grid">
                <div class="field">
                    <label>Contact email <span class="req">*</span></label>
                    <input type="email" name="contact_email" value="{{ old('contact_email') }}" required>
                </div>
                <div class="field">
                    <label>Website</label>
                    <input type="url" name="website" value="{{ old('website') }}" placeholder="https://…">
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label>Preferred tier</label>
                    <select name="tier">
                        <option value="">No preference</option>
                        @foreach (['platinum', 'gold', 'silver'] as $t)
                            <option value="{{ $t }}" @selected(old('tier') === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Logo (PNG / JPG / SVG)</label>
                    <input type="file" name="logo" accept="image/*">
                </div>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Submit partnership interest</button>
        </form>
    </div>
</section>

@include('partials.content-extra', ['page' => 'partners', 'used' => ['intro', 'empty_state', 'form']])
@endsection
