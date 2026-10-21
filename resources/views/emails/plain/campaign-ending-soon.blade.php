@if($noteTest)
[MAIL DE TEST] {!! $noteTest !!}

@endif
Bonjour {!! $salutation !!},

Votre campagne « {!! $campaign->name !!} » se termine le {!! $dateFin !!}, {!! $echeance !!}.

Pour prolonger sa visibilité, ou préparer la suite, nous pouvons vous proposer :
- une reconduction sur vos emplacements actuels, selon leur disponibilité ;
- les emplacements libres en ce moment, à Abidjan et dans le reste du pays ;
- une recommandation adaptée à votre prochain objectif.

Il vous suffit de répondre à ce mail en nous indiquant ce qui vous intéresse : nous revenons vers vous sous 24 heures ouvrées avec une proposition.

Bien cordialement,

Le service commercial de {!! $charte['nom'] !!}
{!! $charte['coordonnees']['activite'] !!}
{!! $charte['coordonnees']['telephones'] !!}
{!! $charte['coordonnees']['email'] !!} · {!! $charte['coordonnees']['site'] !!}

--
Vous recevez ce mail au sujet de votre campagne en cours avec {!! $charte['nom'] !!}.
@if($lienDesinscription)
Ne plus recevoir nos messages commerciaux : {!! $lienDesinscription !!}
@endif
