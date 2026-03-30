<x-guest-layout>
<x-slot name="title">Connexion</x-slot>

<style>
/* ── LOGO ── */
.auth-logo { text-align:center; margin-bottom:28px; }
.auth-logo-mark {
    font-family:var(--font-display); font-weight:800;
    font-size:32px; color:var(--accent); letter-spacing:-1px;
}
.auth-logo-sub {
    font-size:11px; color:var(--text2);
    letter-spacing:2px; text-transform:uppercase; margin-top:5px;
}
.auth-logo-line {
    width:40px; height:2px;
    background:linear-gradient(90deg,var(--accent),transparent);
    margin:10px auto 0; border-radius:2px;
}

/* ── TAB SWITCH ── */
.tab-switch {
    display:flex; background:var(--surface2);
    border-radius:10px; padding:4px; margin-bottom:20px; gap:4px; width:100%;
}
.tab-sw-btn {
    flex:1; padding:9px; text-align:center; border-radius:7px;
    cursor:pointer; font-size:13px; color:var(--text2);
    transition:all .15s; font-weight:500; border:none;
    background:none; font-family:var(--font-body);
}
.tab-sw-btn.active { background:var(--surface); color:var(--text); }

/* ── CARD ── */
.login-card {
    background:var(--surface); border:1px solid var(--border2);
    border-radius:16px; padding:28px;
    box-shadow:0 24px 60px rgba(0,0,0,.5), 0 0 0 1px rgba(255,255,255,.02);
}
.login-title {
    font-family:var(--font-display); font-weight:700;
    font-size:18px; margin-bottom:3px;
}
.login-sub { font-size:12px; color:var(--text2); margin-bottom:24px; }

/* ── ROLE GRID ── */
.role-grid { display:grid; grid-template-columns:1fr 1fr; gap:9px; margin-bottom:22px; }
.role-btn {
    background:var(--surface2); border:1px solid var(--border2);
    border-radius:10px; padding:13px 9px; cursor:pointer;
    text-align:center; transition:all .2s; position:relative;
}
.role-btn:hover { border-color:var(--border2); background:var(--surface3); }
.role-btn.selected { border-color:var(--accent); background:var(--accent-dim); }
.role-btn.selected::after {
    content:'✓'; position:absolute; top:6px; right:8px;
    color:var(--accent); font-size:11px; font-weight:700;
}
.role-icon { font-size:20px; margin-bottom:5px; }
.role-name { font-size:12px; font-weight:600; }
.role-desc { font-size:10px; color:var(--text2); margin-top:2px; }

/* ── FORM GROUPS ── */
.fg { display:flex; flex-direction:column; gap:5px; margin-bottom:13px; }
.fg label {
    font-size:11px; font-weight:600; color:var(--text2);
    text-transform:uppercase; letter-spacing:.6px;
}
.fg input, .fg select {
    background:var(--surface2); border:1px solid var(--border2);
    border-radius:8px; padding:10px 12px; color:var(--text);
    font-family:var(--font-body); font-size:13px;
    outline:none; transition:border-color .15s; width:100%;
}
.fg input:focus, .fg select:focus { border-color:var(--accent); }
.fg input::placeholder { color:var(--text3); }
.fg .field-error { font-size:11px; color:var(--red); margin-top:3px; }
.fg input.is-error { border-color:var(--red); }

/* ── REMEMBER ── */
.remember-row {
    display:flex; align-items:center; gap:8px;
    margin-bottom:14px; font-size:12px; color:var(--text2);
}
.remember-row input[type="checkbox"] {
    width:15px; height:15px; accent-color:var(--accent);
    cursor:pointer;
}

/* ── BOUTON LOGIN ── */
.btn-login {
    width:100%; padding:12px; border-radius:9px;
    background:linear-gradient(135deg,var(--accent),#c07010);
    color:#000; font-weight:700; font-size:14px;
    border:none; cursor:pointer; font-family:var(--font-display);
    transition:all .2s; margin-top:2px; letter-spacing:.2px;
    display:flex; align-items:center; justify-content:center; gap:8px;
}
.btn-login:hover {
    background:linear-gradient(135deg,var(--accent2),var(--accent));
    box-shadow:0 4px 20px rgba(232,160,32,.3);
    transform:translateY(-1px);
}
.btn-login:active { transform:translateY(0); }
.btn-login.blue { background:linear-gradient(135deg,#3b82f6,#6366f1); color:#fff; }
.btn-login.blue:hover { box-shadow:0 4px 20px rgba(99,102,241,.3); }

/* ── FOOTER ── */
.login-footer {
    text-align:center; font-size:11px;
    color:var(--text3); margin-top:14px;
}
.login-footer a { color:var(--accent); text-decoration:none; }
.login-footer a:hover { text-decoration:underline; }

/* ── SESSION ALERT ── */
.session-status {
    margin-bottom:14px; padding:10px 14px;
    background:rgba(34,197,94,.08); border:1px solid rgba(34,197,94,.2);
    border-radius:10px; font-size:12px; color:var(--green);
    display:flex; align-items:center; gap:8px;
}
.auth-error {
    padding:10px 14px; margin-bottom:14px;
    background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.2);
    border-radius:10px; font-size:12px; color:var(--red);
    display:flex; align-items:center; gap:8px;
}

/* ── PANEL TABS ── */
#panel-team, #panel-client { display:none; }
#panel-team.active, #panel-client.active { display:block; }
</style>

{{-- LOGO --}}
<div class="auth-logo">
    <div class="auth-logo-mark">CIBLE CI</div>
    <div class="auth-logo-sub">Régie OOH · Plateforme</div>
    <div class="auth-logo-line"></div>
</div>

{{-- TAB SWITCH --}}
<div class="tab-switch">
    <button type="button" class="tab-sw-btn active" id="tab-team" onclick="switchTab('team')">
        👥 Équipe CIBLE CI
    </button>
    <button type="button" class="tab-sw-btn" id="tab-client" onclick="switchTab('client')">
        🏢 Espace Client
    </button>
</div>

{{-- ══ PANEL ÉQUIPE ══ --}}
<div id="panel-team" class="active">

    {{-- Session status --}}
    @if (session('status'))
        <div class="session-status">✅ {{ session('status') }}</div>
    @endif

    {{-- Erreurs --}}
    @if ($errors->any())
        <div class="auth-error">
            ❌ {{ $errors->first() }}
        </div>
    @endif

    <div class="login-card">
        <div class="login-title">Connexion Équipe</div>
        <div class="login-sub">Connectez-vous à votre espace de travail</div>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            {{-- Email --}}
            <div class="fg">
                <label for="email">E-mail</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="vous@cibleci.ci"
                    class="{{ $errors->has('email') ? 'is-error' : '' }}"
                    required autofocus autocomplete="username"
                >
                @error('email')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>

            {{-- Mot de passe --}}
            <div class="fg">
                <label for="password">Mot de passe</label>
                <div style="position:relative;">
                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="••••••••"
                        class="{{ $errors->has('password') ? 'is-error' : '' }}"
                        style="padding-right:40px;"
                        required autocomplete="current-password"
                    >
                    <button type="button"
                        onclick="togglePwd('password', this)"
                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                               background:none;border:none;cursor:pointer;color:var(--text3);
                               font-size:14px;padding:0;line-height:1;"
                        title="Afficher/masquer">👁️</button>
                </div>
                @error('password')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>

            {{-- Remember me --}}
            <div class="remember-row">
                <input type="checkbox" id="remember_me" name="remember">
                <label for="remember_me" style="cursor:pointer;">Se souvenir de moi</label>
            </div>

            <button type="submit" class="btn-login">
                Accéder à la plateforme <span style="font-size:16px;">→</span>
            </button>
        </form>

        @if (Route::has('password.request'))
            <div class="login-footer">
                Mot de passe oublié ?
                <a href="{{ route('password.request') }}">Réinitialiser</a>
            </div>
        @endif
    </div>

    {{-- Infos sécurité --}}
    <div style="margin-top:14px; padding:10px 14px;
                background:rgba(34,197,94,.05); border:1px solid rgba(34,197,94,.12);
                border-radius:10px; font-size:11px; color:var(--text3);
                display:flex; align-items:center; gap:8px; text-align:center;">
        <span style="font-size:14px;">🔒</span>
        Connexion sécurisée SSL · Session chiffrée
    </div>
</div>

{{-- ══ PANEL CLIENT ══ --}}
<div id="panel-client">
    <div class="login-card">
        <div class="login-title">Espace Client</div>
        <div class="login-sub">Accédez à vos campagnes, piges et propositions</div>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="fg">
                <label for="client-email">E-mail professionnel</label>
                <input
                    id="client-email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="contact@votresociete.com"
                    required autofocus
                >
            </div>

            <div class="fg">
                <label for="client-password">Mot de passe</label>
                <div style="position:relative;">
                    <input
                        id="client-password"
                        type="password"
                        name="password"
                        placeholder="••••••••"
                        style="padding-right:40px;"
                        required
                    >
                    <button type="button"
                        onclick="togglePwd('client-password', this)"
                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                               background:none;border:none;cursor:pointer;color:var(--text3);
                               font-size:14px;padding:0;line-height:1;">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn-login blue">
                Accéder à mon espace <span style="font-size:16px;">→</span>
            </button>
        </form>

        <div class="login-footer" style="margin-top:14px;">
            Premier accès ?
            <a href="mailto:contact@cibleci.ci" style="color:var(--blue);">Contacter CIBLE CI</a>
        </div>
    </div>

    <div style="margin-top:14px; padding:10px 14px;
                background:rgba(59,130,246,.05); border:1px solid rgba(59,130,246,.12);
                border-radius:10px; font-size:11px; color:var(--text3);
                display:flex; align-items:center; gap:8px;">
        <span style="font-size:14px;">ℹ️</span>
        Vos identifiants vous ont été envoyés par votre chargé de compte CIBLE CI.
    </div>
</div>

{{-- FOOTER GLOBAL --}}
<div style="text-align:center; font-size:11px; color:var(--text3); margin-top:20px;">
    © {{ date('Y') }} CIBLE CI · Régie OOH Côte d'Ivoire
</div>

<script>
function switchTab(tab) {
    const isTeam = tab === 'team';
    document.getElementById('panel-team').classList.toggle('active', isTeam);
    document.getElementById('panel-client').classList.toggle('active', !isTeam);
    document.getElementById('tab-team').classList.toggle('active', isTeam);
    document.getElementById('tab-client').classList.toggle('active', !isTeam);
}

function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    btn.style.opacity = isPassword ? '1' : '0.4';
}
</script>
</x-guest-layout>
