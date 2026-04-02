<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mes campagnes — CIBLE CI</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
  :root {
    --gold:#e8a020; --gold-bg:rgba(232,160,32,0.08); --gold-border:rgba(232,160,32,0.2);
    --dark:#080b12; --surface:#111520; --surface2:#181e2e;
    --text:#e2e8f0; --text2:#94a3b8; --text3:#64748b;
    --green:#22c55e; --green-bg:rgba(34,197,94,0.08);
    --blue:#3b82f6; --blue-bg:rgba(59,130,246,0.08);
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

  .main{max-width:1000px;margin:0 auto;padding:28px 16px 60px}
  .page-header{margin-bottom:28px}
  .page-header h1{font-family:'Syne',sans-serif;font-size:clamp(20px,4vw,28px);font-weight:800;color:#f1f5f9}
  .page-header p{font-size:13px;color:var(--text2);margin-top:4px}

  .campaign-card{background:var(--surface);border-radius:14px;margin-bottom:12px;border:1px solid rgba(255,255,255,0.06);overflow:hidden;transition:all 0.2s}
  .campaign-card:hover{border-color:rgba(232,160,32,0.3)}
  .campaign-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:rgba(0,0,0,0.2);flex-wrap:wrap;gap:12px}
  .campaign-name{font-size:16px;font-weight:700}
  .campaign-status{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600}
  .status-actif{background:var(--green-bg);color:#86efac;border:1px solid rgba(34,197,94,0.2)}
  .status-pose{background:var(--blue-bg);color:#93c5fd;border:1px solid rgba(59,130,246,0.2)}
  .status-termine{background:rgba(100,116,139,0.15);color:#94a3b8;border:1px solid rgba(100,116,139,0.2)}
  .status-annule{background:var(--red-bg);color:#fca5a5;border:1px solid rgba(239,68,68,0.2)}
  .campaign-body{padding:16px 20px}
  .campaign-dates{font-size:12px;color:var(--text2);margin-bottom:12px}
  .campaign-stats{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:12px}
  .stat{font-size:12px}
  .stat strong{color:var(--gold)}
  .campaign-footer{display:flex;justify-content:flex-end;padding:12px 20px;border-top:1px solid rgba(255,255,255,0.05)}
  .btn-detail{color:var(--gold);text-decoration:none;font-size:13px;font-weight:600}

  .empty{text-align:center;padding:60px 20px;color:var(--text3)}
  .empty-icon{font-size:48px;margin-bottom:16px;opacity:0.5}

  .pagination-wrap{margin-top:24px;display:flex;justify-content:center}
</style>
</head>
<body>

<nav class="navbar">
  <div class="nav-logo">CIBLE CI</div>
  <span class="nav-badge">Espace Client</span>
  <div class="nav-links" id="nav-links">
    <a href="{{ route('client.dashboard') }}" class="nav-link">🏠 Accueil</a>
    <a href="{{ route('client.propositions') }}" class="nav-link">📋 Propositions</a>
    <a href="{{ route('client.campagnes') }}" class="nav-link active">📢 Campagnes</a>
    <a href="{{ route('client.profil') }}" class="nav-link">👤 Profil</a>
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
    <h1>📢 Mes campagnes publicitaires</h1>
    <p>Suivez toutes vos campagnes en cours et passées</p>
  </div>

  @if($campagnes->isEmpty())
    <div class="empty">
      <div class="empty-icon">📭</div>
      <p>Aucune campagne pour le moment.</p>
      <p style="font-size:12px;margin-top:8px">Vos campagnes apparaîtront ici dès qu'elles seront créées.</p>
    </div>
  @else
    @foreach($campagnes as $camp)
    <div class="campaign-card">
      <div class="campaign-header">
        <div class="campaign-name">{{ $camp->name }}</div>
        <span class="campaign-status status-{{ $camp->status->value }}">{{ ucfirst($camp->status->value) }}</span>
      </div>
      <div class="campaign-body">
        <div class="campaign-dates">
          📅 {{ $camp->start_date->format('d/m/Y') }} → {{ $camp->end_date->format('d/m/Y') }}
        </div>
        <div class="campaign-stats">
          <div class="stat">📍 <strong>{{ $camp->panels->count() }}</strong> panneau(x)</div>
          @if($camp->total_amount > 0)
            <div class="stat">💰 <strong>{{ number_format($camp->total_amount, 0, ',', ' ') }}</strong> FCFA</div>
          @endif
          <div class="stat">⏱️ <strong>{{ $camp->durationHuman() }}</strong></div>
        </div>
      </div>
      <div class="campaign-footer">
        <a href="{{ route('client.campagne.detail', $camp) }}" class="btn-detail">Voir le détail →</a>
      </div>
    </div>
    @endforeach

    <div class="pagination-wrap">
      {{ $campagnes->links() }}
    </div>
  @endif
</div>

<script>
function toggleNav() { document.getElementById('nav-links').classList.toggle('open'); }
</script>
</body>
</html>