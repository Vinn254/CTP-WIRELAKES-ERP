<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>WireLakes Technologies — Construction &amp; Technology Enterprise</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
    <link rel="alternate icon" type="image/png" href="{{ asset('logo.png') }}">
    <style>
        :root {
            --wl-green-dark: #0e6b3a;
            --wl-green-mid:  #2e8b3d;
            --wl-green-lime: #6cc24a;
            --wl-ink:        #10331f;
            --wl-bg:         #f4faf3;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--wl-bg);
            color: var(--wl-ink);
            min-height: 100vh;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 32px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(16, 51, 31, 0.08);
        }
        .brand { display: flex; align-items: center; gap: 14px; }
        .brand img { height: 56px; width: auto; }
        .brand .name {
            font-weight: 800;
            letter-spacing: .04em;
            color: var(--wl-green-dark);
            text-transform: uppercase;
            font-size: 14px;
        }
        .hero {
            max-width: 1100px;
            margin: 0 auto;
            padding: 64px 32px;
        }
        .hero h1 {
            font-size: clamp(28px, 4vw, 44px);
            line-height: 1.1;
            margin: 0 0 16px;
            color: var(--wl-green-dark);
        }
        .hero p.lead {
            font-size: 18px;
            line-height: 1.6;
            margin: 0 0 24px;
            color: #1a3a25;
        }
        .pill {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 999px;
            background: var(--wl-green-lime);
            color: var(--wl-ink);
            font-weight: 700;
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }
        .card {
            background: #fff;
            border: 1px solid #d8eed4;
            border-radius: 14px;
            padding: 20px;
        }
        .card h3 { margin: 0 0 8px; color: var(--wl-green-dark); font-size: 16px; }
        .card p { margin: 0; color: #355a40; line-height: 1.5; font-size: 14px; }
        .footer {
            text-align: center;
            padding: 32px;
            color: #4a6a55;
            font-size: 13px;
        }
        code {
            background: #e7f3e3;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 13px;
            color: var(--wl-green-dark);
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <img src="{{ asset('logo.svg') }}" alt="WireLakes Technologies logo">
            <div>
                <div class="name">WireLakes Technologies</div>
                <div style="font-size: 12px; color: #4a6a55;">Construction &amp; Technology Enterprise</div>
            </div>
        </div>
        <div>
            <a href="/api/health" style="margin-right: 16px; color: var(--wl-green-dark); text-decoration: none; font-weight: 600;">API Health</a>
            <a href="/api/me" style="color: var(--wl-green-dark); text-decoration: none; font-weight: 600;">/api/me</a>
        </div>
    </header>

    <main class="hero">
        <span class="pill">13 modules · 1 platform</span>
        <h1>Community technology, skills, and infrastructure for Kenya.</h1>
        <p class="lead">
            The WireLakes Technologies ERP runs CTP Promoters, Communities, Training, the E-Fundi marketplace,
            projects, construction, e-waste, finance, M&amp;E and a public dashboard — all from one Laravel
            backend, served to this web app and the Flutter mobile app.
        </p>

        <div class="grid">
            <div class="card">
                <h3>Web (Blade)</h3>
                <p>Designed in Laravel + Blade. This page is rendered server-side. Auth, dashboards, and admin tools will live here.</p>
            </div>
            <div class="card">
                <h3>Mobile (Flutter)</h3>
                <p>Targets Android, iOS, and Windows desktop. Talks to the same <code>/api/*</code> endpoints over Sanctum tokens.</p>
            </div>
            <div class="card">
                <h3>Backend</h3>
                <p>Laravel 13 + Sanctum + SQLite (dev). Production switch to MySQL or PostgreSQL by editing <code>.env</code>.</p>
            </div>
        </div>
    </main>

    <footer class="footer">
        © {{ date('Y') }} WireLakes Technologies
    </footer>
</body>
</html>
