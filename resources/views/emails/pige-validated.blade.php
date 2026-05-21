<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Pige photo disponible</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1f2937;line-height:1.55">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px">
<tr><td align="center">
    <table role="presentation" width="100%" style="max-width:580px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.06)">
    <tr><td style="background:#e8a020;padding:18px 26px;color:#fff">
        <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700">CIBLE CI</div>
        <div style="font-size:18px;font-weight:700;margin-top:4px">📸 Pige photo disponible</div>
    </td></tr>
    <tr><td style="padding:26px">
        <p style="margin:0 0 14px;font-size:14px">Bonjour {{ $client?->name ?? '' }},</p>
        <p style="margin:0 0 14px;font-size:14px">
            La photo du panneau <strong>{{ $panel?->reference ?? '—' }}</strong>
            pour votre campagne
            <strong>« {{ $campaign?->name ?? 'votre campagne' }} »</strong>
            est désormais disponible.
        </p>
        @if($pige->photo_path)
        <p style="margin:14px 0">
            <img src="{{ asset('storage/'.$pige->photo_path) }}" alt="Pige photo"
                 style="max-width:100%;border-radius:10px;border:1px solid #e5e7eb">
        </p>
        @endif
        <p style="margin:18px 0 8px;font-size:13px;color:#6b7280">Informations :</p>
        <table role="presentation" width="100%" style="font-size:13px;border-collapse:collapse">
            <tr><td style="padding:4px 0;color:#9ca3af;width:40%">Panneau</td><td style="padding:4px 0"><strong>{{ $panel?->reference }}</strong> · {{ $panel?->commune?->name ?? '' }}</td></tr>
            <tr><td style="padding:4px 0;color:#9ca3af">Campagne</td><td style="padding:4px 0">{{ $campaign?->name }}</td></tr>
            <tr><td style="padding:4px 0;color:#9ca3af">Date de pose</td><td style="padding:4px 0">{{ $pige->taken_at?->format('d/m/Y') ?? '—' }}</td></tr>
        </table>
        <p style="margin:28px 0;text-align:center">
            <a href="{{ $url }}" style="display:inline-block;background:#e8a020;color:#fff;padding:13px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px">
                Consulter la pige →
            </a>
        </p>
        <p style="margin:14px 0;font-size:11px;color:#9ca3af;text-align:center">
            🔒 Lien sécurisé valable {{ $link->expires_at?->diffForHumans(now(), ['parts'=>1]) ?? '30 jours' }}.<br>
            Vous pouvez aussi vous connecter à votre <a href="{{ url('/client') }}" style="color:#c2570d">espace client</a> si vous en avez un.
        </p>
    </td></tr>
    <tr><td style="background:#f4f6f8;padding:14px 26px;text-align:center;font-size:11px;color:#9ca3af">
        © {{ date('Y') }} CIBLE CI — Affichage publicitaire Côte d'Ivoire<br>
        contact@cible-ci.com
    </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
