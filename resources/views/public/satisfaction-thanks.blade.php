<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="theme-color" content="#22c55e">
    <title>Merci — CIBLE CI</title>
    <link rel="icon" href="{{ asset('images/faviconl.png') }}">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            -webkit-font-smoothing: antialiased;
        }
        .wrap {
            max-width: 480px;
            width: 100%;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,.05);
        }
        .check {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 40px;
            font-weight: 700;
            box-shadow: 0 8px 20px rgba(34,197,94,0.3);
        }
        h1 {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 10px;
            letter-spacing: -0.3px;
        }
        p {
            font-size: 15px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 14px;
        }
        .summary {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px;
            margin: 20px 0;
            text-align: left;
            font-size: 13px;
        }
        .summary .lbl { color: #9ca3af; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .summary .val { color: #1f2937; font-weight: 500; margin-top: 2px; }
        .stars-display {
            font-size: 22px;
            color: #fbbf24;
            letter-spacing: 2px;
            margin-top: 4px;
        }
        .brand {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            font-size: 11px;
            color: #9ca3af;
        }
        .brand strong { color: #c2570d; }
    </style>
</head>
<body>

<div class="wrap">
    <div class="check">✓</div>

    @if($alreadyDone && $survey->isCompleted())
        <h1>Vous avez déjà répondu</h1>
        <p>
            Merci, votre avis sur la campagne <strong>{{ $survey->campaign?->name }}</strong>
            a bien été enregistré le {{ $survey->completed_at->format('d/m/Y') }}.
        </p>
    @else
        <h1>Merci pour votre retour ! 🙏</h1>
        <p>
            Votre avis sur la campagne <strong>{{ $survey->campaign?->name }}</strong>
            a bien été enregistré. Il nous aide à améliorer la qualité de nos services.
        </p>
    @endif

    @if($survey->isCompleted() && $survey->score_global)
        <div class="summary">
            <div class="lbl">Note globale attribuée</div>
            <div class="val">
                <span class="stars-display">
                    @for($i = 1; $i <= 5; $i++){{ $i <= $survey->score_global ? '★' : '☆' }}@endfor
                </span>
                <span style="font-size:13px;color:#6b7280;margin-left:8px">{{ $survey->score_global }}/5</span>
            </div>
            @if($survey->would_renew)
                <div class="lbl" style="margin-top:10px;">Renouvellement</div>
                <div class="val" style="color:#16a34a">👍 Vous renouvelleriez avec nous</div>
            @elseif($survey->would_renew === false)
                <div class="lbl" style="margin-top:10px;">Renouvellement</div>
                <div class="val" style="color:#dc2626">Vous ne renouvelleriez pas — nous prendrons contact pour comprendre.</div>
            @endif
        </div>
    @endif

    <p style="font-size:13px;color:#6b7280;margin-top:18px;">
        Vous pouvez fermer cette page. Notre équipe commerciale prendra contact avec vous
        si nécessaire.
    </p>

    <div class="brand">
        <strong>CIBLE CI</strong> · Régie Publicitaire · Abidjan, Côte d'Ivoire
    </div>
</div>

</body>
</html>
