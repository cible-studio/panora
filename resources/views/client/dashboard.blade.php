<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon espace — CIBLE CI</title>
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
    --nav-h:60px;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  html{scroll-behavior:smooth}
  body{background:var(--dark);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}

  /* ── NAVBAR ── */
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

  /* ── MOBILE NAV ── */
  .mobile-menu-btn{display:none;background:none;border:none;color:var(--text2);cursor:pointer;padding:6px}
  @media(max-width:640px){
    .nav-links{display:none;position:fixed;top:var(--nav-h);left:0;right:0;background:var(--surface);border-bottom:1px solid rgba(255,255,255,0.06);padding:12px;flex-direction:column;z-index:99}
    .nav-links.open{display:flex}
    .mobile-menu-btn{display:block}
    .nav-user{display:none}
  }

  /* ── LAYOUT ── */
  .main{max-width:1000px;margin:0 auto;padding:28px 16px 60px}

  /* ── ALERT CHANGE PASSWORD ── */
  .alert-banner{background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.2);border-radius:12px;padding:14px 18px;margin-bottom:24px;display:flex;align-items:center;gap:12px;font-size:13px;color:#fde68a}
  .alert-banner a{color:#fde68a;font-weight:600;text-decoration:underline}

  /* ── HEADER ── */
  .page-header{margin-bottom:28px}
  .page-header h1{font-family:'Syne',sans-serif;font-size:clamp(20px,4vw,28px);font-weight:800;color:#f1f5f9;margin-bottom:4px}
  .page-header p{font-size:13px;color:var(--text2)}

  /* ── STATS GRID ── */
  .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:28px}
  .stat-card{background:var(--surface);border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:18px 16px}
  .stat-num{font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:var(--gold);line-height:1}
  .stat-label{font-size:12px;color:var(--text3);margin-top:5px}

  /* ── SECTION TITLE ── */
  .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;margin-top:28px}
  .section-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
  .section-count{font-size:11px;color:var(--text3);background:var(--surface);border:1px solid rgba(255,255,255,0.06);border-radius:20px;padding:2px 10px}
  .section-link{font-size:12px;color:var(--gold);text-decoration:none}
  .section-link:hover{text-decoration:underline}

  /* ── PROPOSITION CARDS ── */
  .proposition-card{background:var(--surface);border:1px solid var(--gold-border);border-radius:14px;padding:20px;margin-bottom:12px;transition:border-color 0.15s}
  .proposition-card:hover{border-color:rgba(232,160,32,0.4)}
  .prop-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
  .prop-ref{font-family:monospace;font-size:12px;color:var(--gold);font-weight:700}
  .prop-period{font-size:13px;color:var(--text);font-weight:600;margin-top:3px}
  .prop-panels{font-size:12px;color:var(--text2);margin-top:3px}
  .prop-amount{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:var(--gold);text-align:right}
  .prop-amount-sub{font-size:10px;color:var(--text3);text-align:right}
  .prop-thumb-row{display:flex;gap:6px;margin:14px 0;overflow:hidden}
  .prop-thumb{width:56px;height:40px;border-radius:6px;object-fit:cover;background:var(--surface2);border:1px solid rgba(255,255,255,0.06);flex-shrink:0}
  .prop-thumb-more{width:56px;height:40px;border-radius:6px;background:var(--surface2);border:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--text3);flex-shrink:0}
  .prop-actions{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap}
  .btn-confirm{background:var(--gold);color:#0a0d14;font-weight:700;font-size:13px;padding:9px 20px;border-radius:8px;border:none;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;font-family:'Syne',sans-serif}
  .btn-confirm:hover{opacity:0.9}
  .btn-view{background:transparent;color:var(--text2);font-size:12px;padding:9px 16px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
  .btn-view:hover{border-color:rgba(255,255,255,0.2);color:var(--text)}
  .badge-vue{background:var(--green-bg);border:1px solid var(--green-border);color:#86efac;border-radius:20px;padding:2px 10px;font-size:10px;font-weight:600}
  .badge-new{background:var(--gold-bg);border:1px solid var(--gold-border);color:var(--gold);border-radius:20px;padding:2px 10px;font-size:10px;font-weight:600}

  /* ── CAMPAGNE CARDS ── */
  .campaign-row{background:var(--surface);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:16px;margin-bottom:10px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
  .campaign-status{width:10px;height:10px;border-radius:50%;flex-shrink:0}
  .status-actif{background:#22c55e;box-shadow:0 0 6px rgba(34,197,94,0.4)}
  .status-pose{background:#3b82f6;box-shadow:0 0 6px rgba(59,130,246,0.4)}
  .status-termine{background:#64748b}
  .status-annule{background:#ef4444}
  .campaign-info{flex:1;min-width:0}
  .campaign-name{font-size:14px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .campaign-meta{font-size:12px;color:var(--text3);margin-top:2px}
  .campaign-panels{font-size:12px;color:var(--text2)}
  .campaign-amount{font-size:13px;font-weight:700;color:var(--gold);text-align:right;white-space:nowrap}
  .campaign-link{font-size:12px;color:var(--gold);text-decoration:none;flex-shrink:0}
  .campaign-link:hover{text-decoration:underline}

  /* ── EMPTY STATE ── */
  .empty{text-align:center;padding:40px 20px;color:var(--text3);font-size:14px}
  .empty-icon{font-size:36px;margin-bottom:10px;opacity:0.5}

  /* ── PAGINATION ── */
  .pagination-wrap{margin-top:16px}

  @media(max-width:480px){
    .stats-grid{grid-template-columns:1fr 1fr}
    .campaign-row{flex-direction:column;align-items:flex-start}
  }
</style>
</head>
<body>

{{-- ── NAVBAR ── --}}
<nav class="navbar">
  <div class="nav-logo">CIBLE CI</div>
  <span class="nav-badge">Espace Client</span>

  <div class="nav-links" id="nav-links">
    <a href="{{ route('client.dashboard') }}" class="nav-link {{ request()->routeIs('client.dashboard') ? 'active' : '' }}">
      🏠 Accueil
    </a>
    <a href="{{ route('client.propositions') }}" class="nav-link {{ request()->routeIs('client.propositions*') ? 'active' : '' }}">
      📋 Propositions
      @if($stats['propositions_en_attente'] > 0)
        <span style="background:var(--gold);color:#0a0d14;border-radius:20px;padding:0px 6px;font-size:10px;font-weight:700;margin-left:4px">{{ $stats['propositions_en_attente'] }}</span>
      @endif
    </a>
    <a href="{{ route('client.campagnes') }}" class="nav-link {{ request()->routeIs('client.campagnes*') ? 'active' : '' }}">
      📢 Campagnes
    </a>
    <a href="{{ route('client.profil') }}" class="nav-link {{ request()->routeIs('client.profil*') ? 'active' : '' }}">
      👤 Profil
    </a>
  </div>

  <div class="nav-right">
    <span class="nav-user">{{ $client->name }}</span>
    <form method="POST" action="{{ route('client.logout') }}" style="display:inline">
      @csrf
      <button type="submit" class="btn-logout">Déconnexion</button>
    </form>
    <button class="mobile-menu-btn" onclick="toggleNav()" aria-label="Menu">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M3 12h18M3 6h18M3 18h18"/>
      </svg>
    </button>
  </div>
</nav>

<div class="main">

  {{-- ── ALERTE MOT DE PASSE ── --}}
  @if($client->must_change_password)
    <div class="alert-banner">
      🔑 Pour la sécurité de votre compte, veuillez
      <a href="{{ route('client.password.change') }}" style="margin:0 4px">définir un nouveau mot de passe →</a>
    </div>
  @endif

  {{-- ── ALERTS SESSION ── --}}
  @if(session('success'))
    <div style="background:var(--green-bg);border:1px solid var(--green-border);color:#86efac;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px">
      ✅ {{ session('success') }}
    </div>
  @endif
  @if(session('error'))
    <div style="background:var(--red-bg);border:1px solid var(--red-border);color:#fca5a5;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px">
      ⚠️ {{ session('error') }}
    </div>
  @endif

  {{-- ── HEADER ── --}}
  <div class="page-header">
    <h1>Bonjour, {{ explode(' ', $client->name)[0] }} 👋</h1>
    <p>{{ $client->company ?? 'Votre espace client CIBLE CI' }} · Dernière connexion {{ $client->last_login_at?->diffForHumans() ?? 'première visite' }}</p>
  </div>

  {{-- ── STATS ── --}}
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-num">{{ $stats['propositions_en_attente'] }}</div>
      <div class="stat-label">Proposition(s) en attente</div>
    </div>
    <div class="stat-card">
      <div class="stat-num">{{ $stats['campagnes_actives'] }}</div>
      <div class="stat-label">Campagne(s) active(s)</div>
    </div>
    <div class="stat-card">
      <div class="stat-num">{{ $stats['panneaux_actifs'] }}</div>
      <div class="stat-label">Panneau(x) en affichage</div>
    </div>
    <div class="stat-card">
      <div class="stat-num">{{ $stats['campagnes_total'] }}</div>
      <div class="stat-label">Total campagnes</div>
    </div>
  </div>

  {{-- ── PROPOSITIONS EN ATTENTE ── --}}
  <div class="section-header">
    <div class="section-title">
      📋 Propositions en attente
      @if($propositions->count() > 0)
        <span class="section-count">{{ $propositions->count() }}</span>
      @endif
    </div>
    <a href="{{ route('client.propositions') }}" class="section-link">Voir tout →</a>
  </div>

  @if($propositions->isEmpty())
    <div class="empty">
      <div class="empty-icon">📭</div>
      Aucune proposition en attente de votre réponse.
    </div>
  @else
    @foreach($propositions as $res)
    @php
      $totalRes = $res->panels->sum(fn($p) => (float)($p->monthly_rate ?? 0));
      $photos   = $res->panels->take(4)->map(fn($p) => $p->photos->sortBy('ordre')->first());
    @endphp
    <div class="proposition-card">
      <div class="prop-top">
        <div>
          <div class="prop-ref">{{ $res->reference }}</div>
          <div class="prop-period">{{ $res->start_date->format('d/m/Y') }} → {{ $res->end_date->format('d/m/Y') }}</div>
          <div class="prop-panels">{{ $res->panels->count() }} emplacement(s)</div>
        </div>
        <div>
          @if($totalRes > 0)
            <div class="prop-amount">{{ number_format($totalRes, 0, ',', ' ') }}<span style="font-size:12px;font-weight:400"> FCFA/mois</span></div>
          @endif
          @if($res->proposition_viewed_at)
            <span class="badge-vue">👁️ Vue</span>
          @else
            <span class="badge-new">🆕 Nouveau</span>
          @endif
        </div>
      </div>

      {{-- Miniatures panneaux --}}
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
  @endif

  {{-- ── CAMPAGNES ACTIVES ── --}}
  <div class="section-header">
    <div class="section-title">📢 Campagnes actives</div>
    <a href="{{ route('client.campagnes') }}" class="section-link">Voir tout →</a>
  </div>

  @if($campagnesActives->isEmpty())
    <div class="empty">
      <div class="empty-icon">📭</div>
      Aucune campagne active pour le moment.
    </div>
  @else
    @foreach($campagnesActives as $camp)
    <div class="campaign-row">
      <div class="campaign-status status-{{ $camp->status->value }}"></div>
      <div class="campaign-info">
        <div class="campaign-name">{{ $camp->name }}</div>
        <div class="campaign-meta">{{ $camp->start_date->format('d/m/Y') }} → {{ $camp->end_date->format('d/m/Y') }} · {{ $camp->durationHuman() }}</div>
      </div>
      <div class="campaign-panels">{{ $camp->panels_count ?? $camp->panels->count() }} panneau(x)</div>
      @if($camp->total_amount > 0)
        <div class="campaign-amount">{{ number_format($camp->total_amount, 0, ',', ' ') }} FCFA</div>
      @endif
      <a href="{{ route('client.campagne.detail', $camp) }}" class="campaign-link">Détail →</a>
    </div>
    @endforeach
  @endif

</div>

<script>
function toggleNav() {
  document.getElementById('nav-links').classList.toggle('open');
}
</script>

</body>
</html>