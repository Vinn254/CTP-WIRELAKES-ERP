@extends('layouts.app')
@section('title', 'Application received')

@php
    $names = ['ctp' => 'CTP', 'fundi' => 'E-Fundi'];
    $sponsor = $type === 'ctp' ? $record->sponsor : null;
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
            @if ($sponsor)
                <li>{{ $sponsor->name }} is sponsoring your membership.
                    <span class="tag gold">Sponsored</span> Your application is already with the review team.</li>
            @else
                <li>Your application is with the review team. {{ \App\Support\Finance::paybillLine() }}
                    Put your reference {{ $record->reference }} as the reason for any payment you make.</li>
            @endif
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
