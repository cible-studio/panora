<x-admin-layout>
<x-slot name="title">Modifier Tâche de Pose</x-slot>

<div style="max-width:700px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $poseTask->panel->reference }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.pose-tasks.update', $poseTask) }}">
                @csrf
                @method('PUT')

                <div class="form-2col">
                    <div class="mfg">
                        <label>Panneau *</label>
                        <select name="panel_id">
                            @foreach($panels as $panel)
                            <option value="{{ $panel->id }}"
                                {{ old('panel_id', $poseTask->panel_id) == $panel->id ? 'selected' : '' }}>
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
                                {{ old('campaign_id', $poseTask->campaign_id) == $campaign->id ? 'selected' : '' }}>
                                {{ $campaign->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Technicien</label>
                        <select name="assigned_user_id">
                            <option value="">— Non assigné —</option>
                            @foreach($techniciens as $tech)
                            <option value="{{ $tech->id }}"
                                {{ old('assigned_user_id', $poseTask->assigned_user_id) == $tech->id ? 'selected' : '' }}>
                                {{ $tech->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>Nom de l'équipe</label>
                        <input type="text" name="team_name"
                               value="{{ old('team_name', $poseTask->team_name ?? '') }}">
                    </div>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Date planifiée *</label>
                        <input type="datetime-local" name="scheduled_at"
                               value="{{ old('scheduled_at', $poseTask->scheduled_at?->format('Y-m-d\TH:i') ?? '') }}">
                    </div>
                    <div class="mfg">
                        <label>Statut *</label>
                        <select name="status">
                            <option value="planifiee" {{ old('status', $poseTask->status) === 'planifiee' ? 'selected' : '' }}>Planifiée</option>
                            <option value="en_cours"  {{ old('status', $poseTask->status) === 'en_cours'  ? 'selected' : '' }}>En cours</option>
                            <option value="realisee"  {{ old('status', $poseTask->status) === 'realisee'  ? 'selected' : '' }}>Réalisée</option>
                            <option value="annulee"   {{ old('status', $poseTask->status) === 'annulee'   ? 'selected' : '' }}>Annulée</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
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
