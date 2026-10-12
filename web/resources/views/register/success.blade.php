@extends('layouts.app')
@section('title', 'Application received')

@php
    $names = ['ctp' => 'CTP', 'fundi' => 'E-Fundi'];
    $fees = [
        'ctp' => content('membership', 'fees', 'fee_ctp', App\Models\Setting::get('membership_fee_ctp', '500')),
        'fundi' => content('membership', 'fees', 'fee_fundi', App\Models\Setting::get('membership_fee_fundi', '1000')),
    ];
@endphp

@section('content')
<section class="success-box">
    <div class="big-check">✓</div>
    <h1>Your {{ $names[$type] }} application has been received</h1>
    <p>Keep this reference — you will need it when our team contacts you:</p>
    <div class="ref-code">{{ $record->reference }}</div>

    <div class="card" style="text-align:left;margin-top:26px;">
        <h3>What happens next</h3>
        <ol>
            <li>One-time membership fee: <strong>KSh {{ number_format((int) $fees[$type]) }}</strong> via M-Pesa or bank.
                <span class="tag gold">Payment checkout comes online in the next phase — your application is already saved.</span></li>
            <li>A Director/Admin reviews your documents.</li>
            @if ($type === 'fundi')
                <li>The admin contacts your referee to confirm the recommendation.</li>
            @endif
            <li>On approval you are issued a {{ $type === 'ctp' ? 'CTP' : 'Fundi' }} ID
                ({{ $type === 'ctp' ? 'CTP-0001' : 'EF-0001' }} style), plus an activation link by email or WhatsApp
                to set your password and open the member portal.</li>
        </ol>
    </div>

    <p style="margin-top:22px;">
        <a class="btn btn-primary" href="{{ route('home') }}">Back to home</a>
        <a class="btn btn-outline" href="{{ route('contact') }}">Questions? Contact us</a>
    </p>
</section>
@endsection
