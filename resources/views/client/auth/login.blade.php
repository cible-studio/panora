<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Espace Client — CIBLE CI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
  :root {
    --gold: #e8a020; --gold-bg: rgba(232,160,32,0.08); --gold-border: rgba(232,160,32,0.2);
    --dark: #080b12; --surface: #111520; --surface2: #181e2e;
    --text: #e2e8f0; --text2: #94a3b8; --text3: #64748b;
    --red: rgba(239,68,68,0.12); --red-border: rgba(239,68,68,0.3); --red-text: #fca5a5;
    --green-bg: rgba(34,197,94,0.08); --green-border: rgba(34,197,94,0.25); --green-text: #86efac;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--dark); color: var(--text); font-family: 'Inter', sans-serif; min-height: 100vh; display: grid; place-items: center; padding: 16px; }

  /* Fond décoratif subtil */
  body::before {
    content: ''; position: fixed; inset: 0; pointer-events: none;
    background: radial-gradient(ellipse 60% 40% at 50% 0%, rgba(232,160,32,0.04) 0%, transparent 70%);
  }

  .login-wrap { width: 100%; max-width: 400px; }

  /* Logo */
  .logo-section { text-align: center; margin-bottom: 32px; }
  .logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 28px; color: var(--gold); letter-spacing: -0.5px; }
  .logo-sub { font-size: 11px; color: var(--text3); text-transform: uppercase; letter-spacing: 2px; margin-top: 4px; }
  .logo-badge { display: inline-flex; align-items: center; gap: 5px; background: var(--gold-bg); border: 1px solid var(--gold-border); color: var(--gold); border-radius: 20px; padding: 3px 12px; font-size: 11px; font-weight: 600; margin-top: 10px; }

  /* Card */
  .card { background: var(--surface); border: 1px solid rgba(255,255,255,0.06); border-radius: 18px; padding: 36px 32px; }
  .card-title { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 700; color: #f1f5f9; margin-bottom: 6px; }
  .card-sub { font-size: 13px; color: var(--text2); margin-bottom: 28px; line-height: 1.5; }

  /* Alerts */
  .alert { display: flex; align-items: flex-start; gap: 8px; padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; }
  .alert-error { background: var(--red); border: 1px solid var(--red-border); color: var(--red-text); }
  .alert-success { background: var(--green-bg); border: 1px solid var(--green-border); color: var(--green-text); }
  .alert-warning { background: rgba(251,191,36,0.08); border: 1px solid rgba(251,191,36,0.25); color: #fde68a; }

  /* Form */
  .fg { margin-bottom: 18px; }
  .fg label { display: block; font-size: 12px; font-weight: 500; color: var(--text2); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
  .fg input { width: 100%; background: var(--surface2); border: 1px solid rgba(255,255,255,0.08); color: var(--text); border-radius: 10px; padding: 12px 14px; font-size: 14px; font-family: 'Inter', sans-serif; transition: border-color 0.15s; }
  .fg input:focus { outline: none; border-color: rgba(232,160,32,0.4); box-shadow: 0 0 0 3px rgba(232,160,32,0.06); }
  .fg input::placeholder { color: var(--text3); }
  .input-error { border-color: rgba(239,68,68,0.5) !important; }
  .field-error { font-size: 12px; color: var(--red-text); margin-top: 5px; }

  /* Remember */
  .remember-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
  .remember-row label { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text2); cursor: pointer; }
  .remember-row input[type="checkbox"] { accent-color: var(--gold); width: 15px; height: 15px; }

  /* Submit */
  .btn-submit { width: 100%; background: var(--gold); color: #0a0d14; font-weight: 700; font-size: 15px; padding: 13px; border-radius: 10px; border: none; cursor: pointer; font-family: 'Syne', sans-serif; letter-spacing: 0.2px; transition: opacity 0.15s, transform 0.1s; }
  .btn-submit:hover { opacity: 0.9; transform: translateY(-1px); }
  .btn-submit:active { transform: translateY(0); }

  /* Footer */
  .card-footer { text-align: center; margin-top: 24px; font-size: 12px; color: var(--text3); }
  .card-footer a { color: var(--text2); text-decoration: none; transition: color 0.15s; }
  .card-footer a:hover { color: var(--gold); }

  .page-footer { text-align: center; margin-top: 24px; font-size: 11px; color: var(--text3); }
</style>
</head>
<body>

<div class="login-wrap">
  <div class="logo-section">
    <div class="logo">CIBLE CI</div>
    <div class="logo-sub">Régie Publicitaire</div>
    <span class="logo-badge">🏢 Espace Client</span>
  </div>

  <div class="card">
    <div class="card-title">Connexion</div>
    <div class="card-sub">Accédez à vos propositions, campagnes et factures.</div>

    {{-- Alerts --}}
    @if(session('error'))
      <div class="alert alert-error">⚠️ {{ session('error') }}</div>
    @endif
    @if(session('success'))
      <div class="alert alert-success">✅ {{ session('success') }}</div>
    @endif
    @if(session('warning'))
      <div class="alert alert-warning">⚠️ {{ session('warning') }}</div>
    @endif

    <form method="POST" action="{{ route('client.login.post') }}">
      @csrf

      <div class="fg">
        <label for="email">Email professionnel</label>
        <input
          type="email"
          id="email"
          name="email"
          value="{{ old('email') }}"
          placeholder="contact@votresociete.com"
          autocomplete="email"
          autofocus
          class="{{ $errors->has('email') ? 'input-error' : '' }}"
        >
        @error('email')
          <div class="field-error">{{ $message }}</div>
        @enderror
      </div>

      <div class="fg">
        <label for="password">Mot de passe</label>
        <input
          type="password"
          id="password"
          name="password"
          placeholder="••••••••"
          autocomplete="current-password"
          class="{{ $errors->has('password') ? 'input-error' : '' }}"
        >
        @error('password')
          <div class="field-error">{{ $message }}</div>
        @enderror
      </div>

      <div class="remember-row">
        <label>
          <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
          Se souvenir de moi
        </label>
      </div>

      <button type="submit" class="btn-submit">
        Accéder à mon espace →
      </button>
    </form>

    <div class="card-footer">
      Vous n'avez pas encore d'accès ?<br>
      Contactez votre commercial CIBLE CI.
    </div>
  </div>

  <div class="page-footer">
    CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire<br>
    Cet espace est réservé aux clients. <a href="{{ route('login') }}" style="color:inherit">Accès équipe →</a>
  </div>
</div>

</body>
</html>