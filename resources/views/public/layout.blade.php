{{--
    Pages publiques des liens sécurisés (réservation confirmée, facture,
    pige, lot de piges, décapage, lien invalide).

    2026-11-23 : habillage à la charte de la régie (config/charte.php, même
    source que les mails, les PDF et la page du devis) : liseré cinq
    couleurs, logo, polices, couleurs, coordonnées. Avant : « CIBLE CI ·
    Affichage publicitaire » en texte orange, sans logo. Les classes
    utilisées par les pages (.card, .badge-*, .btn-*, .kpi, var(--accent)…)
    sont conservées et re-colorées.
--}}
@php
    $ch       = \App\Support\PdfCharte::data();
    $nomRegie = config('charte.nom', 'CIBLE');
    $coord    = (array) config('charte.coordonnees', []);
    $logo     = config('charte.logos.clair') ? asset(config('charte.logos.clair')) : null;
    $fonts    = config('charte.polices.google_fonts');
    $secours  = config('charte.polices.secours', 'Arial, Helvetica, sans-serif');
    $ffTitres = "'" . config('charte.polices.titres', 'Poppins') . "', " . $secours;
    $ffTexte  = "'" . config('charte.polices.texte', 'Nunito') . "', " . $secours;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $ch['rouge'] }}">
    <title>@yield('title', $nomRegie . ' — Espace client')</title>
    <link rel="icon" href="{{ asset('images/faviconl.png') }}">
    @if($fonts)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="{{ $fonts }}" rel="stylesheet">
    @endif
    <style>
        :root {
            --accent: {{ $ch['rouge'] }};
            --accent-dark: {{ $ch['rouge'] }};
            --bg: {{ $ch['gris_clair'] }};
            --card: {{ $ch['blanc'] }};
            --border: {{ $ch['gris'] }};
            --text: {{ $ch['noir'] }};
            --text2: {{ $ch['texte_doux'] }};
            --text3: {{ $ch['texte_pale'] }};
            --green: {{ $ch['vert'] }};
            --red: {{ $ch['rouge'] }};
            --blue: {{ $ch['bleu'] }};
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            font-family: {!! $ffTexte !!};
            background: var(--bg); color: var(--text);
            -webkit-font-smoothing: antialiased; line-height: 1.5;
        }
        h1, h2, .btn, .kpi-value { font-family: {!! $ffTitres !!}; }
        body { padding: 0 0 60px; }
        .lisere { display: flex; height: 5px; margin-bottom: 24px; }
        .lisere span { flex: 1; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 0 16px; }
        .brand { display: flex; align-items: center; justify-content: center; gap: 14px; margin-bottom: 22px; }
        .brand img { display: block; height: 40px; width: auto; }
        .brand-name { font-family: {!! $ffTitres !!}; font-weight: 800; font-size: 18px; color: var(--text); }
        .brand-sub { font-family: {!! $ffTitres !!}; font-size: 12px; font-weight: 700; color: var(--text2); padding-left: 14px; border-left: 1px solid var(--border); }
        .card { background: var(--card); border-radius: 14px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); padding: 28px 26px; margin-bottom: 14px; }
        h1 { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
        h2 { font-size: 15px; font-weight: 700; margin: 18px 0 10px; }
        .muted { color: var(--text2); font-size: 13px; }
        .small { font-size: 12px; color: var(--text3); }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-green { background: rgba(22,163,74,.12); color: var(--green); }
        .badge-red { background: rgba(220,38,38,.12); color: var(--red); }
        .badge-blue { background: rgba(59,130,246,.12); color: var(--blue); }
        .badge-amber { background: {{ $ch['jaune_clair'] }}; color: var(--text); }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 700; text-decoration: none; border: none; cursor: pointer; transition: all .15s; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { filter: brightness(.92); }
        .btn-ghost { background: var(--bg); color: var(--text2); border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text3); padding: 8px 10px; text-align: left; border-bottom: 1px solid var(--border); }
        td { font-size: 13px; padding: 10px; border-bottom: 1px solid var(--border); }
        .footer { text-align: center; font-size: 11px; color: var(--text3); margin-top: 30px; line-height: 1.6; }
        .footer a { color: var(--accent); text-decoration: none; }
        .footer img { height: 28px; width: auto; display: block; margin: 0 auto 8px; }
        .expires { background: {{ $ch['jaune_clair'] }}; border: 1px solid {{ $ch['jaune'] }}; color: var(--text); border-radius: 10px; padding: 10px 14px; font-size: 12px; margin-bottom: 14px; }
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
    <div class="lisere" aria-hidden="true">
        @foreach($ch['lisere'] as $couleur)<span style="background:{{ $couleur }}"></span>@endforeach
    </div>
    <div class="wrap">
        <div class="brand">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ $nomRegie }}">
            @else
                <div class="brand-name">{{ $nomRegie }}</div>
            @endif
            <div class="brand-sub">{{ config('charte.coordonnees.metier') }}</div>
        </div>

        @isset($link)
        <div class="expires">
            🔒 Lien sécurisé valable jusqu'au {{ $link->expires_at?->format('d/m/Y à H:i') ?? '—' }}.
            Si vous n'êtes pas le destinataire, ignorez cette page.
        </div>
        @endisset

        @yield('content')

        <div class="footer">
            @if($logo)<img src="{{ $logo }}" alt="{{ $nomRegie }}">@endif
            {{ $coord['activite'] ?? '' }}<br>
            @if(!empty($coord['telephones'])){{ $coord['telephones'] }}<br>@endif
            Pour toute question :
            <a href="mailto:{{ $coord['email'] ?? 'commercial@cible-ci.com' }}">{{ $coord['email'] ?? 'commercial@cible-ci.com' }}</a>
            · © {{ date('Y') }} {{ $nomRegie }}
        </div>
    </div>
</body>
</html>
