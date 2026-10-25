<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f7f4;font-family:Segoe UI,Arial,sans-serif;color:#1d2b23;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #dce6dc;border-radius:12px;padding:28px;">
        <p style="margin:0 0 6px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#2f7a44;">
            {{ config('app.name') }}
        </p>
        <h1 style="margin:0 0 14px;font-size:22px;">
            {{ $login ? 'Confirm it is you signing in' : 'Confirm your new account' }}
        </h1>
        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
            {{ $login
                ? 'A sign-in was attempted on your office account. Enter this code on the confirmation screen.'
                : 'Your account is set up. Enter this code to confirm the email address belongs to you.' }}
        </p>
        <p style="margin:0 0 18px;font-size:34px;font-weight:700;letter-spacing:8px;color:#1d2b23;background:#eef6ef;
                  border:1px dashed #2f7a44;border-radius:10px;padding:16px;text-align:center;">
            {{ $code }}
        </p>
        <p style="margin:0;font-size:14px;color:#4c5b50;">
            It stops working after {{ $minutes }} minutes, or after {{ config('account.otp.max_attempts') }} wrong tries.
            If you did not ask for it, tell a super admin — nobody needs your code, so do not send it to anyone.
        </p>
    </div>
</body>
</html>
