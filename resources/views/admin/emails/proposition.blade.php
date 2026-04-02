<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proposition commerciale — CIBLE CI</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0f1117; font-family: 'Helvetica Neue', Arial, sans-serif; color: #e2e8f0; }
  .wrap { max-width: 620px; margin: 0 auto; padding: 32px 16px; }
  .card { background: #1a1f2e; border-radius: 16px; overflow: hidden; border: 1px solid rgba(232,160,32,0.15); }
  .header { background: linear-gradient(135deg, #0f1117 0%, #1a1f2e 100%); padding: 36px 40px; border-bottom: 1px solid rgba(232,160,32,0.2); }
  .logo { font-size: 22px; font-weight: 800; letter-spacing: -0.5px; color: #e8a020; margin-bottom: 4px; }
  .logo-sub { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 2px; }
  .hero { padding: 40px 40px 32px; }
  .badge { display: inline-block; background: rgba(232,160,32,0.15); color: #e8a020; border: 1px solid rgba(232,160,32,0.3); border-radius: 20px; padding: 4px 14px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px; }
  .hero h1 { font-size: 28px; font-weight: 700; color: #f1f5f9; line-height: 1.25; margin-bottom: 12px; }
  .hero p { color: #94a3b8; font-size: 15px; line-height: 1.65; }
  .period-box { margin: 24px 0; background: rgba(232,160,32,0.06); border: 1px solid rgba(232,160,32,0.15); border-radius: 10px; padding: 16px 20px; display: flex; gap: 24px; }
  .period-item { flex: 1; }
  .period-label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
  .period-val { font-size: 15px; font-weight: 600; color: #e8a020; }
  .divider { height: 1px; background: rgba(255,255,255,0.06); margin: 0 40px; }
  .panels-section { padding: 28px 40px; }
  .section-title { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 16px; }
  .panel-row { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
  .panel-row:last-child { border-bottom: none; }
  .panel-img { width: 56px; height: 40px; border-radius: 6px; object-fit: cover; background: #0f1117; flex-shrink: 0; }
  .panel-img-placeholder { width: 56px; height: 40px; border-radius: 6px; background: #0f1117; border: 1px solid rgba(232,160,32,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .panel-ref { font-family: monospace; font-size: 11px; color: #e8a020; font-weight: 700; }
  .panel-name { font-size: 13px; color: #cbd5e1; }
  .panel-meta { font-size: 11px; color: #64748b; margin-top: 2px; }
  .panel-price { margin-left: auto; font-size: 13px; font-weight: 700; color: #f1f5f9; text-align: right; flex-shrink: 0; }
  .panel-price-sub { font-size: 10px; color: #64748b; font-weight: 400; }
  .total-row { margin: 16px 0 0; padding: 14px 16px; background: rgba(232,160,32,0.08); border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
  .total-label { font-size: 13px; color: #94a3b8; }
  .total-amount { font-size: 18px; font-weight: 800; color: #e8a020; }
  .cta-section { padding: 32px 40px; text-align: center; }
  .cta-btn { display: inline-block; background: #e8a020; color: #0f1117; text-decoration: none; font-weight: 800; font-size: 15px; padding: 16px 40px; border-radius: 50px; letter-spacing: 0.3px; }
  .cta-sub { margin-top: 12px; font-size: 12px; color: #64748b; }
  .expire-banner { margin: 0 40px 28px; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #fca5a5; text-align: center; }
  .footer { padding: 24px 40px; border-top: 1px solid rgba(255,255,255,0.06); text-align: center; }
  .footer p { font-size: 11px; color: #475569; line-height: 1.7; }
  .footer a { color: #64748b; }
</style>
</head>
<body>
<div class="wrap">
<div class="card">

  {{-- ── HEADER ── --}}
  <div class="header">
    <div class="logo">CIBLE CI</div>
    <div class="logo-sub">Régie Publicitaire · Abidjan</div>
  </div>

  {{-- ── HERO ── --}}
  <div class="hero">
    <span class="badge">📋 Proposition commerciale</span>
    <h1>Bonjour {{ $client?->name ?? 'Client' }},<br>nous avons sélectionné des emplacements pour vous.</h1>
    <p>Notre équipe commerciale vous propose les panneaux suivants pour votre prochaine campagne. Consultez les détails et confirmez ou refusez en un clic.</p>

    <div class="period-box">
      <div class="period-item">
        <div class="period-label">Début de campagne</div>
        <div class="period-val">{{ $reservation->start_date->format('d/m/Y') }}</div>
      </div>
      <div class="period-item">
        <div class="period-label">Fin de campagne</div>
        <div class="period-val">{{ $reservation->end_date->format('d/m/Y') }}</div>
      </div>
      <div class="period-item">
        <div class="period-label">Panneaux proposés</div>
        <div class="period-val">{{ $panels->count() }} emplacement(s)</div>
      </div>
    </div>
  </div>

  <div class="divider"></div>

  {{-- ── LISTE PANNEAUX ── --}}
  <div class="panels-section">
    <div class="section-title">Emplacements sélectionnés</div>

    @php
      $months = max(1.0, (function($s, $e) {
        $s = \Carbon\Carbon::parse($s)->startOfDay();
        $e = \Carbon\Carbon::parse($e)->endOfDay();
        $m = (int)$s->diffInMonths($e);
        $r = $s->copy()->addMonths($m)->diffInDays($e);
        return (float)($r > 0 ? $m + 1 : $m);
      })($reservation->start_date, $reservation->end_date));
    @endphp

    @foreach($panels->take(8) as $panel)
    @php
      $photo    = $panel->photos->sortBy('ordre')->first();
      $photoUrl = $photo ? asset('storage/' . ltrim($photo->path, '/')) : null;
      $unit     = (float)($panel->monthly_rate ?? 0);
      $total    = $unit * $months;
    @endphp
    <div class="panel-row">
      @if($photoUrl)
        <img src="{{ $photoUrl }}" class="panel-img" alt="{{ $panel->reference }}">
      @else
        <div class="panel-img-placeholder" style="font-family:monospace;font-size:9px;color:#64748b">PH</div>
      @endif
      <div style="flex:1;min-width:0">
        <div class="panel-ref">{{ $panel->reference }}</div>
        <div class="panel-name">{{ \Illuminate\Support\Str::limit($panel->name, 35) }}</div>
        <div class="panel-meta">
          {{ $panel->commune?->name ?? '—' }}
          @if($panel->format?->width && $panel->format?->height)
          · {{ rtrim(rtrim(number_format($panel->format->width, 2, '.', ''), '0'), '.') }}×{{ rtrim(rtrim(number_format($panel->format->height, 2, '.', ''), '0'), '.') }}m
          @endif
          @if($panel->is_lit) · 💡 Éclairé @endif
        </div>
      </div>
      <div class="panel-price">
        @if($total > 0)
          {{ number_format($total, 0, ',', ' ') }}<br>
          <span class="panel-price-sub">FCFA / campagne</span>
        @else
          <span style="color:#64748b;font-size:11px">Sur devis</span>
        @endif
      </div>
    </div>
    @endforeach

    @if($panels->count() > 8)
    <div style="text-align:center;padding:12px 0;font-size:12px;color:#64748b">
      + {{ $panels->count() - 8 }} autre(s) emplacement(s) — consultez le lien ci-dessous
    </div>
    @endif

    {{-- Total --}}
    @php $totalAmount = $panels->sum(fn($p) => (float)($p->monthly_rate ?? 0) * $months); @endphp
    @if($totalAmount > 0)
    <div class="total-row">
      <div class="total-label">Montant total estimé</div>
      <div class="total-amount">{{ number_format($totalAmount, 0, ',', ' ') }} FCFA</div>
    </div>
    @endif
  </div>

  {{-- ── EXPIRATION ── --}}
  @if($expiresAt)
  <div class="expire-banner">
    ⏰ Cette proposition expire le <strong>{{ $expiresAt->format('d/m/Y à H:i') }}</strong>
  </div>
  @endif

  {{-- ── CTA ── --}}
  <div class="cta-section">
    <a href="{{ $lien }}" class="cta-btn">Consulter & Répondre à la proposition →</a>
    <div class="cta-sub">Confirmez ou refusez en quelques secondes · Aucune inscription requise</div>
  </div>

  {{-- ── FOOTER ── --}}
  <div class="footer">
    <p>
      Cet email vous a été envoyé par <strong>CIBLE CI</strong> · Régie Publicitaire · Abidjan, Côte d'Ivoire<br>
      Référence réservation : <code>{{ $reservation->reference }}</code><br>
      Si vous avez des questions, contactez notre équipe commerciale.<br>
      <a href="{{ $lien }}">Voir la proposition en ligne</a>
    </p>
  </div>

</div>
</div>
</body>
</html>