<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f7f4;font-family:Segoe UI,Arial,sans-serif;color:#1d2b23;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #dce6dc;border-radius:12px;padding:28px;">
        <p style="margin:0 0 6px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#2f7a44;">
            {{ config('app.name') }}
        </p>
        <h1 style="margin:0 0 14px;font-size:22px;">We have received your request</h1>

        <p style="margin:0 0 14px;font-size:15px;">Dear {{ $application->name }},</p>
        <p style="margin:0 0 18px;font-size:15px;">
            Your request is on the programme office’s register. We will read it and contact you within
            <b>{{ \App\Models\ProgrammeApplication::TURNAROUND }}</b> on the details below.
        </p>

        <table style="width:100%;border-collapse:collapse;font-size:15px;">
            <tr>
                <td style="padding:6px 0;color:#4c5b50;width:140px;">Reference</td>
                <td style="padding:6px 0;"><b>{{ $application->reference }}</b></td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#4c5b50;">Area</td>
                <td style="padding:6px 0;">{{ $application->areaLabel() }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#4c5b50;">Service</td>
                <td style="padding:6px 0;">{{ $application->category ?: 'As explained below' }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#4c5b50;">Phone</td>
                <td style="padding:6px 0;">{{ $application->phone }}</td>
            </tr>
            @if ($application->whatsapp)
                <tr>
                    <td style="padding:6px 0;color:#4c5b50;">WhatsApp</td>
                    <td style="padding:6px 0;">{{ $application->whatsapp }}</td>
                </tr>
            @endif
            <tr>
                <td style="padding:6px 0;color:#4c5b50;">Email</td>
                <td style="padding:6px 0;word-break:break-all;">{{ $application->email }}</td>
            </tr>
        </table>

        @if ($application->details)
            <p style="margin:16px 0 0;font-size:15px;background:#f4f7f4;border-radius:8px;padding:12px 14px;">
                {{ $application->details }}
            </p>
        @endif

        <p style="margin:18px 0 0;font-size:14px;color:#4c5b50;">
            Keep the reference handy when you call. Quote it on
            <b>{{ $phone ?: 'the programme line' }}</b>@if ($support) or write to <a href="mailto:{{ $support }}"
            style="color:#1d5c2f;word-break:break-all;">{{ $support }}</a>@endif.
        </p>
    </div>
</body>
</html>
