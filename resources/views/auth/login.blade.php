<x-guest-layout>
<x-slot name="title">Connexion</x-slot>

<style>
.auth-logo { text-align:center; margin-bottom:28px; }
.auth-logo-sub { font-size:11px; color:var(--text2); letter-spacing:2px; text-transform:uppercase; margin-top:5px; }
.auth-logo-line { width:40px; height:2px; background:linear-gradient(90deg,var(--accent),transparent); margin:10px auto 0; border-radius:2px; }
.login-card { background:var(--surface); border:1px solid var(--border2); border-radius:16px; padding:28px; box-shadow:0 24px 60px rgba(0,0,0,.5), 0 0 0 1px rgba(255,255,255,.02); }
.login-title { font-family:var(--font-display); font-weight:700; font-size:18px; margin-bottom:3px; }
.login-sub { font-size:12px; color:var(--text2); margin-bottom:24px; }
.fg { display:flex; flex-direction:column; gap:5px; margin-bottom:13px; }
.fg label { font-size:11px; font-weight:600; color:var(--text2); text-transform:uppercase; letter-spacing:.6px; }
.fg input { background:var(--surface2); border:1px solid var(--border2); border-radius:8px; padding:10px 12px; color:var(--text); font-family:var(--font-body); font-size:13px; outline:none; transition:border-color .15s; width:100%; }
.fg input:focus { border-color:var(--accent); }
.fg input::placeholder { color:var(--text3); }
.fg .field-error { font-size:11px; color:var(--red); margin-top:3px; }
.fg input.is-error { border-color:var(--red); }
.remember-row { display:flex; align-items:center; gap:8px; margin-bottom:14px; font-size:12px; color:var(--text2); }
.remember-row input[type="checkbox"] { width:15px; height:15px; accent-color:var(--accent); cursor:pointer; }
.btn-login { width:100%; padding:12px; border-radius:9px; background:linear-gradient(135deg,var(--accent),#c00010); color:#fff; font-weight:700; font-size:14px; border:none; cursor:pointer; font-family:var(--font-display); transition:all .2s; margin-top:2px; letter-spacing:.2px; display:flex; align-items:center; justify-content:center; gap:8px; }
.btn-login:hover { opacity:.9; box-shadow:0 4px 20px rgba(226,6,19,.3); transform:translateY(-1px); }
.btn-login:active { transform:translateY(0); }
.login-footer { text-align:center; font-size:11px; color:var(--text3); margin-top:14px; }
.login-footer a { color:var(--accent); text-decoration:none; }
.login-footer a:hover { text-decoration:underline; }
.session-status { margin-bottom:14px; padding:10px 14px; background:rgba(34,197,94,.08); border:1px solid rgba(34,197,94,.2); border-radius:10px; font-size:12px; color:var(--green); display:flex; align-items:center; gap:8px; }
.auth-error { padding:10px 14px; margin-bottom:14px; background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.2); border-radius:10px; font-size:12px; color:var(--red); display:flex; align-items:center; gap:8px; }
</style>

{{-- LOGO --}}
<div class="auth-logo mt-4 flex flex-col items-center">
    <img class="w-40" src="{{ asset('images/logob.png') }}" alt="Logo">
    <div class="auth-logo-sub mt-3">Régie OOH · Plateforme</div>
    <div class="auth-logo-line"></div>
</div>

{{-- Session status --}}
@if (session('status'))
    <div class="session-status">✅ {{ session('status') }}</div>
@endif

{{-- Erreurs --}}
@if ($errors->any())
    <div class="auth-error">❌ {{ $errors->first() }}</div>
@endif

<div class="login-card">
    <div class="login-title">Connexion Équipe</div>
    <div class="login-sub">Connectez-vous à votre espace de travail</div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="fg">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email"
                value="{{ old('email') }}"
                placeholder="vous@cibleci.ci"
                class="{{ $errors->has('email') ? 'is-error' : '' }}"
                required autofocus autocomplete="username">
            @error('email')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="fg">
            <label for="password">Mot de passe</label>
            <div style="position:relative;">
                <input id="password" type="password" name="password"
                    placeholder="••••••••"
                    class="{{ $errors->has('password') ? 'is-error' : '' }}"
                    style="padding-right:40px;"
                    required autocomplete="current-password">
                <button type="button" onclick="togglePwd('password', this)"
                    style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text3);font-size:14px;padding:0;line-height:1;"
                    title="Afficher/masquer">👁️</button>
            </div>
            @error('password')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

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

<div style="margin-top:14px; padding:10px 14px; background:rgba(34,197,94,.05); border:1px solid rgba(34,197,94,.12); border-radius:10px; font-size:11px; color:var(--text3); display:flex; align-items:center; gap:8px; text-align:center;">
    <span style="font-size:14px;">🔒</span>
    Connexion sécurisée SSL · Session chiffrée
</div>

<div style="text-align:center; font-size:11px; color:var(--text3); margin-top:20px;">
    © {{ date('Y') }} CIBLE CI · Régie OOH Côte d'Ivoire
</div>

<script>
function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    btn.style.opacity = isPassword ? '1' : '0.4';
}
</script>
</x-guest-layout>
