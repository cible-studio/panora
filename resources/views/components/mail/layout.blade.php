<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title ?? 'CIBLE CI' }}</title>
<style>
  /* Reset email-safe (les clients mail varient — on utilise des styles inline-friendly) */
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #0f1117;
    font-family: 'Helvetica Neue', Arial, sans-serif;
    color: #e2e8f0;
    -webkit-font-smoothing: antialiased;
    line-height: 1.5;
  }
  a { color: #e8a020; text-decoration: none; }
  .wrap { max-width: 620px; margin: 0 auto; padding: 32px 16px; }
  .card {
    background: #1a1f2e;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(232,160,32,0.15);
  }

  /* Header — bandeau orange CIBLE CI */
  .email-header {
    background: linear-gradient(135deg, #0d1117 0%, #1a1f2e 100%);
    padding: 28px 36px;
    border-bottom: 3px solid #e8a020;
  }
  .email-header .logo {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 1px;
    color: #e8a020;
    margin-bottom: 4px;
  }
  .email-header .logo-sub {
    font-size: 11px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 2px;
  }

  /* Content */
  .email-body { padding: 36px 36px 24px; }
  .email-body h1 {
    font-size: 22px;
    font-weight: 700;
    color: #f1f5f9;
    line-height: 1.25;
    margin-bottom: 14px;
  }
  .email-body h2 {
    font-size: 16px;
    font-weight: 600;
    color: #e8a020;
    margin: 24px 0 10px;
    letter-spacing: 0.3px;
  }
  .email-body p {
    color: #cbd5e1;
    font-size: 14px;
    line-height: 1.65;
    margin-bottom: 12px;
  }
  .email-body strong { color: #f1f5f9; font-weight: 600; }

  /* Badge */
  .badge {
    display: inline-block;
    background: rgba(232,160,32,0.15);
    color: #e8a020;
    border: 1px solid rgba(232,160,32,0.3);
    border-radius: 20px;
    padding: 4px 14px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 16px;
  }
  .badge-success { background: rgba(34,197,94,0.12); color: #4ade80; border-color: rgba(34,197,94,0.3); }
  .badge-danger  { background: rgba(239,68,68,0.12); color: #f87171; border-color: rgba(239,68,68,0.3); }
  .badge-info    { background: rgba(59,130,246,0.12); color: #60a5fa; border-color: rgba(59,130,246,0.3); }

  /* Info box */
  .info-box {
    background: rgba(232,160,32,0.06);
    border: 1px solid rgba(232,160,32,0.15);
    border-radius: 10px;
    padding: 16px 20px;
    margin: 16px 0;
  }
  .info-box-row {
    display: table;
    width: 100%;
    padding: 6px 0;
  }
  .info-box-row > div { display: table-cell; vertical-align: top; }
  .info-box-row .lbl {
    width: 40%;
    font-size: 11px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .info-box-row .val {
    font-size: 13px;
    color: #f1f5f9;
    font-weight: 500;
  }

  /* CTA button */
  .cta-wrap { text-align: center; padding: 24px 0; }
  .cta-btn {
    display: inline-block;
    background: #e8a020;
    color: #0f1117 !important;
    text-decoration: none;
    font-weight: 800;
    font-size: 14px;
    padding: 14px 36px;
    border-radius: 50px;
    letter-spacing: 0.3px;
  }
  .cta-btn:hover { background: #f0b040; }
  .cta-sub {
    margin-top: 10px;
    font-size: 12px;
    color: #64748b;
  }

  /* Code block (refs, IDs) */
  .code {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    background: rgba(255,255,255,0.05);
    color: #e8a020;
    padding: 2px 8px;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.08);
  }

  /* Footer */
  .email-footer {
    padding: 22px 36px;
    border-top: 1px solid rgba(255,255,255,0.06);
    text-align: center;
  }
  .email-footer p {
    font-size: 11px;
    color: #64748b;
    line-height: 1.7;
    margin-bottom: 6px;
  }
  .email-footer a { color: #94a3b8; text-decoration: underline; }

  /* Responsive */
  @media (max-width: 600px) {
    .wrap { padding: 16px 8px; }
    .email-header, .email-body, .email-footer { padding: 22px 20px; }
    .email-body h1 { font-size: 19px; }
    .info-box-row { display: block; }
    .info-box-row > div { display: block; width: 100% !important; }
    .info-box-row .lbl { margin-bottom: 2px; }
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">

    {{-- ── HEADER UNIFORME ── --}}
    <div class="email-header">
      <div class="logo">CIBLE CI</div>
      <div class="logo-sub">Régie Publicitaire · Abidjan</div>
    </div>

    {{-- ── CONTENU SPÉCIFIQUE ── --}}
    <div class="email-body">
      {{ $slot }}
    </div>

    {{-- ── FOOTER ── --}}
    <div class="email-footer">
      <p>
        Cet email a été envoyé automatiquement par <strong style="color:#94a3b8">CIBLE CI</strong>.<br>
        Régie Publicitaire — Abidjan, Côte d'Ivoire.
      </p>
      @isset($footerNote)
        <p style="margin-top:8px">{{ $footerNote }}</p>
      @endisset
      <p style="margin-top:12px;font-size:10px;color:#475569">
        © {{ date('Y') }} CIBLE CI. Tous droits réservés.
      </p>
    </div>

  </div>
</div>
</body>
</html>
