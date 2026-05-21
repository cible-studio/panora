<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Décappage terminé</title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1f2937;line-height:1.55">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px">
<tr><td align="center">
    <table role="presentation" width="100%" style="max-width:580px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.06)">
    <tr><td style="background:#16a34a;padding:18px 26px;color:#fff">
        <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700">CIBLE CI</div>
        <div style="font-size:18px;font-weight:700;margin-top:4px">✅ Campagne décappée</div>
    </td></tr>
    <tr><td style="padding:26px">
        <p style="margin:0 0 14px;font-size:14px">Bonjour {{ $client?->name ?? '' }},</p>
        <p style="margin:0 0 14px;font-size:14px">
            Votre campagne <strong>« {{ $campaign->name }} »</strong> est officiellement terminée :
            les <strong>{{ $decappedCount }} panneau(x)</strong> ont été retirés du terrain par nos équipes.
        </p>
        <p style="margin:0 0 14px;font-size:14px">
            Merci de votre confiance ! Nous restons à votre disposition pour vos prochaines campagnes.
        </p>

        <p style="margin:28px 0;text-align:center">
            <a href="{{ $url }}" style="display:inline-block;background:#16a34a;color:#fff;padding:13px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px">
                Voir le récapitulatif →
            </a>
        </p>
        <p style="margin:14px 0;font-size:11px;color:#9ca3af;text-align:center">
            🔒 Lien sécurisé valable jusqu'au {{ $link->expires_at?->format('d/m/Y') ?? '—' }}.
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
