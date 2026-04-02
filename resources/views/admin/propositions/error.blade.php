<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }} — CIBLE CI</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400&display=swap" rel="stylesheet">
@vite(['resources/css/app.css'])
<style>
  :root{--gold:#e8a020;--dark:#0b0e17;--surface:#131724;--text:#e2e8f0;--text2:#94a3b8;--text3:#64748b}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--dark);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px}
  .card{background:var(--surface);border-radius:20px;padding:48px 40px;max-width:440px;width:100%;text-align:center}
  .icon{font-size:48px;margin-bottom:20px}
  h1{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:#f8fafc;margin-bottom:10px}
  p{font-size:14px;color:var(--text2);line-height:1.65}
  .logo{font-family:'Syne',sans-serif;font-weight:800;color:var(--gold);margin-top:28px;font-size:14px}
</style>
</head>
<body>
<div class="card">
  <div class="icon">
    @if($type === 'success') 🎉
    @elseif($type === 'warning') ⚠️
    @else ❌ @endif
  </div>
  <h1>{{ $title }}</h1>
  <p>{{ $message }}</p>
  <div class="logo">CIBLE CI · Régie Publicitaire</div>
</div>
</body>
</html>