<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mes propositions — CIBLE CI</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
  :root {
    --gold:#e8a020; --gold-bg:rgba(232,160,32,0.08); --gold-border:rgba(232,160,32,0.2);
    --dark:#080b12; --surface:#111520; --surface2:#181e2e; --surface3:#1f2840;
    --text:#e2e8f0; --text2:#94a3b8; --text3:#64748b;
    --green:#22c55e; --green-bg:rgba(34,197,94,0.08); --green-border:rgba(34,197,94,0.2);
    --blue-bg:rgba(59,130,246,0.08); --blue-border:rgba(59,130,246,0.2); --blue:#93c5fd;
    --red:#ef4444; --red-bg:rgba(239,68,68,0.08); --red-border:rgba(239,68,68,0.2);
    --orange:#f97316; --orange-bg:rgba(249,115,22,0.08);
    --nav-h:60px;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  html{scroll-behavior:smooth}
  body{background:var(--dark);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}

  .navbar{position:sticky;top:0;z-index:100;background:rgba(8,11,18,0.95);backdrop-filter:blur(16px);border-bottom:1px solid rgba(232,160,32,0.08);height:var(--nav-h);display:flex;align-items:center;padding:0 20px;gap:16px}
  .nav-logo{font-family:'Syne',sans-serif;font-weight:800;font-size:18px;color:var(--gold);letter-spacing:-0.3px;flex-shrink:0}
  .nav-badge{background:var(--gold-bg);border:1px solid var(--gold-border);color:var(--gold);border-radius:20px;padding:2px 10px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px}
  .nav-links{display:flex;gap:4px;margin-left:8px}
  .nav-link{color:var(--text3);text-decoration:none;font-size:13px;padding:6px 12px;border-radius:8px;transition:all 0.15s;font-weight:500}
  .nav-link:hover,.nav-link.active{color:var(--text);background:rgba(255,255,255,0.06)}
  .nav-link.active{color:var(--gold)}
  .nav-right{margin-left:auto;display:flex;align-items:center;gap:10px}
  .nav-user{font-size:12px;color:var(--text2)}
  .btn-logout{background:transparent;color:var(--text3);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:6px 12px;font-size:12px;cursor:pointer;text-decoration:none;transition:all 0.15s}
  .btn-logout:hover{color:var(--red);border-color:var(--red-border)}
  .mobile-menu-btn{display:none;background:none;border:none;color:var(--text2);cursor:pointer;padding:6px}
  @media(max-width:640px){
    .nav-links{display:none;position:fixed;top:var(--nav-h);left:0;right:0;background:var(--surface);border-bottom:1px solid rgba(255,255,255,0.06);padding:12px;flex-direction:column;z-index:99}
    .nav-links.open{display:flex}
    .mobile-menu-btn{display:block}
    .nav-user{display:none}
  }

  .main{max-width:1000px;margin:0 auto;padding:28px 16px 60px}
  
  .page-header{margin-bottom:28px}
  .page-header h1{font-family:'Syne',sans-serif;font-size:clamp(20px,4vw,28px);font-weight:800;color:#f1f5f9;margin-bottom:4px}
  .page-header p{font-size:13px;color:var(--text2)}

  .filters{background:var(--surface);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:16px;margin-bottom:24px}
  .filter-group{display:flex;gap:12px;flex-wrap:wrap}
  .filter-select{background:var(--surface2);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:8px 12px;color:var(--text);font-size:13px}

  .proposition-card{background:var(--surface);border:1px solid var(--gold-border);border-radius:14px;padding:20px;margin-bottom:12px;transition:all 0.2s}
  .proposition-card:hover{border-color:rgba(232,160,32,0.4);transform:translateY(-2px)}
  .prop-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
  .prop-ref{font-family:monospace;font-size:12px;color:var(--gold);font-weight:700}
  .prop-period{font-size:13px;color:var(--text);font-weight:600;margin-top:3px}
  .prop-panels{font-size:12px;color:var(--text2);margin-top:3px}
  .prop-amount{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:var(--gold);text-align:right}
  .prop-thumb-row{display:flex;gap:6px;margin:14px 0;overflow-x:auto}
  .prop-thumb{width:56px;height:40px;border-radius:6px;object-fit:cover;background:var(--surface2);border:1px solid rgba(255,255,255,0.06);flex-shrink:0}
  .prop-thumb-more{width:56px;height:40px;border-radius:6px;background:var(--surface2);border:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--text3);flex-shrink:0}
  .prop-actions{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap}
  .btn-confirm{background:var(--gold);color:#0a0d14;font-weight:700;font-size:13px;padding:9px 20px;border-radius:8px;border:none;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;font-family:'Syne',sans-serif}
  .btn-confirm:hover{opacity:0.9}
  .btn-view{background:transparent;color:var(--text2);font-size:12px;padding:9px 16px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
  .btn-view:hover{border-color:rgba(255,255,255,0.2);color:var(--text)}
  .badge-vue{background:var(--green-bg);border:1px solid var(--green-border);color:#86efac;border-radius:20px;padding:2px 10px;font-size:10px;font-weight:600}
  .badge-new{background:var(--gold-bg);border:1px solid var(--gold-border);color:var(--gold);border-radius:20px;padding:2px 10px;font-size:10px;font-weight:600}
  .badge-expired{background:var(--red-bg);border:1px solid var(--red-border);color:#fca5a5;border-radius:20px;padding:2px 10px;font-size:10px;font-weight:600}
  .badge-responded{background:var(--blue-bg);border:1px solid var(--blue-border);color:var(--blue);border-radius:20px;padding:2px 10px;font-size:10px;font-weight:600}

  .empty{text-align:center;padding:60px 20px;color:var(--text3);font-size:14px}
  .empty-icon{font-size:48px;margin-bottom:16px;opacity:0.5}

  .pagination-wrap{margin-top:24px;display:flex;justify-content:center}
  .pagination .page-item{margin:0 2px}
  .pagination .page-link{background:var(--surface);border:1px solid rgba(255,255,255,0.06);color:var(--text2);padding:8px 12px;border-radius:8px;text-decoration:none;font-size:13px}
  .pagination .active .page-link{background:var(--gold);color:#0a0d14;border-color:var(--gold)}
</style>
</head>
<body>

<nav class="navbar">
  <div class="nav-logo">CIBLE CI</div>
  <span class="nav-badge">Espace Client</span>
  <div class="nav-links" id="nav-links">
    <a href="{{ route('client.dashboard') }}" class="nav-link">🏠 Accueil</a>
    <a href="{{ route('client.propositions') }}" class="nav-link active">📋 Propositions</a>
    <a href="{{ route('client.campagnes') }}" class="nav-link">📢 Campagnes</a>
    <a href="{{ route('client.profil') }}" class="nav-link">👤 Profil</a>
  </div>
  <div class="nav-right">
    <span class="nav-user">{{ $client->name }}</span>
    <form method="POST" action="{{ route('client.logout') }}" style="display:inline">
      @csrf
      <button type="submit" class="btn-logout">Déconnexion</button>
    </form>
    <button class="mobile-menu-btn" onclick="toggleNav()">☰</button>
  </div>
</nav>

<div class="main">
  <div class="page-header">
    <h1>📋 Mes propositions</h1>
    <p>Retrouvez toutes les propositions commerciales qui vous ont été envoyées</p>
  </div>

  @if(session('success'))
    <div style="background:var(--green-bg);border:1px solid var(--green-border);color:#86efac;border-radius:10px;padding:12px 16px;margin-bottom:20px">
      ✅ {{ session('success') }}
    </div>
  @endif

  @if($propositions->isEmpty())
    <div class="empty">
      <div class="empty-icon">📭</div>
      <p>Aucune proposition reçue pour le moment.</p>
      <p style="font-size:12px;margin-top:8px">Les propositions commerciales apparaîtront ici dès qu'elles vous seront envoyées.</p>
    </div>
  @else
    @foreach($propositions as $res)
    @php
      $totalRes = $res->panels->sum(fn($p) => (float)($p->monthly_rate ?? 0));
      $photos   = $res->panels->take(4)->map(fn($p) => $p->photos->sortBy('ordre')->first());
      $isExpired = $res->end_date < now();
      $statusBadge = $isExpired ? 'expired' : ($res->proposition_viewed_at ? 'vue' : 'new');
    @endphp
    <div class="proposition-card">
      <div class="prop-top">
        <div>
          <div class="prop-ref">{{ $res->reference }}</div>
          <div class="prop-period">📅 {{ $res->start_date->format('d/m/Y') }} → {{ $res->end_date->format('d/m/Y') }}</div>
          <div class="prop-panels">📍 {{ $res->panels->count() }} emplacement(s)</div>
        </div>
        <div style="text-align:right">
          @if($totalRes > 0)
            <div class="prop-amount">{{ number_format($totalRes, 0, ',', ' ') }} <span style="font-size:12px;font-weight:400">FCFA/mois</span></div>
          @endif
          <div style="margin-top:6px">
            @if($statusBadge == 'new')
              <span class="badge-new">🆕 Nouvelle</span>
            @elseif($statusBadge == 'vue')
              <span class="badge-vue">👁️ Consultée</span>
            @else
              <span class="badge-expired">⏰ Expirée</span>
            @endif
          </div>
        </div>
      </div>

      @if($photos->filter()->isNotEmpty())
      <div class="prop-thumb-row">
        @foreach($photos as $photo)
          @if($photo)
            <img src="{{ asset('storage/' . ltrim($photo->path, '/')) }}" class="prop-thumb" alt="" loading="lazy">
          @endif
        @endforeach
        @if($res->panels->count() > 4)
          <div class="prop-thumb-more">+{{ $res->panels->count() - 4 }}</div>
        @endif
      </div>
      @endif

      <div class="prop-actions">
        <a href="{{ route('client.proposition.detail', $res->proposition_token) }}" class="btn-confirm">
          ✅ Voir et répondre
        </a>
        <a href="{{ route('proposition.show', $res->proposition_token) }}" target="_blank" class="btn-view">
          👁️ Vue publique
        </a>
      </div>
    </div>
    @endforeach

    <div class="pagination-wrap">
      {{ $propositions->links() }}
    </div>
  @endif
</div>

<script>
function toggleNav() {
  document.getElementById('nav-links').classList.toggle('open');
}
</script>
</body>
</html>