@php
    $mpesaLive = \App\Support\Finance::mpesaLive();
    $mpesaPaybill = \App\Support\Finance::paybill();
@endphp
<p class="paybill-line {{ $cls ?? '' }}">M-Pesa Paybill <b>{{ $mpesaPaybill['number'] }}</b>, account <b>{{ $mpesaPaybill['account'] }}</b> —
    {{ $mpesaLive
        ? 'the line is switched on. Take payment on M-Pesa, then record it here so the register carries it.'
        : 'the line is not integrated yet, so write this payment in by hand. The register line is the same either way.' }}
</p>
