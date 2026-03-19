<x-admin-layout>
<x-slot name="title">Modifier — {{ $user->name }}</x-slot>

<div style="max-width:600px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $user->name }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="form-2col">
                    <div class="mfg">
                        <label>Nom complet *</label>
                        <input type="text" name="name"
                               value="{{ old('name', $user->name) }}"
                               class="{{ $errors->has('name') ? 'error' : '' }}">
                    </div>
                    <div class="mfg">
                        <label>Code agent</label>
                        <input type="text" name="agent_code"
                               value="{{ old('agent_code', $user->agent_code) }}">
                    </div>
                </div>

                <div class="mfg">
                    <label>Email *</label>
                    <input type="email" name="email"
                           value="{{ old('email', $user->email) }}"
                           class="{{ $errors->has('email') ? 'error' : '' }}">
                </div>

                <div class="mfg">
                    <label>Rôle *</label>
                    <select name="role">
                        <option value="commercial"   {{ old('role', $user->role->value) === 'commercial'   ? 'selected' : '' }}>💼 Commercial</option>
                        <option value="mediaplanner" {{ old('role', $user->role->value) === 'mediaplanner' ? 'selected' : '' }}>🗓️ Media Planner</option>
                        <option value="technique"    {{ old('role', $user->role->value) === 'technique'    ? 'selected' : '' }}>🔧 Technicien</option>
                        <option value="admin"        {{ old('role', $user->role->value) === 'admin'        ? 'selected' : '' }}>🛡️ Administrateur</option>
                    </select>
                </div>

                <div class="section-label">Nouveau mot de passe (optionnel)</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Mot de passe</label>
                        <input type="password" name="password"
                               placeholder="Laisser vide = inchangé">
                    </div>
                    <div class="mfg">
                        <label>Confirmer</label>
                        <input type="password" name="password_confirmation">
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
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
