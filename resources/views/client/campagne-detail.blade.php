<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $campaign->name }} — CIBLE CI</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
  :root {
    --gold:#e8a020; --gold-bg:rgba(232,160,32,0.08); --gold-border:rgba(232,160,32,0.2);
    --dark:#080b12; --surface:#111520; --surface2:#181e2e;
    --text:#e2e8f0; --text2:#94a3b8; --text3:#64748b;
    --green:#22c55e; --green-bg:rgba(34,197,94,0.08);
    --blue:#3b82f6;
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
  .back-link{display:inline-flex;align-items:center;gap:6px;color:var(--text2);text-decoration:none;font-size:13px;margin-bottom:20px}
  .back-link:hover{color:var(--gold)}

  .page-header{margin-bottom:24px}
  .page-header h1{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:#f1f5f9}
  .page-header p{font-size:13px;color:var(--text2);margin-top:4px}

  .info-card{background:var(--surface);border-radius:16px;padding:20px;margin-bottom:24px;border:1px solid rgba(255,255,255,0.06)}
  .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-top:12px}
  .info-item label{font-size:11px;color:var(--text3);text-transform:uppercase;display:block;margin-bottom:4px}
  .info-item value{font-size:14px;font-weight:600}

  .status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}
  .status-actif{background:var(--green-bg);color:#86efac}
  .status-pose{background:rgba(59,130,246,0.15);color:#93c5fd}
  .status-termine{background:rgba(100,116,139,0.15);color:#94a3b8}

  .panel-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:16px}
  .panel-card{background:var(--surface);border:1px solid rgba(255,255,255,0.06);border-radius:12px;overflow:hidden}
  .panel-img{width:100%;height:140px;object-fit:cover;background:var(--surface2)}
  .panel-info{padding:12px}
  .panel-ref{font-family:monospace;font-size:11px;color:var(--gold);margin-bottom:4px}
  .panel-name{font-weight:600;font-size:14px;margin-bottom:6px}
  .panel-details{font-size:11px;color:var(--text2);margin-bottom:4px}

  .invoice-section{margin-top:24px;padding-top:24px;border-top:1px solid rgba(255,255,255,0.06)}
  .invoice-card{background:var(--surface);border-radius:12px;padding:16px;margin-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
  .invoice-ref{font-family:monospace;font-size:12px;color:var(--gold)}
  .invoice-amount{font-weight:700;font-size:16px}
  .invoice-status{padding:4px 12px;border-radius:20px;font-size:11px}
  .invoice-paid{background:var(--green-bg);color:#86efac}
  .invoice-pending{background:rgba(249,115,22,0.15);color:#fdba74}
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
  <a href="{{ route('client.campagnes') }}" class="back-link">← Retour aux campagnes</a>

  <div class="page-header">
    <h1>{{ $campaign->name }}</h1>
    <p>Campagne #{{ $campaign->reference ?? $campaign->id }}</p>
  </div>

  <div class="info-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
      <h3 style="font-size:14px">📋 Informations générales</h3>
      <span class="status-badge status-{{ $campaign->status->value }}">{{ ucfirst($campaign->status->value) }}</span>
    </div>
    <div class="info-grid">
      <div class="info-item"><label>Début</label><value>{{ $campaign->start_date->format('d/m/Y') }}</value></div>
      <div class="info-item"><label>Fin</label><value>{{ $campaign->end_date->format('d/m/Y') }}</value></div>
      <div class="info-item"><label>Durée</label><value>{{ $campaign->durationHuman() }}</value></div>
      @if($campaign->total_amount)
      <div class="info-item"><label>Montant total</label><value style="color:var(--gold);font-size:18px">{{ number_format($campaign->total_amount, 0, ',', ' ') }} FCFA</value></div>
      @endif
    </div>
  </div>

  <h3 style="font-size:14px;margin-bottom:16px">📍 Emplacements publicitaires ({{ $campaign->panels->count() }})</h3>
  <div class="panel-grid">
    @foreach($campaign->panels as $panel)
    @php $photo = $panel->photos->sortBy('ordre')->first(); @endphp
    <div class="panel-card">
      @if($photo)
        <img src="{{ asset('storage/' . ltrim($photo->path, '/')) }}" class="panel-img" alt="">
      @else
        <div class="panel-img" style="display:flex;align-items:center;justify-content:center;color:var(--text3)">📷</div>
      @endif
      <div class="panel-info">
        <div class="panel-ref">{{ $panel->reference }}</div>
        <div class="panel-name">{{ $panel->name }}</div>
        <div class="panel-details">{{ $panel->commune?->name ?? '—' }}</div>
        <div class="panel-details">{{ $panel->format?->name ?? '—' }} · {{ $panel->is_lit ? '💡 Éclairé' : '☀️ Non éclairé' }}</div>
      </div>
    </div>
    @endforeach
  </div>

  @if($campaign->invoices->isNotEmpty())
  <div class="invoice-section">
    <h3 style="font-size:14px;margin-bottom:16px">💰 Factures associées</h3>
    @foreach($campaign->invoices as $invoice)
    <div class="invoice-card">
      <div>
        <div class="invoice-ref">{{ $invoice->reference }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px">Émise le {{ $invoice->created_at->format('d/m/Y') }}</div>
      </div>
      <div class="invoice-amount">{{ number_format($invoice->amount, 0, ',', ' ') }} FCFA</div>
      <span class="invoice-status {{ $invoice->status === 'paid' ? 'invoice-paid' : 'invoice-pending' }}">
        {{ $invoice->status === 'paid' ? '✓ Payée' : '⏳ En attente' }}
      </span>
    </div>
    @endforeach
  </div>
  @endif
</div>

<script>
function toggleNav() { document.getElementById('nav-links').classList.toggle('open'); }
</script>
</body>
</html>