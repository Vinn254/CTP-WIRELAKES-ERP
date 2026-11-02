@extends('layouts.app')
@section('title', 'Application received')

@php
    $names = ['ctp' => 'CTP', 'fundi' => 'E-Fundi'];
    $needsPay = $record->fee_status === 'awaiting_payment';
    $sponsor = $record->sponsorSummary();
    $fee = $record::feeAmount();
    $paybill = \App\Support\Finance::paybill();
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
            @if (! $needsPay)
                <li>@if ($sponsor){{ $sponsor }} is sponsoring your membership.
                    @else There is no registration fee on your application. @endif
                    <span class="tag gold">No fee to pay</span> Your application is already with the review team.</li>
            @else
                <li><b>Pay the {{ \App\Support\Finance::ksh($fee) }} registration fee</b> on the Paybill
                    <b>{{ $paybill['number'] }}</b>, account number <b>{{ $paybill['account'] }}</b>.
                    Put your reference {{ $record->reference }} as the reason for the payment.</li>
                <li>Once the payment is in, the <b>System Administrator</b> checks it against your reference and only
                    then approves your application.</li>
            @endif
            <li>A Director/Admin reviews your documents.</li>
            @if ($type === 'fundi' && $record->referee_name)
                <li>The admin may contact your referee to confirm the recommendation.</li>
            @endif
            <li>On approval you are issued a {{ $type === 'ctp' ? 'CTP' : 'Fundi' }} ID
                ({{ $type === 'ctp' ? 'CTP-0001' : 'EF-0001' }} style), plus an activation link by email or WhatsApp
                to set your password and open the member portal.</li>
        </ol>

        <p style="margin-top:16px;">We have posted the same instructions to
            <b>{{ $record->email }}</b>@if ($record->phone) and can reach you on <b>{{ $record->phone }}</b>@endif.
            If nothing arrives, check the spelling there or contact us below.</p>
    </div>

    <p style="margin-top:22px;">
        <a class="btn btn-primary" href="{{ route('home') }}">Back to home</a>
        <a class="btn btn-outline" href="{{ route('contact') }}">Questions? Contact us</a>
    </p>
</section>
@endsection
