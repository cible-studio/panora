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
        *, *::before, *::after{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            background:#ffffff;
            color:#111827;
            font-family:'DM Sans', sans-serif;
            font-size:14px;
            min-height:100vh;
        }

        /* Zone centrale */
        .auth-screen{
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px 20px;
        }

        /* Largeur formulaire */
        .auth-wrap{
            width:100%;
            max-width:460px;
            animation:fadeUp .45s ease;
        }

        @keyframes fadeUp{
            from{
                opacity:0;
                transform:translateY(18px);
            }
            to{
                opacity:1;
                transform:translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="auth-screen">
        <div class="auth-wrap">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
