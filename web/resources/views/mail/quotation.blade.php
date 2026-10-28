@php
    $supplier = $quotation->supplier;
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
            @if ($quotation->is_selected)
                This is your quotation <b>{{ $quotation->quotation_ref ?: 'for '.$quotation->request->item_name }}</b>
                of <b>{{ $quotation->amountLabel() }}</b> for <b>{{ $quotation->request->item_name }}</b>.
                The programme office has taken this as its price of record against
                <b>{{ $quotation->request->reference }}</b> — a purchase order follows on the same figure. Please
                check the copy below and tell us at once if it is not what your firm quoted.
            @else
                This is the quotation the programme office holds from your firm:
                <b>{{ $quotation->amountLabel() }}</b> for <b>{{ $quotation->request->item_name }}</b>, filed against
                <b>{{ $quotation->request->reference }}</b>. Confirm the price and the terms still stand, or send the
                revised figure quoting {{ $quotation->request->reference }}.
            @endif
        </p>

        @if ($note)
            <p style="margin:0 0 18px;font-size:14px;line-height:1.7;background:#eef8ec;border:1px solid #cfe6bd;
                      border-radius:10px;padding:12px 14px;">
                {{ $note }}
            </p>
        @endif

        @include('partials.quotation-body', ['quotation' => $quotation])

        <p style="margin:18px 0 0;font-size:13px;line-height:1.7;color:#5b6b5f;">
            Quotations are kept on the programme's procurement file and one is chosen as the price of record before a
            purchase order is written. Nothing is payable until that order is delivered and its delivery note signed.
        </p>
    </div>
</body>
</html>
