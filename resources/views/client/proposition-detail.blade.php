<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proposition {{ $reservation->reference }} — CIBLE CI</title>
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
    --orange:#f97316;
    --nav-h:60px;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--dark);color:var(--text);font-family:'Inter',sans-serif}

  .navbar{position:sticky;top:0;z-index:100;background:rgba(8,11,18,0.95);backdrop-filter:blur(16px);border-bottom:1px solid rgba(232,160,32,0.08);height:var(--nav-h);display:flex;align-items:center;padding:0 20px;gap:16px}
  .nav-logo{font-family:'Syne',sans-serif;font-weight:800;font-size:18px;color:var(--gold)}
  .nav-badge{background:var(--gold-bg);border:1px solid var(--gold-border);color:var(--gold);border-radius:20px;padding:2px 10px;font-size:10px}
  .nav-links{display:flex;gap:4px;margin-left:8px}
  .nav-link{color:var(--text3);text-decoration:none;font-size:13px;padding:6px 12px;border-radius:8px}
  .nav-link:hover,.nav-link.active{color:var(--text);background:rgba(255,255,255,0.06)}
  .nav-right{margin-left:auto;display:flex;align-items:center;gap:10px}
  .btn-logout{background:transparent;color:var(--text3);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:6px 12px;cursor:pointer}
  .btn-logout:hover{color:var(--red)}
  .mobile-menu-btn{display:none}
  @media(max-width:640px){.nav-links{display:none}.mobile-menu-btn{display:block}}

  .main{max-width:1000px;margin:0 auto;padding:28px 16px 60px}

  .page-header{margin-bottom:24px}
  .page-header h1{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:#f1f5f9}
  .page-header p{font-size:13px;color:var(--text2);margin-top:4px}

  .back-link{display:inline-flex;align-items:center;gap:6px;color:var(--text2);text-decoration:none;font-size:13px;margin-bottom:20px}
  .back-link:hover{color:var(--gold)}

  .info-card{background:var(--surface);border-radius:16px;padding:24px;margin-bottom:24px;border:1px solid rgba(255,255,255,0.06)}
  .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-top:16px}
  .info-item label{font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:4px}
  .info-item value{font-size:15px;font-weight:600;color:var(--text)}

  .panel-table{background:var(--surface);border-radius:16px;overflow-x:auto;margin-bottom:24px;border:1px solid rgba(255,255,255,0.06)}
  .panel-table table{width:100%;border-collapse:collapse}
  .panel-table th{text-align:left;padding:14px 12px;background:rgba(0,0,0,0.2);color:var(--text2);font-size:12px;font-weight:600}
  .panel-table td{padding:12px;border-top:1px solid rgba(255,255,255,0.05);font-size:13px}
  .panel-photo{width:50px;height:40px;object-fit:cover;border-radius:6px;background:var(--surface2)}

  .total-card{background:var(--gold-bg);border:1px solid var(--gold-border);border-radius:16px;padding:20px;margin-bottom:24px;text-align:right}
  .total-label{font-size:13px;color:var(--text2);margin-bottom:4px}
  .total-amount{font-family:'Syne',sans-serif;font-size:32px;font-weight:800;color:var(--gold)}

  .action-buttons{display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap}
  .btn-accept{background:var(--green);color:#0a0d14;font-weight:700;padding:12px 28px;border-radius:10px;border:none;cursor:pointer;font-family:'Syne',sans-serif}
  .btn-decline{background:transparent;color:var(--red);border:1px solid var(--red-border);padding:12px 28px;border-radius:10px;cursor:pointer;font-weight:600}
  .btn-accept:hover{opacity:0.9}
  .btn-decline:hover{background:var(--red-bg)}

  .expired-banner{background:var(--red-bg);border:1px solid var(--red-border);border-radius:12px;padding:16px;text-align:center;margin-bottom:24px}
  .expired-banner p{color:#fca5a5;font-size:14px}
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
    <span style="font-size:12px;color:var(--text2)">{{ $client->name }}</span>
    <form method="POST" action="{{ route('client.logout') }}" style="display:inline">
      @csrf
      <button type="submit" class="btn-logout">Déconnexion</button>
    </form>
    <button class="mobile-menu-btn" onclick="toggleNav()">☰</button>
  </div>
</nav>

<div class="main">
  <a href="{{ route('client.propositions') }}" class="back-link">← Retour aux propositions</a>

  <div class="page-header">
    <h1>Proposition {{ $reservation->reference }}</h1>
    <p>Envoyée le {{ $reservation->proposition_sent_at?->format('d/m/Y à H:i') ?? '—' }}</p>
  </div>

  @if($joursRestants < 0)
    <div class="expired-banner">
      <p>⏰ Cette proposition est expirée. Vous ne pouvez plus y répondre.</p>
    </div>
  @elseif($joursRestants <= 7)
    <div class="expired-banner" style="background:var(--orange-bg);border-color:rgba(249,115,22,0.2)">
      <p>⚠️ Plus que {{ abs($joursRestants) }} jour(s) pour répondre à cette proposition.</p>
    </div>
  @endif

  <div class="info-card">
    <h3 style="font-size:14px;margin-bottom:12px">📅 Période de réservation</h3>
    <div class="info-grid">
      <div class="info-item"><label>Début</label><value>{{ $reservation->start_date->format('d/m/Y') }}</value></div>
      <div class="info-item"><label>Fin</label><value>{{ $reservation->end_date->format('d/m/Y') }}</value></div>
      <div class="info-item"><label>Durée</label><value>{{ $months }} mois</value></div>
    </div>
  </div>

  <div class="panel-table">
    <table>
      <thead>
        <tr><th>Emplacement</th><th>Commune</th><th>Format</th><th>Prix mensuel</th><th>Total ({{ $months }} mois)</th></tr>
      </thead>
      <tbody>
        @foreach($panels as $panel)
        <tr>
          <td style="display:flex;align-items:center;gap:10px">
            @if($panel['photo_url'])<img src="{{ $panel['photo_url'] }}" class="panel-photo" alt="">@endif
            <div><strong>{{ $panel['reference'] }}</strong><br><span style="font-size:11px;color:var(--text3)">{{ $panel['name'] }}</span></div>
          </td>
          <td>{{ $panel['commune'] }}</td>
          <td>{{ $panel['format'] }}<br><span style="font-size:10px;color:var(--text3)">{{ $panel['dimensions'] }}</span></td>
          <td>{{ number_format($panel['monthly_rate'], 0, ',', ' ') }} FCFA</td>
          <td><strong>{{ number_format($panel['total'], 0, ',', ' ') }} FCFA</strong></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="total-card">
    <div class="total-label">Montant total de la prestation</div>
    <div class="total-amount">{{ number_format($panels->sum('total'), 0, ',', ' ') }} FCFA</div>
    <div style="font-size:12px;color:var(--text3);margin-top:6px">Soit {{ number_format($panels->sum('monthly_rate'), 0, ',', ' ') }} FCFA / mois</div>
  </div>

  @if($joursRestants >= 0)
  <div class="action-buttons">
    <form method="POST" action="{{ route('proposition.confirmer', $token) }}" onsubmit="return confirm('Confirmer cette proposition ? Un commercial vous contactera pour finaliser.')">
      @csrf
      <button type="submit" class="btn-accept">✅ Accepter la proposition</button>
    </form>
    <form method="POST" action="{{ route('proposition.refuser', $token) }}" onsubmit="return confirm('Refuser cette proposition ?')">
      @csrf
      <button type="submit" class="btn-decline">❌ Refuser</button>
    </form>
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