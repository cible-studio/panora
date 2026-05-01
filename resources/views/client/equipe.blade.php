@extends('client.layout')
@section('title', 'Mon équipe')
@section('page-title', 'Mon équipe')

@section('content')

{{-- ══ RETOUR ══ --}}
<a href="{{ route('client.dashboard') }}"
   style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text3);text-decoration:none;padding:6px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface);transition:all .15s;margin-bottom:18px;"
   onmouseover="this.style.color='var(--text)';this.style.borderColor='var(--border2)';this.style.background='var(--surface2)'"
   onmouseout="this.style.color='var(--text3)';this.style.borderColor='var(--border)';this.style.background='var(--surface)'">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Tableau de bord
</a>

@php $canManage = !session('client_user_role') || session('client_user_role') === 'owner'; @endphp

{{-- ══ ALERTES ══ --}}
@if(session('success'))
<div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#22c55e;display:flex;align-items:center;gap:8px;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    {{ session('success') }}
</div>
@endif
@if($errors->has('delete') || $errors->has('role'))
<div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#ef4444;">
    {{ $errors->first('delete') ?: $errors->first('role') }}
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ══ LISTE DES UTILISATEURS ══ --}}
    <div class="lg:col-span-2">
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <div>
                    <div style="font-size:15px;font-weight:700;color:var(--text);">Utilisateurs du compte</div>
                    <div style="font-size:11px;color:var(--text3);margin-top:2px;">{{ $users->count() }} utilisateur(s)</div>
                </div>
            </div>

            @if($users->isEmpty())
            <div style="padding:60px;text-align:center;color:var(--text3);">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="display:block;margin:0 auto 14px;opacity:.2;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <div style="font-size:13px;font-weight:600;color:var(--text2);margin-bottom:4px;">Aucun utilisateur secondaire</div>
                <div style="font-size:12px;">Ajoutez des collaborateurs pour qu'ils accèdent à votre espace.</div>
            </div>
            @else
            <div>
                @foreach($users as $u)
                @php
                    $initials = collect(explode(' ', $u->name))->map(fn($w) => strtoupper($w[0] ?? ''))->filter()->take(2)->implode('');
                    $isMe     = session('client_user_id') == $u->id;
                @endphp
                <div style="padding:16px 20px;{{ !$loop->last ? 'border-bottom:1px solid var(--border)' : '' }};display:flex;align-items:center;gap:14px;flex-wrap:wrap;transition:background .1s;"
                     onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">

                    {{-- Avatar --}}
                    <div style="width:40px;height:40px;border-radius:10px;background:{{ $u->is_active ? 'linear-gradient(135deg,#e20613,#fab80b)' : 'var(--surface2)' }};display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:{{ $u->is_active ? '#fff' : 'var(--text3)' }};flex-shrink:0;">
                        {{ $initials }}
                    </div>

                    {{-- Infos --}}
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span style="font-size:13px;font-weight:600;color:{{ $u->is_active ? 'var(--text)' : 'var(--text3)' }};">{{ $u->name }}</span>
                            @if($isMe)
                            <span style="font-size:9px;font-weight:700;padding:2px 6px;border-radius:5px;background:rgba(226,6,19,.1);color:#e20613;">Vous</span>
                            @endif
                            <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;background:{{ $u->role === 'owner' ? 'rgba(250,184,11,.1)' : 'rgba(148,163,184,.1)' }};color:{{ $u->role === 'owner' ? '#fab80b' : '#94a3b8' }};">
                                {{ $u->role === 'owner' ? 'Propriétaire' : 'Membre' }}
                            </span>
                            @if(!$u->is_active)
                            <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;background:rgba(239,68,68,.1);color:#ef4444;">Désactivé</span>
                            @endif
                        </div>
                        <div style="font-size:11px;color:var(--text3);margin-top:2px;">{{ $u->email }}</div>
                        @if($u->last_login_at)
                        <div style="font-size:10px;color:var(--text3);margin-top:1px;">Dernière connexion : {{ $u->last_login_at->diffForHumans() }}</div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    @if($canManage && !$isMe)
                    <div style="display:flex;gap:6px;flex-shrink:0;">
                        <button onclick="openEditModal({{ $u->id }}, '{{ addslashes($u->name) }}', '{{ $u->email }}', '{{ $u->role }}', {{ $u->is_active ? 'true' : 'false' }})"
                                style="padding:6px 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:11px;font-weight:600;color:var(--text2);cursor:pointer;transition:all .15s;"
                                onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">
                            Modifier
                        </button>
                        <form method="POST" action="{{ route('client.equipe.destroy', $u) }}" onsubmit="return confirm('Supprimer {{ addslashes($u->name) }} ?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    style="padding:6px 12px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;font-size:11px;font-weight:600;color:#ef4444;cursor:pointer;transition:all .15s;"
                                    onmouseover="this.style.background='rgba(239,68,68,.15)'" onmouseout="this.style.background='rgba(239,68,68,.08)'">
                                Supprimer
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- ══ FORMULAIRE AJOUT ══ --}}
    <div class="lg:col-span-1">
        @if($canManage)
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;">
            <h3 style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:16px;">Ajouter un utilisateur</h3>

            @if($errors->any() && !$errors->has('delete') && !$errors->has('role'))
            <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#ef4444;">
                @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('client.equipe.store') }}">
                @csrf
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;">Nom complet *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="100"
                           style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;">Email *</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;">Rôle *</label>
                    <select name="role"
                            style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:13px;color:var(--text);outline:none;cursor:pointer;">
                        <option value="member" {{ old('role') === 'member' ? 'selected' : '' }}>Membre</option>
                        <option value="owner"  {{ old('role') === 'owner'  ? 'selected' : '' }}>Propriétaire</option>
                    </select>
                    <div style="font-size:10px;color:var(--text3);margin-top:4px;">Le propriétaire peut gérer l'équipe.</div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;">Mot de passe *</label>
                    <input type="password" name="password" required minlength="8"
                           style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'"
                           placeholder="Min. 8 caractères">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;">Confirmer le mot de passe *</label>
                    <input type="password" name="password_confirmation" required
                           style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
                </div>
                <button type="submit"
                        style="width:100%;padding:10px;background:#e20613;color:#fff;font-weight:700;border-radius:9px;font-size:13px;border:none;cursor:pointer;transition:opacity .15s;"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    Ajouter l'utilisateur
                </button>
            </form>
        </div>
        @else
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;text-align:center;color:var(--text3);">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 10px;opacity:.3;"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <div style="font-size:12px;">Seul le propriétaire peut gérer les utilisateurs.</div>
        </div>
        @endif
    </div>
</div>

{{-- ══ MODAL MODIFICATION ══ --}}
<div id="modal-edit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:16px;"
     onclick="if(event.target===this)closeEditModal()">
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:14px;max-width:420px;width:100%;padding:28px;position:relative;"
         onclick="event.stopPropagation()">
        <button onclick="closeEditModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;color:var(--text3);cursor:pointer;font-size:18px;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text3)'">✕</button>
        <h3 style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:18px;">Modifier l'utilisateur</h3>

        <form id="form-edit" method="POST" action="">
            @csrf @method('PATCH')
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;">Nom complet</label>
                <input type="text" id="edit-name" name="name" required maxlength="100"
                       style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;">Rôle</label>
                <select id="edit-role" name="role"
                        style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:13px;color:var(--text);outline:none;cursor:pointer;">
                    <option value="member">Membre</option>
                    <option value="owner">Propriétaire</option>
                </select>
            </div>
            <div style="margin-bottom:18px;display:flex;align-items:center;gap:10px;">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" id="edit-active" name="is_active" value="1"
                       style="width:16px;height:16px;cursor:pointer;accent-color:#e20613;">
                <label for="edit-active" style="font-size:13px;color:var(--text);cursor:pointer;">Compte actif</label>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="closeEditModal()"
                        style="padding:9px 18px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:13px;color:var(--text2);cursor:pointer;">Annuler</button>
                <button type="submit"
                        style="padding:9px 20px;background:#e20613;color:#fff;font-weight:700;border-radius:8px;font-size:13px;border:none;cursor:pointer;transition:opacity .15s;"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, email, role, isActive) {
    document.getElementById('form-edit').action = '/client/equipe/' + id;
    document.getElementById('edit-name').value  = name;
    document.getElementById('edit-role').value  = role;
    document.getElementById('edit-active').checked = isActive;
    const m = document.getElementById('modal-edit');
    m.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeEditModal() {
    document.getElementById('modal-edit').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeEditModal(); });
</script>

@endsection
