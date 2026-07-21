<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Devis {{ $quote->reference }} — CIBLE CI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        header.public-hdr {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff; padding: 22px 24px; text-align: center;
        }
        header.public-hdr h1 { font-size: 20px; font-weight: 800; letter-spacing: 0.5px; }
        header.public-hdr small { display:block; font-size:11.5px; opacity:0.7; margin-top:4px; }
        footer.public-ftr {
            padding: 24px; text-align: center; font-size: 12px; color: #64748b;
            background: #fff; border-top: 1px solid #e2e8f0; margin-top: 40px;
        }
    </style>
</head>
<body>
    <header class="public-hdr">
        <h1>CIBLE CI · Régie Publicitaire</h1>
        <small>Devis {{ $quote->reference }} adressé à {{ $quote->client?->name }}</small>
    </header>

    @if(session('success')) <div style="max-width:960px;margin:16px auto 0;padding:14px;background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534">{{ session('success') }}</div> @endif
    @if(session('error'))   <div style="max-width:960px;margin:16px auto 0;padding:14px;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b">{{ session('error') }}</div> @endif
    @if(session('warning')) <div style="max-width:960px;margin:16px auto 0;padding:14px;background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;color:#78350f">{{ session('warning') }}</div> @endif
    @if(session('info'))    <div style="max-width:960px;margin:16px auto 0;padding:14px;background:#dbeafe;border:1px solid #93c5fd;border-radius:8px;color:#1e40af">{{ session('info') }}</div> @endif

    @include('quotes._show_content', ['quote' => $quote, 'isPublic' => true])

    <footer class="public-ftr">
        <div>© {{ date('Y') }} CIBLE CI · Régie publicitaire · Abidjan, Côte d'Ivoire</div>
        <div style="margin-top:6px">Contact : {{ config('billing.company.email') }} · {{ config('billing.company.phone') }}</div>
    </footer>
</body>
</html>
