@extends('layouts.app')
@section('title', 'Donate')

@section('content')
<section class="section grid-2">
    <div>
        <span class="tag">{{ content('donate','intro','tag','Donate') }}</span>
        <h1>{{ content('donate','intro','title','Fund a skill. Change a life.') }}</h1>
        <p class="sub">{{ content('donate','intro','sub','Every donation goes directly to programme work. One-time or recurring — M-Pesa, card or bank.') }}</p>
    </div>

    <div class="card">
        <h2>Donation form</h2>
        @include('partials.errors')
        <form method="POST" action="{{ route('donate.store') }}">
            @csrf
            <div class="field">
                <label>Amount (KSh) <span class="req">*</span></label>
                <input type="number" name="amount" min="1" step="1" value="{{ old('amount') }}" required>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label>Payment method <span class="req">*</span></label>
                    <select name="method" required>
                        @foreach (['mpesa' => 'M-Pesa', 'card' => 'Card', 'bank' => 'Bank transfer'] as $k => $v)
                            <option value="{{ $k }}" @selected(old('method', 'mpesa') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Frequency <span class="req">*</span></label>
                    <select name="frequency" required>
                        @foreach (['one_time' => 'One-time', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $k => $v)
                            <option value="{{ $k }}" @selected(old('frequency', 'one_time') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label>Your name</label>
                    <input type="text" name="donor_name" value="{{ old('donor_name') }}" maxlength="120">
                </div>
                <div class="field">
                    <label>M-Pesa phone / contact</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" maxlength="20" placeholder="07XX XXX XXX">
                </div>
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}">
            </div>
            <div class="field">
                <label>Message (optional)</label>
                <textarea name="message" rows="3">{{ old('message') }}</textarea>
            </div>
            <div class="field checkbox-row">
                <input type="checkbox" name="anonymous" value="1" @checked(old('anonymous'))>
                <label>Donate anonymously (leaderboard shows no name)</label>
            </div>
            <button class="btn btn-lime btn-lg btn-block" type="submit">Record my pledge</button>
        </form>
    </div>
</section>

@include('partials.content-extra', ['page' => 'donate', 'used' => ['intro']])
@endsection
