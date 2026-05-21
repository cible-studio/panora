<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Campagne bientôt terminée</title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1f2937;line-height:1.55">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px">
<tr><td align="center">
    <table role="presentation" width="100%" style="max-width:580px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.06)">
    <tr><td style="background:#f59e0b;padding:18px 26px;color:#fff">
        <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700">CIBLE CI</div>
        <div style="font-size:18px;font-weight:700;margin-top:4px">⏰ Votre campagne se termine bientôt</div>
    </td></tr>
    <tr><td style="padding:26px">
        <p style="margin:0 0 14px;font-size:14px">Bonjour {{ $client?->name ?? '' }},</p>
        <p style="margin:0 0 14px;font-size:14px">
            Votre campagne <strong>« {{ $campaign->name }} »</strong> arrive à échéance dans
            <strong style="color:#d97706">{{ $daysRemaining }} jour(s)</strong>
            ({{ \Carbon\Carbon::parse($campaign->end_date)->format('d/m/Y') }}).
        </p>
        <p style="margin:0 0 14px;font-size:14px">
            Souhaitez-vous prolonger ou planifier votre prochaine campagne ?
            Contactez-nous dès maintenant pour bénéficier d'une <strong>offre de fidélité</strong>.
        </p>
        <p style="margin:28px 0;text-align:center">
            <a href="mailto:contact@cible-ci.com?subject=Prolongation %20{{ urlencode($campaign->name) }}"
               style="display:inline-block;background:#e8a020;color:#fff;padding:13px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px">
                Demander un devis →
            </a>
        </p>
    </td></tr>
    <tr><td style="background:#f4f6f8;padding:14px 26px;text-align:center;font-size:11px;color:#9ca3af">
        © {{ date('Y') }} CIBLE CI — Affichage publicitaire Côte d'Ivoire
    </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
