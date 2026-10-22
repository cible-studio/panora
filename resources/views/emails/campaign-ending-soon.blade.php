{{--
  Mail au client : « Votre campagne se termine le … » (J-7) — refait le
  2026-10-21. Même format léger que le modèle Brevo des disponibilités
  (docs/diffusion/modele-brevo-disponibilites-leger.html) : aucune image,
  aucun bouton, réponse par retour de mail, marque discrète (signature et
  liseré aux cinq couleurs en CSS). Objectif : la boîte de réception.
  Envoyé par RelanceFinCampagneService, par Brevo uniquement.
--}}
@php
    $coul    = $charte['couleurs'];
    $coord   = $charte['coordonnees'];
    $lisere  = collect($charte['lisere'])->map(fn($k) => $coul[$k] ?? null)->filter();
    $tels    = collect(preg_split('/\s*·\s*/', (string) $coord['telephones'], -1, PREG_SPLIT_NO_EMPTY));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
<title>Votre campagne se termine le {{ $dateFin }}</title>
</head>
<body style="margin:0; padding:0; background-color:#FFFFFF;">
<div style="display:none; font-size:1px; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; mso-hide:all;">« {{ $campaign->name }} » se termine {{ $echeance }}. Prolonger sa visibilité ou préparer la suite : répondez simplement à ce mail.&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FFFFFF;">
<tr><td style="padding:24px 16px;">
<!--[if mso]><table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:560px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:23px; color:{{ $coul['noir'] }}; word-break:break-word; overflow-wrap:anywhere;">

@if($noteTest)
<tr><td style="padding:0 0 18px 0;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr><td style="border-left:3px solid {{ $coul['jaune'] }}; background-color:{{ $coul['gris'] }}; padding:10px 14px; font-size:13px; line-height:19px;">
    <strong>Mail de test.</strong> {{ $noteTest }}
  </td></tr>
  </table>
</td></tr>
@endif

<tr><td style="padding:0 0 16px 0;">
  Bonjour {{ $salutation }},
</td></tr>

<tr><td style="padding:0 0 16px 0;">
  Votre campagne <strong>« {{ $campaign->name }} »</strong> se termine le <strong>{{ $dateFin }}</strong>, {{ $echeance }}.
</td></tr>

<tr><td style="padding:0 0 10px 0;">
  Pour prolonger sa visibilité, ou préparer la suite, nous pouvons vous proposer&nbsp;:
</td></tr>

<tr><td style="padding:0 0 16px 0;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr><td style="border-left:3px solid {{ $coul['rouge'] }}; padding:2px 0 2px 14px;">
    <strong>Une reconduction</strong> sur vos emplacements actuels, selon leur disponibilité&nbsp;;<br>
    <strong>Les emplacements libres</strong> en ce moment, à Abidjan et dans le reste du pays&nbsp;;<br>
    <strong>Une recommandation</strong> adaptée à votre prochain objectif.
  </td></tr>
  </table>
</td></tr>

<tr><td style="padding:0 0 16px 0;">
  Il vous suffit de <strong>répondre à ce mail</strong> en nous indiquant ce qui vous intéresse&nbsp;: nous revenons vers vous sous 24&nbsp;heures ouvrées avec une proposition.
</td></tr>

<tr><td style="padding:0 0 22px 0;">
  Bien cordialement,
</td></tr>

<tr><td style="padding:0 0 26px 0;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 10px 0;"><tr>
    @foreach($lisere as $couleur)
    <td width="12" height="3" style="width:12px; height:3px; background-color:{{ $couleur }}; font-size:0; line-height:0;">&nbsp;</td>
    @endforeach
  </tr></table>
  <strong>Le service commercial de {{ $charte['nom'] }}</strong><br>
  <span style="font-size:13px; line-height:20px; color:#555555;">
    {{ $coord['activite'] }}<br>
    @foreach($tels as $tel)<span style="white-space:nowrap;">{{ $tel }}</span>@if(!$loop->last) · @endif @endforeach<br>
    {{ $coord['email'] }} · {{ $coord['site'] }}
  </span>
</td></tr>

<tr><td style="border-top:1px solid {{ $coul['gris'] }}; padding:12px 0 0 0; font-size:12px; line-height:18px; color:#777777;">
  Vous recevez ce mail au sujet de votre campagne en cours avec {{ $charte['nom'] }}.
  @if($lienDesinscription)
    <a href="{{ $lienDesinscription }}" style="color:#777777; text-decoration:underline;">Ne plus recevoir nos messages commerciaux</a>
  @endif
</td></tr>

</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>
