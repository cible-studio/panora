@php
    /**
     * Layout email Panora — refonte branding (Évolution).
     *
     * - "Panora" est la plateforme.
     * - "CIBLE CI" est la régie qui l'opère ; il pourra y avoir d'autres
     *   instances (panora-regieX) → l'opérateur est paramétré via
     *   config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI')).
     *
     * COMPATIBILITÉ TRANSFERT (Gmail/Outlook strippent le <style> au
     * forward) : tous les styles critiques sont DUPLIQUÉS en inline
     * style="..." sur chaque élément. Le <style> dans <head> ne sert
     * plus que pour les @media (responsive) et le hover.
     *
     * Logos :
     *   - public/images/panora.png         (couleur/foncé sur fond clair)
     *   - public/images/panora-blanc.png   (clair sur fond foncé)
     */
    $operatorName = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $platformName = 'Panora';

    $resolveLogo = function (string $rel): ?string {
        $abs = public_path($rel);
        if (!is_file($abs) || !is_readable($abs)) return null;
        // URL absolue prioritaire (Gmail proxy cache mieux qu'un base64).
        return asset($rel);
    };
    $logoPanora     = $resolveLogo('images/panora.png');
    $logoPanoraDark = $resolveLogo('images/panora-blanc.png'); // pour bandeau foncé
    // Logo opérateur (CIBLE CI / régie utilisatrice).
    // logon.png = version pour fond foncé · logol.png = pour fond clair.
    $logoOperatorDark  = $resolveLogo('images/logon.png');
    $logoOperatorLight = $resolveLogo('images/logol.png');
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
    <title>{{ $title ?? $platformName }}</title>
    <style type="text/css">
        /* Le <style> peut être stripped par Gmail au transfert. Les styles
           critiques (header/footer + branding) sont aussi dupliqués en
           inline ci-dessous (style=""). Cette section reste pour les
           éléments du corps de mail (.info, .pill, .cta, etc.) afin de
           ne pas casser les emails enfants existants. */
        body, table, td, p, a, li { -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; }
        table, td { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; display: block; }
        a { color: #c2570d; text-decoration: none; }
        a:hover { text-decoration: underline; }

        .body h1 { font-size: 22px; font-weight: 600; color: #111827; line-height: 1.3; margin: 0 0 12px; letter-spacing: -0.3px; }
        .body h2 { font-size: 14px; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; margin: 28px 0 8px; }
        .body p { font-size: 15px; color: #4b5563; margin: 0 0 14px; line-height: 1.6; }
        .body strong { color: #111827; font-weight: 600; }

        .info { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 18px 22px; margin: 18px 0 22px; }
        .info-row { display: table; width: 100%; padding: 6px 0; }
        .info-row > div { display: table-cell; vertical-align: top; padding: 4px 0; }
        .info-row .lbl { width: 38%; font-size: 13px; color: #6b7280; font-weight: 500; }
        .info-row .val { font-size: 14px; color: #111827; font-weight: 500; text-align: right; }

        code, .code { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; font-size: 13px; background: #f3f4f6; color: #111827; padding: 2px 6px; border-radius: 4px; border: 1px solid #e5e7eb; }
        .code-strong { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; font-size: 15px; background: #fff7ed; color: #c2570d; padding: 6px 12px; border-radius: 6px; border: 1px solid #fed7aa; font-weight: 600; display: inline-block; }

        .alert { padding: 14px 18px; border-radius: 8px; font-size: 13px; margin: 18px 0; border-left: 3px solid #94a3b8; background: #f8fafc; color: #475569; }
        .alert-warning { background: #fffbeb; border-left-color: #f59e0b; color: #92400e; }
        .alert-success { background: #f0fdf4; border-left-color: #22c55e; color: #166534; }
        .alert-danger  { background: #fef2f2; border-left-color: #ef4444; color: #991b1b; }

        .pill { display: inline-block; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; margin-bottom: 12px; }
        .pill-success { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .pill-danger  { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .pill-warning { background: #fffbeb; color: #92400e; border-color: #fde68a; }

        .cta-wrap { text-align: center; margin: 28px 0; }
        .cta { display: inline-block; background: #c2570d; color: #ffffff !important; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; letter-spacing: 0.2px; }
        .cta:hover { background: #a04609; }
        .cta-fallback { margin-top: 14px; font-size: 12px; color: #6b7280; word-break: break-all; }

        ul.steps { margin: 14px 0 18px; padding: 0 0 0 18px; color: #4b5563; font-size: 14px; }
        ul.steps li { margin: 6px 0; line-height: 1.55; }

        @media only screen and (max-width: 600px) {
            .resp-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .resp-h1  { font-size: 19px !important; }
            .resp-cta { display: block !important; padding: 14px 20px !important; }
            .info-row { display: block; }
            .info-row > div { display: block; width: 100% !important; padding: 2px 0; }
            .info-row .val { text-align: left; }
        }
        @media (prefers-color-scheme: dark) {
            body, .container, .header, .body, .footer { background-color: #ffffff !important; color: #1f2937 !important; }
        }
    </style>
</head>
<body style="margin:0 !important;padding:0 !important;width:100% !important;background-color:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#1f2937;line-height:1.6;-webkit-font-smoothing:antialiased;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f4f6f8" style="background-color:#f4f6f8;">
    <tr>
        <td align="center" style="padding:32px 16px;">

            {{-- Préheader (texte caché de la preview boîte mail) --}}
            @isset($preheader)
                <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f4f6f8;">
                    {{ $preheader }}
                </div>
            @endisset

            <table role="presentation" class="container" width="580" cellspacing="0" cellpadding="0" border="0"
                   style="max-width:580px;width:100%;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">

                {{-- ═══ HEADER : Panora seul (gros logo, fond foncé) ═══ --}}
                {{-- Le logo opérateur (CIBLE) est uniquement dans le footer
                     — sa version sur fond noir (logon.png) rendait mal en
                     en-tête (petit + fond noir non transparent). --}}
                <tr>
                    <td class="header resp-pad"
                        style="padding:28px 36px;background:#0f172a;border-bottom:1px solid #1e293b;text-align:left;">
                        @if($logoPanoraDark)
                            <img src="{{ $logoPanoraDark }}" alt="{{ $platformName }}" height="38"
                                 style="height:38px;width:auto;border:0;display:inline-block;outline:none;text-decoration:none;">
                        @else
                            <span style="font-size:24px;font-weight:800;color:#ffffff;letter-spacing:-0.3px;">{{ $platformName }}</span>
                        @endif
                    </td>
                </tr>

                {{-- ═══ CORPS ═══ --}}
                <tr>
                    <td class="body resp-pad" style="padding:32px 36px;background:#ffffff;color:#1f2937;font-size:15px;line-height:1.6;">
                        {{ $slot }}
                    </td>
                </tr>

                {{-- ═══ FOOTER : Panora à gauche · CIBLE à droite ═══ --}}
                <tr>
                    <td class="footer resp-pad"
                        style="padding:22px 36px;background:#f9fafb;border-top:1px solid #e5e7eb;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td align="left" valign="middle" style="vertical-align:middle;">
                                    @if($logoPanora)
                                        <img src="{{ $logoPanora }}" alt="{{ $platformName }}" height="28"
                                             style="height:28px;width:auto;border:0;display:block;outline:none;text-decoration:none;">
                                    @else
                                        <span style="font-size:16px;font-weight:800;color:#1f2937;">{{ $platformName }}</span>
                                    @endif
                                </td>
                                <td align="right" valign="middle" style="vertical-align:middle;text-align:right;">
                                    @if($logoOperatorLight)
                                        <img src="{{ $logoOperatorLight }}" alt="{{ $operatorName }}" height="28"
                                             style="height:28px;width:auto;border:0;display:inline-block;outline:none;text-decoration:none;">
                                    @else
                                        <span style="font-size:14px;font-weight:700;color:#1f2937;">{{ $operatorName }}</span>
                                    @endif
                                </td>
                            </tr>
                        </table>

                        @isset($footerNote)
                            <p style="font-size:12px;color:#6b7280;margin:14px 0 0;line-height:1.6;">{{ $footerNote }}</p>
                        @endisset

                        <p style="font-size:11px;color:#9ca3af;margin:10px 0 0;line-height:1.6;">
                            © {{ date('Y') }} {{ $platformName }} · {{ $operatorName }} — Côte d'Ivoire. Tous droits réservés.
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>

{{-- ═══════════════════════════════════════════════════════════════════
     UTILITAIRES INLINE (à utiliser dans les vues emails enfant)

     Les classes suivantes sont remplacées par des helpers inline pour
     survivre au forward Gmail/Outlook. Aux vues enfants d'utiliser
     directement les style="" recommandés ci-dessous.

     <h1>      → <h1 style="font-size:22px;font-weight:600;color:#111827;line-height:1.3;margin:0 0 12px;letter-spacing:-0.3px;" class="resp-h1">

     <h2>      → <h2 style="font-size:14px;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:0.5px;margin:28px 0 8px;">

     <p>       → <p style="font-size:15px;color:#4b5563;margin:0 0 14px;line-height:1.6;">

     CTA      → <a href="..." class="resp-cta" style="display:inline-block;background:#c2570d;color:#ffffff !important;font-size:14px;font-weight:600;text-decoration:none;padding:12px 28px;border-radius:6px;letter-spacing:0.2px;">Mon CTA</a>

     INFO     → <table style="width:100%;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;margin:18px 0 22px;">
                  <tr><td style="padding:18px 22px;">
                    <table width="100%"><tr>
                      <td style="font-size:13px;color:#6b7280;font-weight:500;padding:4px 0;">Label</td>
                      <td style="font-size:14px;color:#111827;font-weight:500;text-align:right;padding:4px 0;">Valeur</td>
                    </tr></table>
                  </td></tr>
                </table>

     PILL     → <span style="display:inline-block;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;margin-bottom:12px;">Statut</span>

     CODE     → <span style="font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace;font-size:15px;background:#fff7ed;color:#c2570d;padding:6px 12px;border-radius:6px;border:1px solid #fed7aa;font-weight:600;display:inline-block;">CODE-123</span>

     Les anciennes classes CSS (.body h1, .info-row, .pill, .cta, .code,
     .code-strong, .alert, .alert-success, .alert-warning, .alert-danger)
     sont conservées dans les vues existantes — elles restent visibles
     dans la plupart des clients mail modernes (Apple Mail, Gmail web,
     Thunderbird, Outlook moderne) — mais disparaissent au transfert.

     Pour les nouveaux mails, préférer le style="" inline duppliqué.
═══════════════════════════════════════════════════════════════════ --}}
