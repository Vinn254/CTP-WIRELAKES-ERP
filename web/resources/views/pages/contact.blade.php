@extends('layouts.app')
@section('title', 'Contact')

@section('content')
<section class="section grid-2">
    <div>
        <span class="tag">{{ content('contact','intro','tag','Contact') }}</span>
        <h1>{{ content('contact','intro','title','Talk to us') }}</h1>
        <div class="card">
            <h3>{{ content('contact','office','card_title','Kisumu head office') }}</h3>
            <p>{!! nl2br(e(content('contact','office','address',"CTP-WireLakes Programme\nKisumu, Kenya"))) !!}</p>
            <p>📞 {{ content('contact','office','phone','+254 700 000 000') }}<br>✉️ <a href="mailto:{{ $officeEmail = content('contact','office','email','info@ctp-wirelakes.ke') }}">{{ $officeEmail }}</a></p>
        </div>
        <p style="margin-top:16px;"><a class="btn btn-outline" href="{{ route('faq') }}">{{ content('contact','office','faq_note','Read the FAQ first? Many answers are there →') }}</a></p>
    </div>
    <div class="card">
        <h2>{{ content('contact','form','title','Send a message') }}</h2>
        @include('partials.errors')
        <form method="POST" action="{{ route('contact.store') }}">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Name <span class="req">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="120">
                </div>
                <div class="field">
                    <label>Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" maxlength="20">
                </div>
            </div>
            <div class="field">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required>
            </div>
            <div class="field">
                <label>Message <span class="req">*</span></label>
                <textarea name="message" rows="5" required>{{ old('message') }}</textarea>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Send message</button>
        </form>
    </div>
</section>

@include('partials.content-extra', ['page' => 'contact', 'used' => ['intro', 'office', 'form']])
@endsection
