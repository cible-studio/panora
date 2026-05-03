<x-admin-layout title="Modifier — {{ $campaign->name }}">

<x-slot:topbarLeft>
  <a href="{{ route('admin.campaigns.show', $campaign) }}" class="btn btn-ghost">← Retour</a>
</x-slot:topbarLeft>

<div style="max-width:720px;margin:0 auto;">

    {{-- Fil d'Ariane --}}
    <div class="breadcrumb">
        <a href="{{ route('admin.campaigns.index') }}">Campagnes</a>
        <span>›</span>
        <a href="{{ route('admin.campaigns.show', $campaign) }}">{{ $campaign->name }}</a>
        <span>›</span>
        <span>Modifier</span>
    </div>

    {{-- Messages flash --}}
    @if(session('error'))
    <div class="alert-danger">
        ✕ {{ session('error') }}
    </div>
    @endif

    @if(session('success'))
    <div class="alert-success">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- Carte principale --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Modifier la campagne</h2>
                <p class="card-subtitle">
                    Créée le {{ $campaign->created_at->format('d/m/Y') }}
                    — {{ $campaign->panels_count ?? $campaign->panels->count() }} panneau(x)
                </p>
            </div>
            <div class="badge-status status-{{ $campaign->status->value }}">
                {{ $campaign->status->label() }}
            </div>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('admin.campaigns.update', $campaign) }}" id="campaign-form">
                @csrf @method('PUT')

                {{-- ══ ALERTES RÈGLES MÉTIER ══ --}}
                @if($campaign->status->value === 'actif')
                <div class="alert-warning mb-4">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#f59e0b">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <div class="text-sm font-semibold" style="color:var(--text)">⚠️ Campagne active</div>
                            <p class="text-xs mt-1" style="color:var(--text2)">
                                La date de début <strong class="text-sm" style="color:var(--accent)">{{ $campaign->start_date->format('d/m/Y') }}</strong> 
                                ne peut pas être modifiée (déjà lancée).<br>
                                Vous pouvez prolonger ou réduire la campagne via la date de fin.
                            </p>
                        </div>
                    </div>
                </div>
                @endif

                @if($campaign->status->value === 'planifie')
                <div class="alert-info mb-4">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#3b82f6">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <div class="text-sm font-semibold" style="color:var(--text)">📅 Campagne planifiée</div>
                            <p class="text-xs mt-1" style="color:var(--text2)">
                                Les dates sont librement modifiables. Le statut sera automatiquement recalculé après la sauvegarde.
                            </p>
                        </div>
                    </div>
                </div>
                @endif

                @if(in_array($campaign->status->value, ['pose', 'termine', 'annule']))
                <div class="alert-danger mb-4">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <div class="text-sm font-semibold" style="color:var(--red)">⛔ Modification restreinte</div>
                            <p class="text-xs mt-1" style="color:var(--text2)">
                                Une campagne « {{ $campaign->status->label() }} » ne peut pas être modifiée.
                                @if($campaign->status->value === 'termine')
                                Vous pouvez la prolonger depuis la fiche campagne pour la réactiver.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                @endif

                {{-- ══ FORMULAIRE ══ --}}
                <div class="form-grid">
                    {{-- Nom --}}
                    <div class="form-group full-width">
                        <label class="form-label">NOM DE LA CAMPAGNE *</label>
                        <input type="text" name="name"
                               value="{{ old('name', $campaign->name) }}"
                               class="form-input @error('name') is-invalid @enderror"
                               {{ in_array($campaign->status->value, ['pose', 'termine', 'annule']) ? 'disabled' : '' }}>
                        @error('name')
                        <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Client --}}
                    <div class="form-group">
                        <label class="form-label">CLIENT *</label>
                        <select name="client_id"
                                class="form-select @error('client_id') is-invalid @enderror"
                                {{ in_array($campaign->status->value, ['pose', 'termine', 'annule']) ? 'disabled' : '' }}>
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}"
                                {{ old('client_id', $campaign->client_id) == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                            @endforeach
                        </select>
                        @error('client_id')
                        <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Statut (lecture seule) --}}
                    <div class="form-group">
                        <label class="form-label">STATUT ACTUEL</label>
                        <div class="status-display status-{{ $campaign->status->value }}">
                            {{ $campaign->status->label() }}
                            <span class="status-hint">
                                — Changer depuis la fiche campagne
                            </span>
                        </div>
                    </div>

                   {{-- Date début --}}
                    <div class="form-group">
                        <label class="form-label">DATE DÉBUT *</label>
                        
                        @if($campaign->status->value === 'actif')
                            {{-- Campagne active : affichage en lecture seule avec champ caché --}}
                            <input type="text"
                                value="{{ $campaign->start_date->format('d/m/Y') }}"
                                class="form-input"
                                readonly
                                disabled
                                style="background:var(--surface3); cursor:not-allowed; opacity:0.8;">
                            {{-- Champ caché qui sera envoyé --}}
                            <input type="hidden" name="start_date" 
                                value="{{ $campaign->start_date->format('Y-m-d') }}">
                            <p class="form-hint">
                                🔒 Date de début non modifiable (campagne déjà lancée)
                            </p>
                        @elseif(in_array($campaign->status->value, ['pose', 'termine', 'annule']))
                            {{-- Campagne terminée/annulée : complètement désactivé --}}
                            <input type="date" name="start_date"
                                value="{{ $campaign->start_date->format('Y-m-d') }}"
                                class="form-input"
                                disabled>
                        @else
                            {{-- Campagne planifiée : modifiable --}}
                            <input type="date" name="start_date"
                                value="{{ old('start_date', $campaign->start_date->format('Y-m-d')) }}"
                                class="form-input @error('start_date') is-invalid @enderror"
                                required>
                        @endif
                        
                        @error('start_date')
                        <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Date fin --}}
                    <div class="form-group">
                        <label class="form-label">DATE FIN *</label>
                        <input type="date" name="end_date"
                               value="{{ old('end_date', $campaign->end_date->format('Y-m-d')) }}"
                               min="{{ $campaign->start_date->copy()->addDay()->format('Y-m-d') }}"
                               class="form-input @error('end_date') is-invalid @enderror"
                               {{ in_array($campaign->status->value, ['pose', 'termine', 'annule']) ? 'disabled' : '' }}>
                        @error('end_date')
                        <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Notes --}}
                    <div class="form-group full-width">
                        <label class="form-label">NOTES INTERNES</label>
                        <textarea name="notes" rows="4"
                                  placeholder="Informations complémentaires, instructions de pose…"
                                  class="form-textarea @error('notes') is-invalid @enderror"
                                  {{ in_array($campaign->status->value, ['pose', 'termine', 'annule']) ? 'disabled' : '' }}>{{ old('notes', $campaign->notes) }}</textarea>
                        @error('notes')
                        <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- ══ ACTIONS ══ --}}
                <div class="form-actions">
                    <a href="{{ route('admin.campaigns.show', $campaign) }}" class="btn btn-ghost">
                        Annuler
                    </a>
                    <button type="submit" class="btn btn-primary"
                            {{ in_array($campaign->status->value, ['pose', 'termine', 'annule']) ? 'disabled' : '' }}>
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ============================================
   STYLES HARMONISÉS AVEC LA CHARTE PANORA
   ============================================ */

/* Breadcrumb */
.breadcrumb {
    font-size: 12px;
    color: var(--text3);
    margin-bottom: 16px;
}

.breadcrumb a {
    color: var(--text3);
    text-decoration: none;
    transition: color 0.2s;
}

.breadcrumb a:hover {
    color: var(--accent);
}

.breadcrumb span {
    margin: 0 6px;
}

/* Card */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

.card-header {
    padding: 20px 24px;
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.card-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    margin: 0 0 4px;
}

.card-subtitle {
    font-size: 12px;
    color: var(--text3);
    margin: 0;
}

.card-body {
    padding: 24px;
}

/* Badge statut */
.badge-status {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-actif {
    background: rgba(34, 197, 94, 0.12);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.status-pose {
    background: rgba(59, 130, 246, 0.12);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.status-termine {
    background: rgba(107, 114, 128, 0.12);
    color: #9ca3af;
    border: 1px solid rgba(107, 114, 128, 0.3);
}

.status-planifie {
    background: rgba(232, 160, 32, 0.12);
    color: #e8a020;
    border: 1px solid rgba(232, 160, 32, 0.3);
}

.status-annule {
    background: rgba(239, 68, 68, 0.12);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Alertes */
.alert-danger {
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.25);
    border-radius: 10px;
    padding: 14px 16px;
    color: #f87171;
    font-size: 13px;
    margin-bottom: 16px;
}

.alert-success {
    background: rgba(34, 197, 94, 0.08);
    border: 1px solid rgba(34, 197, 94, 0.25);
    border-radius: 10px;
    padding: 14px 16px;
    color: #4ade80;
    font-size: 13px;
    margin-bottom: 16px;
}

.alert-warning {
    background: rgba(245, 158, 11, 0.08);
    border: 1px solid rgba(245, 158, 11, 0.25);
    border-radius: 12px;
    padding: 14px 18px;
}

.alert-info {
    background: rgba(59, 130, 246, 0.08);
    border: 1px solid rgba(59, 130, 246, 0.25);
    border-radius: 12px;
    padding: 14px 18px;
}

/* Formulaire */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text3);
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border2);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
    color: var(--text);
    transition: all 0.2s;
    box-sizing: border-box;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 2px rgba(232, 160, 32, 0.1);
}

.form-input:disabled, .form-select:disabled, .form-textarea:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.form-input.is-invalid, .form-select.is-invalid, .form-textarea.is-invalid {
    border-color: var(--red);
}

.form-error {
    font-size: 11px;
    color: var(--red);
    margin-top: 4px;
}

.form-hint {
    font-size: 10px;
    color: var(--text3);
    margin-top: 4px;
}

/* Statut display */
.status-display {
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
}

.status-display.status-actif {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.status-display.status-pose {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #3b82f6;
}

.status-display.status-termine {
    background: rgba(107, 114, 128, 0.1);
    border: 1px solid rgba(107, 114, 128, 0.3);
    color: #9ca3af;
}

.status-display.status-planifie {
    background: rgba(232, 160, 32, 0.1);
    border: 1px solid rgba(232, 160, 32, 0.3);
    color: #e8a020;
}

.status-display.status-annule {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
}

.status-hint {
    font-size: 11px;
    font-weight: 400;
    margin-left: 8px;
    color: var(--text3);
}

/* Actions */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

.btn {
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: inline-flex;
    align-items: center;
}

.btn-primary {
    background: var(--accent);
    color: #000;
}

.btn-primary:hover:not(:disabled) {
    background: #f0b33a;
    transform: translateY(-1px);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-ghost {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text2);
}

.btn-ghost:hover {
    border-color: var(--accent);
    color: var(--accent);
}

/* Utilitaires */
.w-4 { width: 16px; }
.h-4 { height: 16px; }
.w-5 { width: 20px; }
.h-5 { height: 20px; }
.mr-1 { margin-right: 4px; }
.mt-0\.5 { margin-top: 2px; }
.mt-1 { margin-top: 4px; }
.mb-4 { margin-bottom: 16px; }
.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.flex-shrink-0 { flex-shrink: 0; }
.gap-3 { gap: 12px; }

@media (max-width: 640px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .form-actions {
        flex-direction: column-reverse;
    }
    
    .form-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 🔒 Bloquer la date de début si campagne active
    @if($campaign->status->value === 'actif')
    const startDateInput = document.querySelector('[name="start_date"]');
    if (startDateInput) {
        startDateInput.disabled = true;
        startDateInput.classList.add('opacity-50', 'cursor-not-allowed');
        startDateInput.title = "La date de début d'une campagne active ne peut pas être modifiée";
    }
    @endif

    // ⚠️ Avertir si la nouvelle date de fin est avant aujourd'hui
    const endDateInput = document.querySelector('[name="end_date"]');
    const form = document.getElementById('campaign-form');
    
    if (endDateInput && form) {
        form.addEventListener('submit', function(e) {
            const endDate = new Date(endDateInput.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (endDate < today) {
                const confirmMsg = "⚠️ Attention : La date de fin est antérieure à aujourd'hui.\n\n"
                                 + "La campagne sera automatiquement marquée comme terminée.\n\n"
                                 + "Voulez-vous continuer ?";
                if (!confirm(confirmMsg)) {
                    e.preventDefault();
                }
            }
        });
    }

    // 🎯 Mise à jour du min de la date de fin quand la date de début change
    const startDateInputActive = document.querySelector('[name="start_date"]:not([disabled])');
    if (startDateInputActive && endDateInput) {
        startDateInputActive.addEventListener('change', function() {
            const startDate = this.value;
            if (startDate) {
                const nextDay = new Date(startDate);
                nextDay.setDate(nextDay.getDate() + 1);
                const minDate = nextDay.toISOString().split('T')[0];
                endDateInput.min = minDate;
                
                if (endDateInput.value && endDateInput.value <= startDate) {
                    endDateInput.value = minDate;
                }
            }
        });
    }
});
</script>
@endpush

</x-admin-layout>