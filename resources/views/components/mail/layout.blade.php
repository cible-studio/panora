@php
    /**
     * Habillage commun des mails Panora — charte graphique de la régie
     * (config/charte.php, CIBLE par défaut). Refonte 2026-10-01 sur le
     * modèle validé de resources/views/emails/diffusion-dispos.blade.php.
     *
     * Structure : carte blanche arrondie (liseré 5 couleurs, logo, corps,
     * note de bas de mail) posée sur un fond gris très clair · pied de page
     * clair sous la carte (logo, slogan, coordonnées, « Envoyé par
     * Panora »). Largeur fluide (100 %, 600 px maximum).
     *
     * 2026-10-29 (direction) : plus aucun fond noir (étiquettes, code mis en
     * avant, pied de page) — style clair des grandes marques. Teintes
     * calculées par App\Support\PdfCharte (mêmes que les PDF).
     *
     * Variables / slots inchangés : $title, $preheader, $footerNote, $slot.
     * Les classes utilisées par les vues enfants (.info, .info-row, .lbl,
     * .val, .pill*, .cta*, .alert*, .code, .code-strong, ul.steps,
     * .body h1/h2/p, .resp-*) sont conservées et re-stylées à la charte.
     *
     * COMPATIBILITÉ TRANSFERT : le <style> ci-dessous est converti en
     * styles en ligne à l'envoi (inliner CSS de AppServiceProvider) ; les
     * blocs structurants (en-tête, pied) sont en plus écrits en ligne.
     * Le <style> restant ne sert qu'au responsive (@media).
     */
    $c = config('charte.couleurs');
    $rouge  = $c['rouge']  ?? '#E20613';
    $jaune  = $c['jaune']  ?? '#FAB80B';
    $vert   = $c['vert']   ?? '#3AA835';
    $bleu   = $c['bleu']   ?? '#3F7FC0';
    $violet = $c['violet'] ?? '#81358A';
    $gris   = $c['gris']   ?? '#E6E6E6';
    $noir   = $c['noir']   ?? '#111111';
    $blanc  = $c['blanc']  ?? '#FFFFFF';

    $teintes   = \App\Support\PdfCharte::data();
    $fondPage  = $teintes['gris_clair'] ?? '#F4F4F4';
    $texteDoux = $teintes['texte_doux'] ?? '#535353';
    $textePale = $teintes['texte_pale'] ?? '#888888';
    $clair     = fn (string $nom) => $teintes[$nom . '_clair'] ?? $fondPage;

    $lisere = collect(config('charte.lisere', ['rouge', 'jaune', 'vert', 'bleu', 'violet']))
        ->map(fn ($k) => $c[$k] ?? null)->filter()->values();

    $nomRegie = config('charte.nom', 'CIBLE');
    $slogan   = config('charte.slogan');
    $coord    = config('charte.coordonnees', []);

    $policeTitres = config('charte.polices.titres', 'Poppins');
    $policeTexte  = config('charte.polices.texte', 'Nunito');
    $secours      = config('charte.polices.secours', 'Arial, Helvetica, sans-serif');
    $fontsUrl     = config('charte.polices.google_fonts');
    $ffTitres     = "'{$policeTitres}', {$secours}";
    $ffTexte      = "'{$policeTexte}', {$secours}";
    $ffMono       = "Consolas, Menlo, 'Courier New', monospace";

    $logoClair  = config('charte.logos.clair') ? asset(config('charte.logos.clair')) : null;
    $logoSombre = config('charte.logos.sombre') ? asset(config('charte.logos.sombre')) : null;

    $siteUrl = ! empty($coord['site'])
        ? (str_starts_with($coord['site'], 'http') ? $coord['site'] : 'https://' . $coord['site'])
        : null;
@endphp
<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>{{ $title ?? $nomRegie }}</title>
    <!--[if mso]><style>td,p,a,div,span,h1,h2,li{font-family:Arial,Helvetica,sans-serif !important;}</style><![endif]-->
    @if($fontsUrl)
    <!--[if !mso]><!--><link href="{{ $fontsUrl }}" rel="stylesheet"><!--<![endif]-->
    @endif
    <style type="text/css">
        body, table, td, p, a, li { -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; }
        table, td { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; display: block; }
        a { color: {{ $rouge }}; text-decoration: underline; }

        .body h1 { font-family: {!! $ffTitres !!}; font-size: 22px; font-weight: 800; color: {{ $noir }}; line-height: 1.3; margin: 0 0 14px; }
        .body h2 { font-family: {!! $ffTitres !!}; font-size: 12px; font-weight: 700; color: {{ $rouge }}; text-transform: uppercase; letter-spacing: 1.2px; line-height: 1.4; margin: 28px 0 10px; }
        .body p { font-family: {!! $ffTexte !!}; font-size: 15px; color: {{ $texteDoux }}; margin: 0 0 14px; line-height: 1.6; }
        .body strong { color: {{ $noir }}; font-weight: 700; }

        .info { background: {{ $blanc }}; border-top: 1px solid {{ $gris }}; padding: 0; margin: 20px 0 24px; }
        .info-row { display: table; width: 100%; padding: 0; border-bottom: 1px solid {{ $gris }}; }
        .info-row > div { display: table-cell; vertical-align: top; padding: 10px 0; }
        .info-row .lbl { width: 38%; font-family: {!! $ffTexte !!}; font-size: 13px; line-height: 1.5; color: {{ $texteDoux }}; font-weight: 400; padding-right: 12px; }
        .info-row .val { font-family: {!! $ffTitres !!}; font-size: 14px; line-height: 1.5; color: {{ $noir }}; font-weight: 700; text-align: right; }

        code, .code { font-family: {!! $ffMono !!}; font-size: 13px; background: {{ $fondPage }}; color: {{ $noir }}; padding: 2px 6px; border: 0; border-radius: 4px; font-weight: 700; }
        .code-strong { font-family: {!! $ffMono !!}; font-size: 16px; background: {{ $fondPage }}; color: {{ $noir }}; padding: 8px 14px; border: 1px solid {{ $gris }}; border-radius: 8px; font-weight: 700; display: inline-block; letter-spacing: 1px; }

        .alert { font-family: {!! $ffTexte !!}; padding: 12px 16px; font-size: 14px; line-height: 1.55; margin: 18px 0; background: {{ $clair('bleu') }}; color: {{ $noir }}; border: 0; border-left: 4px solid {{ $bleu }}; border-radius: 8px; }
        .alert-warning { background: {{ $clair('jaune') }}; border-left-color: {{ $jaune }}; }
        .alert-success { background: {{ $clair('vert') }}; border-left-color: {{ $vert }}; }
        .alert-danger  { background: {{ $clair('rouge') }}; border-left-color: {{ $rouge }}; }

        .pill { display: inline-block; font-family: {!! $ffTitres !!}; font-size: 11px; line-height: 16px; font-weight: 700; padding: 4px 12px; text-transform: uppercase; letter-spacing: 0.6px; background: {{ $clair('violet') }}; color: {{ $violet }}; border: 0; border-radius: 999px; margin-bottom: 16px; }
        .pill-info    { background: {{ $clair('bleu') }}; color: {{ $bleu }}; }
        .pill-success { background: {{ $clair('vert') }}; color: {{ $vert }}; }
        .pill-danger  { background: {{ $clair('rouge') }}; color: {{ $rouge }}; }
        .pill-warning { background: {{ $clair('jaune') }}; color: {{ $noir }}; }

        .cta-wrap { text-align: center; margin: 28px 0; }
        .cta { display: inline-block; font-family: {!! $ffTitres !!}; background: {{ $rouge }}; color: {{ $blanc }} !important; font-size: 14px; line-height: 18px; font-weight: 700; text-decoration: none; padding: 14px 28px; border: 0; border-radius: 8px; }
        .cta-fallback { font-family: {!! $ffTexte !!}; margin-top: 14px; font-size: 12px; line-height: 1.5; color: {{ $textePale }}; word-break: break-all; }
        .cta-fallback a { color: {{ $texteDoux }}; }

        ul.steps { font-family: {!! $ffTexte !!}; margin: 14px 0 18px; padding: 0 0 0 18px; color: {{ $texteDoux }}; font-size: 14px; }
        ul.steps li { margin: 6px 0; line-height: 1.55; }

        @media only screen and (max-width: 599px) {
            .resp-pad { padding-left: 20px !important; padding-right: 20px !important; }
            .resp-h1  { font-size: 19px !important; }
            .body h1  { font-size: 19px !important; }
            .resp-cta, .cta { display: block !important; padding: 14px 16px !important; }
            .info-row { display: block !important; }
            .info-row > div { display: block !important; width: 100% !important; text-align: left !important; }
            .info-row .lbl { padding: 10px 0 0 !important; }
            .info-row .val { padding: 2px 0 10px !important; }
            .foot-col { display: block !important; width: 100% !important; text-align: left !important; }
            .foot-col-r { padding-top: 14px !important; }
        }
        @media (prefers-color-scheme: dark) {
            .body { background-color: {{ $blanc }} !important; color: {{ $noir }} !important; }
        }
    </style>
</head>
<body style="margin:0 !important;padding:0 !important;width:100% !important;background-color:{{ $fondPage }};font-family:{!! $ffTexte !!};color:{{ $noir }};line-height:1.6;-webkit-font-smoothing:antialiased;">

{{-- Préheader (texte caché de l'aperçu boîte mail) --}}
@isset($preheader)
    <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;color:{{ $gris }};">{{ $preheader }}</div>
@endisset

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="{{ $fondPage }}" style="background-color:{{ $fondPage }};">
    <tr>
        <td align="center" style="padding:32px 12px;">
            <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
            <table role="presentation" class="container" width="100%" cellspacing="0" cellpadding="0" border="0"
                   style="width:100%;max-width:600px;word-break:break-word;">

                {{-- ═══ Carte : liseré, en-tête, corps ═══ --}}
                <tr><td style="background-color:{{ $blanc }};border:1px solid {{ $gris }};border-radius:12px;overflow:hidden;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">

                {{-- ═══ Liseré aux couleurs de la charte ═══ --}}
                <tr>
                    <td style="font-size:0;line-height:0;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
                            @foreach($lisere as $couleur)
                                <td width="{{ round(100 / max($lisere->count(), 1)) }}%" height="4" style="height:4px;background-color:{{ $couleur }};font-size:0;line-height:0;">&nbsp;</td>
                            @endforeach
                        </tr></table>
                    </td>
                </tr>

                {{-- ═══ En-tête : logo de la régie ═══ --}}
                <tr>
                    <td class="header resp-pad" style="background-color:{{ $blanc }};padding:24px 32px 20px;border-bottom:1px solid {{ $gris }};">
                        @if($logoClair)
                            <img src="{{ $logoClair }}" width="110" alt="{{ $nomRegie }}" border="0"
                                 style="display:block;width:110px;height:auto;border:0;outline:none;text-decoration:none;font-family:{!! $ffTitres !!};font-size:20px;font-weight:800;color:{{ $noir }};">
                        @else
                            <span style="font-family:{!! $ffTitres !!};font-size:22px;font-weight:800;color:{{ $noir }};">{{ $nomRegie }}</span>
                        @endif
                    </td>
                </tr>

                {{-- ═══ Corps ═══ --}}
                <tr>
                    <td class="body resp-pad" style="padding:28px 32px 32px;background-color:{{ $blanc }};font-family:{!! $ffTexte !!};color:{{ $noir }};font-size:15px;line-height:1.6;">
                        {{ $slot }}

                        @isset($footerNote)
                            @if(trim((string) $footerNote) !== '')
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:28px;border-top:1px solid {{ $gris }};">
                                    <tr>
                                        <td style="padding-top:14px;font-family:{!! $ffTexte !!};font-size:12px;line-height:1.6;color:{{ $textePale }};">{{ $footerNote }}</td>
                                    </tr>
                                </table>
                            @endif
                        @endisset
                    </td>
                </tr>

                </table>
                </td></tr>

                {{-- ═══ Pied de page clair, sous la carte ═══ --}}
                <tr>
                    <td class="footer resp-pad" align="center" style="padding:24px 32px 8px;text-align:center;">
                        @if($logoClair)
                            <img src="{{ $logoClair }}" width="72" alt="{{ $nomRegie }}" border="0"
                                 style="display:inline-block;width:72px;height:auto;border:0;outline:none;text-decoration:none;font-family:{!! $ffTitres !!};font-size:14px;font-weight:800;color:{{ $noir }};">
                        @else
                            <span style="font-family:{!! $ffTitres !!};font-size:16px;font-weight:800;color:{{ $noir }};">{{ $nomRegie }}</span>
                        @endif
                        @if($slogan)
                            <div style="padding-top:8px;font-family:{!! $ffTitres !!};font-size:13px;line-height:1.4;font-weight:700;color:{{ $noir }};">{{ $slogan }}</div>
                        @endif
                        <div style="padding-top:10px;font-family:{!! $ffTexte !!};font-size:12px;line-height:1.7;color:{{ $textePale }};">
                            @if(!empty($coord['activite'])){{ $nomRegie }} · {{ $coord['activite'] }}<br>@endif
                            @if(!empty($coord['adresse'])){{ $coord['adresse'] }}<br>@endif
                            @if(!empty($coord['telephones'])){{ $coord['telephones'] }}<br>@endif
                            @if(!empty($coord['email']))<a href="mailto:{{ $coord['email'] }}" style="color:{{ $texteDoux }};text-decoration:underline;">{{ $coord['email'] }}</a>@endif
                            @if(!empty($coord['email']) && $siteUrl) · @endif
                            @if($siteUrl)<a href="{{ $siteUrl }}" style="color:{{ $texteDoux }};text-decoration:underline;">{{ $coord['site'] }}</a>@endif
                        </div>
                        <div style="padding-top:10px;font-family:{!! $ffTexte !!};font-size:11px;line-height:1.6;color:{{ $textePale }};">
                            Mail envoyé par Panora · © {{ date('Y') }} {{ $nomRegie }}
                        </div>
                    </td>
                </tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td>
    </tr>
</table>

</body>
</html>
