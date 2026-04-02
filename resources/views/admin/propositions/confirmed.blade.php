<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proposition confirmée — CIBLE CI</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet">
@vite(['resources/css/app.css'])
<style>
  :root { --gold:#e8a020; --dark:#0b0e17; --surface:#131724; --text:#e2e8f0; --text2:#94a3b8; --text3:#64748b; }
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--dark);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px}
  .card{background:var(--surface);border:1px solid rgba(34,197,94,0.2);border-radius:20px;padding:48px 40px;max-width:480px;width:100%;text-align:center}
  .icon{font-size:56px;margin-bottom:20px;line-height:1}
  h1{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;color:#f8fafc;margin-bottom:10px}
  p{font-size:14px;color:var(--text2);line-height:1.65;margin-bottom:6px}
  .ref{font-family:monospace;font-size:12px;color:var(--gold);background:rgba(232,160,32,0.08);border:1px solid rgba(232,160,32,0.15);border-radius:6px;padding:4px 12px;display:inline-block;margin:16px 0}
  .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:20px 0;text-align:left}
  .info-item{background:#1a2030;border-radius:10px;padding:12px 14px}
  .info-label{font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
  .info-val{font-size:13px;font-weight:600;color:var(--text)}
  .divider{height:1px;background:rgba(255,255,255,0.06);margin:20px 0}
  .note{font-size:12px;color:var(--text3);margin-top:8px}
  .logo{font-family:'Syne',sans-serif;font-weight:800;color:var(--gold);margin-top:28px;font-size:14px}
</style>
</head>
<body>
<div class="card">
  <div class="icon">🎉</div>
  <h1>Proposition confirmée !</h1>
  <p>Merci <strong>{{ $client?->name ?? 'Client' }}</strong> — votre confirmation a bien été enregistrée.</p>
  <p>Notre équipe commerciale va prendre contact avec vous pour la suite.</p>

  <div class="ref">Réf. {{ $reservation->reference }}</div>

  <div class="info-grid">
    <div class="info-item">
      <div class="info-label">Début campagne</div>
      <div class="info-val">{{ $reservation->start_date->format('d/m/Y') }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">Fin campagne</div>
      <div class="info-val">{{ $reservation->end_date->format('d/m/Y') }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">Panneaux réservés</div>
      <div class="info-val">{{ $reservation->panels->count() }}</div>
    </div>
    @if($campaign)
    <div class="info-item">
      <div class="info-label">Campagne créée</div>
      <div class="info-val" style="color:#e8a020">{{ $campaign->name }}</div>
    </div>
    @endif
  </div>

  <div class="divider"></div>
  <p class="note">Un email de confirmation vous sera envoyé.<br>Pour toute question, contactez notre équipe commerciale.</p>
  <div class="logo">CIBLE CI · Régie Publicitaire</div>
</div>
</body>
</html>