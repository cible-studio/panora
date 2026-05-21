<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Facture {{ $invoice->reference ?? '' }}</title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1f2937;line-height:1.55">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px">
<tr><td align="center">
    <table role="presentation" width="100%" style="max-width:580px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.06)">
    <tr><td style="background:{{ $isReminder ? '#dc2626' : '#e8a020' }};padding:18px 26px;color:#fff">
        <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700">CIBLE CI</div>
        <div style="font-size:18px;font-weight:700;margin-top:4px">
            @if($isReminder)
                🔔 Rappel — Facture impayée
            @else
                📄 Nouvelle facture
            @endif
        </div>
    </td></tr>
    <tr><td style="padding:26px">
        <p style="margin:0 0 14px;font-size:14px">Bonjour {{ $client?->name ?? '' }},</p>
        @if($isReminder)
        <p style="margin:0 0 14px;font-size:14px">
            Nous vous rappelons que la facture <strong>{{ $invoice->reference }}</strong>
            d'un montant de <strong>{{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA TTC</strong>
            n'a pas encore été réglée.
        </p>
        @else
        <p style="margin:0 0 14px;font-size:14px">
            Veuillez trouver votre facture <strong>{{ $invoice->reference }}</strong>
            d'un montant de <strong>{{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA TTC</strong>.
        </p>
        @endif

        <table role="presentation" width="100%" style="font-size:13px;border-collapse:collapse;margin:18px 0">
            <tr><td style="padding:4px 0;color:#9ca3af;width:40%">Référence</td><td style="padding:4px 0"><strong style="font-family:monospace">{{ $invoice->reference }}</strong></td></tr>
            <tr><td style="padding:4px 0;color:#9ca3af">Montant HT</td><td style="padding:4px 0">{{ number_format($invoice->amount, 0, ',', ' ') }} FCFA</td></tr>
            <tr><td style="padding:4px 0;color:#9ca3af">TVA</td><td style="padding:4px 0">{{ number_format($invoice->tva, 0, ',', ' ') }} FCFA</td></tr>
            <tr><td style="padding:4px 0;color:#9ca3af">Total TTC</td><td style="padding:4px 0;font-weight:700;color:#16a34a;font-size:15px">{{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA</td></tr>
            <tr><td style="padding:4px 0;color:#9ca3af">Émise le</td><td style="padding:4px 0">{{ $invoice->issued_at?->format('d/m/Y') ?? $invoice->created_at->format('d/m/Y') }}</td></tr>
        </table>

        <p style="margin:28px 0;text-align:center">
            <a href="{{ $url }}" style="display:inline-block;background:{{ $isReminder ? '#dc2626' : '#e8a020' }};color:#fff;padding:13px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px">
                Consulter la facture →
            </a>
        </p>
        <p style="margin:14px 0;font-size:11px;color:#9ca3af;text-align:center">
            🔒 Lien sécurisé valable jusqu'au {{ $link->expires_at?->format('d/m/Y') ?? '—' }}.<br>
            Pour toute question : <a href="mailto:contact@cible-ci.com" style="color:#c2570d">contact@cible-ci.com</a>
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
