@php
    /**
     * Habillage commun des mails Panora — charte graphique de la régie
     * (config/charte.php, CIBLE par défaut). Refonte 2026-10-01 sur le
     * modèle validé de resources/views/emails/diffusion-dispos.blade.php.
     *
     * Structure : liseré 5 couleurs · en-tête blanc + logo · corps blanc ·
     * note de bas de mail · pied noir (logo sombre, slogan, coordonnées,
     * « Envoyé par Panora »). Largeur fluide (100 %, 600 px maximum).
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
        .body p { font-family: {!! $ffTexte !!}; font-size: 15px; color: {{ $noir }}; margin: 0 0 14px; line-height: 1.6; }
        .body strong { color: {{ $noir }}; font-weight: 700; }

        .info { background: {{ $blanc }}; border-top: 2px solid {{ $noir }}; padding: 0; margin: 20px 0 24px; }
        .info-row { display: table; width: 100%; padding: 0; border-bottom: 1px solid {{ $gris }}; }
        .info-row > div { display: table-cell; vertical-align: top; padding: 10px 0; }
        .info-row .lbl { width: 38%; font-family: {!! $ffTexte !!}; font-size: 13px; line-height: 1.5; color: {{ $noir }}; font-weight: 400; padding-right: 12px; }
        .info-row .val { font-family: {!! $ffTitres !!}; font-size: 14px; line-height: 1.5; color: {{ $noir }}; font-weight: 700; text-align: right; }

        code, .code { font-family: {!! $ffMono !!}; font-size: 13px; background: {{ $gris }}; color: {{ $noir }}; padding: 2px 6px; border: 0; font-weight: 700; }
        .code-strong { font-family: {!! $ffMono !!}; font-size: 16px; background: {{ $noir }}; color: {{ $jaune }}; padding: 8px 14px; border: 0; font-weight: 700; display: inline-block; letter-spacing: 1px; }

        .alert { font-family: {!! $ffTexte !!}; padding: 12px 16px; font-size: 14px; line-height: 1.55; margin: 18px 0; background: {{ $blanc }}; color: {{ $noir }}; border: 1px solid {{ $gris }}; border-left: 4px solid {{ $bleu }}; }
        .alert-warning { border-left-color: {{ $jaune }}; }
        .alert-success { border-left-color: {{ $vert }}; }
        .alert-danger  { border-left-color: {{ $rouge }}; }

        .pill { display: inline-block; font-family: {!! $ffTitres !!}; font-size: 11px; line-height: 16px; font-weight: 700; padding: 5px 12px 5px 10px; text-transform: uppercase; letter-spacing: 1px; background: {{ $noir }}; color: {{ $blanc }}; border: 0; border-left: 4px solid {{ $violet }}; margin-bottom: 16px; }
        .pill-info    { border-left-color: {{ $bleu }}; }
        .pill-success { border-left-color: {{ $vert }}; }
        .pill-danger  { border-left-color: {{ $rouge }}; }
        .pill-warning { border-left-color: {{ $jaune }}; }

        .cta-wrap { text-align: center; margin: 28px 0; }
        .cta { display: inline-block; font-family: {!! $ffTitres !!}; background: {{ $rouge }}; color: {{ $blanc }} !important; font-size: 14px; line-height: 18px; font-weight: 700; text-decoration: none; padding: 14px 28px; border: 0; }
        .cta-fallback { font-family: {!! $ffTexte !!}; margin-top: 14px; font-size: 12px; line-height: 1.5; color: {{ $noir }}; word-break: break-all; }
        .cta-fallback a { color: {{ $noir }}; }

        ul.steps { font-family: {!! $ffTexte !!}; margin: 14px 0 18px; padding: 0 0 0 18px; color: {{ $noir }}; font-size: 14px; }
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
<body style="margin:0 !important;padding:0 !important;width:100% !important;background-color:{{ $gris }};font-family:{!! $ffTexte !!};color:{{ $noir }};line-height:1.6;-webkit-font-smoothing:antialiased;">

{{-- Préheader (texte caché de l'aperçu boîte mail) --}}
@isset($preheader)
    <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;color:{{ $gris }};">{{ $preheader }}</div>
@endisset

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="{{ $gris }}" style="background-color:{{ $gris }};">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
            <table role="presentation" class="container" width="100%" cellspacing="0" cellpadding="0" border="0"
                   style="width:100%;max-width:600px;word-break:break-word;">

                {{-- ═══ Liseré aux couleurs de la charte ═══ --}}
                <tr>
                    <td style="font-size:0;line-height:0;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
                            @foreach($lisere as $couleur)
                                <td width="{{ round(100 / max($lisere->count(), 1)) }}%" height="6" style="height:6px;background-color:{{ $couleur }};font-size:0;line-height:0;">&nbsp;</td>
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
                                        <td style="padding-top:14px;font-family:{!! $ffTexte !!};font-size:12px;line-height:1.6;color:{{ $noir }};">{{ $footerNote }}</td>
                                    </tr>
                                </table>
                            @endif
                        @endisset
                    </td>
                </tr>

                {{-- ═══ Pied de page ═══ --}}
                <tr>
                    <td class="footer resp-pad" style="background-color:{{ $noir }};padding:24px 32px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td class="foot-col" valign="middle" style="vertical-align:middle;">
                                    @if($logoSombre)
                                        <img src="{{ $logoSombre }}" width="90" alt="{{ $nomRegie }}" border="0"
                                             style="display:block;width:90px;height:auto;border:0;outline:none;text-decoration:none;font-family:{!! $ffTitres !!};font-size:16px;font-weight:800;color:{{ $blanc }};">
                                    @else
                                        <span style="font-family:{!! $ffTitres !!};font-size:18px;font-weight:800;color:{{ $blanc }};">{{ $nomRegie }}</span>
                                    @endif
                                </td>
                                @if($slogan)
                                    <td class="foot-col foot-col-r" valign="middle" align="right" style="vertical-align:middle;text-align:right;font-family:{!! $ffTitres !!};font-size:14px;line-height:1.4;font-weight:700;color:{{ $blanc }};">
                                        {{ $slogan }}
                                    </td>
                                @endif
                            </tr>
                        </table>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:16px;">
                            <tr>
                                <td style="padding-top:14px;font-family:{!! $ffTexte !!};font-size:12px;line-height:1.7;color:{{ $gris }};">
                                    @if(!empty($coord['activite']))<strong style="color:{{ $blanc }};">{{ $nomRegie }}</strong> · {{ $coord['activite'] }}<br>@endif
                                    @if(!empty($coord['adresse'])){{ $coord['adresse'] }}<br>@endif
                                    @if(!empty($coord['telephones'])){{ $coord['telephones'] }}<br>@endif
                                    @if(!empty($coord['email']))<a href="mailto:{{ $coord['email'] }}" style="color:{{ $gris }};text-decoration:underline;">{{ $coord['email'] }}</a>@endif
                                    @if(!empty($coord['email']) && $siteUrl) · @endif
                                    @if($siteUrl)<a href="{{ $siteUrl }}" style="color:{{ $gris }};text-decoration:underline;">{{ $coord['site'] }}</a>@endif
                                </td>
                            </tr>
                            <tr>
                                <td style="padding-top:12px;font-family:{!! $ffTexte !!};font-size:11px;line-height:1.6;color:{{ $gris }};">
                                    Mail envoyé par Panora · © {{ date('Y') }} {{ $nomRegie }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td>
    </tr>
</table>

</body>
</html>
