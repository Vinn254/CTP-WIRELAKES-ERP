@php
    use App\Support\Finance;

    $brandName = content('site', 'branding', 'brand_name', 'WireLakes Technologies');
    $paybill = Finance::paybill();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->reference }} — {{ $brandName }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2ef; font-family: Segoe UI, Arial, sans-serif; color: #1d2b23; }
        .bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: center;
               padding: 12px 16px; background: #066722; color: #fff; }
        .bar b { font-size: .95rem; margin-right: auto; }
        .bar a, .bar button { font: inherit; font-size: .85rem; font-weight: 700; color: #066722; background: #d8f3c8;
                              border: 0; border-radius: 8px; padding: 8px 14px; text-decoration: none; cursor: pointer; }
        .bar a.ghost { color: #fff; background: transparent; border: 1px solid rgba(255, 255, 255, .55); }
        .sheet { max-width: 780px; margin: 22px auto 40px; }
        .sheet > div { box-shadow: 0 12px 34px rgba(14, 40, 22, .13); }
        @media print {
            body { background: #fff; }
            .bar, .foot { display: none !important; }
            .sheet { margin: 0; max-width: none; }
            .sheet > div { box-shadow: none; border: 0 !important; }
            a { text-decoration: none; }
        }
        .foot { text-align: center; font-size: .82rem; color: #5b6b5f; padding: 0 16px 30px; }
    </style>
</head>
<body>
<div class="bar">
    <b>{{ $invoice->reference }} — {{ $invoice->memberName() }}</b>
    <button type="button" onclick="window.print()">Print / save as PDF</button>
    <a class="ghost" href="{{ $back ?? route('admin.invoices.show', $invoice) }}">{{ $backLabel ?? 'Back to the invoice' }}</a>
</div>

<div class="sheet">
    @include('partials.invoice-body', ['invoice' => $invoice])
</div>

<p class="foot">
    {{ Finance::paybillLine() }}
    <br>Quoting {{ $invoice->reference }} as the reason is what lets the office match your payment to this invoice.
</p>
</body>
</html>
