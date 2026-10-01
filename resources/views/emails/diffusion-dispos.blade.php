@php
    /*
     * Confirmation interne d'une diffusion des disponibilités (ou alerte
     * d'échec) — aux couleurs de la charte CIBLE, comme le mail client
     * (docs/diffusion/modele-brevo-disponibilites.html). 2026-10-01.
     *
     * Mise en page propre à ce mail (pas x-mail.layout) : les autres mails
     * internes de Panora gardent leur habillage. Styles en ligne (ils
     * survivent au transfert), largeur fluide (100 %, 600 px maximum).
     *
     * Charte : rouge #E20613 · jaune #FAB80B · vert #3AA835 · bleu #3F7FC0 ·
     * violet #81358A · gris #E6E6E6 · noir #111111 · blanc.
     * Poppins (titres) · Nunito (texte) — Arial si la messagerie les refuse.
     */
    $title = $succes
        ? 'Disponibilités envoyées aux clients'
        : "L'envoi des disponibilités a échoué";
    $preheader = $succes
        ? ($test
            ? "Test réussi — les disponibilités {$periode} sont arrivées dans la liste Tests internes."
            : "{$envoi->nb_destinataires} client(s) ont reçu les disponibilités {$periode}.")
        : "Les disponibilités {$periode} ne sont pas parties. Aucun client ne les a reçues.";

    // Couleur du statut : vert = parti, jaune = test parti, rouge = échec.
    $couleur  = ! $succes ? '#E20613' : ($test ? '#FAB80B' : '#3AA835');
    $surtitre = $succes ? ($test ? 'Envoi de test réussi' : 'Envoi réussi') : "Échec de l'envoi";
    $titre    = $succes
        ? ($test ? 'Le test est bien parti' : 'Les clients ont reçu les disponibilités')
        : 'Les disponibilités ne sont pas parties';

    $type = ucfirst($envoi->libelleMode()) . ($envoi->auteur ? ' — ' . $envoi->auteur->name : '');

    $chiffres = [
        $succes
            ? ['valeur' => $envoi->nb_destinataires ?? 0, 'libelle' => $test ? 'Destinataires de test' : 'Clients destinataires']
            : ['valeur' => '—', 'libelle' => 'Aucun client contacté'],
        ['valeur' => $envoi->nb_panneaux ?? 0, 'libelle' => 'Panneaux présentés'],
        ['valeur' => $succes ? ($envoi->envoye_at?->format('H\hi') ?? '—') : '—',
         'libelle' => $succes ? 'Envoyé le ' . ($envoi->envoye_at?->format('d/m') ?? '') : 'Non envoyé'],
    ];

    $titres = "font-family:'Poppins', Arial, Helvetica, sans-serif;";
    $texte  = "font-family:'Nunito', Arial, Helvetica, sans-serif;";
    $petit  = "{$titres}font-size:11px;line-height:14px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;";
    $lbl    = "{$texte}font-size:13px;line-height:18px;color:#111111;padding:10px 0;border-bottom:1px solid #E6E6E6;vertical-align:top;";
    $val    = "{$titres}font-size:14px;line-height:18px;font-weight:700;color:#111111;padding:10px 0;border-bottom:1px solid #E6E6E6;text-align:right;vertical-align:top;";
    $bouton = "display:inline-block;{$titres}font-size:14px;line-height:18px;font-weight:700;text-decoration:none;padding:14px 24px;margin:0 4px 10px;";

    $logoClair  = asset('images/logol.png');
    $logoSombre = asset('images/logob.png');
@endphp
<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{ $title }}</title>
<!--[if mso]><style>td,p,a,div,span{font-family:Arial,Helvetica,sans-serif !important;}</style><![endif]-->
<!--[if !mso]><!--><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700;800&family=Nunito:wght@400;700&display=swap" rel="stylesheet"><!--<![endif]-->
<style>
  body { margin:0 !important; padding:0 !important; width:100% !important; -webkit-text-size-adjust:100%; }
  @media screen and (max-width:599px) {
    .px { padding-left:20px !important; padding-right:20px !important; }
    .bouton { display:block !important; margin:0 0 10px 0 !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:#E6E6E6;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">{{ $preheader }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#E6E6E6;">
<tr><td align="center" style="padding:24px 12px;">
<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;word-break:break-word;">

{{-- Liseré aux cinq couleurs du symbole CIBLE --}}
<tr><td style="font-size:0;line-height:0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        @foreach(['#E20613', '#FAB80B', '#3AA835', '#3F7FC0', '#81358A'] as $c)
            <td width="20%" height="6" style="height:6px;background-color:{{ $c }};font-size:0;line-height:0;">&nbsp;</td>
        @endforeach
    </tr></table>
</td></tr>

{{-- En-tête --}}
<tr><td class="px" style="background-color:#FFFFFF;padding:24px 32px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        <td valign="middle"><img src="{{ $logoClair }}" width="110" alt="CIBLE" border="0" style="display:block;width:110px;height:auto;border:0;{{ $titres }}font-size:20px;font-weight:800;color:#111111;"></td>
        <td valign="middle" align="right" style="{{ $petit }}color:#111111;">Diffusion<br>des disponibilités</td>
    </tr></table>
</td></tr>

{{-- Bandeau de statut --}}
<tr><td style="background-color:#111111;border-left:6px solid {{ $couleur }};">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        <td class="px" style="padding:26px 32px 26px 26px;">
            <div style="{{ $petit }}color:{{ $couleur }};">{{ $surtitre }}</div>
            <div style="{{ $titres }}font-size:22px;line-height:28px;font-weight:800;color:#FFFFFF;margin-top:8px;">{{ $titre }}</div>
            <div style="{{ $texte }}font-size:15px;line-height:21px;color:#E6E6E6;margin-top:6px;">Disponibilités <strong style="color:#FFFFFF;">{{ $periode }}</strong></div>
        </td>
    </tr></table>
</td></tr>

{{-- Corps --}}
<tr><td class="px" style="background-color:#FFFFFF;padding:28px 32px 32px 32px;">

    @if($test)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:22px;"><tr>
            <td style="border-left:4px solid #FAB80B;padding:4px 0 4px 14px;{{ $texte }}font-size:14px;line-height:21px;color:#111111;">
                <strong style="{{ $titres }}">Mode test</strong> — seule la liste « Tests internes » de Brevo est concernée. Aucun client n'a été contacté.
            </td>
        </tr></table>
    @endif

    {{-- Chiffres clés --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        @foreach($chiffres as $i => $c)
            <td width="33%" valign="top" style="width:33%;padding:{{ $i === 0 ? '0 5px 0 0' : ($i === 2 ? '0 0 0 5px' : '0 3px') }};">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E6E6E6;"><tr>
                    <td align="center" valign="top" height="66" style="height:66px;padding:14px 6px;">
                        <div style="{{ $titres }}font-size:26px;line-height:30px;font-weight:800;color:{{ $i === 0 ? $couleur : '#111111' }};">{{ $c['valeur'] }}</div>
                        <div style="{{ $texte }}font-size:12px;line-height:16px;color:#111111;margin-top:4px;">{{ $c['libelle'] }}</div>
                    </td>
                </tr></table>
            </td>
        @endforeach
    </tr></table>

    {{-- Cause et marche à suivre (échec) --}}
    @unless($succes)
        <div style="{{ $petit }}color:#E20613;margin-top:26px;">Cause</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:8px;"><tr>
            <td style="border-left:4px solid #E20613;padding:4px 0 4px 14px;{{ $texte }}font-size:14px;line-height:21px;color:#111111;">
                {{ $envoi->erreur ?: 'Inconnue — voir le journal du serveur.' }}
            </td>
        </tr></table>
        <div style="{{ $petit }}color:#111111;margin-top:22px;">Que faire</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:6px;">
            @foreach([
                "Corriger la cause ci-dessus, ou la transmettre à l'administrateur.",
                "Ouvrir l'écran de diffusion dans Panora.",
                'Cliquer sur « Envoyer maintenant ».',
            ] as $n => $etape)
                <tr>
                    <td width="30" valign="top" style="width:30px;padding:5px 0;{{ $titres }}font-size:13px;line-height:20px;font-weight:800;color:#E20613;">0{{ $n + 1 }}</td>
                    <td valign="top" style="padding:5px 0;{{ $texte }}font-size:14px;line-height:20px;color:#111111;">{{ $etape }}</td>
                </tr>
            @endforeach
        </table>
    @endunless

    {{-- Détails --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;border-top:2px solid #111111;">
        <tr><td style="{{ $lbl }}">Période</td><td style="{{ $val }}">{{ $periode }}</td></tr>
        <tr><td style="{{ $lbl }}">Type d'envoi</td><td style="{{ $val }}">{{ $type }}</td></tr>
        @if($succes)
            <tr><td style="{{ $lbl }}">Envoyé le</td><td style="{{ $val }}">{{ $envoi->envoye_at?->format('d/m/Y à H\hi') ?? '—' }}</td></tr>
        @endif
        @if($envoi->brevo_campaign_id)
            <tr><td style="{{ $lbl }}">Campagne Brevo</td><td style="{{ $val }}">n° {{ $envoi->brevo_campaign_id }}</td></tr>
        @endif
    </table>

    {{-- Actions --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:28px;">
        <tr><td align="center">
            @if($succes && $lienApercu)
                <a href="{{ $lienApercu }}" class="bouton" style="{{ $bouton }}background-color:#E20613;color:#FFFFFF;">Voir les statistiques Brevo</a>
            @endif
            @unless($succes)
                <a href="{{ $lienEcran }}" class="bouton" style="{{ $bouton }}background-color:#E20613;color:#FFFFFF;">Ouvrir l'écran de diffusion</a>
            @endunless
            @if($lienPdf)
                <a href="{{ $lienPdf }}" class="bouton" style="{{ $bouton }}background-color:#FFFFFF;color:#111111;border:2px solid #111111;padding:12px 22px;">{{ $succes ? 'Voir le catalogue envoyé' : 'Voir le catalogue préparé' }}</a>
            @endif
        </td></tr>
        @if($succes)
            <tr><td align="center" style="{{ $texte }}font-size:12px;line-height:18px;color:#111111;padding-top:6px;">
                @if($lienApercu)
                    Ouvertures, clics et désinscriptions — accès au compte Brevo nécessaire.<br>
                @endif
                <a href="{{ $lienEcran }}" style="color:#111111;text-decoration:underline;">Écran de diffusion dans Panora</a>
            </td></tr>
        @endif
    </table>

</td></tr>

{{-- Pied de page --}}
<tr><td class="px" style="background-color:#111111;padding:24px 32px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        <td valign="middle"><img src="{{ $logoSombre }}" width="90" alt="CIBLE" border="0" style="display:block;width:90px;height:auto;border:0;{{ $titres }}font-size:16px;font-weight:800;color:#FFFFFF;"></td>
        <td valign="middle" align="right" style="{{ $texte }}font-size:12px;line-height:18px;color:#E6E6E6;">
            <strong style="{{ $titres }}color:#FFFFFF;">Vous visez juste.</strong><br>
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
