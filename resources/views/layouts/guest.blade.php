<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CIBLE CI — {{ $title ?? 'Connexion' }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            background: #ffffff;
            color: #111827;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
        }

        /* ── Split layout ── */
        .auth-screen {
            display: flex;
            height: 100vh;
        }

        /* ── Left: image panel ── */
        .auth-image-panel {
            flex: 0 0 60%;
            position: relative;
            overflow: hidden;
        }

        .auth-bg-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        /* Subtle dark gradient at bottom for readability */
        .auth-image-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                160deg,
                rgba(0, 0, 0, 0.08) 0%,
                rgba(0, 0, 0, 0.45) 100%
            );
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 48px;
        }

        .auth-image-tagline {
            color: rgba(255, 255, 255, 0.9);
            font-family: 'Syne', sans-serif;
            font-size: 28px;
            font-weight: 700;
            line-height: 1.25;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.4);
            margin-bottom: 10px;
        }

        .auth-image-sub {
            color: rgba(255, 255, 255, 0.65);
            font-size: 13px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 500;
        }

        /* ── Right: form panel ── */
        .auth-form-panel {
            flex: 1;
            height: 100vh;
            overflow-y: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 40px;
            background: #ffffff;
            box-shadow: -8px 0 40px rgba(0, 0, 0, 0.06);
        }

        .auth-wrap {
            width: 100%;
            max-width: 420px;
        }

        /* ── Responsive: mobile stacks vertically ── */
        @media (max-width: 900px) {
            html, body { overflow: auto; }

            .auth-screen {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
            }

            .auth-image-panel {
                height: 240px;
                flex: none;
            }

            .auth-image-tagline { font-size: 20px; }
            .auth-image-overlay { padding: 28px; }

            .auth-form-panel {
                width: 100%;
                height: auto;
                padding: 40px 24px;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
    <div class="auth-screen">

        {{-- ══ LEFT — image ══ --}}
        <div class="auth-image-panel">
            <img src="{{ asset('images/peroquet.jpg') }}"
                 alt="CIBLE CI"
                 class="auth-bg-img"
                 onerror="this.closest('.auth-image-panel').style.background='linear-gradient(135deg,#e20613,#7c000a)'">

            <div class="auth-image-overlay">
                <div class="auth-image-tagline">
                    Votre régie OOH<br>en un seul espace.
                </div>
                <div class="auth-image-sub">CIBLE CI · Côte d'Ivoire</div>
            </div>
        </div>

        {{-- ══ RIGHT — form ══ --}}
        <div class="auth-form-panel">
            <div class="auth-wrap">
                {{ $slot }}
            </div>
        </div>

    </div>
</body>
</html>
