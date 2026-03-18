<x-admin-layout>
<x-slot name="title">Nouvel Utilisateur</x-slot>

<div style="max-width:600px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">➕ Nouvel Utilisateur</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf

                @if($errors->any())
                <div style="background:rgba(239,68,68,.1); border:1px solid var(--red);
                            border-radius:8px; padding:12px; margin-bottom:16px;">
                    <div style="color:var(--red); font-weight:600; margin-bottom:8px;">❌ Erreurs :</div>
                    <ul style="color:var(--red); padding-left:16px;">
                        @foreach($errors->all() as $error)
                            <li style="font-size:13px;">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="form-2col">
                    <div class="mfg">
                        <label>Nom complet *</label>
                        <input type="text" name="name"
                               value="{{ old('name') }}"
                               placeholder="Ex: Jean Kouassi"
                               class="{{ $errors->has('name') ? 'error' : '' }}">
                    </div>
                    <div class="mfg">
                        <label>Code agent</label>
                        <input type="text" name="agent_code"
                               value="{{ old('agent_code') }}"
                               placeholder="Ex: AGT-001">
                    </div>
                </div>

                <div class="mfg">
                    <label>Email *</label>
                    <input type="email" name="email"
                           value="{{ old('email') }}"
                           placeholder="email@cibleci.com"
                           class="{{ $errors->has('email') ? 'error' : '' }}">
                </div>

                <div class="mfg">
                    <label>Rôle *</label>
                    <select name="role">
                        <option value="commercial"   {{ old('role') === 'commercial'   ? 'selected' : '' }}>💼 Commercial</option>
                        <option value="mediaplanner" {{ old('role') === 'mediaplanner' ? 'selected' : '' }}>🗓️ Media Planner</option>
                        <option value="technique"    {{ old('role') === 'technique'    ? 'selected' : '' }}>🔧 Technicien</option>
                        <option value="admin"        {{ old('role') === 'admin'        ? 'selected' : '' }}>🛡️ Administrateur</option>
                    </select>
                </div>

                <div class="section-label">Mot de passe</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Mot de passe *</label>
                        <input type="password" name="password"
                               placeholder="Min. 8 caractères"
                               class="{{ $errors->has('password') ? 'error' : '' }}">
                    </div>
                    <div class="mfg">
                        <label>Confirmer *</label>
                        <input type="password" name="password_confirmation"
                               placeholder="Répéter le mot de passe">
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer l'utilisateur
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>
