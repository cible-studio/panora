<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demande de démo Panora</title>
</head>
<body style="margin:0;padding:0;background:#f7f8fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#1f2937;line-height:1.5">
    <div style="max-width:600px;margin:24px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.06)">
        <div style="padding:20px 24px;background:linear-gradient(135deg,#e8a020,#ea580c);color:#fff">
            <h1 style="margin:0;font-size:20px;font-weight:800">🔔 Nouvelle demande de démo Panora</h1>
            <div style="font-size:13px;opacity:.9;margin-top:4px">Reçue le {{ $p['received_at'] ?? '—' }}</div>
        </div>

        <div style="padding:24px">
            <table style="width:100%;border-collapse:collapse;font-size:14px">
                <tr>
                    <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;color:#64748b;font-weight:600;width:130px">Nom</td>
                    <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;color:#0f172a;font-weight:700">{{ $p['nom'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;color:#64748b;font-weight:600">Régie</td>
                    <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;color:#0f172a;font-weight:700">{{ $p['regie'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;color:#64748b;font-weight:600">Rôle</td>
                    <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;color:#0f172a">{{ ucfirst($p['role'] ?? '—') }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;color:#64748b;font-weight:600">Téléphone</td>
                    <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;color:#0f172a">
                        <a href="tel:{{ $p['tel'] ?? '' }}" style="color:#ea580c;text-decoration:none;font-weight:700">{{ $p['tel'] ?? '—' }}</a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;color:#64748b;font-weight:600">Email</td>
                    <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;color:#0f172a">
                        <a href="mailto:{{ $p['email'] ?? '' }}" style="color:#ea580c;text-decoration:none;font-weight:700">{{ $p['email'] ?? '—' }}</a>
                    </td>
                </tr>
                @if(!empty($p['message']))
                <tr>
                    <td style="padding:10px 0;color:#64748b;font-weight:600;vertical-align:top">Message</td>
                    <td style="padding:10px 0;color:#0f172a;white-space:pre-wrap">{{ $p['message'] }}</td>
                </tr>
                @endif
            </table>

            <div style="margin-top:24px;padding:12px 14px;background:#f8fafc;border-radius:8px;font-size:11px;color:#94a3b8">
                IP : {{ $p['ip'] ?? '—' }}<br>
                User-Agent : {{ $p['ua'] ?? '—' }}
            </div>

            <div style="margin-top:20px;text-align:center">
                <a href="mailto:{{ $p['email'] ?? '' }}?subject=Re%3A%20Votre%20demande%20de%20d%C3%A9mo%20Panora"
                   style="display:inline-block;padding:12px 24px;background:linear-gradient(135deg,#e8a020,#ea580c);color:#fff;text-decoration:none;border-radius:10px;font-weight:800;font-size:14px">
                    Répondre à {{ $p['nom'] ?? 'la demande' }}
                </a>
            </div>
        </div>

        <div style="padding:14px 24px;background:#f8fafc;border-top:1px solid #f1f5f9;font-size:11px;color:#94a3b8;text-align:center">
            Envoyé automatiquement depuis la landing publique <strong>Panora</strong>
        </div>
    </div>
</body>
</html>
