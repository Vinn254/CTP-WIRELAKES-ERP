<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f7f4;font-family:Segoe UI,Arial,sans-serif;color:#1d2b23;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #dce6dc;border-radius:12px;padding:28px;">
        <p style="margin:0 0 6px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#2f7a44;">
            {{ config('app.name') }}
        </p>
        <h1 style="margin:0 0 14px;font-size:22px;">Your {{ $what }} application is approved</h1>
        <p style="margin:0 0 10px;font-size:15px;line-height:1.6;">
            Hello {{ $invite->name }}, the office has approved you as a {{ $what }}
            @if ($code)<b>{{ $code }}</b>@endif. Open your account by setting your own password on the link below.
        </p>
        <p style="margin:0 0 18px;">
            <a href="{{ $link }}"
               style="display:inline-block;background:#1d5c2f;color:#ffffff;text-decoration:none;border-radius:8px;
                      padding:12px 18px;font-weight:700;">Activate my account</a>
        </p>
        <p style="margin:0 0 12px;font-size:14px;color:#4c5b50;">
            The link stops working on {{ $invite->expires_at?->format('d M Y') ?: '30 days from today' }}. Ask the
            office for a new one if it runs out.
        </p>
        <p style="margin:0;font-size:14px;color:#4c5b50;">
            Did not apply for this? Tell the office and do not open the link.
        </p>
    </div>
</body>
</html>
