<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CIBLE CI — {{ $title ?? 'Connexion' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-body);
            font-size: 14px;
            min-height: 100vh;
            overflow: hidden;
        }
        .auth-bg {
            position: fixed; inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% -10%, rgba(232,160,32,.12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 110%, rgba(59,130,246,.06) 0%, transparent 50%);
            pointer-events: none; z-index: 0;
        }
        .auth-grid {
            position: fixed; inset: 0;
            background-image:
                linear-gradient(var(--border) 1px, transparent 1px),
                linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size: 48px 48px;
            opacity: .35; pointer-events: none; z-index: 0;
        }
        .auth-screen {
            position: relative; z-index: 1;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px 20px;
        }
        .auth-wrap {
            width: 100%; max-width: 440px;
            animation: fadeUp .45s ease;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="auth-bg"></div>
    <div class="auth-grid"></div>
    <div class="auth-screen">
        <div class="auth-wrap">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
