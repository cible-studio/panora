<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Changer de mot de passe — CIBLE CI</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css'])
<style>
  :root{--gold:#e8a020;--dark:#080b12;--surface:#111520;--surface2:#181e2e;--text:#e2e8f0;--text2:#94a3b8;--text3:#64748b}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--dark);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh;display:grid;place-items:center;padding:16px}
  .wrap{width:100%;max-width:400px}
  .logo{font-family:'Syne',sans-serif;font-weight:800;font-size:22px;color:var(--gold);text-align:center;margin-bottom:24px}
  .card{background:var(--surface);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:32px}
  .card-title{font-family:'Syne',sans-serif;font-size:20px;font-weight:700;color:#f1f5f9;margin-bottom:6px}
  .card-sub{font-size:13px;color:var(--text2);margin-bottom:24px;line-height:1.5}
  .alert{padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
  .alert-warning{background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2);color:#fde68a}
  .fg{margin-bottom:16px}
  .fg label{display:block;font-size:12px;font-weight:500;color:var(--text2);margin-bottom:5px;text-transform:uppercase;letter-spacing:0.5px}
  .fg input{width:100%;background:var(--surface2);border:1px solid rgba(255,255,255,0.08);color:var(--text);border-radius:8px;padding:11px 13px;font-size:14px;font-family:'Inter',sans-serif}
  .fg input:focus{outline:none;border-color:rgba(232,160,32,0.4)}
  .field-error{font-size:12px;color:#fca5a5;margin-top:4px}
  .rules{font-size:11px;color:var(--text3);margin-bottom:20px;padding:10px 14px;background:rgba(255,255,255,0.02);border-radius:8px;line-height:1.8}
  .btn{width:100%;background:var(--gold);color:#0a0d14;font-weight:700;font-size:14px;padding:12px;border-radius:8px;border:none;cursor:pointer;font-family:'Syne',sans-serif}
  .btn:hover{opacity:0.9}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">CIBLE CI</div>
  <div class="card">
    <div class="card-title">Définir votre mot de passe</div>
    <div class="card-sub">
      @if(auth('client')->user()?->must_change_password)
        Bienvenue ! Pour sécuriser votre compte, choisissez un mot de passe personnel.
      @else
        Changez votre mot de passe actuel.
      @endif
    </div>
 
    @if(session('warning'))
      <div class="alert alert-warning">⚠️ {{ session('warning') }}</div>
    @endif
 
    <form method="POST" action="{{ route('client.password.update') }}">
      @csrf
 
      @if(!auth('client')->user()?->must_change_password)
      <div class="fg">
        <label>Mot de passe actuel</label>
        <input type="password" name="current_password" autocomplete="current-password">
        @error('current_password') <div class="field-error">{{ $message }}</div> @enderror
      </div>
      @endif
 
      <div class="fg">
        <label>Nouveau mot de passe</label>
        <input type="password" name="password" autocomplete="new-password">
        @error('password') <div class="field-error">{{ $message }}</div> @enderror
      </div>
 
      <div class="fg">
        <label>Confirmer le mot de passe</label>
        <input type="password" name="password_confirmation" autocomplete="new-password">
      </div>
 
      <div class="rules">
        ✅ Minimum 8 caractères · Lettres et chiffres requis
      </div>
 
      <button type="submit" class="btn">Enregistrer mon mot de passe →</button>
    </form>
  </div>
</div>
</body>
</html>