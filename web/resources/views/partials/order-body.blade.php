@php
    use App\Support\Finance;

    $brandName = content('site', 'branding', 'brand_name', 'WireLakes Technologies');
    $logo = content('site', 'branding', 'logo');
    $logo = $logo ? (str_starts_with($logo, 'http') ? $logo : url($logo)) : asset('logo.png');
    $programmeEmail = \App\Models\Setting::get('programme_email');
    $programmePhone = \App\Models\Setting::get('programme_phone');
    $supplier = $order->supplier;
    $pr = $order->request;
    $ink = '#1d2b23';
    $muted = '#5b6b5f';
    $line = '#dce6dc';
    $green = '#066722';
@endphp
<div style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid {{$line}};border-radius:12px;overflow:hidden;font-family:Segoe UI,Arial,sans-serif;color:{{$ink}};">
    <div style="background:{{$green}};color:#ffffff;padding:22px 26px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td valign="middle">
                    <img src="{{ $logo }}" alt="" width="46" height="46"
                         style="display:block;border-radius:8px;background:#ffffff;padding:4px;">
                </td>
                <td valign="middle" style="padding-left:12px;">
                    <div style="font-size:17px;font-weight:700;">{{ $brandName }}</div>
                    <div style="font-size:12px;opacity:.85;">
                        @if ($programmeEmail){{ $programmeEmail }}@endif
                        @if ($programmePhone) · {{ $programmePhone }}@endif
                    </div>
                </td>
                <td valign="middle" align="right">
                    <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;opacity:.85;">Purchase order</div>
                    <div style="font-size:20px;font-weight:800;">{{ $order->reference }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div style="padding:24px 26px 6px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td valign="top" width="52%" style="padding-right:18px;">
                    <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{$muted}};">To</div>
                    <div style="font-size:15px;font-weight:700;margin-top:6px;">{{ $supplier->name ?? 'The supplier' }}</div>
                    @if ($supplier?->contact_name)
                        <div style="font-size:13px;color:{{$muted}};">Attn. {{ $supplier->contact_name }}</div>
                    @endif
                    @if ($supplier?->phone)<div style="font-size:13px;color:{{$muted}};">{{ $supplier->phone }}</div>@endif
                    @if ($supplier?->email)<div style="font-size:13px;color:{{$muted}};">{{ $supplier->email }}</div>@endif
                    @if ($supplier?->county)<div style="font-size:13px;color:{{$muted}};">{{ $supplier->county }}</div>@endif
                </td>
                <td valign="top" width="48%">
                    <div style="font-size:13px;color:{{$muted}};">Order date
                        <b style="color:{{$ink}};">{{ ($order->issued_on ?? $order->created_at)->format('d M Y') }}</b></div>
                    <div style="font-size:13px;color:{{$muted}};margin-top:4px;">Against request
                        <b style="color:{{$ink}};">{{ $pr->reference }}</b></div>
                    @if ($pr->needed_by)
                        <div style="font-size:13px;color:{{$muted}};margin-top:4px;">Wanted by {{ $pr->needed_by->format('d M Y') }}</div>
                    @endif
                    @if ($pr->county)
                        <div style="font-size:13px;color:{{$muted}};margin-top:4px;">Deliver to {{ $pr->county }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div style="padding:16px 26px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <tr>
                <td style="border-bottom:1px solid {{$line}};padding:0 0 8px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{$muted}};">Item</td>
                <td align="right" style="border-bottom:1px solid {{$line}};padding:0 0 8px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{$muted}};">Qty</td>
                <td align="right" style="border-bottom:1px solid {{$line}};padding:0 0 8px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{$muted}};">Unit price</td>
                <td align="right" style="border-bottom:1px solid {{$line}};padding:0 0 8px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{$muted}};">Amount</td>
            </tr>
            <tr>
                <td style="padding:12px 0;font-size:15px;font-weight:700;">{{ $pr->item_name }}
                    @if ($pr->description)
                        <div style="font-size:12px;font-weight:400;color:{{$muted}};margin-top:4px;">{{ $pr->description }}</div>
                    @endif
                </td>
                <td align="right" style="padding:12px 0;font-size:14px;">{{ $order->orderedLabel() }}</td>
                <td align="right" style="padding:12px 0;font-size:14px;">{{ Finance::ksh((float) $order->unit_cost_ksh) }}</td>
                <td align="right" style="padding:12px 0;font-size:15px;font-weight:700;">{{ $order->amountLabel() }}</td>
            </tr>
            <tr>
                <td colspan="3" style="border-top:2px solid {{$green}};padding:12px 0;font-size:15px;font-weight:800;color:{{$green}};">
                    Total payable on delivery</td>
                <td align="right"
                    style="border-top:2px solid {{$green}};padding:12px 0;font-size:18px;font-weight:800;color:{{$green}};">
                    {{ $order->amountLabel() }}</td>
            </tr>
        </table>
    </div>

    @if ($order->note || $pr->county)
        <div style="margin:14px 26px 0;background:#eef8ec;border:1px solid #cfe6bd;border-radius:10px;padding:14px 16px;">
            <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{$green}};font-weight:800;">
                Delivery and payment</div>
            <div style="font-size:13px;margin-top:6px;line-height:1.7;color:{{$ink}};">
                Present this order number and one delivery note with every consignment; the store counts goods against
                the quantity above. Quote {{ $order->reference }} on your invoice so the office matches it to the order.
                Payment is made after the delivery note is signed in.
            </div>
            @if ($order->note)
                <div style="font-size:13px;margin-top:8px;line-height:1.7;color:{{$ink}};"><b>On this order:</b> {{ $order->note }}</div>
            @endif
        </div>
    @endif

    @php $payTo = $supplier?->payLabel(); @endphp
    @if ($payTo && $payTo !== 'Payment details not recorded')
        <div style="padding:14px 26px 0;font-size:13px;color:{{$muted}};">
            <b style="color:{{$ink}};">Payment details we hold for you:</b> {{ $payTo }}
        </div>
    @endif

    <div style="padding:16px 26px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td valign="bottom" style="width:50%;padding-right:24px;">
                    <div style="border-bottom:1px solid {{$ink}};height:38px;"></div>
                    <div style="font-size:12px;color:{{$muted}};margin-top:6px;">
                        For {{ $brandName }} — issued by the programme office</div>
                </td>
                <td valign="bottom">
                    <div style="border-bottom:1px solid {{$ink}};height:38px;"></div>
                    <div style="font-size:12px;color:{{$muted}};margin-top:6px;">
                        Accepted and signed by {{ $supplier->name ?? 'the supplier' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-top:20px;border-top:1px solid {{$line}};padding:14px 26px 20px;font-size:12px;color:{{$muted}};">
        Raised by {{ $order->raiser->name ?? 'the office' }} · {{ $brandName }}
        @if ($programmeEmail) · {{ $programmeEmail }}@endif
        @if ($order->sent_to) · sent to {{ $order->sent_to }}@endif
    </div>
</div>
