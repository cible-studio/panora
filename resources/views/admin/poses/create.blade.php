<x-admin-layout>
<x-slot name="title">Nouvelle Tâche de Pose</x-slot>

<div style="max-width:700px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">➕ Nouvelle Tâche de Pose</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.pose-tasks.store') }}">
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

                <div class="section-label">Panneau & Campagne</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Panneau *</label>
                        <select name="panel_id"
                                class="{{ $errors->has('panel_id') ? 'error' : '' }}">
                            <option value="">— Sélectionner —</option>
                            @foreach($panels as $panel)
                            <option value="{{ $panel->id }}"
                                {{ old('panel_id') == $panel->id ? 'selected' : '' }}>
                                {{ $panel->reference }} — {{ $panel->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>Campagne</label>
                        <select name="campaign_id">
                            <option value="">— Aucune —</option>
                            @foreach($campaigns as $campaign)
                            <option value="{{ $campaign->id }}"
                                {{ old('campaign_id') == $campaign->id ? 'selected' : '' }}>
                                {{ $campaign->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="section-label">Équipe</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Technicien assigné</label>
                        <select name="assigned_user_id">
                            <option value="">— Non assigné —</option>
                            @foreach($techniciens as $tech)
                            <option value="{{ $tech->id }}"
                                {{ old('assigned_user_id') == $tech->id ? 'selected' : '' }}>
                                {{ $tech->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>Nom de l'équipe</label>
                        <input type="text" name="team_name"
                               value="{{ old('team_name') }}"
                               placeholder="Ex: Équipe A">
                    </div>
                </div>

                <div class="section-label">Planning</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Date planifiée *</label>
                        <input type="datetime-local" name="scheduled_at"
                               value="{{ old('scheduled_at') }}"
                               class="{{ $errors->has('scheduled_at') ? 'error' : '' }}">
                    </div>
                    <div class="mfg">
                        <label>Statut *</label>
                        <select name="status">
                            <option value="planifiee" {{ old('status') === 'planifiee' ? 'selected' : '' }} selected>Planifiée</option>
                            <option value="en_cours"  {{ old('status') === 'en_cours'  ? 'selected' : '' }}>En cours</option>
                            <option value="realisee"  {{ old('status') === 'realisee'  ? 'selected' : '' }}>Réalisée</option>
                            <option value="annulee"   {{ old('status') === 'annulee'   ? 'selected' : '' }}>Annulée</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer la tâche
                    </button>
                    <a href="{{ route('admin.pose-tasks.index') }}"
                       class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>
