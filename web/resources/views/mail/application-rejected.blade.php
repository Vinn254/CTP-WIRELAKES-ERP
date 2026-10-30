<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f7f4;font-family:Segoe UI,Arial,sans-serif;color:#1d2b23;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #dce6dc;border-radius:12px;padding:28px;">
        <p style="margin:0 0 6px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#2f7a44;">
            {{ config('app.name') }}
        </p>
        <h1 style="margin:0 0 14px;font-size:22px;">Your {{ $what }} application was not approved</h1>
        <p style="margin:0 0 10px;font-size:15px;line-height:1.6;">Hello {{ $name }}, the office reviewed your
            application and could not approve it as it stands.</p>
        <div style="margin:0 0 18px;padding:14px 16px;background:#f2f7f2;border-left:4px solid #1d5c2f;
                    border-radius:8px;font-size:15px;line-height:1.6;">{{ $reason }}</div>
        <p style="margin:0;font-size:14px;color:#4c5b50;">
            Once that is sorted out you are welcome to apply again, or reply to this email and the office will walk
            you through it. Your application stays on file either way.
        </p>
    </div>
</body>
</html>
