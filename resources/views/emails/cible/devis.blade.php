@php
    /*
     * Demande de devis reçue depuis le site de la régie — habillage à la
     * charte (config/charte.php), sur le modèle de emails/diffusion-dispos.
     * Mail autonome (pas x-mail.layout) : styles en ligne, largeur fluide
     * (100 %, 600 px maximum). Contenu inchangé.
     */
    $ch      = config('charte.couleurs');
    $secours = config('charte.polices.secours', 'Arial, Helvetica, sans-serif');
    $titres  = "font-family:'" . config('charte.polices.titres', 'Poppins') . "', {$secours};";
    $texte   = "font-family:'" . config('charte.polices.texte', 'Nunito') . "', {$secours};";
    $petit   = "{$titres}font-size:11px;line-height:14px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;";
    $lbl     = "{$texte}font-size:13px;line-height:18px;color:{$ch['noir']};padding:10px 12px 10px 0;border-bottom:1px solid {$ch['gris']};vertical-align:top;width:140px;";
    $val     = "{$titres}font-size:14px;line-height:18px;font-weight:700;color:{$ch['noir']};padding:10px 0;border-bottom:1px solid {$ch['gris']};vertical-align:top;";
    $valN    = "{$texte}font-size:14px;line-height:18px;color:{$ch['noir']};padding:10px 0;border-bottom:1px solid {$ch['gris']};vertical-align:top;";
    $lien    = "color:{$ch['rouge']};text-decoration:underline;";

    $nomRegie   = config('charte.nom', 'CIBLE');
    $slogan     = config('charte.slogan');
    $logoClair  = asset(config('charte.logos.clair', 'images/logol.png'));
    $logoSombre = asset(config('charte.logos.sombre', 'images/logob.png'));
    $lisere     = collect(config('charte.lisere', ['rouge', 'jaune', 'vert', 'bleu', 'violet']))
        ->map(fn ($k) => $ch[$k] ?? null)->filter()->values();
@endphp
<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Demande de devis</title>
<!--[if mso]><style>td,p,a,div,span{font-family:Arial,Helvetica,sans-serif !important;}</style><![endif]-->
@if(config('charte.polices.google_fonts'))
<!--[if !mso]><!--><link href="{{ config('charte.polices.google_fonts') }}" rel="stylesheet"><!--<![endif]-->
@endif
<style>
  body { margin:0 !important; padding:0 !important; width:100% !important; -webkit-text-size-adjust:100%; }
  @media screen and (max-width:599px) {
    .px { padding-left:20px !important; padding-right:20px !important; }
    .lbl-col { width:110px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:{{ $ch['gris'] }};">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $ch['gris'] }};">
<tr><td align="center" style="padding:24px 12px;">
<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;word-break:break-word;">

{{-- Liseré --}}
<tr><td style="font-size:0;line-height:0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        @foreach($lisere as $c)
            <td width="20%" height="6" style="height:6px;background-color:{{ $c }};font-size:0;line-height:0;">&nbsp;</td>
        @endforeach
    </tr></table>
</td></tr>

{{-- En-tête --}}
<tr><td class="px" style="background-color:{{ $ch['blanc'] }};padding:24px 32px;">
    <img src="{{ $logoClair }}" width="110" alt="{{ $nomRegie }}" border="0" style="display:block;width:110px;height:auto;border:0;{{ $titres }}font-size:20px;font-weight:800;color:{{ $ch['noir'] }};">
</td></tr>

{{-- Bandeau --}}
<tr><td style="background-color:{{ $ch['noir'] }};border-left:6px solid {{ $ch['rouge'] }};">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        <td class="px" style="padding:24px 32px 24px 26px;">
            <div style="{{ $petit }}color:{{ $ch['jaune'] }};">CIBLE CI · Nouvelle demande de devis</div>
            <div style="{{ $titres }}font-size:22px;line-height:28px;font-weight:800;color:{{ $ch['blanc'] }};margin-top:8px;">{{ $d['entreprise'] ?? '—' }}</div>
        </td>
    </tr></table>
</td></tr>

{{-- Corps --}}
<tr><td class="px" style="background-color:{{ $ch['blanc'] }};padding:28px 32px 32px 32px;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:2px solid {{ $ch['noir'] }};">
        <tr><td class="lbl-col" style="{{ $lbl }}">Nom</td><td style="{{ $val }}">{{ $d['nom'] ?? '—' }}</td></tr>
        <tr><td class="lbl-col" style="{{ $lbl }}">Poste</td><td style="{{ $valN }}">{{ $d['poste'] ?? '—' }}</td></tr>
        <tr><td class="lbl-col" style="{{ $lbl }}">Entreprise</td><td style="{{ $val }}">{{ $d['entreprise'] ?? '—' }}</td></tr>
        <tr><td class="lbl-col" style="{{ $lbl }}">Téléphone</td><td style="{{ $valN }}"><a href="tel:{{ $d['tel'] ?? '' }}" style="{{ $lien }}">{{ $d['tel'] ?? '—' }}</a></td></tr>
        <tr><td class="lbl-col" style="{{ $lbl }}">Email</td><td style="{{ $valN }}"><a href="mailto:{{ $d['email'] ?? '' }}" style="{{ $lien }}">{{ $d['email'] ?? '—' }}</a></td></tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;border-top:2px solid {{ $ch['noir'] }};">
        <tr><td class="lbl-col" style="{{ $lbl }}">Besoin</td><td style="{{ $val }}">{{ ucfirst($d['besoin'] ?? '—') }}</td></tr>
        <tr><td class="lbl-col" style="{{ $lbl }}">Zone visée</td><td style="{{ $valN }}">{{ ucfirst($d['zone'] ?? '—') }}</td></tr>
        <tr><td class="lbl-col" style="{{ $lbl }}">Budget indicatif</td><td style="{{ $valN }}">{{ $d['budget'] ?? '—' }}</td></tr>
        <tr><td class="lbl-col" style="{{ $lbl }}">Période souhaitée</td><td style="{{ $valN }}">{{ $d['periode'] ?? '—' }}</td></tr>
    </table>

    @if(!empty($d['message']))
        <div style="{{ $petit }}color:{{ $ch['rouge'] }};margin-top:26px;">Message</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:8px;"><tr>
            <td style="border-left:4px solid {{ $ch['rouge'] }};padding:4px 0 4px 14px;{{ $texte }}font-size:14.5px;line-height:1.6;color:{{ $ch['noir'] }};white-space:pre-wrap;">{{ $d['message'] }}</td>
        </tr></table>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:28px;border-top:1px solid {{ $ch['gris'] }};"><tr>
        <td style="padding-top:14px;{{ $texte }}font-size:12px;line-height:18px;color:{{ $ch['noir'] }};">
            Reçu le {{ $d['received_at'] ?? now()->format('d/m/Y H:i') }}<br>
            IP : {{ $d['ip'] ?? '—' }}
        </td>
    </tr></table>

</td></tr>

{{-- Pied de page --}}
<tr><td class="px" style="background-color:{{ $ch['noir'] }};padding:24px 32px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        <td valign="middle"><img src="{{ $logoSombre }}" width="90" alt="{{ $nomRegie }}" border="0" style="display:block;width:90px;height:auto;border:0;{{ $titres }}font-size:16px;font-weight:800;color:{{ $ch['blanc'] }};"></td>
        <td valign="middle" align="right" style="{{ $texte }}font-size:12px;line-height:18px;color:{{ $ch['gris'] }};">
            @if($slogan)<strong style="{{ $titres }}color:{{ $ch['blanc'] }};">{{ $slogan }}</strong><br>@endif
            Mail interne envoyé par Panora
        </td>
    </tr></table>
</td></tr>

</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>
