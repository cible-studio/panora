<!DOCTYPE html>
<html lang="fr" id="html-root" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Connexion Client — CIBLE CI</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }

        [data-theme="dark"] {
            --bg:       #080a12;
            --surface:  #0d0f1a;
            --surface2: #13162a;
            --border:   rgba(255,255,255,0.07);
            --border2:  rgba(255,255,255,0.12);
            --text:     #f1f5f9;
            --text2:    #94a3b8;
            --text3:    #4b5563;
            --logo-src: url('{{ asset("images/logob.png") }}');
        }
        [data-theme="light"] {
            --bg:       #f1f3f7;
            --surface:  #ffffff;
            --surface2: #f4f5f8;
            --border:   rgba(0,0,0,0.08);
            --border2:  rgba(0,0,0,0.14);
            --text:     #0f172a;
            --text2:    #475569;
            --text3:    #94a3b8;
            --logo-src: url('{{ asset("images/logol.png") }}');
        }

        body {
            background:var(--bg); color:var(--text);
            font-family:'DM Sans',sans-serif; min-height:100vh;
            display:grid; place-items:center; padding:20px;
            transition:background .2s, color .2s;
        }

        body::before {
            content:''; position:fixed; inset:0; pointer-events:none;
            background:radial-gradient(ellipse 70% 50% at 50% -10%, rgba(226,6,19,0.06) 0%, transparent 70%);
        }

        .wrap { width:100%; max-width:420px; }

        /* ── Logo ── */
        .logo-section { text-align:center; margin-bottom:28px; }
        .logo-section img { width: 150px; margin-bottom:16px; }
        .logo-badge {
            display:inline-flex; align-items:center; gap:6px;
            background:rgba(226,6,19,.08); border:1px solid rgba(226,6,19,.2);
            color:#e20613; border-radius:20px; padding:4px 14px;
            font-size:11px; font-weight:700; letter-spacing:.04em;
        }

        /* ── Barre couleurs ── */
        .color-bar {
            height:4px; border-radius:4px 4px 0 0;
            background:linear-gradient(90deg,#e20613 20%,#fab80b 40%,#22c55e 60%,#81358a 80%,#3f7fc0 100%);
        }

        /* ── Card ── */
        .card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:0 0 16px 16px; padding:32px;
            box-shadow:0 20px 60px rgba(0,0,0,.15);
        }
        .card-title { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; color:var(--text); margin-bottom:5px; }
        .card-sub { font-size:13px; color:var(--text2); margin-bottom:24px; line-height:1.5; }

        /* ── Alerts ── */
        .alert { display:flex; align-items:flex-start; gap:8px; padding:11px 14px; border-radius:10px; font-size:13px; margin-bottom:18px; }
        .alert-error   { background:rgba(239,68,68,.08);   border:1px solid rgba(239,68,68,.25);   color:#fca5a5; }
        .alert-success { background:rgba(34,197,94,.08);   border:1px solid rgba(34,197,94,.25);   color:#86efac; }
        .alert-warning { background:rgba(250,184,11,.08);  border:1px solid rgba(250,184,11,.25);  color:#fde68a; }

        /* ── Form ── */
        .fg { margin-bottom:16px; }
        .fg label { display:block; font-size:10px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:.08em; margin-bottom:6px; }
        .fg input {
            width:100%; background:var(--surface2); border:1px solid var(--border2);
            color:var(--text); border-radius:10px; padding:11px 14px;
            font-size:14px; font-family:'DM Sans',sans-serif; transition:border-color .15s, box-shadow .15s;
        }
        .fg input:focus { outline:none; border-color:rgba(226,6,19,.5); box-shadow:0 0 0 3px rgba(226,6,19,.07); }
        .fg input::placeholder { color:var(--text3); }
        .fg input.is-error { border-color:rgba(239,68,68,.5); }
        .field-error { font-size:11px; color:#fca5a5; margin-top:4px; }

        .remember-row { display:flex; align-items:center; gap:8px; margin-bottom:20px; font-size:13px; color:var(--text2); }
        .remember-row input[type="checkbox"] { accent-color:#e20613; width:15px; height:15px; cursor:pointer; }

        /* ── Bouton ── */
        .btn-submit {
            width:100%; background:#e20613; color:#fff;
            font-weight:700; font-size:14px; padding:13px;
            border-radius:10px; border:none; cursor:pointer;
            font-family:'Syne',sans-serif; letter-spacing:.2px;
            transition:opacity .15s, transform .1s;
            display:flex; align-items:center; justify-content:center; gap:8px;
        }
        .btn-submit:hover { opacity:.9; transform:translateY(-1px); }
        .btn-submit:active { transform:translateY(0); }

        /* ── Footer card ── */
        .card-footer { text-align:center; margin-top:20px; font-size:12px; color:var(--text3); line-height:1.7; }

        /* ── Page footer ── */
        .page-footer { text-align:center; margin-top:20px; font-size:11px; color:var(--text3); line-height:1.8; }
        .page-footer a { color:var(--text3); text-decoration:none; transition:color .15s; }
        .page-footer a:hover { color:#e20613; }

        /* ── Theme toggle ── */
        .theme-toggle {
            position:fixed; top:16px; right:16px;
            padding:7px 10px; background:var(--surface); border:1px solid var(--border2);
            border-radius:9px; cursor:pointer; font-size:15px;
            transition:all .15s; color:var(--text2);
        }
        .theme-toggle:hover { border-color:#e20613; }
    </style>
</head>
<body>

<script>
    (function(){
        const t = localStorage.getItem('client-theme') || 'dark';
        document.getElementById('html-root').setAttribute('data-theme', t);
    })();
</script>

<button class="theme-toggle" id="theme-btn" onclick="toggleTheme()" title="Changer le thème">🌙</button>

<div class="wrap">

    {{-- Logo --}}
    <div class="logo-section flex flex-col items-center gap-2">
        <img id="logo-img" src="{{ asset('images/logob.png') }}" alt="CIBLE CI">
        <div>
            <span class="logo-badge">Espace Client</span>
        </div>
    </div>

    {{-- Barre couleurs + Card --}}
    <div class="color-bar"></div>
    <div class="card">
        <div class="card-title">Connexion</div>
        <div class="card-sub">Accédez à vos propositions, campagnes et factures.</div>

        @if(session('error'))
            <div class="alert alert-error">⚠️ {{ session('error') }}</div>
        @endif
        @if(session('success'))
            <div class="alert alert-success">✅ {{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="alert alert-warning">⚠️ {{ session('warning') }}</div>
        @endif

        <form method="POST" action="{{ route('client.login.post') }}">
            @csrf

            <div class="fg">
                <label for="email">Email professionnel</label>
                <input type="email" id="email" name="email"
                       value="{{ old('email') }}"
                       placeholder="contact@votresociete.com"
                       autocomplete="email" autofocus
                       class="{{ $errors->has('email') ? 'is-error' : '' }}">
                @error('email')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="fg">
                <label for="password">Mot de passe</label>
                <div style="position:relative;">
                    <input type="password" id="password" name="password"
                           placeholder="••••••••"
                           autocomplete="current-password"
                           style="padding-right:42px;"
                           class="{{ $errors->has('password') ? 'is-error' : '' }}">
                    <button type="button"
                            onclick="const i=document.getElementById('password');i.type=i.type==='password'?'text':'password';this.style.opacity=i.type==='text'?'1':'.4'"
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text3);font-size:16px;opacity:.4;padding:0;">
                        👁
                    </button>
                </div>
                @error('password')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="remember-row">
                <input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember" style="cursor:pointer;">Se souvenir de moi</label>
            </div>

            <button type="submit" class="btn-submit">
                Accéder à mon espace
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
        </form>

        <div class="card-footer">
            Pas encore d'accès ?<br>
            Contactez votre commercial <strong style="color:var(--text2);">CIBLE CI</strong>.
        </div>
    </div>

    <div class="page-footer">
        © {{ date('Y') }} CIBLE CI · Régie OOH · Abidjan, Côte d'Ivoire<br>
        <a href="{{ route('login') }}">Accès équipe →</a>
    </div>
</div>

<script>
function toggleTheme() {
    const root = document.getElementById('html-root');
    const current = root.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    localStorage.setItem('client-theme', next);
    document.getElementById('theme-btn').textContent = next === 'dark' ? '🌙' : '☀️';
    document.getElementById('logo-img').src = next === 'dark'
        ? '{{ asset("images/logob.png") }}'
        : '{{ asset("images/logol.png") }}';
}

// Appliquer le bon état au chargement
(function(){
    const t = localStorage.getItem('client-theme') || 'dark';
    document.getElementById('theme-btn').textContent = t === 'dark' ? '🌙' : '☀️';
    document.getElementById('logo-img').src = t === 'dark'
        ? '{{ asset("images/logob.png") }}'
        : '{{ asset("images/logol.png") }}';
})();
</script>
</body>
</html>
