@if($noteTest)
[MAIL DE TEST] {!! $noteTest !!}

@endif
{!! $texte !!}

L'équipe commerciale {!! $charte['nom'] !!}
{!! $charte['coordonnees']['activite'] !!}
{!! $charte['coordonnees']['telephones'] !!}
{!! $charte['coordonnees']['email'] !!} · {!! $charte['coordonnees']['site'] !!}

--
Vous recevez ce mail au sujet de votre campagne avec {!! $charte['nom'] !!}.
@if($lienDesinscription)
Ne plus recevoir nos messages commerciaux : {!! $lienDesinscription !!}
@endif
