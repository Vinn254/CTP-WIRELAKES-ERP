@php
    $paybill = \App\Support\Finance::paybill();
@endphp
<p class="paybill-line">For any type of payment use this Paybill <b>{{ $paybill['number'] }}</b>,
    account number <b>{{ $paybill['account'] }}</b>.</p>
