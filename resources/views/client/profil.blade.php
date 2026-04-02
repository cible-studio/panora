<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon profil — CIBLE CI</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
  :root {
    --gold:#e8a020; --gold-bg:rgba(232,160,32,0.08); --gold-border:rgba(232,160,32,0.2);
    --dark:#080b12; --surface:#111520; --surface2:#181e2e;
    --text:#e2e8f0; --text2:#94a3b8; --text3:#64748b;
    --green:#22c55e; --green-bg:rgba(34,197,94,0.08);
    --red:#ef4444; --red-bg:rgba(239,68,68,0.08);
    --nav-h:60px;
  }
  *{margin:0;padding:0;box-sizing:border-box}
  body{background:var(--dark);color:var(--text);font-family:'Inter',sans-serif}

  .navbar{position:sticky;top:0;background:rgba(8,11,18,0.95);backdrop-filter:blur(16px);border-bottom:1px solid rgba(232,160,32,0.08);height:var(--nav-h);display:flex;align-items:center;padding:0 20px;gap:16px}
  .nav-logo{font-family:'Syne',sans-serif;font-weight:800;font-size:18px;color:var(--gold)}
  .nav-badge{background:var(--gold-bg);border:1px solid var(--gold-border);color:var(--gold);border-radius:20px;padding:2px 10px;font-size:10px}
  .nav-links{display:flex;gap:4px;margin-left:8px}
  .nav-link{color:var(--text3);text-decoration:none;font-size:13px;padding:6px 12px;border-radius:8px}
  .nav-link:hover,.nav-link.active{color:var(--text);background:rgba(255,255,255,0.06)}
  .nav-link.active{color:var(--gold)}
  .nav-right{margin-left:auto;display:flex;align-items:center;gap:10px}
  .btn-logout{background:transparent;color:var(--text3);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:6px 12px;cursor:pointer}
  .mobile-menu-btn{display:none}
  @media(max-width:640px){.nav-links{display:none}.mobile-menu-btn{display:block}}

  .main{max-width:800px;margin:0 auto;padding:28px 16px 60px}
  .page-header{margin-bottom:28px}
  .page-header h1{font-family:'Syne',sans-serif;font-size:clamp(20px,4vw,28px);font-weight:800;color:#f1f5f9}
  .page-header p{font-size:13px;color:var(--text2);margin-top:4px}

  .profile-card{background:var(--surface);border-radius:16px;border:1px solid rgba(255,255,255,0.06);overflow:hidden}
  .profile-header{padding:20px;background:rgba(0,0,0,0.2);border-bottom:1px solid rgba(255,255,255,0.05)}
  .profile-company{font-size:20px;font-weight:700;color:var(--gold)}
  .profile-ncc{font-family:monospace;font-size:12px;color:var(--text2);margin-top:4px}

  .profile-form{padding:20px}
  .form-group{margin-bottom:20px}
  .form-label{display:block;font-size:12px;font-weight:600;color:var(--text2);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px}
  .form-input{width:100%;background:var(--surface2);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px 14px;color:var(--text);font-size:14px}
  .form-input:focus{outline:none;border-color:var(--gold)}
  .form-input:disabled{opacity:0.5;cursor:not-allowed}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}

  .btn-submit{background:var(--gold);color:#0a0d14;font-weight:700;padding:12px 24px;border-radius:10px;border:none;cursor:pointer;font-family:'Syne',sans-serif;margin-top:8px}
  .btn-submit:hover{opacity:0.9}

  .info-note{background:var(--gold-bg);border:1px solid var(--gold-border);border-radius:10px;padding:12px;margin-top:20px;font-size:12px;color:var(--text2);text-align:center}
</style>
</head>
<body>

<nav class="navbar">
  <div class="nav-logo">CIBLE CI</div>
  <span class="nav-badge">Espace Client</span>
  <div class="nav-links" id="nav-links">
    <a href="{{ route('client.dashboard') }}" class="nav-link">🏠 Accueil</a>
    <a href="{{ route('client.propositions') }}" class="nav-link">📋 Propositions</a>
    <a href="{{ route('client.campagnes') }}" class="nav-link">📢 Campagnes</a>
    <a href="{{ route('client.profil') }}" class="nav-link active">👤 Profil</a>
  </div>
  <div class="nav-right">
    <span style="font-size:12px;color:var(--text2)">{{ $client->name }}</span>
    <form method="POST" action="{{ route('client.logout') }}">
      @csrf
      <button type="submit" class="btn-logout">Déconnexion</button>
    </form>
    <button class="mobile-menu-btn" onclick="toggleNav()">☰</button>
  </div>
</nav>

<div class="main">
  <div class="page-header">
    <h1>👤 Mon profil</h1>
    <p>Gérez vos informations personnelles et de contact</p>
  </div>

  @if(session('success'))
    <div style="background:var(--green-bg);border:1px solid rgba(34,197,94,0.2);color:#86efac;border-radius:10px;padding:12px 16px;margin-bottom:20px">
      ✅ {{ session('success') }}
    </div>
  @endif

  <div class="profile-card">
    <div class="profile-header">
      <div class="profile-company">{{ $client->company ?? 'Client' }}</div>
      <div class="profile-ncc">NCC : {{ $client->ncc ?? '—' }}</div>
    </div>

    <form method="POST" action="{{ route('client.profil.update') }}" class="profile-form">
      @csrf
      @method('PATCH')

      <div class="form-group">
        <label class="form-label">Nom complet</label>
        <input type="text" class="form-input" value="{{ $client->name }}" disabled>
        <div style="font-size:11px;color:var(--text3);margin-top:4px">Contactez l'administrateur pour modifier cette information</div>
      </div>

      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" class="form-input" value="{{ $client->email }}" disabled>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Téléphone</label>
          <input type="text" name="phone" class="form-input" value="{{ old('phone', $client->phone) }}" placeholder="+225 XX XX XX XX">
          @error('phone')<div style="color:#fca5a5;font-size:11px;margin-top:4px">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
          <label class="form-label">Personne de contact</label>
          <input type="text" name="contact_name" class="form-input" value="{{ old('contact_name', $client->contact_name) }}" placeholder="Nom du référent">
          @error('contact_name')<div style="color:#fca5a5;font-size:11px;margin-top:4px">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Adresse</label>
        <input type="text" name="address" class="form-input" value="{{ old('address', $client->address) }}" placeholder="Adresse complète">
        @error('address')<div style="color:#fca5a5;font-size:11px;margin-top:4px">{{ $message }}</div>@enderror
      </div>

      <!-- <div class="form-group">
        <label class="form-label">Ville</label>
        <input type="text" name="city" class="form-input" value="{{ old('city', $client->city) }}" placeholder="Abidjan, Bouaké...">
        @error('city')<div style="color:#fca5a5;font-size:11px;margin-top:4px">{{ $message }}</div>@enderror
      </div> -->

      <button type="submit" class="btn-submit">💾 Mettre à jour mon profil</button>
    </form>

    <div class="info-note">
      🔐 Besoin de changer votre mot de passe ? 
      <a href="{{ route('client.password.change') }}" style="color:var(--gold);text-decoration:none;font-weight:600">Cliquez ici →</a>
    </div>
  </div>
</div>

<script>
function toggleNav() { document.getElementById('nav-links').classList.toggle('open'); }
</script>
</body>
</html>