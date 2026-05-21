<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>{{ $title }}</title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1f2937;line-height:1.55">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px">
<tr><td align="center">
    <table role="presentation" width="100%" style="max-width:580px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.06)">
    <tr><td style="background:{{ $color }};padding:16px 22px;color:#fff">
        <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700;opacity:.85">PANORA · ALERTE INTERNE</div>
        <div style="font-size:17px;font-weight:700;margin-top:4px">{{ $emoji }} {{ $title }}</div>
    </td></tr>
    <tr><td style="padding:22px 24px">
        <p style="margin:0 0 14px;font-size:14px;color:#374151">{{ $summary }}</p>
        @if(!empty($lines))
            <table role="presentation" width="100%" style="font-size:13px;border-collapse:collapse;background:#f9fafb;border-radius:10px">
                @foreach($lines as $line)
                    <tr><td style="padding:8px 14px;border-bottom:1px solid #e5e7eb;color:#374151">{{ $line }}</td></tr>
                @endforeach
            </table>
        @endif
        @if($ctaLabel && $ctaUrl)
        <p style="margin:24px 0 4px;text-align:center">
            <a href="{{ $ctaUrl }}" style="display:inline-block;background:{{ $color }};color:#fff;padding:12px 26px;border-radius:10px;text-decoration:none;font-weight:700;font-size:13px">
                {{ $ctaLabel }}
            </a>
        </p>
        @endif
        @if($footer)
            <p style="margin:14px 0 0;font-size:11px;color:#9ca3af;text-align:center">{{ $footer }}</p>
        @endif
    </td></tr>
    <tr><td style="background:#f4f6f8;padding:12px 24px;text-align:center;font-size:10px;color:#9ca3af">
        Notification automatique Panora · {{ now()->format('d/m/Y H:i') }}
    </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
