<x-admin-layout title="Modifier {{ $reservation->reference }}">

<x-slot name="topbarActions">
    <a href="{{ route('admin.reservations.show', $reservation) }}"
       class="btn btn-ghost">← Retour</a>
</x-slot>

<div style="max-width:960px;">

    @if($errors->any())
        <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);
                    border-radius:10px;padding:14px 16px;margin-bottom:16px;">
            @foreach($errors->all() as $e)
                <div style="color:var(--red);font-size:13px;margin-bottom:3px;">✕ {{ $e }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST"
          action="{{ route('admin.reservations.update', $reservation) }}"
          x-data="reservationEditForm()">
        @csrf @method('PUT')

        {{-- ── Informations générales ── --}}
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                <span class="card-title">Informations générales</span>
                <span style="font-size:12px;color:var(--text2);">
                    <span style="font-family:monospace;color:var(--accent);">
                        {{ $reservation->reference }}
                    </span>
                    — {{ $reservation->status->label() }}
                </span>
            </div>
            <div class="card-body">

                <div class="mfg">
                    <label>Client *</label>
                    <select name="client_id" required
                            style="background:var(--surface2);border:1px solid var(--border2);
                                   border-radius:8px;padding:9px 12px;color:var(--text);
                                   font-size:13px;outline:none;width:100%;">
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}"
                                    {{ old('client_id', $reservation->client_id) == $client->id
                                        ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Date de début *</label>
                        <input type="date" name="start_date"
                               x-model="startDate"
                               value="{{ old('start_date', $reservation->start_date->format('Y-m-d')) }}"
                               @change="loadPanels" required/>
                    </div>
                    <div class="mfg">
                        <label>Date de fin *</label>
                        <input type="date" name="end_date"
                               x-model="endDate"
                               value="{{ old('end_date', $reservation->end_date->format('Y-m-d')) }}"
                               @change="loadPanels" required/>
                    </div>
                </div>

                {{-- Résumé durée + montant --}}
                <div x-show="startDate && endDate && getDuration()"
                     style="font-size:12px;color:var(--text2);
                            margin-top:-8px;margin-bottom:12px;
                            padding:8px 10px;background:var(--surface2);
                            border-radius:7px;display:inline-flex;gap:16px;">
                    <span>📅 <span x-text="getDuration()"></span></span>
                    <span style="color:var(--accent);font-weight:700;">
                        Total estimé : <span x-text="formatTotal()"></span> FCFA
                    </span>
                </div>

                <div class="mfg">
                    <label>Notes</label>
                    <textarea name="notes" rows="3"
                              placeholder="Remarques, instructions particulières…"
                              style="background:var(--surface2);border:1px solid var(--border2);
                                     border-radius:8px;padding:9px 12px;color:var(--text);
                                     font-size:13px;outline:none;width:100%;resize:vertical;">{{ old('notes', $reservation->notes) }}</textarea>
                </div>

            </div>
        </div>

        {{-- ── Panneaux disponibles ── --}}
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                <span class="card-title">Panneaux disponibles</span>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <span style="font-size:12px;color:var(--text2);"
                          x-text="selectedIds.length + ' sélectionné(s) / '
                                  + filteredPanels.length + ' disponible(s)'">
                    </span>
                    {{-- Filtre commune --}}
                    <select x-model="filterCommune" @change="applyFilters"
                            style="background:var(--surface2);border:1px solid var(--border2);
                                   border-radius:7px;padding:5px 9px;color:var(--text);
                                   font-size:12px;outline:none;">
                        <option value="">Toutes communes</option>
                        @foreach($communes as $c)
                            <option value="{{ $c->name }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    {{-- Filtre format --}}
                    <select x-model="filterFormat" @change="applyFilters"
                            style="background:var(--surface2);border:1px solid var(--border2);
                                   border-radius:7px;padding:5px 9px;color:var(--text);
                                   font-size:12px;outline:none;">
                        <option value="">Tous formats</option>
                        @foreach($formats as $f)
                            <option value="{{ $f->name }}">{{ $f->name }}</option>
                        @endforeach
                    </select>

                    <select x-model="filterDimension" @change="applyFilters"
                            style="background:var(--surface2);border:1px solid var(--border2);
                                border-radius:7px;padding:5px 9px;color:var(--text);
                                font-size:12px;outline:none;">
                        <option value="">Toutes dimensions</option>
                        <template x-for="dim in availableDimensions" :key="dim">
                            <option :value="dim" x-text="dim"></option>
                        </template>
                    </select>

                </div>
            </div>

            {{-- Note pédagogique --}}
            <div style="padding:10px 17px;background:var(--accent-dim);
                        border-bottom:1px solid rgba(232,160,32,.2);
                        font-size:12px;color:var(--text2);">
                ℹ️ Seuls les panneaux <strong style="color:var(--text);">disponibles</strong>
                sur la période sélectionnée sont affichés.
                Les panneaux déjà sur cette réservation sont pré-sélectionnés.
            </div>

            {{-- Chargement --}}
            <div x-show="loading"
                 style="padding:40px;text-align:center;color:var(--text2);">
                <div style="font-size:28px;margin-bottom:8px;">⏳</div>
                Chargement des disponibilités…
            </div>

            {{-- Erreur dates --}}
            <div x-show="!loading && error"
                 style="padding:16px 17px;color:var(--red);font-size:13px;">
                ⚠️ <span x-text="error"></span>
            </div>

            {{-- Attente saisie dates --}}
            <div x-show="!loading && !error && !startDate || !endDate"
                 style="padding:40px;text-align:center;color:var(--text3);font-size:13px;">
                Saisissez les dates pour voir les panneaux disponibles.
            </div>

            {{-- Aucun résultat --}}
            <div x-show="!loading && !error && startDate && endDate && filteredPanels.length === 0"
                 style="padding:40px;text-align:center;color:var(--text3);">
                <div style="font-size:28px;margin-bottom:8px;">🔍</div>
                Aucun panneau disponible sur cette période.
            </div>

            {{-- Tableau panneaux --}}
            <div x-show="!loading && !error && filteredPanels.length > 0" class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;">
                                <input type="checkbox"
                                       @change="toggleAll($event)"
                                       :checked="filteredPanels.length > 0
                                           && filteredPanels.every(p => selectedIds.includes(p.id))"
                                       style="accent-color:var(--accent);
                                              width:15px;height:15px;cursor:pointer;">
                            </th>
                            <th>Référence</th>
                            <th>Nom</th>
                            <th>Commune</th>
                            <th>Format</th>
                            <th>Éclairé</th>
                            <th style="text-align:right;">Tarif / mois</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="panel in filteredPanels" :key="panel.id">
                            <tr @click="togglePanel(panel.id)"
                                style="cursor:pointer;transition:background .1s;"
                                :style="selectedIds.includes(panel.id)
                                    ? 'background:var(--accent-dim);'
                                    : ''">
                                <td @click.stop>
                                    <input type="checkbox"
                                           :value="panel.id"
                                           :checked="selectedIds.includes(panel.id)"
                                           @change="togglePanel(panel.id)"
                                           style="accent-color:var(--accent);
                                                  width:15px;height:15px;cursor:pointer;">
                                </td>
                                <td>
                                    <span style="font-family:monospace;font-size:12px;
                                                 background:var(--surface3);padding:2px 7px;
                                                 border-radius:5px;color:var(--accent);"
                                          x-text="panel.reference"></span>
                                </td>
                                <td style="font-weight:500;" x-text="panel.name"></td>
                                <td style="color:var(--text2);" x-text="panel.commune ?? '—'"></td>
                                <td style="color:var(--text2);" x-text="panel.format ?? '—'"></td>
                                <td>
                                    <span x-show="panel.is_lit"
                                          style="color:var(--accent);font-size:12px;">✦ Oui</span>
                                    <span x-show="!panel.is_lit"
                                          style="color:var(--text3);font-size:12px;">Non</span>
                                </td>
                                <td style="text-align:right;font-weight:600;color:var(--accent);"
                                    x-text="panel.monthly_rate
                                        ? Number(panel.monthly_rate).toLocaleString('fr-FR') + ' FCFA'
                                        : '—'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Hidden inputs pour soumission --}}
            <template x-for="id in selectedIds" :key="id">
                <input type="hidden" name="panel_ids[]" :value="id">
            </template>

            {{-- Barre récap sélection --}}
            <div x-show="selectedIds.length > 0"
                 style="padding:12px 17px;border-top:1px solid var(--border);
                        display:flex;justify-content:space-between;align-items:center;
                        background:var(--accent-dim);">
                <span style="color:var(--text2);font-size:13px;">
                    <strong style="color:var(--accent);" x-text="selectedIds.length"></strong>
                    panneau(x) sélectionné(s)
                </span>
                <span style="font-weight:700;color:var(--accent);font-size:15px;">
                    Total : <span x-text="formatTotal()"></span> FCFA
                </span>
            </div>

            @error('panel_ids')
                <div style="padding:10px 17px;color:var(--red);font-size:13px;">
                    ✕ {{ $message }}
                </div>
            @enderror
        </div>

        {{-- ── Actions ── --}}
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <span style="font-size:12px;color:var(--text3);">
                ⚠️ La modification recalcule le montant automatiquement.
            </span>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('admin.reservations.show', $reservation) }}"
                   class="btn btn-ghost">Annuler</a>
                <button type="submit" class="btn btn-primary btn-lg"
                        :disabled="selectedIds.length === 0"
                        :style="selectedIds.length === 0
                            ? 'opacity:0.5;cursor:not-allowed;' : ''">
                    ✓ Enregistrer les modifications
                </button>
            </div>
        </div>

    </form>
</div>

@push('scripts')
<script>
function reservationEditForm() {

    filterDimension: '',
    return {
        startDate:      '{{ $reservation->start_date->format('Y-m-d') }}',
        endDate:        '{{ $reservation->end_date->format('Y-m-d') }}',
        panels:         [],
        filteredPanels: [],
        // Pré-sélection : panneaux déjà sur cette réservation
        selectedIds:    @json(old('panel_ids', $selectedPanelIds)),
        loading:        false,
        error:          null,
        excludeId:      {{ $reservation->id }},
        filterCommune:  '',
        filterFormat:   '',

        init() {
            // Charger immédiatement si dates déjà présentes
            if (this.startDate && this.endDate) {
                this.loadPanels();
            }
        },

        async loadPanels() {
            if (! this.startDate || ! this.endDate) return;

            if (this.endDate <= this.startDate) {
                this.error          = 'La date de fin doit être après la date de début.';
                this.panels         = [];
                this.filteredPanels = [];
                return;
            }

            this.loading = true;
            this.error   = null;

            try {
                const params = new URLSearchParams({
                    start_date:             this.startDate,
                    end_date:               this.endDate,
                    // CRUCIAL : exclure cette réservation pour que ses panneaux
                    // apparaissent dans la liste et restent sélectionnables
                    exclude_reservation_id: this.excludeId,
                });

                const res = await fetch(
                    `/admin/reservations/available-panels?${params}`,
                    {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept':       'application/json',
                        }
                    }
                );

                if (! res.ok) throw new Error('Erreur serveur (' + res.status + ')');

                const available = await res.json();

                // ── SÉCURITÉ : ne garder en sélection QUE les panneaux
                //    présents dans la liste retournée par l'API.
                //    Empêche de soumettre un panneau occupé/maintenance.
                this.selectedIds = this.selectedIds.filter(
                    id => available.some(p => p.id === id)
                );

                this.panels = available;
                this.applyFilters();

            } catch (e) {
                this.error          = 'Impossible de charger les panneaux : ' + e.message;
                this.panels         = [];
                this.filteredPanels = [];
            } finally {
                this.loading = false;
            }
        },

        applyFilters() {
            this.filteredPanels = this.panels.filter(p => {
                const okCommune = ! this.filterCommune || p.commune === this.filterCommune;
                const okFormat  = ! this.filterFormat  || p.format  === this.filterFormat;
                const okDim = ! this.filterDimension || p.dimensions === this.filterDimension;
                return okCommune && okFormat && okDim;
                return okCommune && okFormat;
            });
        },
        
        get availableDimensions() {
            const dims = this.panels
                .map(p => p.dimensions)
                .filter(Boolean);
            return [...new Set(dims)].sort();
        },

        togglePanel(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx === -1) this.selectedIds.push(id);
            else            this.selectedIds.splice(idx, 1);
        },

        toggleAll(e) {
            const filteredIds = this.filteredPanels.map(p => p.id);
            if (e.target.checked) {
                filteredIds.forEach(id => {
                    if (! this.selectedIds.includes(id)) this.selectedIds.push(id);
                });
            } else {
                this.selectedIds = this.selectedIds.filter(
                    id => ! filteredIds.includes(id)
                );
            }
        },

        getMonths() {
            if (! this.startDate || ! this.endDate) return 1;
            const diff   = (new Date(this.endDate) - new Date(this.startDate))
                           / (1000 * 60 * 60 * 24);
            return Math.max(Math.ceil(diff / 30), 1);
        },

        getDuration() {
            if (! this.startDate || ! this.endDate) return '';
            const days = Math.round(
                (new Date(this.endDate) - new Date(this.startDate))
                / (1000 * 60 * 60 * 24)
            );
            if (days <= 0) return '';
            const months = Math.ceil(days / 30);
            return `${days} jour(s) — ${months} mois facturé(s)`;
        },

        formatTotal() {
            const months = this.getMonths();
            const total  = this.selectedIds.reduce((sum, id) => {
                const p = this.panels.find(p => p.id === id);
                return sum + (p ? (parseFloat(p.monthly_rate) || 0) * months : 0);
            }, 0);
            return Math.round(total).toLocaleString('fr-FR');
        },
    };
}
</script>
@endpush

</x-admin-layout>