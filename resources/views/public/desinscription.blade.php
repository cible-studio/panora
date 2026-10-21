{{--
  Page du lien « Ne plus recevoir nos messages commerciaux » (2026-10-21).
  Autonome et à la charte (config/charte.php) : fond blanc, accent rouge,
  liseré aux cinq couleurs. Cf. DesinscriptionController.
--}}
@php
    $charte = config('charte');
    $coul   = $charte['couleurs'];
    $lisere = collect($charte['lisere'])->map(fn($k) => $coul[$k] ?? null)->filter();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Désinscription — {{ $charte['nom'] }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="{{ $charte['polices']['google_fonts'] }}" rel="stylesheet">
<style>
    * { box-sizing: border-box; }
    body { margin: 0; background: {{ $coul['blanc'] }}; color: {{ $coul['noir'] }};
           font-family: '{{ $charte['polices']['texte'] }}', {{ $charte['polices']['secours'] }}; line-height: 1.55; }
    .page { max-width: 520px; margin: 0 auto; padding: 48px 16px; }
    .lisere { display: flex; gap: 0; margin-bottom: 22px; }
    .lisere span { width: 22px; height: 4px; }
    h1 { font-family: '{{ $charte['polices']['titres'] }}', {{ $charte['polices']['secours'] }};
         font-size: 22px; font-weight: 800; margin: 0 0 12px; }
    p { margin: 0 0 14px; font-size: 15px; }
    .adresse { font-weight: 700; word-break: break-all; }
    .muted { color: #555; font-size: 13px; }
    button { background: {{ $coul['rouge'] }}; color: {{ $coul['blanc'] }}; border: 0; border-radius: 8px;
             padding: 12px 20px; font: 700 15px '{{ $charte['polices']['texte'] }}', {{ $charte['polices']['secours'] }};
             cursor: pointer; margin: 6px 0 18px; }
    button:hover { filter: brightness(.92); }
    .ok { border-left: 3px solid {{ $coul['vert'] }}; padding: 2px 0 2px 14px; margin-bottom: 18px; }
    .pied { border-top: 1px solid {{ $coul['gris'] }}; padding-top: 14px; margin-top: 26px; }
</style>
</head>
<body>
<main class="page">
    <div class="lisere">@foreach($lisere as $c)<span style="background:{{ $c }}"></span>@endforeach</div>

    @if($confirme)
        <h1>C'est noté</h1>
        <div class="ok">
            <p>L'adresse <span class="adresse">{{ $email }}</span> ne recevra plus nos messages commerciaux.</p>
        </div>
        <p class="muted">Les factures et les documents liés à vos commandes continueront de vous parvenir.
            Pour revenir sur ce choix, il suffit de nous écrire.</p>
    @elseif($deja)
        <h1>Vous êtes déjà désinscrit</h1>
        <p>L'adresse <span class="adresse">{{ $email }}</span> ne reçoit plus nos messages commerciaux.</p>
        <p class="muted">Pour revenir sur ce choix, il suffit de nous écrire.</p>
    @else
        <h1>Ne plus recevoir nos messages</h1>
        <p>L'adresse <span class="adresse">{{ $email }}</span> ne recevra plus nos messages commerciaux :
            emplacements disponibles et fin de campagne.</p>
        <form method="POST" action="{{ $action }}">
            <button type="submit">Confirmer la désinscription</button>
        </form>
        <p class="muted">Les factures et les documents liés à vos commandes continueront de vous parvenir.</p>
    @endif

    <div class="pied muted">
        <strong>{{ $charte['nom'] }}</strong> · {{ $charte['coordonnees']['activite'] }}<br>
        {{ $charte['coordonnees']['telephones'] }} · {{ $charte['coordonnees']['email'] }}
    </div>
</main>
</body>
</html>
