@php
    $supplier = $order->supplier;
    $greeting = $supplier?->contact_name ?: ($supplier?->name ?: 'Partner');
@endphp
<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f7f4;font-family:Segoe UI,Arial,sans-serif;color:#1d2b23;">
    <div style="max-width:680px;margin:0 auto;">
        <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">
            Hello {{ $greeting }},
        </p>
        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
            This is purchase order <b>{{ $order->reference }}</b> from {{ config('app.name') }} for
            <b>{{ $order->request->item_name }}</b> — {{ $order->orderedLabel() }} at
            {{ \App\Support\Finance::ksh((float) $order->unit_cost_ksh) }}, a total of
            <b>{{ $order->amountLabel() }}</b>. The document is below: print it, keep it, and quote
            {{ $order->reference }} on your delivery note and invoice so the office matches both to this order.
        </p>

        @if ($note)
            <p style="margin:0 0 18px;font-size:14px;line-height:1.7;background:#eef8ec;border:1px solid #cfe6bd;
                      border-radius:10px;padding:12px 14px;">
                {{ $note }}
            </p>
        @endif

        @include('partials.order-body', ['order' => $order])

        <p style="margin:18px 0 0;font-size:13px;line-height:1.7;color:#5b6b5f;">
            Goods are paid for after the store signs the delivery note in. If the price or the quantity above is not
            what was agreed, reply to this email before delivering and the office will settle it on this same order
            number — the programme does not send a second order over the first.
        </p>
    </div>
</body>
</html>
