<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Campagne assignée</title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1f2937;line-height:1.55">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px">
<tr><td align="center">
    <table role="presentation" width="100%" style="max-width:580px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.06)">
    <tr><td style="background:#3b82f6;padding:18px 26px;color:#fff">
        <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700">PANORA · ASSIGNATION</div>
        <div style="font-size:18px;font-weight:700;margin-top:4px">📋 Campagne assignée</div>
    </td></tr>
    <tr><td style="padding:26px">
        <p style="margin:0 0 14px;font-size:14px">Bonjour {{ $campaign->commercial?->name ?? '' }},</p>
        <p style="margin:0 0 14px;font-size:14px">
            <strong>{{ $assignedBy->name }}</strong> vous a assigné comme commercial référent
            sur la campagne <strong>« {{ $campaign->name }} »</strong> du client
            <strong>{{ $client?->name ?? '—' }}</strong>.
        </p>
        <table role="presentation" width="100%" style="font-size:13px;border-collapse:collapse;margin:18px 0;background:#f9fafb;border-radius:10px">
            <tr><td style="padding:8px 14px;color:#9ca3af;width:38%">Période</td>
                <td style="padding:8px 14px"><strong>{{ $campaign->start_date->format('d/m/Y') }} → {{ $campaign->end_date->format('d/m/Y') }}</strong></td></tr>
            <tr><td style="padding:8px 14px;color:#9ca3af">Panneaux</td>
                <td style="padding:8px 14px"><strong>{{ $totalPanels }}</strong></td></tr>
            <tr><td style="padding:8px 14px;color:#9ca3af">Montant total</td>
                <td style="padding:8px 14px;font-weight:700;color:#16a34a">{{ number_format($campaign->total_amount, 0, ',', ' ') }} FCFA</td></tr>
            <tr><td style="padding:8px 14px;color:#9ca3af">Statut</td>
                <td style="padding:8px 14px">{{ $campaign->status?->label() ?? '—' }}</td></tr>
        </table>
        <p style="margin:0 0 14px;font-size:13px;color:#6b7280">
            Vous recevrez désormais les notifications opérationnelles liées à cette campagne (poses, piges, fin imminente, etc.).
        </p>
        <p style="margin:28px 0;text-align:center">
            <a href="{{ $showLink }}" style="display:inline-block;background:#3b82f6;color:#fff;padding:13px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px">
                Ouvrir la campagne →
            </a>
        </p>
    </td></tr>
    <tr><td style="background:#f4f6f8;padding:14px 26px;text-align:center;font-size:11px;color:#9ca3af">
        Notification automatique Panora · {{ now()->format('d/m/Y H:i') }}
    </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
