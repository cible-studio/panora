{{--
  Boutons de réponse des mails de renouvellement (2026-10-22).
  Empilés, pleine largeur (lisibles au doigt sur téléphone), coins
  arrondis. Un seul bouton plein (vert) : le choix qu'on espère.
  Les autres sont blancs à liseré, pour rester sobres (boîte de
  réception plutôt que Promotions).
--}}
@php
    $coul = $charte['couleurs'];
    $styles = [
        'renouveler' => ['fond' => $coul['vert'], 'bord' => $coul['vert'], 'texte' => '#FFFFFF'],
        'ajuster'    => ['fond' => '#FFFFFF', 'bord' => $coul['jaune'], 'texte' => $coul['noir']],
        'refus'      => ['fond' => '#FFFFFF', 'bord' => '#C8C8C8', 'texte' => '#555555'],
        'rappel'     => ['fond' => '#FFFFFF', 'bord' => $coul['bleu'], 'texte' => $coul['bleu']],
    ];
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:440px;">
@foreach($boutons as $b)
  @php $s = $styles[$b['choix']] ?? $styles['refus']; @endphp
  <tr><td style="padding:0 0 10px 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td align="center" bgcolor="{{ $s['fond'] }}" style="background-color:{{ $s['fond'] }}; border:2px solid {{ $s['bord'] }}; border-radius:10px;">
      <a href="{{ $b['lien'] }}" target="_blank" style="display:block; padding:13px 18px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:20px; font-weight:bold; color:{{ $s['texte'] }}; text-decoration:none; border-radius:10px;">{{ $b['libelle'] }}</a>
    </td></tr>
    </table>
  </td></tr>
@endforeach
</table>
