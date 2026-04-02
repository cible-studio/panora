<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proposition — CIBLE CI</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
  :root {
    --gold: #e8a020;
    --gold-light: rgba(232,160,32,0.12);
    --gold-border: rgba(232,160,32,0.25);
    --dark: #0b0e17;
    --surface: #131724;
    --surface2: #1a2030;
    --surface3: #212840;
    --text: #e2e8f0;
    --text2: #94a3b8;
    --text3: #64748b;
    --green: #22c55e;
    --red: #ef4444;
    --radius: 14px;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body { background: var(--dark); color: var(--text); font-family: 'Inter', sans-serif; min-height: 100vh; }

  /* ── NAVBAR ── */
  .navbar { position: sticky; top: 0; z-index: 100; background: rgba(11,14,23,0.9); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(232,160,32,0.1); padding: 0 24px; height: 60px; display: flex; align-items: center; justify-content: space-between; }
  .nav-logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 20px; color: var(--gold); letter-spacing: -0.5px; }
  .nav-meta { font-size: 12px; color: var(--text3); }

  /* ── HERO ── */
  .hero { background: linear-gradient(160deg, #131724 0%, #0d1421 100%); border-bottom: 1px solid rgba(232,160,32,0.1); padding: 48px 24px 40px; text-align: center; }
  .hero-badge { display: inline-flex; align-items: center; gap: 6px; background: var(--gold-light); border: 1px solid var(--gold-border); color: var(--gold); border-radius: 20px; padding: 5px 16px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px; }
  .hero h1 { font-family: 'Syne', sans-serif; font-size: clamp(24px, 5vw, 38px); font-weight: 800; color: #f8fafc; line-height: 1.2; margin-bottom: 12px; }
  .hero-sub { font-size: 15px; color: var(--text2); max-width: 520px; margin: 0 auto 28px; line-height: 1.65; }

  /* Période */
  .period-grid { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-bottom: 20px; }
  .period-card { background: var(--surface); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 12px 20px; text-align: center; min-width: 120px; }
  .period-label { font-size: 10px; color: var(--text3); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
  .period-val { font-size: 16px; font-weight: 700; color: var(--gold); font-family: 'Syne', sans-serif; }

  /* Expiration countdown */
  .expire-bar { display: inline-flex; align-items: center; gap: 6px; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 8px; padding: 8px 16px; font-size: 12px; color: #fca5a5; }

  /* ── ALERTS ── */
  .alert { margin: 16px 24px; padding: 14px 18px; border-radius: 10px; font-size: 14px; display: flex; align-items: flex-start; gap: 10px; }
  .alert-error { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5; }
  .alert-success { background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.25); color: #86efac; }

  /* ── CONTENU PRINCIPAL ── */
  .main { max-width: 860px; margin: 0 auto; padding: 32px 16px 80px; }

  /* ── PANNEAUX GRID ── */
  .panels-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
  .panels-title { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; color: var(--text); }
  .panels-count { font-size: 12px; color: var(--text3); background: var(--surface); border: 1px solid rgba(255,255,255,0.06); border-radius: 20px; padding: 3px 12px; }

  .panels-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin-bottom: 32px; }

  .panel-card { background: var(--surface); border: 1px solid rgba(255,255,255,0.06); border-radius: var(--radius); overflow: hidden; transition: transform 0.15s, border-color 0.15s; }
  .panel-card:hover { transform: translateY(-2px); border-color: var(--gold-border); }
  .panel-img { width: 100%; height: 160px; object-fit: cover; background: var(--surface2); }
  .panel-img-ph { width: 100%; height: 160px; background: var(--surface2); display: flex; align-items: center; justify-content: center; font-family: monospace; font-size: 14px; color: var(--text3); border-bottom: 1px solid rgba(255,255,255,0.04); }
  .panel-body { padding: 14px 16px; }
  .panel-ref { font-family: monospace; font-size: 12px; font-weight: 700; color: var(--gold); margin-bottom: 4px; }
  .panel-name { font-size: 14px; font-weight: 600; color: var(--text); margin-bottom: 6px; line-height: 1.3; }
  .panel-meta { font-size: 12px; color: var(--text3); line-height: 1.7; }
  .panel-price { margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; }
  .price-label { font-size: 11px; color: var(--text3); }
  .price-val { font-size: 15px; font-weight: 700; color: var(--gold); font-family: 'Syne', sans-serif; }
  .lit-badge { display: inline-flex; align-items: center; gap: 3px; background: rgba(250,204,21,0.1); border: 1px solid rgba(250,204,21,0.2); color: #fde047; border-radius: 4px; padding: 1px 6px; font-size: 10px; font-weight: 600; }

  /* ── TOTAL BOX ── */
  .total-box { background: var(--surface); border: 1px solid var(--gold-border); border-radius: var(--radius); padding: 20px 24px; margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
  .total-info { }
  .total-label { font-size: 13px; color: var(--text2); margin-bottom: 4px; }
  .total-amount { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: var(--gold); }
  .total-sub { font-size: 11px; color: var(--text3); margin-top: 2px; }
  .total-right { text-align: right; }
  .total-panels { font-size: 13px; color: var(--text2); }
  .total-duration { font-size: 13px; color: var(--text3); margin-top: 2px; }

  /* ── CTA SECTION ── */
  .cta-section { background: var(--surface); border: 1px solid rgba(255,255,255,0.06); border-radius: var(--radius); padding: 28px 24px; text-align: center; }
  .cta-title { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 700; color: var(--text); margin-bottom: 8px; }
  .cta-sub { font-size: 14px; color: var(--text2); margin-bottom: 24px; line-height: 1.5; }
  .cta-buttons { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
  .btn-confirm { background: var(--gold); color: #0b0e17; font-weight: 700; font-size: 15px; padding: 14px 36px; border-radius: 50px; border: none; cursor: pointer; transition: opacity 0.15s, transform 0.15s; font-family: 'Syne', sans-serif; letter-spacing: 0.3px; }
  .btn-confirm:hover { opacity: 0.9; transform: scale(1.02); }
  .btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
  .btn-refuse { background: transparent; color: var(--text2); font-size: 14px; padding: 14px 28px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.12); cursor: pointer; transition: border-color 0.15s, color 0.15s; }
  .btn-refuse:hover { border-color: rgba(239,68,68,0.4); color: #fca5a5; }
  .cta-note { margin-top: 16px; font-size: 11px; color: var(--text3); }

  /* ── MODAL CONFIRMATION ── */
  .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); z-index: 200; display: none; align-items: center; justify-content: center; padding: 16px; }
  .modal-overlay.open { display: flex; }
  .modal { background: var(--surface2); border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; padding: 32px; width: 100%; max-width: 480px; animation: modalFadeIn 0.2s ease; }
  @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
  .modal h3 { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 700; margin-bottom: 12px; color: var(--text); text-align: center; }
  .modal p { font-size: 14px; color: var(--text2); margin-bottom: 24px; text-align: center; line-height: 1.5; }
  .modal-warning { background: rgba(232,160,32,0.08); border: 1px solid rgba(232,160,32,0.2); border-radius: 12px; padding: 12px 16px; margin-bottom: 24px; font-size: 12px; color: var(--gold); display: flex; align-items: center; gap: 8px; }
  .modal-warning span:first-child { font-size: 18px; }
  .modal-btns { display: flex; gap: 12px; }
  .btn-modal-cancel { flex: 1; background: transparent; color: var(--text2); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; }
  .btn-modal-cancel:hover { border-color: var(--text3); color: var(--text); }
  .btn-modal-confirm { flex: 1; background: var(--gold); color: #0b0e17; border: none; border-radius: 12px; padding: 12px; cursor: pointer; font-size: 14px; font-weight: 700; transition: opacity 0.2s; }
  .btn-modal-confirm:hover { opacity: 0.9; }

  /* ── MODAL REFUS ── */
  .modal-refus textarea { width: 100%; background: var(--surface3); border: 1px solid rgba(255,255,255,0.08); color: var(--text); border-radius: 12px; padding: 12px; font-size: 13px; font-family: 'Inter', sans-serif; resize: vertical; min-height: 80px; margin-bottom: 20px; }
  .modal-refus textarea:focus { outline: none; border-color: rgba(239,68,68,0.4); }

  /* ── FOOTER ── */
  .page-footer { text-align: center; padding: 24px; font-size: 11px; color: var(--text3); border-top: 1px solid rgba(255,255,255,0.04); }

  /* ── RESPONSIVE ── */
  @media (max-width: 600px) {
    .panels-grid { grid-template-columns: 1fr; }
    .hero { padding: 32px 16px 28px; }
    .total-box { flex-direction: column; text-align: center; }
    .total-right { text-align: center; }
    .cta-buttons { flex-direction: column; }
    .btn-confirm, .btn-refuse { width: 100%; }
    .modal { padding: 24px; }
    .modal-btns { flex-direction: column; }
  }
</style>
</head>
<body>

{{-- ── NAVBAR ── --}}
<nav class="navbar">
  <div class="nav-logo">CIBLE CI</div>
  <div class="nav-meta">Proposition · Réf. {{ $reservation->reference }}</div>
</nav>

{{-- ── ALERTS ── --}}
@if(session('error'))
  <div class="alert alert-error">⚠️ {{ session('error') }}</div>
@endif
@if(session('success'))
  <div class="alert alert-success">✅ {{ session('success') }}</div>
@endif

{{-- ── HERO ── --}}
<div class="hero">
  <div class="hero-badge">
    <span>📋</span> Proposition Commerciale
  </div>
  <h1>
    Bonjour {{ $reservation->client?->name ?? 'Client' }},<br>
    voici votre sélection de panneaux
  </h1>
  <p class="hero-sub">
    Notre équipe commerciale a sélectionné <strong>{{ $panels->count() }} emplacement(s)</strong>
    correspondant à vos besoins. Consultez les détails et répondez en un clic.
  </p>

  <div class="period-grid">
    <div class="period-card">
      <div class="period-label">Début</div>
      <div class="period-val">{{ $reservation->start_date->format('d/m/Y') }}</div>
    </div>
    <div class="period-card">
      <div class="period-label">Fin</div>
      <div class="period-val">{{ $reservation->end_date->format('d/m/Y') }}</div>
    </div>
    <div class="period-card">
      <div class="period-label">Durée</div>
      <div class="period-val">{{ round($months) }} mois</div>
    </div>
    <div class="period-card">
      <div class="period-label">Panneaux</div>
      <div class="period-val">{{ $panels->count() }}</div>
    </div>
  </div>

  @if($expiresIn !== null && $expiresIn > 0)
    <div class="expire-bar">
      ⏰ Expire dans {{ $expiresIn > 24 ? round($expiresIn / 24) . ' jour(s)' : $expiresIn . ' heure(s)' }}
      — {{ $reservation->proposition_expires_at->format('d/m/Y à H:i') }}
    </div>
  @elseif($expiresIn !== null && $expiresIn <= 0)
    <div class="expire-bar">⚠️ Cette proposition a expiré</div>
  @endif
</div>

{{-- ── CONTENU PRINCIPAL ── --}}
<div class="main">

  {{-- Grille panneaux --}}
  <div class="panels-header">
    <div class="panels-title">Emplacements sélectionnés</div>
    <div class="panels-count">{{ $panels->count() }} panneau(x)</div>
  </div>

  <div class="panels-grid">
    @foreach($panels as $panel)
    <div class="panel-card">
      @if($panel['photo_url'])
        <img src="{{ $panel['photo_url'] }}" class="panel-img" alt="{{ $panel['reference'] }}" loading="lazy">
      @else
        <div class="panel-img-ph">{{ $panel['reference'] }}</div>
      @endif

      <div class="panel-body">
        <div class="panel-ref">{{ $panel['reference'] }}</div>
        <div class="panel-name">{{ Str::limit($panel['name'], 45) }}</div>
        <div class="panel-meta">
          📍 {{ $panel['commune'] }}
          @if($panel['zone'] !== '—') · {{ $panel['zone'] }} @endif
          @if($panel['dimensions']) · {{ $panel['dimensions'] }} @endif
          @if($panel['category'] !== '—') · {{ $panel['category'] }} @endif
        </div>
        @if($panel['is_lit'])
          <span class="lit-badge" style="margin-top:6px;display:inline-flex">💡 Éclairé</span>
        @endif

        <div class="panel-price">
          <span class="price-label">Tarif / campagne</span>
          @if($panel['total'] > 0)
            <span class="price-val">{{ number_format($panel['total'], 0, ',', ' ') }} FCFA</span>
          @else
            <span class="price-val" style="font-size:12px;color:var(--text3)">Sur devis</span>
          @endif
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Total --}}
  @php
    $totalAmount = $panels->sum('total');
    $panelCount  = $panels->count();
    $duration    = $reservation->start_date->diff($reservation->end_date);
    $durationStr = round($months) . ' mois';
  @endphp

  @if($totalAmount > 0)
  <div class="total-box">
    <div class="total-info">
      <div class="total-label">Montant total estimé HT</div>
      <div class="total-amount">{{ number_format($totalAmount, 0, ',', ' ') }} FCFA</div>
      <div class="total-sub">Hors taxes · Devis définitif sur confirmation</div>
    </div>
    <div class="total-right">
      <div class="total-panels">{{ $panelCount }} emplacement(s)</div>
      <div class="total-duration">{{ $durationStr }} de campagne</div>
    </div>
  </div>
  @endif

  {{-- CTA --}}
  @if($expiresIn === null || $expiresIn > 0)
  <div class="cta-section">
    <div class="cta-title">Quelle est votre décision ?</div>
    <div class="cta-sub">
      Votre réponse sera prise en compte immédiatement.<br>
      En confirmant, les panneaux vous seront attribués et une campagne sera créée.
    </div>

    <div class="cta-buttons">
      <button type="button" class="btn-confirm" id="btn-confirm" onclick="openConfirmModal()">
        ✅ Je confirme cette proposition
      </button>

      <button type="button" class="btn-refuse" onclick="openRefusModal()">
        ✗ Je refuse
      </button>
    </div>

    <div class="cta-note">
      🔒 Votre réponse est sécurisée · Aucune inscription requise · CIBLE CI · Abidjan
    </div>
  </div>
  @endif

</div>

{{-- ── MODAL CONFIRMATION ── --}}
<div class="modal-overlay" id="modal-confirm">
  <div class="modal">
    <h3>✅ Confirmer la proposition</h3>
    <p>Souhaitez-vous confirmer cette proposition ?<br>Les panneaux vous seront attribués immédiatement.</p>
    <div class="modal-warning">
      <span>🔒</span>
      <span>Cette action est définitive et déclenche la création de votre campagne.</span>
    </div>
    <div class="modal-btns">
      <button type="button" class="btn-modal-cancel" onclick="closeConfirmModal()">Annuler</button>
      <button type="button" class="btn-modal-confirm" id="modal-confirm-btn" onclick="submitConfirm()">Confirmer la proposition</button>
    </div>
  </div>
</div>

{{-- ── MODAL REFUS ── --}}
<div class="modal-overlay modal-refus" id="modal-refus">
  <div class="modal">
    <h3>✗ Refuser la proposition</h3>
    <p>Souhaitez-vous indiquer un motif ? Cela nous aidera à mieux vous proposer des emplacements adaptés.</p>

    <form method="POST" action="{{ route('proposition.refuser', $token) }}" id="form-refuser">
      @csrf
      <textarea name="motif" placeholder="Motif (optionnel) — ex: budget insuffisant, zones non souhaitées..."></textarea>
      <div class="modal-btns">
        <button type="button" class="btn-modal-cancel" onclick="closeRefusModal()">Annuler</button>
        <button type="submit" class="btn-modal-confirm">Confirmer le refus</button>
      </div>
    </form>
  </div>
</div>

{{-- Formulaire de confirmation caché --}}
<form method="POST" action="{{ route('proposition.confirmer', $token) }}" id="form-confirmer" style="display:none;">
  @csrf
</form>

{{-- ── FOOTER ── --}}
<div class="page-footer">
  CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire<br>
  Référence : {{ $reservation->reference }} · Proposition envoyée le {{ $reservation->proposition_sent_at?->format('d/m/Y') }}
</div>

<script>
function openConfirmModal() {
  document.getElementById('modal-confirm').classList.add('open');
}

function closeConfirmModal() {
  document.getElementById('modal-confirm').classList.remove('open');
}

function submitConfirm() {
  const btn = document.getElementById('modal-confirm-btn');
  const confirmBtn = document.getElementById('btn-confirm');
  
  // Désactiver les boutons
  btn.disabled = true;
  btn.textContent = 'Confirmation en cours...';
  if (confirmBtn) confirmBtn.disabled = true;
  
  // Soumettre le formulaire
  document.getElementById('form-confirmer').submit();
}

function openRefusModal() {
  document.getElementById('modal-refus').classList.add('open');
}

function closeRefusModal() {
  document.getElementById('modal-refus').classList.remove('open');
}

// Fermer les modals au clic en dehors
document.getElementById('modal-confirm').addEventListener('click', function(e) {
  if (e.target === this) closeConfirmModal();
});
document.getElementById('modal-refus').addEventListener('click', function(e) {
  if (e.target === this) closeRefusModal();
});

// Désactiver le double-soumission du formulaire de refus
document.getElementById('form-refuser')?.addEventListener('submit', function() {
  const btn = this.querySelector('button[type="submit"]');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Refus en cours...';
  }
});
</script>

</body>
</html>