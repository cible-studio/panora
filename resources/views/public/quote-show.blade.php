{{--
    Lien privé du devis (/devis/{token}) — page vue par TOUS les clients
    depuis le mail du devis (2026-11-23).

    2026-11-23 : habillage à la charte de la régie (config/charte.php, même
    source que les mails et les PDF) : liseré cinq couleurs, logo, polices,
    couleurs. Avant, un bandeau bleu nuit « CIBLE CI » sans logo, et le
    violet de Panora. Le contenu (quotes._show_content) reste commun avec
    l'espace client : seules ses variables de couleur --qw-* sont remplacées.
--}}
@php
    $ch = \App\Support\PdfCharte::data();
    $nomRegie = config('charte.nom', 'CIBLE');
    $coord    = (array) config('charte.coordonnees', []);
    $logo     = config('charte.logos.clair') ? asset(config('charte.logos.clair')) : null;
    $fonts    = config('charte.polices.google_fonts');
    $secours  = config('charte.polices.secours', 'Arial, Helvetica, sans-serif');
    $ffTitres = "'" . config('charte.polices.titres', 'Poppins') . "', " . $secours;
    $ffTexte  = "'" . config('charte.polices.texte', 'Nunito') . "', " . $secours;
    $siteUrl  = !empty($coord['site'])
        ? (str_starts_with($coord['site'], 'http') ? $coord['site'] : 'https://' . $coord['site'])
        : null;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="{{ $ch['rouge'] }}">
    <title>Devis {{ $quote->reference }} — {{ $nomRegie }}</title>
    <link rel="icon" href="{{ asset('images/faviconl.png') }}">
    @if($fonts)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="{{ $fonts }}" rel="stylesheet">
    @endif
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body.charte { font-family: {!! $ffTexte !!}; background: {{ $ch['gris_clair'] }}; color: {{ $ch['noir'] }}; -webkit-font-smoothing: antialiased; }
        body.charte h1, body.charte .qw-btn { font-family: {!! $ffTitres !!}; }
        .lisere { display: flex; height: 5px; }
        .lisere span { flex: 1; }
        header.public-hdr { background: {{ $ch['blanc'] }}; border-bottom: 1px solid {{ $ch['gris'] }}; }
        header.public-hdr .in { max-width: 960px; margin: 0 auto; padding: 18px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        header.public-hdr img { display: block; height: 44px; width: auto; }
        header.public-hdr .nom { font-family: {!! $ffTitres !!}; font-size: 22px; font-weight: 800; color: {{ $ch['noir'] }}; }
        header.public-hdr .metier { font-family: {!! $ffTitres !!}; font-size: 13px; font-weight: 700; color: {{ $ch['texte_doux'] }}; padding-left: 14px; border-left: 1px solid {{ $ch['gris'] }}; }
        header.public-hdr .pour { text-align: right; font-size: 13px; color: {{ $ch['texte_doux'] }}; line-height: 1.45; }
        header.public-hdr .pour strong { color: {{ $ch['noir'] }}; }
        .flash { max-width: 912px; margin: 16px auto 0; padding: 14px 16px; border-radius: 8px; border-left: 4px solid; font-size: 14px; }
        .flash-success { background: {{ $ch['vert_clair'] }}; border-color: {{ $ch['vert'] }}; }
        .flash-error   { background: {{ $ch['rouge_clair'] }}; border-color: {{ $ch['rouge'] }}; }
        .flash-warning { background: {{ $ch['jaune_clair'] }}; border-color: {{ $ch['jaune'] }}; }
        .flash-info    { background: {{ $ch['bleu_clair'] }}; border-color: {{ $ch['bleu'] }}; }

        /* Couleurs du contenu commun (quotes._show_content) à la charte. */
        body.charte .qw {
            --qw-accent: {{ $ch['rouge'] }};
            --qw-titre: {{ $ch['noir'] }}; --qw-texte: {{ $ch['texte_doux'] }}; --qw-doux: {{ $ch['texte_pale'] }};
            --qw-total-fond: {{ $ch['bleu'] }}; --qw-total-texte: {{ $ch['blanc'] }};
            --qw-ok: {{ $ch['vert'] }}; --qw-ok-fonce: {{ $ch['vert'] }}; --qw-ok-bord: {{ $ch['vert'] }};
            --qw-refus: {{ $ch['rouge'] }}; --qw-refus-bord: {{ $ch['rouge'] }}; --qw-refus-doux: {{ $ch['rouge_clair'] }};
            --qw-modif: {{ $ch['violet'] }}; --qw-modif-bord: {{ $ch['violet'] }}; --qw-modif-doux: {{ $ch['violet_clair'] }};
            --qw-decision-fond: {{ $ch['jaune_clair'] }}; --qw-decision-bord: {{ $ch['jaune'] }}; --qw-decision-texte: {{ $ch['noir'] }};
        }
        body.charte .qw-btn-accept:hover { filter: brightness(.92); }

        footer.public-ftr { margin-top: 40px; background: {{ $ch['blanc'] }}; border-top: 1px solid {{ $ch['gris'] }}; }
        footer.public-ftr .in { max-width: 960px; margin: 0 auto; padding: 22px 24px; display: flex; justify-content: space-between; gap: 18px; flex-wrap: wrap; font-size: 12.5px; color: {{ $ch['texte_doux'] }}; line-height: 1.6; }
        footer.public-ftr img { height: 30px; width: auto; display: block; margin-bottom: 6px; }
        footer.public-ftr a { color: {{ $ch['rouge'] }}; text-decoration: none; }
        @media (max-width: 720px) {
            header.public-hdr .pour { text-align: left; }
            .flash { margin-left: 16px; margin-right: 16px; }
        }
    </style>
</head>
<body class="charte">
    <div class="lisere" aria-hidden="true">
        @foreach($ch['lisere'] as $couleur)<span style="background:{{ $couleur }}"></span>@endforeach
    </div>
    <header class="public-hdr">
        <div class="in">
            <div style="display:flex;align-items:center;gap:14px">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $nomRegie }}">
                @else
                    <span class="nom">{{ $nomRegie }}</span>
                @endif
                <span class="metier">{{ config('charte.coordonnees.metier') }}</span>
            </div>
            <div class="pour">
                Devis <strong>{{ $quote->reference }}</strong><br>
                adressé à <strong>{{ $quote->client?->name }}</strong>
            </div>
        </div>
    </header>

    @foreach(['success', 'error', 'warning', 'info'] as $type)
        @if(session($type))
            <div class="flash flash-{{ $type }}">{{ session($type) }}</div>
        @endif
    @endforeach

    @include('quotes._show_content', ['quote' => $quote, 'isPublic' => true])

    <footer class="public-ftr">
        <div class="in">
            <div>
                @if($logo)<img src="{{ $logo }}" alt="{{ $nomRegie }}">@endif
                <div>{{ $coord['activite'] ?? '' }}</div>
                @if(!empty($coord['adresse']))<div>{{ $coord['adresse'] }}</div>@endif
            </div>
            <div style="text-align:right">
                {{-- Pas de slogan écrit : le logo le porte déjà (demande du 2026-10). --}}
                @if(!empty($coord['telephones']))<div>{{ $coord['telephones'] }}</div>@endif
                @if(!empty($coord['email']))<div><a href="mailto:{{ $coord['email'] }}">{{ $coord['email'] }}</a></div>@endif
                @if($siteUrl)<div><a href="{{ $siteUrl }}" target="_blank" rel="noopener">{{ $coord['site'] }}</a></div>@endif
            </div>
        </div>
    </footer>
</body>
</html>
