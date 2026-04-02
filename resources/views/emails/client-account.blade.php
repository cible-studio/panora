{{-- ════════════════════════════════════════════════════════
     resources/views/emails/client-account.blade.php
     Email de bienvenue avec credentials
     ════════════════════════════════════════════════════════ --}}
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $isReset ? 'Nouveau mot de passe' : 'Bienvenue' }} — CIBLE CI</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:#0f1117;font-family:'Helvetica Neue',Arial,sans-serif;color:#e2e8f0}
  .wrap{max-width:560px;margin:0 auto;padding:28px 16px}
  .card{background:#1a1f2e;border-radius:16px;overflow:hidden;border:1px solid rgba(232,160,32,0.15)}
  .header{background:#131724;padding:28px 36px;border-bottom:1px solid rgba(232,160,32,0.15)}
  .logo{font-size:20px;font-weight:800;color:#e8a020;letter-spacing:-0.3px}
  .logo-sub{font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:2px;margin-top:3px}
  .body{padding:32px 36px}
  .badge{display:inline-block;background:rgba(232,160,32,0.1);color:#e8a020;border:1px solid rgba(232,160,32,0.25);border-radius:20px;padding:3px 14px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:18px}
  h1{font-size:22px;font-weight:700;color:#f1f5f9;margin-bottom:10px}
  p{font-size:14px;color:#94a3b8;line-height:1.7;margin-bottom:12px}
  .creds{background:#0f1117;border:1px solid rgba(232,160,32,0.15);border-radius:12px;padding:20px 24px;margin:20px 0}
  .cred-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04)}
  .cred-row:last-child{border-bottom:none}
  .cred-label{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px}
  .cred-val{font-size:14px;font-weight:600;color:#e2e8f0;font-family:monospace}
  .cta{text-align:center;margin:24px 0}
  .cta a{display:inline-block;background:#e8a020;color:#0f1117;font-weight:700;font-size:14px;padding:13px 32px;border-radius:50px;text-decoration:none}
  .warning{background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.2);border-radius:8px;padding:12px 16px;font-size:12px;color:#fde68a;margin:16px 0}
  .footer{padding:20px 36px;border-top:1px solid rgba(255,255,255,0.05);text-align:center;font-size:11px;color:#475569}
</style>
</head>
<body>
<div class="wrap">
<div class="card">
  <div class="header">
    <div class="logo">CIBLE CI</div>
    <div class="logo-sub">Régie Publicitaire · Abidjan</div>
  </div>
  <div class="body">
    <span class="badge">{{ $isReset ? '🔑 Réinitialisation' : '🎉 Bienvenue' }}</span>
    <h1>
      @if($isReset)
        Votre mot de passe a été réinitialisé
      @else
        Votre espace client est prêt, {{ $client->name }} !
      @endif
    </h1>

    @if($isReset)
      <p>Un nouveau mot de passe temporaire a été créé pour votre compte. Connectez-vous avec les identifiants ci-dessous et changez-le immédiatement.</p>
    @else
      <p>Bienvenue sur votre espace client CIBLE CI. Vous pouvez maintenant consulter vos propositions commerciales, suivre vos campagnes et accéder à vos factures.</p>
    @endif

    <div class="creds">
      <div class="cred-row">
        <span class="cred-label">Adresse URL</span>
        <span class="cred-val">{{ url('/client/login') }}</span>
      </div>
      <div class="cred-row">
        <span class="cred-label">Email</span>
        <span class="cred-val">{{ $client->email }}</span>
      </div>
      <div class="cred-row">
        <span class="cred-label">Mot de passe temporaire</span>
        <span class="cred-val">{{ $motDePasse }}</span>
      </div>
    </div>

    <div class="warning">
      ⚠️ Ce mot de passe est temporaire. Vous devrez en définir un personnel à votre première connexion.
    </div>

    <div class="cta">
      <a href="{{ $loginUrl }}">Accéder à mon espace →</a>
    </div>
  </div>
  <div class="footer">
    CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire<br>
    Si vous n'attendiez pas cet email, ignorez-le ou contactez notre équipe.
  </div>
</div>
</div>
</body>
</html>