<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demande de devis</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; background: #f7f7f5; padding: 30px; color: #0f172a;">
    <div style="max-width: 620px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <div style="background: #0f172a; color: #fff; padding: 26px 32px; border-bottom: 3px solid #e8a020;">
            <div style="font-size: 12px; letter-spacing: .14em; text-transform: uppercase; color: #e8a020; font-weight: 700;">CIBLE CI · Nouvelle demande de devis</div>
            <div style="font-size: 22px; margin-top: 6px; font-weight: 700;">{{ $d['entreprise'] ?? '—' }}</div>
        </div>

        <div style="padding: 26px 32px;">
            <table style="width:100%; border-collapse: collapse; font-size: 14px;">
                <tr><td style="padding: 8px 0; color: #64748b; width: 140px;">Nom</td><td style="font-weight: 600;">{{ $d['nom'] ?? '—' }}</td></tr>
                <tr><td style="padding: 8px 0; color: #64748b;">Poste</td><td>{{ $d['poste'] ?? '—' }}</td></tr>
                <tr><td style="padding: 8px 0; color: #64748b;">Entreprise</td><td style="font-weight: 600;">{{ $d['entreprise'] ?? '—' }}</td></tr>
                <tr><td style="padding: 8px 0; color: #64748b;">Téléphone</td><td><a href="tel:{{ $d['tel'] ?? '' }}" style="color: #e8a020;">{{ $d['tel'] ?? '—' }}</a></td></tr>
                <tr><td style="padding: 8px 0; color: #64748b;">Email</td><td><a href="mailto:{{ $d['email'] ?? '' }}" style="color: #e8a020;">{{ $d['email'] ?? '—' }}</a></td></tr>
                <tr><td colspan="2" style="border-top: 1px dashed #e2e8f0; padding-top: 12px;"></td></tr>
                <tr><td style="padding: 8px 0; color: #64748b;">Besoin</td><td style="font-weight: 600;">{{ ucfirst($d['besoin'] ?? '—') }}</td></tr>
                <tr><td style="padding: 8px 0; color: #64748b;">Zone visée</td><td>{{ ucfirst($d['zone'] ?? '—') }}</td></tr>
                <tr><td style="padding: 8px 0; color: #64748b;">Budget indicatif</td><td>{{ $d['budget'] ?? '—' }}</td></tr>
                <tr><td style="padding: 8px 0; color: #64748b;">Période souhaitée</td><td>{{ $d['periode'] ?? '—' }}</td></tr>
            </table>

            @if(!empty($d['message']))
            <div style="margin-top: 22px; padding: 18px; background: #fbf8f1; border-left: 3px solid #e8a020; border-radius: 4px;">
                <div style="font-size: 12px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #e8a020; margin-bottom: 8px;">Message</div>
                <div style="font-size: 14.5px; line-height: 1.6; color: #1e293b; white-space: pre-wrap;">{{ $d['message'] }}</div>
            </div>
            @endif

            <div style="margin-top: 28px; padding-top: 18px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8;">
                Reçu le {{ $d['received_at'] ?? now()->format('d/m/Y H:i') }}<br>
                IP : {{ $d['ip'] ?? '—' }}
            </div>
        </div>
    </div>
</body>
</html>
