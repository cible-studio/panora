<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e8a020">
    <title>@yield('title', 'CIBLE CI — Espace client')</title>
    <link rel="icon" href="{{ asset('images/faviconl.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #e8a020;
            --accent-dark: #c2570d;
            --bg: #f4f6f8;
            --card: #ffffff;
            --border: #e5e7eb;
            --text: #1f2937;
            --text2: #6b7280;
            --text3: #9ca3af;
            --green: #16a34a;
            --red: #dc2626;
            --blue: #3b82f6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            font-family: 'DM Sans', system-ui, -apple-system, sans-serif;
            background: var(--bg); color: var(--text);
            -webkit-font-smoothing: antialiased; line-height: 1.5;
        }
        body { padding: 24px 16px 60px; }
        .wrap { max-width: 760px; margin: 0 auto; }
        .brand { text-align: center; margin-bottom: 22px; }
        .brand-name { font-weight: 700; font-size: 13px; color: var(--accent-dark); letter-spacing: 2px; text-transform: uppercase; }
        .brand-sub { font-size: 11px; color: var(--text3); margin-top: 2px; }
        .card { background: var(--card); border-radius: 14px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); padding: 28px 26px; margin-bottom: 14px; }
        h1 { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
        h2 { font-size: 15px; font-weight: 700; margin: 18px 0 10px; }
        .muted { color: var(--text2); font-size: 13px; }
        .small { font-size: 12px; color: var(--text3); }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-green { background: rgba(22,163,74,.12); color: var(--green); }
        .badge-red { background: rgba(220,38,38,.12); color: var(--red); }
        .badge-blue { background: rgba(59,130,246,.12); color: var(--blue); }
        .badge-amber { background: rgba(232,160,32,.12); color: var(--accent-dark); }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 700; text-decoration: none; border: none; cursor: pointer; transition: all .15s; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-dark); }
        .btn-ghost { background: var(--bg); color: var(--text2); border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text3); padding: 8px 10px; text-align: left; border-bottom: 1px solid var(--border); }
        td { font-size: 13px; padding: 10px; border-bottom: 1px solid var(--border); }
        .footer { text-align: center; font-size: 11px; color: var(--text3); margin-top: 30px; line-height: 1.6; }
        .footer a { color: var(--accent-dark); text-decoration: none; }
        .expires { background: rgba(232,160,32,.08); border: 1px solid rgba(232,160,32,.25); color: var(--accent-dark); border-radius: 10px; padding: 10px 14px; font-size: 12px; margin-bottom: 14px; }
        .invalid-state { text-align: center; padding: 40px 20px; }
        .invalid-state .icon { font-size: 56px; opacity: .35; margin-bottom: 14px; }
        .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin: 14px 0; }
        .kpi { background: var(--bg); border-radius: 10px; padding: 12px 14px; }
        .kpi-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text3); }
        .kpi-value { font-size: 18px; font-weight: 700; color: var(--text); margin-top: 4px; }
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
        .photo-grid img { width: 100%; height: 140px; object-fit: cover; border-radius: 8px; }
    </style>
    @stack('styles')
</head>
<body>
    <div class="wrap">
        <div class="brand">
            <div class="brand-name">CIBLE CI</div>
            <div class="brand-sub">Affichage publicitaire — Côte d'Ivoire</div>
        </div>

        @isset($link)
        <div class="expires">
            🔒 Lien sécurisé valable jusqu'au {{ $link->expires_at?->format('d/m/Y à H:i') ?? '—' }}.
            Si vous n'êtes pas le destinataire, ignorez cette page.
        </div>
        @endisset

        @yield('content')

        <div class="footer">
            © {{ date('Y') }} CIBLE CI — Tous droits réservés.<br>
            Pour toute question : <a href="mailto:contact@cible-ci.com">contact@cible-ci.com</a>
        </div>
    </div>
</body>
</html>
