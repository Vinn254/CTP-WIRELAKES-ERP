<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f7f4;font-family:Segoe UI,Arial,sans-serif;color:#1d2b23;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #dce6dc;border-radius:12px;padding:28px;">
        <p style="margin:0 0 6px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#2f7a44;">
            {{ config('app.name') }}
        </p>
        <h1 style="margin:0 0 14px;font-size:22px;">Your {{ $what }} application has been received</h1>

        <p style="margin:0 0 14px;font-size:15px;">Dear {{ $member->full_name }},</p>

        <table style="width:100%;border-collapse:collapse;font-size:15px;">
            <tr>
                <td style="padding:6px 0;color:#4c5b50;width:140px;">Reference</td>
                <td style="padding:6px 0;"><b>{{ $member->reference }}</b></td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#4c5b50;">Register</td>
                <td style="padding:6px 0;">{{ $grade }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#4c5b50;">Phone</td>
                <td style="padding:6px 0;">{{ $member->phone ?: '—' }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#4c5b50;">Email</td>
                <td style="padding:6px 0;word-break:break-all;">{{ $member->email }}</td>
            </tr>
        </table>

        @if ($needsPay)
            <h2 style="margin:22px 0 8px;font-size:17px;">Pay the registration fee</h2>
            <p style="margin:0 0 12px;font-size:15px;">
                The registration fee is <b>{{ \App\Support\Finance::ksh($fee) }}</b>. Send it on the Paybill:
            </p>

            <table style="width:100%;border-collapse:collapse;font-size:15px;background:#f4f7f4;border-radius:8px;">
                <tr>
                    <td style="padding:8px 12px;color:#4c5b50;">Paybill number</td>
                    <td style="padding:8px 12px;"><b>{{ $paybill['number'] }}</b></td>
                </tr>
                <tr>
                    <td style="padding:8px 12px;color:#4c5b50;">Account number</td>
                    <td style="padding:8px 12px;"><b>{{ $paybill['account'] }}</b></td>
                </tr>
            </table>

            <p style="margin:12px 0 0;font-size:15px;">
                Put your reference <b>{{ $member->reference }}</b> as the reason for payment, so the fee is matched to
                your application.
            </p>
        @else
            <p style="margin:18px 0 0;font-size:15px;background:#f4f7f4;border-radius:8px;padding:12px 14px;">
                @if ($sponsor){{ $sponsor }} is sponsoring your membership. @endif
                There is no registration fee for you to pay.
            </p>
        @endif

        <h2 style="margin:22px 0 8px;font-size:17px;">What happens next</h2>
        <ol style="margin:0;padding-left:20px;font-size:15px;">
            @if ($needsPay)
                <li style="margin-bottom:6px;">You send the {{ \App\Support\Finance::ksh($fee) }} fee on the Paybill
                    above, quoting your reference.</li>
                <li style="margin-bottom:6px;">The <b>System Administrator</b> checks that the payment reached us, and
                    only then approves your application.</li>
            @else
                <li style="margin-bottom:6px;">Your application goes straight to the <b>System Administrator</b> for
                    approval.</li>
            @endif
            <li style="margin-bottom:6px;">The office reviews your documents.</li>
            <li>On approval your {{ $grade }} ID is issued, with an activation link to open your member account.</li>
        </ol>

        <p style="margin:18px 0 0;font-size:14px;color:#4c5b50;">
            Keep the reference handy when you call. Quote it on
            <b>{{ $phone ?: 'the programme line' }}</b>@if ($support) or write to <a href="mailto:{{ $support }}"
            style="color:#1d5c2f;word-break:break-all;">{{ $support }}</a>@endif.
        </p>
    </div>
</body>
</html>
