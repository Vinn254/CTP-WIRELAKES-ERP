@php
    use App\Models\Setting;
    use App\Support\Finance;

    $brandName = content('site', 'branding', 'brand_name', 'WireLakes Technologies');
    $logo = content('site', 'branding', 'logo');
    $logo = $logo ? (str_starts_with($logo, 'http') ? $logo : url($logo)) : asset('logo.png');
    $programmeEmail = Setting::get('programme_email');
    $programmePhone = Setting::get('programme_phone');
    $paybill = Finance::paybill();
    $ink = '#1d2b23';
    $muted = '#5b6b5f';
    $line = '#dce6dc';
    $green = '#066722';
    $box = 'background:#ffffff;border:1px solid '.$line.';border-radius:12px;';
@endphp
<div style="max-width:680px;margin:0 auto;{{ $box }}padding:0;overflow:hidden;font-family:Segoe UI,Arial,sans-serif;color:{{ $ink }};">
    <div style="background:{{ $green }};color:#ffffff;padding:22px 26px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td valign="middle">
                    <img src="{{ $logo }}" alt="" width="46" height="46"
                         style="display:block;border-radius:8px;background:#ffffff;padding:4px;">
                </td>
                <td valign="middle" style="padding-left:12px;">
                    <div style="font-size:17px;font-weight:700;letter-spacing:.01em;">{{ $brandName }}</div>
                    <div style="font-size:12px;opacity:.85;">
                        @if ($programmeEmail){{ $programmeEmail }}@endif
                        @if ($programmePhone) · {{ $programmePhone }}@endif
                    </div>
                </td>
                <td valign="middle" align="right">
                    <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;opacity:.85;">Invoice</div>
                    <div style="font-size:20px;font-weight:800;">{{ $invoice->reference }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div style="padding:24px 26px 6px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td valign="top" width="50%" style="padding-right:18px;">
                    <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{ $muted }};">Billed to</div>
                    <div style="font-size:15px;font-weight:700;margin-top:6px;">{{ $invoice->memberName() }}</div>
                    @if ($invoice->party_name && $invoice->party_name !== $invoice->memberName())
                        <div style="font-size:13px;color:{{ $muted }};">{{ $invoice->party_name }}</div>
                    @endif
                    @if ($code = $invoice->ctp?->ctp_code ?: $invoice->fundi?->ef_code)
                        <div style="font-size:13px;color:{{ $muted }};">{{ $code }}</div>
                    @endif
                    @if ($invoice->email())
                        <div style="font-size:13px;color:{{ $muted }};">{{ $invoice->email() }}</div>
                    @endif
                    @if ($invoice->party_phone)
                        <div style="font-size:13px;color:{{ $muted }};">{{ $invoice->party_phone }}</div>
                    @endif
                </td>
                <td valign="top" width="50%">
                    <div style="font-size:13px;color:{{ $muted }};">Issued
                        <b style="color:{{ $ink }};">{{ $invoice->issued_on->format('d M Y') }}</b></div>
                    <div style="font-size:13px;color:{{ $muted }};margin-top:4px;">Due by
                        <b style="color:{{ $ink }};">{{ $invoice->due_on?->format('d M Y') ?: 'on receipt' }}</b></div>
                    @if ($invoice->project)
                        <div style="font-size:13px;color:{{ $muted }};margin-top:4px;">{{ $invoice->project->title }}</div>
                    @endif
                    <div style="font-size:13px;margin-top:8px;">
                        <span style="display:inline-block;background:{{ $invoice->status === 'settled' ? '#e7f4e6' : ($invoice->status === 'cancelled' ? '#f2f4f6' : '#fff3e0') }};
                                     color:{{ $invoice->status === 'settled' ? $green : ($invoice->status === 'cancelled' ? $muted : '#a4650a') }};
                                     border-radius:999px;padding:3px 11px;font-weight:700;font-size:12px;">
                            {{ $invoice->statusLabel() }}@if ($invoice->status === 'open' && $invoice->isOverdue()) · overdue @endif
                        </span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div style="padding:16px 26px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <tr>
                <td style="border-bottom:1px solid {{ $line }};padding:0 0 8px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{ $muted }};">What is being invoiced</td>
                <td align="right" style="border-bottom:1px solid {{ $line }};padding:0 0 8px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{ $muted }};">Amount</td>
            </tr>
            <tr>
                <td style="padding:12px 0;font-size:15px;font-weight:700;">{{ $invoice->title }}</td>
                <td align="right" style="padding:12px 0;font-size:15px;font-weight:700;">{{ $invoice->amountLabel() }}</td>
            </tr>
            @if ($invoice->details)
                <tr>
                    <td colspan="2" style="padding:0 0 12px;font-size:13px;line-height:1.6;color:{{ $muted }};">{{ $invoice->details }}</td>
                </tr>
            @endif
            @foreach ($invoice->payments->whereNull('voided_at') as $p)
                <tr>
                    <td style="padding:6px 0;font-size:13px;color:{{ $muted }};">
                        Paid {{ $p->happened_on->format('d M Y') }} · {{ $p->methodLabel() }}
                        @if ($p->reference) · {{ $p->reference }}@endif
                    </td>
                    <td align="right" style="padding:6px 0;font-size:13px;color:{{ $muted }};">− {{ $p->amountLabel() }}</td>
                </tr>
            @endforeach
            <tr>
                <td style="padding:14px 0 4px;font-size:13px;color:{{ $muted }};">Billed</td>
                <td align="right" style="padding:14px 0 4px;font-size:13px;color:{{ $muted }};">{{ $invoice->amountLabel() }}</td>
            </tr>
            <tr>
                <td style="padding:0 0 12px;font-size:13px;color:{{ $muted }};">Received so far</td>
                <td align="right" style="padding:0 0 12px;font-size:13px;color:{{ $muted }};">{{ $invoice->paidLabel() }}</td>
            </tr>
            <tr>
                <td style="border-top:2px solid {{ $green }};padding:12px 0;font-size:15px;font-weight:800;color:{{ $green }};">
                    {{ $invoice->status === 'cancelled' ? 'Invoice cancelled' : 'Balance due' }}</td>
                <td align="right" style="border-top:2px solid {{ $green }};padding:12px 0;font-size:18px;font-weight:800;color:{{ $green }};">
                    {{ $invoice->status === 'cancelled' ? '—' : $invoice->balanceLabel() }}</td>
            </tr>
        </table>
    </div>

    @if ($invoice->status !== 'cancelled')
        <div style="margin:6px 26px 0;background:#eef8ec;border:1px solid #cfe6bd;border-radius:10px;padding:14px 16px;">
            <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{ $green }};font-weight:800;">How to pay</div>
            <div style="font-size:14px;margin-top:6px;line-height:1.6;">
                For any payment use this Paybill <b>{{ $paybill['number'] }}</b>,
                account number <b>{{ $paybill['account'] }}</b>.
            </div>
            <div style="font-size:12px;color:{{ $muted }};margin-top:6px;">
                Put {{ $invoice->reference }} as the reason so the office can match your payment to this invoice.
            </div>
        </div>
    @endif

    @if ($invoice->note)
        <div style="padding:14px 26px 0;font-size:13px;color:{{ $muted }};line-height:1.6;">{{ $invoice->note }}</div>
    @endif
    @if ($invoice->status === 'cancelled')
        <div style="padding:14px 26px 0;font-size:13px;color:#a4650a;">
            Cancelled by {{ $invoice->canceller->name ?? 'the office' }}
            {{ $invoice->cancel_reason ? ' — '.$invoice->cancel_reason : '' }}
        </div>
    @endif

    <div style="margin-top:20px;border-top:1px solid {{ $line }};padding:14px 26px 20px;font-size:12px;color:{{ $muted }};">
        Raised by {{ $invoice->raiser->name ?? 'the office' }} · {{ $brandName }}
        @if ($programmeEmail) · {{ $programmeEmail }}@endif
    </div>
</div>
