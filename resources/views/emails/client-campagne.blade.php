{{--
  Mail au client avant l'échéance de sa campagne (2026-10-22) : mail J-7
  simple, séquence de renouvellement J-60 → J-7, accusé de réception.
  Cf. App\Mail\MailClientCampagne — texte modifiable dans Panora
  (ModelesMail). Format léger : aucune image, boutons sobres, marque
  discrète (signature et liseré aux cinq couleurs en CSS).
--}}
@php
    $coul   = $charte['couleurs'];
    $coord  = $charte['coordonnees'];
    $lisere = collect($charte['lisere'])->map(fn($k) => $coul[$k] ?? null)->filter();
    $tels   = collect(preg_split('/\s*·\s*/', (string) $coord['telephones'], -1, PREG_SPLIT_NO_EMPTY));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
<title>{{ $campaign->name }}</title>
</head>
<body style="margin:0; padding:0; background-color:#FFFFFF;">
<div style="display:none; font-size:1px; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; mso-hide:all;">{{ $preheader }}&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;</div>
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

@foreach($paragraphes as $p)
  @if($p['type'] === 'bloc')
<tr><td style="padding:2px 0 18px 0;">{!! $p['html'] !!}</td></tr>
  @elseif($p['type'] === 'liste')
<tr><td style="padding:0 0 16px 0;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr><td style="border-left:3px solid {{ $coul['rouge'] }}; padding:2px 0 2px 14px;">{!! $p['html'] !!}</td></tr>
  </table>
</td></tr>
  @else
<tr><td style="padding:0 0 16px 0;">{!! $p['html'] !!}</td></tr>
  @endif
@endforeach

<tr><td style="padding:6px 0 26px 0;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 10px 0;"><tr>
    @foreach($lisere as $couleur)
    <td width="12" height="3" style="width:12px; height:3px; background-color:{{ $couleur }}; font-size:0; line-height:0;">&nbsp;</td>
    @endforeach
  </tr></table>
  <strong>L'équipe commerciale {{ $charte['nom'] }}</strong><br>
  <span style="font-size:13px; line-height:20px; color:#555555;">
    {{ $coord['activite'] }}<br>
    @foreach($tels as $tel)<span style="white-space:nowrap;">{{ $tel }}</span>@if(!$loop->last) · @endif @endforeach<br>
    {{ $coord['email'] }} · {{ $coord['site'] }}
  </span>
</td></tr>

<tr><td style="border-top:1px solid {{ $coul['gris'] }}; padding:12px 0 0 0; font-size:12px; line-height:18px; color:#777777;">
  Vous recevez ce mail au sujet de votre campagne avec {{ $charte['nom'] }}.
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
