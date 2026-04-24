@extends('client.layout')
@section('title', 'Mon profil')
@section('page-title', 'Mon profil')

@section('content')
@php $client = auth('client')->user(); @endphp

{{-- ══ HEADER PROFIL ══ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:20px;">
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:20px;">
        <div style="display:flex;flex-direction:column;align-items:center;gap:10px;">
            <div style="width:72px;height:72px;border-radius:18px;background:linear-gradient(135deg,#e20613,#fab80b);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;color:#fff;">
                {{ strtoupper(mb_substr($client->name, 0, 1)) }}
            </div>
            <span style="font-size:10px;font-weight:700;background:rgba(226,6,19,.1);color:#e20613;padding:3px 10px;border-radius:20px;border:1px solid rgba(226,6,19,.2);">
                Client Premium
            </span>
        </div>
        <div style="flex:1;min-width:0;">
            <h1 style="font-size:20px;font-weight:700;color:var(--text);margin-bottom:4px;">{{ $client->name }}</h1>
            @if($client->ncc)
                <div style="font-family:monospace;font-size:12px;color:var(--text3);margin-bottom:8px;">NCC : {{ $client->ncc }}</div>
            @endif
            <div style="display:flex;flex-wrap:wrap;gap:14px;font-size:13px;color:var(--text2);">
                <span>{{ $client->email }}</span>
                @if($client->phone)<span>{{ $client->phone }}</span>@endif
            </div>
        </div>
        <a href="{{ route('client.password.change') }}"
           style="padding:9px 18px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;font-size:13px;color:var(--text2);text-decoration:none;transition:all .15s;white-space:nowrap;"
           onmouseover="this.style.color='var(--text)';this.style.borderColor='var(--accent)'" onmouseout="this.style.color='var(--text2)';this.style.borderColor='var(--border2)'">
            Changer le mot de passe
        </a>
    </div>
</div>

{{-- ══ STATS ══ --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2" style="margin:0 auto 10px;display:block;">
            <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
        </svg>
        <div style="font-size:16px;font-weight:700;color:#fab80b;">{{ $client->created_at->format('d/m/Y') }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Membre depuis</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="{{ $client->password_changed_at ? '#22c55e' : '#fab80b' }}" stroke-width="2" style="margin:0 auto 10px;display:block;">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        <div style="font-size:13px;font-weight:600;color:{{ $client->password_changed_at ? '#22c55e' : '#fab80b' }};">
            {{ $client->password_changed_at ? 'Sécurisé' : 'À sécuriser' }}
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Mot de passe</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3f7fc0" stroke-width="2" style="margin:0 auto 10px;display:block;">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
        </svg>
        <div style="font-size:12px;color:var(--text2);">{{ $client->last_login_at ? $client->last_login_at->diffForHumans() : 'Première connexion' }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Dernière activité</div>
    </div>
</div>

{{-- ══ INFOS GÉNÉRALES ══ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:16px;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);background:var(--surface2);">
        <div style="font-size:13px;font-weight:600;color:var(--text);">Informations générales</div>
    </div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">
        <div>
            <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:4px;">Nom / Raison sociale</div>
            <div style="font-size:14px;color:var(--text);">{{ $client->name }}</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px;">Contactez votre commercial pour modifier</div>
        </div>
        <div>
            <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:4px;">Email professionnel</div>
            <div style="font-size:14px;color:var(--text);">{{ $client->email }}</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px;">Non modifiable en ligne</div>
        </div>
        @if($client->ncc)
        <div>
            <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:4px;">NCC (Numéro Client)</div>
            <div style="font-size:14px;color:var(--text);font-family:monospace;">{{ $client->ncc }}</div>
        </div>
        @endif
    </div>
</div>

{{-- ══ COORDONNÉES (modifiable) ══ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:16px;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);background:var(--surface2);">
        <div style="font-size:13px;font-weight:600;color:var(--text);">Coordonnées</div>
    </div>
    <div style="padding:20px;">
        <form method="POST" action="{{ route('client.profil.update') }}">
            @csrf @method('PATCH')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4" style="margin-bottom:16px;">
                <div>
                    <label style="display:block;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px;">Téléphone</label>
                    <input type="tel" name="phone"
                           style="width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:9px 14px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'"
                           value="{{ old('phone', $client->phone) }}" placeholder="+225 XX XX XX XX">
                    @error('phone')<div style="color:#ef4444;font-size:11px;margin-top:3px;">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label style="display:block;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px;">Personne de contact</label>
                    <input type="text" name="contact_name"
                           style="width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:9px 14px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'"
                           value="{{ old('contact_name', $client->contact_name) }}" placeholder="Nom du référent">
                    @error('contact_name')<div style="color:#ef4444;font-size:11px;margin-top:3px;">{{ $message }}</div>@enderror
                </div>
                <div class="md:col-span-2">
                    <label style="display:block;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px;">Adresse</label>
                    <input type="text" name="address"
                           style="width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:9px 14px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'"
                           value="{{ old('address', $client->address) }}" placeholder="Adresse complète">
                    @error('address')<div style="color:#ef4444;font-size:11px;margin-top:3px;">{{ $message }}</div>@enderror
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <button type="submit"
                        style="padding:9px 20px;background:#e20613;color:#fff;font-weight:600;border-radius:9px;font-size:13px;border:none;cursor:pointer;transition:opacity .15s;"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    Enregistrer
                </button>
                <span style="font-size:11px;color:var(--text3);">Seuls les champs de contact sont modifiables</span>
            </div>
        </form>
    </div>
</div>

{{-- ══ SÉCURITÉ ══ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);background:var(--surface2);">
        <div style="font-size:13px;font-weight:600;color:var(--text);">Sécurité du compte</div>
    </div>
    <div>
        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border);gap:10px;">
            <div>
                <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:3px;">Mot de passe</div>
                @if($client->password_changed_at)
                    <div style="font-size:12px;color:#22c55e;">Modifié le {{ $client->password_changed_at->format('d/m/Y') }}</div>
                @else
                    <div style="font-size:12px;color:#fab80b;">Jamais modifié — action recommandée</div>
                @endif
            </div>
            <a href="{{ route('client.password.change') }}"
               style="padding:7px 14px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:12px;color:var(--text2);text-decoration:none;transition:all .15s;"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">
                Modifier →
            </a>
        </div>
        @if($client->last_login_at)
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);">
            <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:3px;">Dernière activité</div>
            <div style="font-size:12px;color:var(--text2);">
                Connexion le {{ $client->last_login_at->format('d/m/Y à H:i') }}
                @if($client->last_login_ip) · depuis {{ $client->last_login_ip }}@endif
            </div>
        </div>
        @endif
        <div style="padding:16px 20px;background:rgba(226,6,19,.04);">
            <div style="display:flex;gap:12px;align-items:flex-start;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2" style="flex-shrink:0;margin-top:2px;">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div style="font-size:12px;color:var(--text2);">
                    <span style="font-weight:600;color:#e20613;">Conseil de sécurité</span><br>
                    Utilisez un mot de passe unique et ne le partagez jamais.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
