@php
    use App\Support\Finance;
@endphp
<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f7f4;font-family:Segoe UI,Arial,sans-serif;color:#1d2b23;">
    <div style="max-width:680px;margin:0 auto;">
        <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">
            Hello {{ $invoice->memberName() }},
        </p>
        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
            @if ($invoice->status === 'settled')
                This is the invoice {{ $invoice->reference }}, now settled in full. Thank you — nothing further is owed.
            @else
                This is invoice <b>{{ $invoice->reference }}</b> for <b>{{ $invoice->amountLabel() }}</b>,
                raised by the programme office
                @if ($invoice->due_on)and due by <b>{{ $invoice->due_on->format('d M Y') }}</b>@endif.
                The document is below; the same page can be printed or saved from the office.
            @endif
        </p>

        @include('partials.invoice-body', ['invoice' => $invoice])

        <p style="margin:18px 0 0;font-size:13px;line-height:1.7;color:#5b6b5f;">
            {{ Finance::paybillLine() }} Quote {{ $invoice->reference }} as the reason so the office can match your
            payment. If anything on this invoice looks wrong, reply to this email before paying and the office will
            correct it — the record is amended, never re-raised under a new number.
        </p>
    </div>
</body>
</html>
