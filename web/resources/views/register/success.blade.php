@extends('layouts.app')
@section('title', 'Application received')

@php
    $names = ['ctp' => 'CTP', 'fundi' => 'E-Fundi', 'hirer' => 'Hirer'];
    $fees = ['ctp' => App\Models\Setting::get('membership_fee_ctp', '500'), 'fundi' => App\Models\Setting::get('membership_fee_fundi', '1000')];
@endphp

@section('content')
<section class="success-box">
    <div class="big-check">✓</div>
    <h1>Your {{ $names[$type] }} application has been received</h1>
    <p>Keep this reference — you will need it when our team contacts you:</p>
    <div class="ref-code">{{ $record->reference }}</div>

    <div class="card" style="text-align:left;margin-top:26px;">
        <h3>What happens next</h3>
        @if ($type === 'hirer')
            <ol>
                <li>An admin verifies your organisation (registration is free).</li>
                <li>On approval you receive a Hirer ID (e.g. HIR-0001) and portal access.</li>
                <li>You can then post your project to the marketplace.</li>
            </ol>
        @else
            <ol>
                <li>One-time membership fee: <strong>KSh {{ number_format((int) $fees[$type]) }}</strong> via M-Pesa or bank.
                    <span class="tag gold">Payment checkout comes online in the next phase — your application is already saved.</span></li>
                <li>A Director/Admin reviews your documents.</li>
                @if ($type === 'fundi')
                    <li>The admin contacts your referee to confirm the recommendation.</li>
                @endif
                <li>On approval you are issued a {{ $type === 'ctp' ? 'CTP' : 'Fundi' }} ID
                    ({{ $type === 'ctp' ? 'CTP-0001' : 'EF-0001' }} style) and receive welcome email + SMS with portal login details.</li>
            </ol>
        @endif
    </div>

    <p style="margin-top:22px;">
        <a class="btn btn-primary" href="{{ route('home') }}">Back to home</a>
        <a class="btn btn-outline" href="{{ route('contact') }}">Questions? Contact us</a>
    </p>
</section>
@endsection
