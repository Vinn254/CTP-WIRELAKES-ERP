<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f7f4;font-family:Segoe UI,Arial,sans-serif;color:#1d2b23;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #dce6dc;border-radius:12px;padding:28px;">
        <p style="margin:0 0 6px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#2f7a44;">
            {{ config('app.name') }}
        </p>
        <h1 style="margin:0 0 14px;font-size:22px;">Reset your password</h1>
        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
            This link opens once, and only for {{ $minutes }} minutes.
        </p>
        <p style="margin:0 0 22px;">
            <a href="{{ $url }}"
               style="display:inline-block;background:#2f7a44;color:#ffffff;text-decoration:none;font-weight:600;
                      padding:12px 22px;border-radius:8px;">Choose a new password</a>
        </p>
        <p style="margin:0 0 10px;font-size:13px;color:#4c5b50;word-break:break-all;">
            If the button above does not work, open this address in your browser:<br>{{ $url }}
        </p>
        <p style="margin:0;font-size:13px;color:#4c5b50;">
            Nobody at the programme will ever email you asking for your password. If you did not request this,
            ignore the email and tell a super admin.
        </p>
    </div>
</body>
</html>
