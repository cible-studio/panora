{{--
  Page de réponse du client sur le renouvellement de sa campagne
  (2026-10-22). Ouverte depuis les boutons des mails J-60 → J-7.
  Autonome et à la charte (config/charte.php). Cf. RenouvellementPublicController.
  États : formulaire | merci | confirme | expire.
--}}
@php
    $charte = config('charte');
    $coul   = $charte['couleurs'];
    $lisere = collect($charte['lisere'])->map(fn($k) => $coul[$k] ?? null)->filter();
    $fin    = \App\Mail\MailClientCampagne::dateEnLettres($campagne?->end_date);
    $aides  = [
        'renouveler' => 'Mêmes emplacements ? Même durée ? Précisez si besoin.',
        'ajuster'    => 'Panneaux à retirer ou à ajouter, emplacements à changer, durée…',
        'refus'      => 'Si vous le souhaitez, dites-nous pourquoi : cela nous aide à mieux vous servir.',
        'rappel'     => 'Le meilleur moment pour vous joindre, ou un autre numéro.',
    ];
    $teintes = ['renouveler' => $coul['vert'], 'ajuster' => $coul['jaune'], 'refus' => '#9A9A9A', 'rappel' => $coul['bleu']];
    $commercial = $campagne?->resolveCommercialContact()?->name;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Votre campagne — {{ $charte['nom'] }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="{{ $charte['polices']['google_fonts'] }}" rel="stylesheet">
<style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #F6F6F6; color: {{ $coul['noir'] }};
           font-family: '{{ $charte['polices']['texte'] }}', {{ $charte['polices']['secours'] }}; line-height: 1.55; }
    .page { max-width: 560px; margin: 0 auto; padding: 32px 16px 48px; }
    .carte { background: #FFFFFF; border-radius: 16px; padding: 26px 22px; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
    .lisere { display: flex; margin-bottom: 18px; }
    .lisere span { width: 22px; height: 4px; }
    .marque { font-family: '{{ $charte['polices']['titres'] }}', {{ $charte['polices']['secours'] }}; font-weight: 800; letter-spacing: .5px; font-size: 13px; color: #555; margin-bottom: 6px; }
    h1 { font-family: '{{ $charte['polices']['titres'] }}', {{ $charte['polices']['secours'] }}; font-size: 22px; font-weight: 800; margin: 0 0 14px; line-height: 1.3; }
    p { margin: 0 0 14px; font-size: 15px; }
    .recap { border: 1px solid {{ $coul['gris'] }}; border-radius: 12px; padding: 12px 16px; background: #FAFAFA; margin: 0 0 20px; font-size: 14px; }
    .recap div { display: flex; justify-content: space-between; gap: 12px; padding: 3px 0; }
    .recap span:first-child { color: #777; }
    .recap strong.fin { color: {{ $coul['rouge'] }}; }
    .choix { display: block; position: relative; border: 2px solid {{ $coul['gris'] }}; border-radius: 12px; padding: 14px 16px 14px 46px; margin-bottom: 10px; cursor: pointer; font-weight: 700; font-size: 15px; transition: border-color .15s, background .15s; }
    /* Bouton radio natif (visible partout) ; le cadre coloré est un plus des navigateurs récents. */
    .choix input { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; margin: 0; accent-color: var(--c); }
    .choix:has(input:checked) { border-color: var(--c); background: #FCFCFC; }
    label.zone { display: block; font-size: 13px; font-weight: 700; color: #555; margin: 18px 0 6px; }
    textarea { width: 100%; min-height: 96px; border: 1px solid #D0D0D0; border-radius: 10px; padding: 10px 12px; font: 15px '{{ $charte['polices']['texte'] }}', {{ $charte['polices']['secours'] }}; resize: vertical; }
    textarea:focus { outline: 2px solid {{ $coul['bleu'] }}; border-color: transparent; }
    button { width: 100%; background: {{ $coul['rouge'] }}; color: #fff; border: 0; border-radius: 12px; padding: 15px 20px; font: 800 16px '{{ $charte['polices']['texte'] }}', {{ $charte['polices']['secours'] }}; cursor: pointer; margin-top: 16px; }
    button:hover { filter: brightness(.93); }
    .petit { font-size: 12.5px; color: #777; margin-top: 12px; }
    .ok { border-left: 3px solid {{ $coul['vert'] }}; padding: 2px 0 2px 14px; margin: 0 0 16px; }
    .essai { background: #FFF8E6; border: 1px solid {{ $coul['jaune'] }}; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
    .pied { text-align: center; font-size: 12.5px; color: #777; margin-top: 22px; line-height: 1.6; }
</style>
</head>
<body>
<main class="page">
    <div class="carte">
        <div class="lisere">@foreach($lisere as $c)<span style="background:{{ $c }}"></span>@endforeach</div>
        <div class="marque">{{ $charte['nom'] }}</div>

        @if($essai)
            <div class="essai"><strong>Aperçu de test.</strong> Rien ne sera enregistré. Après confirmation, l'accusé de réception et le mail à l'équipe partent vers les adresses de test.</div>
        @endif

        @if($etat === 'expire')
            <h1>Ce lien n'est plus actif</h1>
            <p>La campagne concernée est terminée depuis plus d'un mois. Pour toute demande, contactez-nous directement : nous vous répondrons avec plaisir.</p>

        @elseif($etat === 'confirme')
            <h1>Votre renouvellement est confirmé</h1>
            <div class="ok"><p>Le renouvellement de votre campagne <strong>« {{ $campagne?->name }} »</strong> est confirmé.</p></div>
            <p class="petit">Pour toute modification, contactez {{ $commercial ?: 'votre commercial' }}.</p>

        @elseif($etat === 'merci')
            <h1>Merci, votre réponse est bien enregistrée</h1>
            <div class="ok"><p><strong>{{ $choixListe[$choix] ?? '' }}</strong></p></div>
            <p>{{ $commercial ?: 'Votre commercial' }} revient vers vous très prochainement pour la suite. Vous allez également recevoir un accusé de réception par mail.</p>
            <p class="petit">Vous avez changé d'avis ? Contactez {{ $commercial ?: 'votre commercial' }} : une seule réponse est possible par ce lien.</p>

        {{-- Une seule réponse par lien (2026-10-30). --}}
        @elseif($etat === 'repondu')
            <h1>Votre réponse est déjà enregistrée</h1>
            <div class="ok"><p>Le {{ $reponse['le']?->format('d/m/Y') }} : <strong>{{ $choixListe[$reponse['choix']] ?? '' }}</strong></p></div>
            <p>Une seule réponse est possible par ce lien. Pour la modifier, contactez {{ $commercial ?: 'votre commercial' }} : coordonnées ci-dessous.</p>
            @if($essai)
                <p class="petit"><a href="?refaire=1">Recommencer l'essai</a></p>
            @endif

        @else
            <h1>Votre campagne arrive à échéance</h1>
            <div class="recap">
                <div><span>Campagne</span><strong>{{ $campagne?->name }}</strong></div>
                @if($campagne?->client)<div><span>Client</span><strong>{{ $campagne->client->name }}</strong></div>@endif
                <div><span>Échéance</span><strong class="fin">{{ $fin }}</strong></div>
            </div>

            <form method="POST" action="{{ $action }}">
                @csrf
                <p style="font-weight:700;margin-bottom:10px;">Votre intention :</p>
                @foreach($choixListe as $cle => $libelle)
                    <label class="choix" style="--c: {{ $teintes[$cle] }};">
                        <input type="radio" name="choix" value="{{ $cle }}" @checked($choix === $cle) data-aide="{{ $aides[$cle] }}">
                        {{ $libelle }}
                    </label>
                @endforeach

                <label class="zone" for="message">Un message pour votre commercial <span style="font-weight:400;">(facultatif)</span></label>
                <textarea id="message" name="message" maxlength="2000" placeholder="{{ $aides[$choix] ?? '' }}"></textarea>

                <button type="submit">Confirmer ma réponse</button>
                <p class="petit">Votre réponse nous indique vos intentions. Elle ne vaut pas engagement : le renouvellement sera formalisé avec {{ $commercial ?: 'votre commercial' }}.</p>
            </form>
        @endif
    </div>

    <div class="pied">
        <strong>{{ $charte['nom'] }}</strong> · {{ $charte['coordonnees']['activite'] }}<br>
        {{ $charte['coordonnees']['telephones'] }} · {{ $charte['coordonnees']['email'] }}
    </div>
</main>
<script>
    // Aide du champ libre selon le choix (simple confort de saisie).
    document.querySelectorAll('input[name="choix"]').forEach(function (r) {
        r.addEventListener('change', function () {
            var t = document.getElementById('message');
            if (t) t.placeholder = r.dataset.aide || '';
        });
    });
</script>
</body>
</html>
