{{-- Pages d'erreur de Panora (2026-10-26). Autonomes : ni Vite, ni session, ni
     mise en page de l'administration. Elles s'affichent donc aussi pour un
     visiteur déconnecté et pendant une mise à jour de l'application. --}}
@php
    $espaceClient = request()->is('client', 'client/*');
    $connexion = $espaceClient ? route('client.login') : route('login');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @yield('entete')
    <title>@yield('titre') · Panora</title>
    <link rel="icon" href="{{ asset('images/favicond.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #0b0d12; --surface: #12151c; --border: #2a303f; --text: #eaedf5; --text2: #8a90a2; --text3: #5a6078; --accent: #fab80b; --accent-dark: #c97d10; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 24px 16px; color: var(--text);
            background: radial-gradient(900px 500px at 50% -10%, rgba(250, 184, 11, .08), transparent 60%), var(--bg);
            font-family: 'DM Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        .carte {
            width: 100%; max-width: 460px; padding: 36px 32px 28px; text-align: center;
            background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .35);
        }
        .logo { height: 34px; margin-bottom: 26px; }
        .code { font-size: 12px; letter-spacing: .14em; text-transform: uppercase; color: var(--accent); font-weight: 700; margin-bottom: 10px; }
        h1 { font-size: 22px; line-height: 1.25; margin: 0 0 12px; font-weight: 700; }
        p { margin: 0 0 8px; color: var(--text2); font-size: 14.5px; line-height: 1.6; }
        .actions { display: flex; flex-direction: column; gap: 10px; margin-top: 24px; }
        .btn {
            display: block; width: 100%; padding: 12px 16px; border-radius: 10px; border: 1px solid transparent;
            font: 700 14.5px/1.2 inherit; text-decoration: none; cursor: pointer;
        }
        .btn-primaire { background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: #14100a; }
        .btn-secondaire { background: transparent; color: var(--text); border-color: var(--border); }
        .btn:hover { filter: brightness(1.08); }
        .btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .note { margin-top: 16px; font-size: 12.5px; color: var(--text3); line-height: 1.5; }
    </style>
</head>
<body>
    <main class="carte">
        <img class="logo" src="{{ asset('images/panora-blanc.png') }}" alt="Panora">
        <div class="code">@yield('code')</div>
        <h1>@yield('titre')</h1>
        @yield('message')
        <div class="actions">
            @section('actions')
                <a class="btn btn-primaire" href="{{ $connexion }}">Se connecter</a>
                <button type="button" class="btn btn-secondaire" data-repli="{{ $connexion }}"
                        onclick="history.length > 1 ? history.back() : location.assign(this.dataset.repli)">Revenir à la page précédente</button>
            @show
        </div>
        @section('note')
            <div class="note">Déjà connecté ? « Se connecter » vous ramène directement à votre tableau de bord.</div>
        @show
    </main>
</body>
</html>
