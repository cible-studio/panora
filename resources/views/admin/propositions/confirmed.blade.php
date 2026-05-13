{{-- Page publique : confirmation enregistrée — design sobre & pro --}}
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proposition confirmée — PANORA</title>
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
        --accent-soft: #fff7ed;
        --green:     #16a34a;
        --green-soft:#f0fdf4;
        --green-border:#bbf7d0;
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
        max-width: 560px;
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

    /* Check icon */
    .check {
        width: 64px;
        height: 64px;
        margin: 0 auto 24px;
        border-radius: 50%;
        background: var(--green-soft);
        border: 1px solid var(--green-border);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .check svg { color: var(--green); }

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
        margin: 0 0 24px;
        line-height: 1.6;
    }
    .lead strong { color: var(--text); font-weight: 600; }

    /* Récap */
    .summary {
        background: #f9fafb;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px 22px;
        margin: 24px 0;
        text-align: left;
    }
    .row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        padding: 6px 0;
        font-size: 13px;
    }
    .row + .row { border-top: 1px solid var(--border); margin-top: 4px; padding-top: 10px; }
    .row .lbl { color: var(--text2); }
    .row .val { color: var(--text); font-weight: 600; }
    .row .val.code {
        font-family: ui-monospace, "SF Mono", Menlo, monospace;
        font-size: 12px;
        background: #fff;
        border: 1px solid var(--border);
        padding: 2px 8px;
        border-radius: 4px;
    }
    .row .val.accent { color: var(--accent); }

    /* Next steps */
    .next {
        background: var(--accent-soft);
        border: 1px solid #fed7aa;
        border-radius: var(--radius);
        padding: 14px 18px;
        margin-top: 16px;
        text-align: left;
    }
    .next-title {
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--accent);
        margin-bottom: 8px;
    }
    .next ul {
        margin: 0;
        padding: 0 0 0 18px;
        font-size: 13px;
        color: var(--text2);
        line-height: 1.6;
    }
    .next li { margin: 3px 0; }

    .note {
        font-size: 12px;
        color: var(--text3);
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
        line-height: 1.6;
    }

    /* Footer */
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

{{-- Header --}}
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

{{-- Main --}}
<div class="container">
    <div class="card">

        {{-- Check icon --}}
        <div class="check">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>

        <h1>Proposition confirmée</h1>
        <p class="lead">
            Merci <strong>{{ $client?->name ?? 'Client' }}</strong>, votre confirmation a bien été enregistrée.
            Notre équipe commerciale va prendre contact avec vous pour finaliser les prochaines étapes.
        </p>

        {{-- Récap --}}
        <div class="summary">
            <div class="row">
                <span class="lbl">Référence</span>
                <span class="val code">{{ $reservation->reference }}</span>
            </div>
            <div class="row">
                <span class="lbl">Période</span>
                <span class="val">
                    {{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}
                </span>
            </div>
            @php $panelCount = $reservation->panels->count() + $reservation->externalPanels->count(); @endphp
            <div class="row">
                <span class="lbl">Emplacements</span>
                <span class="val">{{ $panelCount }} panneau{{ $panelCount > 1 ? 'x' : '' }}</span>
            </div>
            @if($reservation->total_amount !== null)
                <div class="row">
                    <span class="lbl">Montant estimé</span>
                    @if((float) $reservation->total_amount > 0)
                        <span class="val accent">{{ number_format((float) $reservation->total_amount, 0, ',', ' ') }} FCFA</span>
                    @else
                        <span class="val accent" style="color:#16a34a;">0 FCFA · Offert</span>
                    @endif
                </div>
            @endif
            @if($campaign)
                <div class="row">
                    <span class="lbl">Campagne créée</span>
                    <span class="val accent">{{ $campaign->name }}</span>
                </div>
            @endif
            <div class="row">
                <span class="lbl">Confirmée le</span>
                <span class="val">{{ now()->format('d/m/Y à H:i') }}</span>
            </div>
        </div>

        {{-- Next steps --}}
        <div class="next">
            <div class="next-title">Prochaines étapes</div>
            <ul>
                <li>Vous recevrez un email récapitulatif sous peu.</li>
                <li>Notre équipe vous contactera pour le calendrier de pose.</li>
                <li>La facturation sera émise selon les conditions convenues.</li>
            </ul>
        </div>

        {{-- Espace client : 2 cas selon que le client a déjà un compte
             ou non. S'il a un compte → CTA vers /client/login (puis
             /client/campagnes). S'il n'en a pas → invitation à en créer
             un pour suivre la campagne en temps réel. --}}
        @php
            $hasAccount = $client && !empty($client->password);
            $operator   = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
        @endphp
        <div style="margin-top:18px;padding:16px 18px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;text-align:left;">
            @if($hasAccount)
                <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#1d4ed8;margin-bottom:6px;">
                    📊 Suivre votre campagne
                </div>
                <p style="font-size:13px;color:#1e3a8a;margin:0 0 10px;line-height:1.6;">
                    Connectez-vous à votre espace client PANORA pour suivre l'avancement
                    de votre campagne en temps réel (poses, photos d'affichage, factures).
                </p>
                <a href="{{ route('client.login') }}"
                   style="display:inline-block;background:#2563eb;color:#fff;padding:10px 18px;
                          border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
                    Accéder à mon espace
                </a>
            @else
                <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#1d4ed8;margin-bottom:6px;">
                    ✨ Suivez votre campagne en temps réel
                </div>
                <p style="font-size:13px;color:#1e3a8a;margin:0 0 4px;line-height:1.6;">
                    PANORA met à votre disposition un espace client gratuit pour suivre
                    l'avancement de votre campagne, voir les photos d'affichage en direct,
                    et télécharger vos factures.
                </p>
                <p style="font-size:12px;color:#475569;margin:0 0 10px;">
                    Votre commercial vous transmettra les identifiants par email d'ici peu.
                </p>
            @endif
        </div>

        <div class="note">
            Vous pouvez fermer cette page. Pour toute question, contactez votre interlocuteur commercial.
        </div>
    </div>
</div>

<footer class="footer">
    © {{ date('Y') }} PANORA · {{ $operator ?? 'CIBLE CI' }} — Régie Publicitaire — Abidjan, Côte d'Ivoire
</footer>

</body>
</html>
