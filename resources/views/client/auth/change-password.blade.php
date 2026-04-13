@extends('client.layout')
@section('title', 'Sécurité du compte')
@section('page-title', 'Sécurité')

@section('content')
<div style="max-width:440px;margin:0 auto;">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:36px;text-align:center;">

        <div style="width:64px;height:64px;border-radius:16px;background:rgba(226,6,19,.1);border:1px solid rgba(226,6,19,.2);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>

        <h1 style="font-size:20px;font-weight:700;color:var(--text);margin-bottom:6px;">
            @if(auth('client')->user()?->must_change_password) Définir votre mot de passe
            @else Changer mon mot de passe @endif
        </h1>
        <p style="font-size:13px;color:var(--text3);margin-bottom:24px;line-height:1.6;">
            @if(auth('client')->user()?->must_change_password)
                Bienvenue ! Pour sécuriser votre compte, définissez un mot de passe personnel.
            @else
                Choisissez un mot de passe robuste et unique pour sécuriser votre compte.
            @endif
        </p>

        @if(session('warning'))
        <div style="background:rgba(250,184,11,.08);border:1px solid rgba(250,184,11,.2);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#fab80b;text-align:left;">
            {{ session('warning') }}
        </div>
        @endif

        <form method="POST" action="{{ route('client.password.update') }}" style="text-align:left;">
            @csrf

            @if(!auth('client')->user()?->must_change_password)
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px;">Mot de passe actuel</label>
                <input type="password" name="current_password"
                       style="width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:10px 14px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
                @error('current_password')<div style="color:#ef4444;font-size:11px;margin-top:3px;">{{ $message }}</div>@enderror
            </div>
            @endif

            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px;">Nouveau mot de passe</label>
                <input type="password" name="password"
                       style="width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:10px 14px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
                @error('password')<div style="color:#ef4444;font-size:11px;margin-top:3px;">{{ $message }}</div>@enderror
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px;">Confirmer le mot de passe</label>
                <input type="password" name="password_confirmation"
                       style="width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:10px 14px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
            </div>

            {{-- Règles --}}
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:20px;">
                <div style="font-size:10px;font-weight:700;color:#e20613;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">Règles de sécurité</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                    @foreach(['Minimum 8 caractères','1 lettre majuscule','1 lettre minuscule','1 chiffre'] as $rule)
                    <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--text2);">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        {{ $rule }}
                    </div>
                    @endforeach
                </div>
            </div>

            <div style="display:flex;gap:10px;">
                @if(!auth('client')->user()?->must_change_password)
                <a href="{{ route('client.profil') }}"
                   style="flex:1;text-align:center;padding:10px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;font-size:13px;color:var(--text2);text-decoration:none;transition:all .15s;"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">
                    Annuler
                </a>
                @endif
                <button type="submit"
                        style="flex:1;padding:10px;background:#e20613;color:#fff;font-weight:600;border-radius:9px;font-size:13px;border:none;cursor:pointer;transition:opacity .15s;"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    @if(auth('client')->user()?->must_change_password) Enregistrer et continuer
                    @else Mettre à jour @endif
                </button>
            </div>
        </form>

        @if(!auth('client')->user()?->must_change_password)
        <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
            <a href="{{ route('client.dashboard') }}"
               style="font-size:12px;color:var(--text3);text-decoration:none;transition:color .15s;"
               onmouseover="this.style.color='#e20613'" onmouseout="this.style.color='var(--text3)'">
                ← Retour au tableau de bord
            </a>
        </div>
        @endif
    </div>
</div>
@endsection
