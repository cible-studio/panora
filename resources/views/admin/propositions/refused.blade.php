{{-- Page publique : refus enregistré — design sobre & pro --}}
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proposition refusée — CIBLE CI</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="{{ asset('images/faviconl.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --bg:        #f4f6f8;
        --card:      #ffffff;
        --border:    #e5e7eb;
        --text:      #111827;
        --text2:     #4b5563;
        --text3:     #9ca3af;
        --accent:    #c2570d;
        --neutral-soft: #f3f4f6;
        --neutral-border: #e5e7eb;
        --radius:    8px;
        --radius-lg: 12px;
    }
    *, *::before, *::after { box-sizing: border-box; }
    body, html { margin: 0; padding: 0; }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
        background: var(--bg);
        color: var(--text);
        font-size: 14px;
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /* Header */
    .header {
        background: var(--card);
        border-bottom: 1px solid var(--border);
    }
    .header-inner {
        max-width: 720px;
        margin: 0 auto;
        padding: 16px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .brand-logo {
        font-weight: 700;
        font-size: 18px;
        color: var(--text);
        letter-spacing: -0.3px;
    }
    .brand-logo .accent { color: var(--accent); }
    .brand-sub {
        font-size: 11px;
        color: var(--text3);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-top: -2px;
    }
    .header-meta {
        font-size: 12px;
        color: var(--text3);
    }
    .header-meta .ref {
        font-family: ui-monospace, "SF Mono", Menlo, monospace;
        color: var(--text);
        font-weight: 600;
    }

    /* Main */
    .container {
        flex: 1;
        max-width: 520px;
        width: 100%;
        margin: 0 auto;
        padding: 48px 24px;
    }

    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 40px 36px;
        text-align: center;
    }

    /* Icon discret (pas de rouge agressif — un X neutre dans un cercle gris) */
    .ico {
        width: 64px;
        height: 64px;
        margin: 0 auto 24px;
        border-radius: 50%;
        background: var(--neutral-soft);
        border: 1px solid var(--neutral-border);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .ico svg { color: var(--text2); }

    h1 {
        font-size: 22px;
        font-weight: 600;
        color: var(--text);
        line-height: 1.3;
        margin: 0 0 10px;
        letter-spacing: -0.3px;
    }
    .lead {
        font-size: 14px;
        color: var(--text2);
        margin: 0 0 18px;
        line-height: 1.6;
    }
    .lead strong { color: var(--text); font-weight: 600; }

    .info {
        background: #f9fafb;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 14px 18px;
        margin-top: 20px;
        font-size: 13px;
        color: var(--text2);
        line-height: 1.6;
        text-align: left;
    }
    .info strong { color: var(--text); }

    .note {
        font-size: 12px;
        color: var(--text3);
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
        line-height: 1.6;
    }

    .footer {
        max-width: 720px;
        margin: 0 auto;
        padding: 16px 24px 24px;
        text-align: center;
        font-size: 11px;
        color: var(--text3);
    }

    @media (max-width: 540px) {
        .container { padding: 24px 16px; }
        .card { padding: 32px 22px; }
        h1 { font-size: 20px; }
    }
</style>
</head>
<body>

<header class="header">
    <div class="header-inner">
        <div>
            <div class="brand-logo">CIBLE <span class="accent">CI</span></div>
            <div class="brand-sub">Régie Publicitaire</div>
        </div>
        <div class="header-meta">
            Réf. <span class="ref">{{ $reservation->reference }}</span>
        </div>
    </div>
</header>

<div class="container">
    <div class="card">

        <div class="ico">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </div>

        <h1>Refus enregistré</h1>
        <p class="lead">
            Merci pour votre retour, <strong>{{ $client?->name ?? 'Client' }}</strong>.
            Votre décision a bien été enregistrée et notre équipe commerciale en sera informée.
        </p>

        <div class="info">
            Si vous le souhaitez, nous pouvons vous proposer une <strong>nouvelle sélection</strong>
            d'emplacements adaptés à vos besoins (budget, zones, période). Notre équipe vous contactera
            sous peu.
        </div>

        <div class="note">
            Les emplacements de cette proposition sont désormais à nouveau disponibles. Vous pouvez fermer cette page.
        </div>
    </div>
</div>

<footer class="footer">
    © {{ date('Y') }} CIBLE CI — Régie Publicitaire — Abidjan, Côte d'Ivoire
</footer>

</body>
</html>
