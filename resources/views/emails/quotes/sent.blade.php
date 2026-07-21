<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $quote->title }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; background:#f5f5f2; padding:30px; color:#0f172a; margin:0;">
    <table role="presentation" style="max-width:620px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.06);">
        <tr>
            <td style="background:#8b5cf6; padding:24px 32px; color:#fff; text-align:center;">
                <div style="font-size:12px; letter-spacing:.15em; text-transform:uppercase; opacity:.85">Devis commercial</div>
                <div style="font-size:22px; font-weight:800; margin-top:4px; letter-spacing:.5px">CIBLE CI</div>
            </td>
        </tr>
        <tr>
            <td style="padding:28px 32px 8px 32px;">
                <div style="font-size:15px; color:#334155;">Bonjour {{ $quote->client?->name ?: 'cher client' }},</div>
                <div style="margin-top:16px; font-size:14.5px; line-height:1.6; color:#334155;">
                    Suite à nos échanges, je vous prie de bien vouloir trouver ci-joint le devis
                    <strong>{{ $quote->reference }}</strong> pour votre projet
                    « <strong>{{ $quote->title }}</strong> ».
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:20px 32px;">
                <table role="presentation" style="width:100%; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                    <tr>
                        <td style="padding:6px 0; color:#64748b; font-size:12.5px;">Référence</td>
                        <td style="padding:6px 0; text-align:right; font-weight:700; font-family:monospace;">{{ $quote->reference }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0; color:#64748b; font-size:12.5px;">Nombre de panneaux</td>
                        <td style="padding:6px 0; text-align:right; font-weight:600;">{{ $quote->lines->sum('quantite') }}</td>
                    </tr>
                    @if($quote->period_start && $quote->period_end)
                        <tr>
                            <td style="padding:6px 0; color:#64748b; font-size:12.5px;">Période envisagée</td>
                            <td style="padding:6px 0; text-align:right; font-weight:600;">{{ $quote->period_start->format('d/m/Y') }} → {{ $quote->period_end->format('d/m/Y') }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:6px 0; color:#64748b; font-size:12.5px;">Validité jusqu'au</td>
                        <td style="padding:6px 0; text-align:right; font-weight:600; color:#b45309;">{{ $quote->expires_at?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="border-top:1px dashed #cbd5e1; padding-top:10px;"></td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0; font-weight:800; font-size:15px;">TOTAL À PAYER</td>
                        <td style="padding:8px 0; text-align:right; font-weight:800; font-size:18px; color:#8b5cf6;">
                            {{ number_format($quote->total_a_payer, 0, ',', ' ') }} <span style="font-size:12px; color:#94a3b8">FCFA</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        @if($quote->notes_client)
            <tr>
                <td style="padding:0 32px 20px 32px;">
                    <div style="padding:14px; background:#f5f3ff; border-left:3px solid #8b5cf6; border-radius:6px; font-size:13.5px; line-height:1.55; color:#334155; white-space:pre-wrap;">{{ $quote->notes_client }}</div>
                </td>
            </tr>
        @endif
        <tr>
            <td style="padding:8px 32px 30px 32px; text-align:center;">
                <a href="{{ $consultUrl }}" style="display:inline-block; padding:14px 32px; background:#8b5cf6; color:#fff; text-decoration:none; font-weight:700; border-radius:8px; font-size:15px;">
                    Consulter le devis en ligne
                </a>
                <div style="margin-top:14px; font-size:12px; color:#94a3b8;">Le PDF est également joint à ce mail.</div>
            </td>
        </tr>
        <tr>
            <td style="padding:0 32px 30px 32px; font-size:13.5px; color:#334155; line-height:1.6;">
                Vous pourrez y consulter le détail complet et me faire part de votre décision :
                accepter, refuser, ou demander une modification.
                <br><br>
                Je reste à votre disposition pour tout complément d'information.
                <br><br>
                Cordialement,<br>
                <strong>{{ $quote->commercial?->name ?? 'L\'équipe CIBLE CI' }}</strong>
                @if($quote->commercial?->email) <br><a href="mailto:{{ $quote->commercial->email }}" style="color:#8b5cf6">{{ $quote->commercial->email }}</a> @endif
            </td>
        </tr>
        <tr>
            <td style="padding:16px 32px; background:#0f172a; color:rgba(255,255,255,.65); font-size:11px; text-align:center;">
                CIBLE CI · Régie publicitaire · Abidjan, Côte d'Ivoire · commercial@cible-ci.com
            </td>
        </tr>
    </table>
</body>
</html>
