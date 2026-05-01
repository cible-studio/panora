@php
    /**
     * Logo CIBLE CI — embarqué en base64 pour fiabilité (Gmail / Outlook
     * masquent souvent les images externes par défaut).
     * Cherche dans plusieurs chemins, fallback texte si rien trouvé.
     */
    $logoSrc = (function () {
        foreach ([
            public_path('images/logo-cible.png'),
            public_path('images/logol.png'),
            public_path('images/logo.png'),
        ] as $p) {
            if (is_file($p) && is_readable($p)) {
                $mime = mime_content_type($p) ?: 'image/png';
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p));
            }
        }
        return null;
    })();
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
    <title>{{ $title ?? 'CIBLE CI' }}</title>
    <style>
        /* ──────────────────────────────────────────────────────────
           Reset email-safe + design system minimaliste light theme
           Inspiré : Stripe, Linear, Notion, GitHub
        ────────────────────────────────────────────────────────── */
        body, table, td, p, a, li {
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
        }
        table, td { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; display: block; }
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: #f4f6f8;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            color: #1f2937;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        a { color: #c2570d; text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ── Card container ── */
        .container {
            max-width: 580px;
            margin: 0 auto;
            background: #ffffff;
        }

        /* ── Header ── */
        .header {
            padding: 32px 40px 24px;
            border-bottom: 1px solid #f1f5f9;
            text-align: left;
        }
        .header img { height: 32px; width: auto; }
        .header .brand-text {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            letter-spacing: -0.2px;
            line-height: 1;
        }

        /* ── Body ── */
        .body { padding: 32px 40px; }
        .body h1 {
            font-size: 22px;
            font-weight: 600;
            color: #111827;
            line-height: 1.3;
            margin: 0 0 12px;
            letter-spacing: -0.3px;
        }
        .body h2 {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 28px 0 8px;
        }
        .body p {
            font-size: 15px;
            color: #4b5563;
            margin: 0 0 14px;
            line-height: 1.6;
        }
        .body strong { color: #111827; font-weight: 600; }

        /* ── Info card (clé/valeur) ── */
        .info {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 18px 22px;
            margin: 18px 0 22px;
        }
        .info-row { display: table; width: 100%; padding: 6px 0; }
        .info-row > div { display: table-cell; vertical-align: top; padding: 4px 0; }
        .info-row .lbl {
            width: 38%;
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }
        .info-row .val {
            font-size: 14px;
            color: #111827;
            font-weight: 500;
            text-align: right;
        }

        /* ── Code (refs, mots de passe) ── */
        code, .code {
            font-family: ui-monospace, "SF Mono", "Cascadia Mono", Menlo, Consolas, monospace;
            font-size: 13px;
            background: #f3f4f6;
            color: #111827;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
        }
        .code-strong {
            font-family: ui-monospace, "SF Mono", "Cascadia Mono", Menlo, Consolas, monospace;
            font-size: 15px;
            background: #fff7ed;
            color: #c2570d;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #fed7aa;
            font-weight: 600;
            display: inline-block;
        }

        /* ── Alert box ── */
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 13px;
            margin: 18px 0;
            border-left: 3px solid #94a3b8;
            background: #f8fafc;
            color: #475569;
        }
        .alert-warning {
            background: #fffbeb;
            border-left-color: #f59e0b;
            color: #92400e;
        }
        .alert-success {
            background: #f0fdf4;
            border-left-color: #22c55e;
            color: #166534;
        }
        .alert-danger {
            background: #fef2f2;
            border-left-color: #ef4444;
            color: #991b1b;
        }

        /* ── Status pill ── */
        .pill {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            margin-bottom: 12px;
        }
        .pill-success { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .pill-danger  { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .pill-warning { background: #fffbeb; color: #92400e; border-color: #fde68a; }

        /* ── CTA button ── */
        .cta-wrap { text-align: center; margin: 28px 0; }
        .cta {
            display: inline-block;
            background: #c2570d;
            color: #ffffff !important;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 6px;
            letter-spacing: 0.2px;
        }
        .cta:hover { background: #a04609; text-decoration: none; }
        .cta-fallback {
            margin-top: 14px;
            font-size: 12px;
            color: #6b7280;
            word-break: break-all;
        }

        /* ── List ── */
        ul.steps {
            margin: 14px 0 18px;
            padding: 0 0 0 18px;
            color: #4b5563;
            font-size: 14px;
        }
        ul.steps li { margin: 6px 0; line-height: 1.55; }

        /* ── Footer ── */
        .footer {
            padding: 24px 40px 32px;
            border-top: 1px solid #f1f5f9;
            background: #ffffff;
        }
        .footer p {
            font-size: 12px;
            color: #6b7280;
            margin: 0 0 6px;
            line-height: 1.6;
        }
        .footer .footer-meta {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 12px;
        }
        .footer a { color: #6b7280; text-decoration: underline; }

        /* ── Responsive ── */
        @media only screen and (max-width: 600px) {
            .header, .body, .footer { padding-left: 24px !important; padding-right: 24px !important; }
            .body h1 { font-size: 19px; }
            .info-row { display: block; }
            .info-row > div { display: block; width: 100% !important; padding: 2px 0; }
            .info-row .val { text-align: left; }
            .cta { display: block; padding: 14px 20px; }
        }

        /* ── Force light en dark mode (Apple Mail / Outlook macOS) ── */
        @media (prefers-color-scheme: dark) {
            body, .container, .header, .body, .footer { background-color: #ffffff !important; color: #1f2937 !important; }
        }
    </style>
</head>
<body>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f4f6f8">
        <tr>
            <td align="center" style="padding: 32px 16px;">

                {{-- Préheader (texte caché de la preview boîte mail) --}}
                @isset($preheader)
                    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f4f6f8;">
                        {{ $preheader }}
                    </div>
                @endisset

                <table role="presentation" class="container" width="580" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">

                    {{-- Header avec logo --}}
                    <tr>
                        <td class="header">
                            @if($logoSrc)
                                <img src="{{ $logoSrc }}" alt="CIBLE CI" height="32">
                            @else
                                <div class="brand-text">CIBLE CI</div>
                            @endif
                        </td>
                    </tr>

                    {{-- Corps --}}
                    <tr>
                        <td class="body">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td class="footer">
                            @isset($footerNote)
                                <p>{{ $footerNote }}</p>
                            @endisset
                            <p>
                                Vous avez reçu ce message car vous êtes en relation avec
                                <strong style="color:#374151;">CIBLE CI</strong>, régie publicitaire à Abidjan, Côte d'Ivoire.
                            </p>
                            <p class="footer-meta">
                                © {{ date('Y') }} CIBLE CI. Tous droits réservés.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
