<x-admin-layout>
<x-slot name="title">Nouvel utilisateur</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour aux utilisateurs
    </a>
</x-slot:topbarLeft>

<div style="max-width:720px;margin:0 auto">

    {{-- Breadcrumb --}}
    <div style="font-size:12px;color:var(--text3);margin-bottom:16px">
        <a href="{{ route('admin.users.index') }}" style="color:var(--text3);text-decoration:none">Utilisateurs</a>
        <span style="margin:0 6px">›</span>
        <span style="color:var(--text)">Nouvel utilisateur</span>
    </div>

    {{-- Intro card --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:center;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(63,127,192,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3f7fc0" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        </div>
        <div>
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Créer un compte utilisateur</div>
            <div style="font-size:12px;color:var(--text3);line-height:1.5">
                Compte commercial, media planner, comptable ou administrateur.
                Le code agent (SC/MP/CP/AD) est généré automatiquement selon le rôle si tu le laisses vide.
            </div>
        </div>
    </div>

    @if($errors->any())
    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:14px 16px;margin-bottom:16px">
        @foreach($errors->all() as $error)
            <div style="color:#ef4444;font-size:13px;display:flex;gap:6px;align-items:flex-start;margin-bottom:3px">
                <span>⚠️</span><span>{{ $error }}</span>
            </div>
        @endforeach
    </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div class="card-title">➕ Nouvel utilisateur</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf

                <div class="form-2col">
                    <div class="mfg">
                        <label>Nom complet <span style="color:var(--red)">*</span></label>
                        <input type="text" name="name"
                               value="{{ old('name') }}"
                               placeholder="Ex: Jean Kouassi"
                               class="{{ $errors->has('name') ? 'error' : '' }}"
                               required>
                    </div>
                    <div class="mfg">
                        <label>Code agent</label>
                        <input type="text" name="agent_code"
                               value="{{ old('agent_code') }}"
                               placeholder="Auto-généré (ex: SC-001, TT-001)">
                        <small style="display:block;color:var(--text3);font-size:11px;margin-top:4px;line-height:1.5">
                            Laissez vide pour génération auto :
                            💼&nbsp;Commercial&nbsp;→&nbsp;<strong>SC-XXX</strong> ·
                            🔧&nbsp;Technicien&nbsp;→&nbsp;<strong>TT-XXX</strong> ·
                            🗓️&nbsp;Media Planner&nbsp;→&nbsp;<strong>MP-XXX</strong> ·
                            🛡️&nbsp;Admin&nbsp;→&nbsp;<strong>AD-XXX</strong>
                        </small>
                    </div>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Email <span style="color:var(--red)">*</span></label>
                        <input type="email" name="email"
                               value="{{ old('email') }}"
                               placeholder="email@cibleci.com"
                               class="{{ $errors->has('email') ? 'error' : '' }}"
                               required>
                    </div>
                    <div class="mfg">
                        <label>
                            <span style="display:inline-flex;align-items:center;gap:6px">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="#22c55e"><path d="M20.5 3.5C18.2 1.2 15.2 0 12 0 5.4 0 0 5.4 0 12c0 2.1.6 4.2 1.6 6L0 24l6.2-1.6c1.7.9 3.7 1.5 5.7 1.5 6.6 0 12-5.4 12-12 0-3.2-1.2-6.2-3.4-8.4z"/></svg>
                                Numéro WhatsApp
                            </span>
                        </label>
                        <input type="tel" name="whatsapp_number"
                               value="{{ old('whatsapp_number') }}"
                               placeholder="0707070707 ou +2250707070707"
                               class="{{ $errors->has('whatsapp_number') ? 'error' : '' }}">
                        <small style="display:block;color:var(--text3);font-size:11px;margin-top:4px">
                            Optionnel — pour notifier les techniciens des assignations de pose
                        </small>
                    </div>
                </div>

                <div class="mfg">
                    <label>Rôle <span style="color:var(--red)">*</span></label>
                    <select name="role" required>
                        <option value="commercial"   {{ old('role') === 'commercial'   ? 'selected' : '' }}>💼 Commercial</option>
                        <option value="mediaplanner" {{ old('role') === 'mediaplanner' ? 'selected' : '' }}>🗓️ Media Planner</option>
                        <option value="comptable"    {{ old('role') === 'comptable'    ? 'selected' : '' }}>📊 Comptable</option>
                        <option value="admin"        {{ old('role') === 'admin'        ? 'selected' : '' }}>🛡️ Administrateur</option>
                    </select>
                    <small style="display:block;color:var(--text3);font-size:11px;margin-top:4px">
                        Pour créer un technicien terrain → <a href="{{ route('admin.pose-tasks.techniciens.create') }}" style="color:var(--accent);font-weight:700;text-decoration:none">Gestion Pose → 🔧 Techniciens</a> (pas de mot de passe requis).
                    </small>
                </div>

                <div class="section-label">Mot de passe</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label for="user_password">Mot de passe <span style="color:var(--red)">*</span></label>
                        <x-password-input id="user_password" name="password" placeholder="Min. 8 caractères" autocomplete="new-password" />
                    </div>
                    <div class="mfg">
                        <label for="user_password_confirmation">Confirmer <span style="color:var(--red)">*</span></label>
                        <x-password-input id="user_password_confirmation" name="password_confirmation" placeholder="Répéter le mot de passe" autocomplete="new-password" />
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;padding-top:18px;border-top:1px solid var(--border)">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer l'utilisateur
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>
