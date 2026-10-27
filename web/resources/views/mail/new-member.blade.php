<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f7f4;font-family:Segoe UI,Arial,sans-serif;color:#1d2b23;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #dce6dc;border-radius:12px;padding:28px;">
        <p style="margin:0 0 6px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#2f7a44;">
            {{ config('app.name') }}
        </p>
        <h1 style="margin:0 0 14px;font-size:22px;">New {{ $what }} application on the register</h1>
        <table style="width:100%;border-collapse:collapse;font-size:15px;">
            <tr>
                <td style="padding:6px 0;color:#4c5b50;width:130px;">Name</td>
                <td style="padding:6px 0;"><b>{{ $member->full_name }}</b></td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#4c5b50;">Reference</td>
                <td style="padding:6px 0;">{{ $member->reference }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#4c5b50;">Phone</td>
                <td style="padding:6px 0;">{{ $member->phone ?: '—' }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#4c5b50;">Email</td>
                <td style="padding:6px 0;word-break:break-all;">{{ $member->email ?: '—' }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#4c5b50;">Where</td>
                <td style="padding:6px 0;">{{ collect([$member->ward ?? null, $member->sub_county ?? null, $member->county ?? null])->filter()->implode(', ') ?: '—' }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#4c5b50;">Status</td>
                <td style="padding:6px 0;">{{ $member->status }}</td>
            </tr>
        </table>
        <p style="margin:18px 0 0;">
            <a href="{{ $link }}"
               style="display:inline-block;background:#1d5c2f;color:#ffffff;text-decoration:none;border-radius:8px;
                      padding:12px 18px;font-weight:700;">Open the record</a>
        </p>
    </div>
</body>
</html>
