@php
    use App\Support\Finance;

    $brandName = content('site', 'branding', 'brand_name', 'WireLakes Technologies');
    $logo = content('site', 'branding', 'logo');
    $logo = $logo ? (str_starts_with($logo, 'http') ? $logo : url($logo)) : asset('logo.png');
    $programmeEmail = \App\Models\Setting::get('programme_email');
    $programmePhone = \App\Models\Setting::get('programme_phone');
    $supplier = $quotation->supplier;
    $pr = $quotation->request;
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
                    <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;opacity:.85;">Quotation</div>
                    <div style="font-size:20px;font-weight:800;">{{ $quotation->quotation_ref ?: $pr->reference }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div style="padding:24px 26px 6px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td valign="top" width="52%" style="padding-right:18px;">
                    <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{$muted}};">Supplier</div>
                    <div style="font-size:15px;font-weight:700;margin-top:6px;">{{ $supplier->name ?? 'The supplier' }}</div>
                    @if ($supplier?->contact_name)
                        <div style="font-size:13px;color:{{$muted}};">Attn. {{ $supplier->contact_name }}</div>
                    @endif
                    @if ($supplier?->phone)<div style="font-size:13px;color:{{$muted}};">{{ $supplier->phone }}</div>@endif
                    @if ($supplier?->email)<div style="font-size:13px;color:{{$muted}};">{{ $supplier->email }}</div>@endif
                    @if ($supplier)<div style="font-size:13px;color:{{$muted}};">Supplies {{ $supplier->suppliesLabel() }}</div>@endif
                </td>
                <td valign="top" width="48%">
                    <div style="font-size:13px;color:{{$muted}};">Quoted on
                        <b style="color:{{$ink}};">{{ $quotation->quoted_on?->format('d M Y') ?? 'date not recorded' }}</b></div>
                    <div style="font-size:13px;color:{{$muted}};margin-top:4px;">Against request
                        <b style="color:{{$ink}};">{{ $pr->reference }}</b></div>
                    @if ($pr->needed_by)
                        <div style="font-size:13px;color:{{$muted}};margin-top:4px;">Wanted by {{ $pr->needed_by->format('d M Y') }}</div>
                    @endif
                    <div style="font-size:13px;margin-top:8px;">
                        <span style="display:inline-block;background:{{ $quotation->is_selected ? '#e7f4e6' : '#f2f4f6' }};
                                     color:{{ $quotation->is_selected ? $green : $muted }};border-radius:999px;
                                     padding:3px 11px;font-weight:700;font-size:12px;">
                            {{ $quotation->is_selected ? 'Price accepted' : 'On file' }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div style="padding:16px 26px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <tr>
                <td style="border-bottom:1px solid {{$line}};padding:0 0 8px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{$muted}};">What was asked for</td>
                <td align="right" style="border-bottom:1px solid {{$line}};padding:0 0 8px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{$muted}};">Quantity</td>
                <td align="right" style="border-bottom:1px solid {{$line}};padding:0 0 8px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{$muted}};">Price quoted</td>
            </tr>
            <tr>
                <td style="padding:12px 0;font-size:15px;font-weight:700;">{{ $pr->item_name }}
                    @if ($pr->description)
                        <div style="font-size:12px;font-weight:400;color:{{$muted}};margin-top:4px;">{{ $pr->description }}</div>
                    @endif
                </td>
                <td align="right" style="padding:12px 0;font-size:14px;">
                    {{ rtrim(rtrim(number_format((float) $pr->quantity, 2), '0'), '.') }} {{ $pr->unit }}</td>
                <td align="right" style="padding:12px 0;font-size:15px;font-weight:700;">{{ $quotation->amountLabel() }}</td>
            </tr>
            <tr>
                <td colspan="2" style="border-top:2px solid {{$green}};padding:12px 0;font-size:15px;font-weight:800;color:{{$green}};">
                    Total quoted</td>
                <td align="right" style="border-top:2px solid {{$green}};padding:12px 0;font-size:18px;font-weight:800;color:{{$green}};">
                    {{ $quotation->amountLabel() }}</td>
            </tr>
        </table>
    </div>

    @if ($quotation->note)
        <div style="margin:14px 26px 0;background:#eef8ec;border:1px solid #cfe6bd;border-radius:10px;padding:14px 16px;">
            <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{$green}};font-weight:800;">
                Terms the supplier wrote</div>
            <div style="font-size:13px;margin-top:6px;line-height:1.7;color:{{$ink}};">{{ $quotation->note }}</div>
        </div>
    @endif

    <div style="padding:14px 26px 0;font-size:13px;color:{{$muted}};line-height:1.7;">
        This is the quotation as the programme office holds it, attached to {{ $pr->reference }}. If the figure or the
        terms above are not what your firm quoted, reply to this email and the office corrects the record — a price is
        never retyped over another one.
    </div>

    <div style="padding:16px 26px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td valign="bottom" style="width:50%;padding-right:24px;">
                    <div style="border-bottom:1px solid {{$ink}};height:38px;"></div>
                    <div style="font-size:12px;color:{{$muted}};margin-top:6px;">
                        Checked by the Procurement Officer</div>
                </td>
                <td valign="bottom">
                    <div style="border-bottom:1px solid {{$ink}};height:38px;"></div>
                    <div style="font-size:12px;color:{{$muted}};margin-top:6px;">
                        Confirmed by {{ $supplier->name ?? 'the supplier' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-top:20px;border-top:1px solid {{$line}};padding:14px 26px 20px;font-size:12px;color:{{$muted}};">
        Put on file by {{ $quotation->adder->name ?? 'the office' }} · {{ $brandName }}
        @if ($programmeEmail) · {{ $programmeEmail }}@endif
        @if ($quotation->sent_to) · sent to {{ $quotation->sent_to }}@endif
    </div>
</div>
