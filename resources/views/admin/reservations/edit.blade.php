<x-admin-layout title="Modifier {{ $reservation->reference }}">
    <x-slot:topbarActions>
        <a href="{{ route('admin.reservations.show', $reservation) }}" class="btn btn-ghost">← Retour</a>
    </x-slot:topbarActions>

    {{-- URL AJAX injectée côté serveur --}}
    <script>
        window.AVAILABLE_PANELS_URL = '{{ route("admin.reservations.available-panels") }}';
    </script>

    <div id="edit-reservation-app" x-data="reservationEdit()" x-init="init()">
        <form method="POST" action="{{ route('admin.reservations.update', $reservation) }}" id="edit-form">
            @csrf @method('PUT')
            <input type="hidden" name="last_updated_at" value="{{ $reservation->updated_at->timestamp }}">

            {{-- ══ INFOS GÉNÉRALES ══ --}}
            <div class="card mb-4">
                <div class="card-header">
                    <span class="card-title">Informations générales</span>
                    <span class="badge badge-info">{{ $reservation->reference }} — {{ $reservation->status->label() }}</span>
                </div>
                <div class="card-body">
                    @if($errors->any())
                    <div class="alert alert-danger mb-3">
                        @foreach($errors->all() as $e)
                        <div>✕ {{ $e }}</div>
                        @endforeach
                    </div>
                    @endif

                    <div class="form-group">
                        <label>Client *</label>
                        <select name="client_id" required class="form-select">
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id', $reservation->client_id) == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Date de début *</label>
                            <input type="date" name="start_date" id="start-date"
                                   value="{{ old('start_date', $reservation->start_date->format('Y-m-d')) }}"
                                   class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label>Date de fin *</label>
                            <input type="date" name="end_date" id="end-date"
                                   value="{{ old('end_date', $reservation->end_date->format('Y-m-d')) }}"
                                   class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="3" class="form-textarea"
                                  placeholder="Remarques, instructions particulières…">{{ old('notes', $reservation->notes) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- ══ PANNEAUX DISPONIBLES AVEC PAGINATION ══ --}}
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Panneaux disponibles</span>
                    <div class="header-actions">
                        <span class="stats-badge"
                              x-text="selectedCount + ' sélectionné(s) / ' + totalDisplayed + ' disponible(s)'">
                        </span>
                        <button type="button" class="btn-reset-filters"
                                @click="resetFilters"
                                x-show="hasActiveFilters">↺ Réinitialiser</button>
                    </div>
                </div>

                {{-- Filtres avancés --}}
                <div class="filters-section">
                    <div class="search-wrapper">
                        <span class="search-icon">🔍</span>
                        <input type="text" x-model="searchTerm" @input="applyFilters"
                               placeholder="Rechercher par référence, nom, commune, zone..."
                               class="search-input">
                    </div>

                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label">📍 Commune</label>
                            <select x-model="filters.commune_id" @change="loadPanels" class="filter-select">
                                <option value="">Toutes</option>
                                @foreach($communes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">🗺️ Zone</label>
                            <select x-model="filters.zone_id" @change="loadPanels" class="filter-select">
                                <option value="">Toutes</option>
                                @foreach($zones as $z)
                                <option value="{{ $z->id }}">{{ $z->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">📏 Format</label>
                            <select x-model="filters.format_id" @change="loadPanels" class="filter-select">
                                <option value="">Tous</option>
                                @foreach($formats as $f)
                                <option value="{{ $f->id }}">{{ $f->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">📐 Dimensions</label>
                            <select x-model="filters.dimensions" @change="loadPanels" class="filter-select">
                                <option value="">Toutes</option>
                                <template x-for="dim in dimensionsList" :key="dim">
                                    <option :value="dim" x-text="dim"></option>
                                </template>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">💡 Éclairage</label>
                            <select x-model="filters.is_lit" @change="loadPanels" class="filter-select">
                                <option value="">Tous</option>
                                <option value="1">💡 Éclairé</option>
                                <option value="0">🌙 Non éclairé</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Loader --}}
                <div x-show="loading" class="loader-container">
                    <div class="loader-spinner"></div>
                    <span>Chargement des panneaux disponibles...</span>
                </div>

                {{-- Erreur --}}
                <div x-show="error" class="alert alert-danger" x-text="error" style="margin:16px;"></div>

                {{-- Dates manquantes --}}
                <div x-show="!loading && !error && (!startDate || !endDate)" class="empty-state">
                    <div>📅</div>
                    <div>Saisissez les dates pour voir les panneaux disponibles</div>
                </div>

                {{-- Aucun résultat --}}
                <div x-show="!loading && !error && startDate && endDate && filteredPanels.length === 0" class="empty-state">
                    <div>🔍</div>
                    <div>Aucun panneau disponible sur cette période</div>
                    <div class="empty-sub">Modifiez les dates ou les filtres</div>
                </div>

                {{-- TABLEAU AVEC PAGINATION --}}
                <div x-show="!loading && !error && filteredPanels.length > 0" class="table-container">
                    <table class="data-table">
                        <thead>
                                <th style="width:40px;">
                                    <input type="checkbox" @change="toggleAll"
                                           :checked="allSelected" class="checkbox">
                                </th>
                                <th>Référence</th>
                                <th>Nom</th>
                                <th>Commune / Zone</th>
                                <th>Format / Dim.</th>
                                <th>💡</th>
                                <th>Disponibilité</th>
                                <th class="text-right">Tarif / mois</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="panel in paginatedPanels" :key="panel.id">
                                <tr @click="togglePanel(panel.id)"
                                    :class="{ 'selected': selectedIds.includes(panel.id) }">
                                    <td @click.stop>
                                        <input type="checkbox"
                                               :checked="selectedIds.includes(panel.id)"
                                               @change="togglePanel(panel.id)"
                                               class="checkbox">
                                    </td>
                                    <td><span class="reference" x-text="panel.reference"></span></td>
                                    <td class="font-medium" x-text="panel.name"></td>
                                    <td>
                                        <div x-text="panel.commune || '—'"></div>
                                        <small class="text-muted" x-text="panel.zone ? 'Zone: ' + panel.zone : ''"></small>
                                    </td>
                                    <td>
                                        <div x-text="panel.format || '—'"></div>
                                        <small class="text-muted" x-text="panel.dimensions || ''"></small>
                                    </td>
                                    <td>
                                        <span x-show="panel.is_lit" class="lit-badge">💡 Oui</span>
                                        <span x-show="!panel.is_lit" class="non-lit-badge">Non</span>
                                    </td>
                                    <td>
                                        <template x-if="panel.available">
                                            <span class="status-badge status-libre">✅ Libre</span>
                                        </template>
                                        <template x-if="!panel.available && panel.release_date">
                                            <div>
                                                <span class="status-badge status-occupe">🔒 Occupé</span>
                                                <div class="release-info" x-html="'Libre le ' + panel.release_date"></div>
                                            </div>
                                        </template>
                                        <template x-if="!panel.available && !panel.release_date">
                                            <span class="status-badge status-occupe">🔒 Occupé</span>
                                        </template>
                                    </td>
                                    <td class="text-right price" x-text="formatPrice(panel.monthly_rate)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    {{-- PAGINATION --}}
                    <div class="pagination-wrapper" x-show="totalPages > 1">
                        <button @click="prevPage" type="button" :disabled="currentPage === 1" class="page-btn">← Précédent</button>
                        
                        {{-- Affichage des pages --}}
                        <div class="page-numbers" x-show="totalPages > 1">
                            <template x-for="page in getPageNumbers()" :key="page">
                                <button type="button" @click="goToPage(page)" 
                                        :class="{'active-page': currentPage === page, 'page-number': true}"
                                        x-text="page"></button>
                            </template>
                        </div>
                        
                        <span class="page-info">
                            Page <strong x-text="currentPage"></strong> / <strong x-text="totalPages"></strong>
                            <span class="page-total" x-text="'(' + totalDisplayed + ' panneaux)'"></span>
                        </span>
                        
                        <button type="button" @click="nextPage" :disabled="currentPage === totalPages" class="page-btn">Suivant →</button>
                    </div>

                    {{-- Sélecteur d'items par page --}}
                    <div class="per-page-selector" x-show="totalPages > 1">
                        <label>Afficher :</label>
                        <select x-model="perPage" @change="currentPage = 1">
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                        <span>par page</span>
                    </div>
                </div>

                {{-- Hidden inputs pour soumission --}}
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="panel_ids[]" :value="id">
                </template>

                {{-- Barre récap --}}
                <div x-show="selectedIds.length > 0" class="selection-bar">
                    <div class="selection-info">
                        <span class="selection-count" x-text="selectedIds.length"></span>
                        <span>panneau(x) sélectionné(s)</span>
                        <span class="total-price">Total : <strong x-text="formatTotal()"></strong> FCFA</span>
                    </div>
                    <div class="selection-actions">
                        <button type="button" class="btn-clear" @click="clearSelection">✕ Vider</button>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="form-actions">
                <span class="info-text">⚠️ La modification recalcule le montant automatiquement</span>
                <div class="action-buttons">
                    <a href="{{ route('admin.reservations.show', $reservation) }}" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary" :disabled="selectedIds.length === 0">
                        ✓ Enregistrer les modifications
                    </button>
                </div>
            </div>
        </form>
    </div>

    <style>
        /* Variables et styles existants */
        :root {
            --edit-bg:#1a1a2a;--edit-surface:#252530;--edit-surface-hover:#2d2d3a;
            --edit-border:#3a3a48;--edit-accent:#e8a020;--edit-accent-dim:rgba(232,160,32,.12);
            --edit-text:#e8e8f0;--edit-text-dim:#9ca3af;--edit-text-muted:#6b7280;
            --edit-success:#22c55e;--edit-warning:#e8a020;--edit-danger:#ef4444;
        }

        .card {
            background: var(--edit-bg);
            border-radius: 20px;
            border: 1px solid var(--edit-border);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .card-header {
            padding: 16px 20px;
            background: var(--edit-surface);
            border-bottom: 1px solid var(--edit-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-title { font-size: 16px; font-weight: 600; }
        .card-body { padding: 20px; }
        .badge-info {
            background: var(--edit-accent-dim);
            color: var(--edit-accent);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-family: monospace;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--edit-text-muted);
            margin-bottom: 6px;
        }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            background: var(--edit-surface);
            border: 1px solid var(--edit-border);
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 13px;
            color: var(--edit-text);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .alert { padding: 12px 16px; border-radius: 12px; font-size: 13px; }
        .alert-danger { background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.3); color: var(--edit-danger); }
        
        /* Filtres */
        .filters-section { padding: 16px 20px; border-bottom: 1px solid var(--edit-border); background: var(--edit-surface); }
        .search-wrapper { position: relative; margin-bottom: 16px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 14px; color: var(--edit-text-muted); }
        .search-input { width: 100%; padding: 10px 12px 10px 36px; background: var(--edit-bg); border: 1px solid var(--edit-border); border-radius: 12px; font-size: 13px; color: var(--edit-text); }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-label { font-size: 10px; font-weight: 600; text-transform: uppercase; color: var(--edit-text-muted); }
        .filter-select { background: var(--edit-bg); border: 1px solid var(--edit-border); border-radius: 10px; padding: 8px 10px; font-size: 12px; color: var(--edit-text); cursor: pointer; }
        .btn-reset-filters { background: var(--edit-surface); border: 1px solid var(--edit-border); border-radius: 8px; padding: 4px 12px; font-size: 11px; color: var(--edit-text-muted); cursor: pointer; }
        .btn-reset-filters:hover { border-color: var(--edit-danger); color: var(--edit-danger); }
        .stats-badge { font-size: 12px; color: var(--edit-text-dim); background: var(--edit-surface); padding: 4px 10px; border-radius: 20px; }
        .header-actions { display: flex; align-items: center; gap: 12px; }
        
        /* Loader */
        .loader-container { padding: 60px; text-align: center; color: var(--edit-text-muted); }
        .loader-spinner { width: 32px; height: 32px; border: 3px solid var(--edit-border); border-top-color: var(--edit-accent); border-radius: 50%; animation: spin .8s linear infinite; margin: 0 auto 12px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Empty state */
        .empty-state { text-align: center; padding: 60px; color: var(--edit-text-muted); }
        .empty-state div:first-child { font-size: 48px; margin-bottom: 12px; }
        .empty-sub { font-size: 12px; margin-top: 8px; }
        
        /* Table */
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; background: var(--edit-surface); font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--edit-text-muted); border-bottom: 1px solid var(--edit-border); }
        .data-table td { padding: 12px 16px; font-size: 13px; border-bottom: 1px solid var(--edit-border); cursor: pointer; transition: background .15s; }
        .data-table tr:hover td { background: var(--edit-surface-hover); }
        .data-table tr.selected td { background: var(--edit-accent-dim); }
        .checkbox { accent-color: var(--edit-accent); width: 16px; height: 16px; cursor: pointer; }
        .reference { font-family: monospace; background: var(--edit-surface); padding: 2px 8px; border-radius: 6px; font-size: 12px; color: var(--edit-accent); }
        .font-medium { font-weight: 500; }
        .text-right { text-align: right; }
        .price { font-weight: 600; color: var(--edit-accent); white-space: nowrap; }
        .text-muted { color: var(--edit-text-muted); font-size: 10px; }
        .lit-badge { color: var(--edit-accent); font-size: 12px; }
        .non-lit-badge { color: var(--edit-text-muted); font-size: 12px; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-libre { background: rgba(34,197,94,.15); color: #22c55e; }
        .status-occupe { background: rgba(239,68,68,.15); color: #ef4444; }
        .release-info { font-size: 10px; color: var(--edit-warning); margin-top: 3px; }
        
        /* Pagination */
        .pagination-wrapper {
            padding: 16px 20px;
            border-top: 1px solid var(--edit-border);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .page-btn {
            background: var(--edit-surface);
            border: 1px solid var(--edit-border);
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 12px;
            color: var(--edit-text);
            cursor: pointer;
            transition: all .2s;
        }
        .page-btn:hover:not(:disabled) { border-color: var(--edit-accent); color: var(--edit-accent); }
        .page-btn:disabled { opacity: .5; cursor: not-allowed; }
        .page-info { font-size: 13px; color: var(--edit-text-dim); }
        .page-total { font-size: 11px; color: var(--edit-text-muted); margin-left: 6px; }
        
        .per-page-selector {
            padding: 12px 20px;
            border-top: 1px solid var(--edit-border);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--edit-text-muted);
            background: var(--edit-surface);
        }
        .per-page-selector select {
            background: var(--edit-bg);
            border: 1px solid var(--edit-border);
            border-radius: 6px;
            padding: 4px 8px;
            color: var(--edit-text);
            font-size: 12px;
            cursor: pointer;
        }
        
        /* Selection bar */
        .selection-bar {
            padding: 14px 20px;
            border-top: 1px solid var(--edit-border);
            background: var(--edit-accent-dim);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .selection-info { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .selection-count { font-size: 20px; font-weight: 800; color: var(--edit-accent); }
        .total-price { font-size: 14px; }
        .total-price strong { color: var(--edit-accent); font-size: 16px; }
        .btn-clear { background: transparent; border: 1px solid var(--edit-border); border-radius: 8px; padding: 6px 14px; font-size: 12px; color: var(--edit-text-muted); cursor: pointer; }
        .btn-clear:hover { border-color: var(--edit-danger); color: var(--edit-danger); }
        
        /* Form actions */
        .form-actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-top: 8px; }
        .info-text { font-size: 12px; color: var(--edit-text-muted); }
        .action-buttons { display: flex; gap: 12px; }
        .btn { padding: 10px 20px; border-radius: 12px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; }
        .btn-primary { background: var(--edit-accent); color: #000; }
        .btn-primary:hover:not(:disabled) { background: #f0b040; transform: translateY(-1px); }
        .btn-primary:disabled { opacity: .5; cursor: not-allowed; }
        .btn-ghost { background: transparent; border: 1px solid var(--edit-border); color: var(--edit-text-dim); }
        .btn-ghost:hover { border-color: var(--edit-accent); color: var(--edit-accent); }
        
        .mb-4 { margin-bottom: 20px; }

        /* Numéros de page */
.page-numbers {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}

.page-number {
    background: var(--edit-surface);
    border: 1px solid var(--edit-border);
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 12px;
    color: var(--edit-text);
    cursor: pointer;
    transition: all 0.2s;
    min-width: 36px;
}

.page-number:hover:not(.active-page) {
    border-color: var(--edit-accent);
    color: var(--edit-accent);
}

.active-page {
    background: var(--edit-accent);
    border-color: var(--edit-accent);
    color: #000;
    font-weight: 600;
}
    </style>

    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => {
    Alpine.data('reservationEdit', () => ({
        // Données
        startDate: '{{ $reservation->start_date->format('Y-m-d') }}',
        endDate: '{{ $reservation->end_date->format('Y-m-d') }}',
        allPanels: [],
        filteredPanels: [],
        selectedIds: @json(old('panel_ids', $selectedPanelIds)),
        loading: false,
        error: null,
        excludeId: {{ $reservation->id }},
        
        // Filtres
        searchTerm: '',
        filters: { commune_id: '', zone_id: '', format_id: '', dimensions: '', is_lit: '' },
        
        // Pagination
        currentPage: 1,
        perPage: 20,
        
        // Données disponibles
        dimensionsList: @json($dimensions ?? []),
        
        // Computed
        get totalDisplayed() { return this.filteredPanels.length; },
        get totalPages() { return Math.ceil(this.filteredPanels.length / this.perPage); },
        get paginatedPanels() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.filteredPanels.slice(start, start + this.perPage);
        },
        get selectedCount() { return this.selectedIds.length; },
        get hasActiveFilters() {
            return this.searchTerm || Object.values(this.filters).some(v => v !== '');
        },
        get allSelected() {
            return this.filteredPanels.length > 0 && 
                   this.filteredPanels.every(p => this.selectedIds.includes(p.id));
        },
        
        // Méthodes de navigation
        firstPage() { this.currentPage = 1; },
        lastPage() { this.currentPage = this.totalPages; },
        prevPage() { if (this.currentPage > 1) this.currentPage--; },
        nextPage() { if (this.currentPage < this.totalPages) this.currentPage++; },
        goToPage(page) {
            if (typeof page === 'number') {
                this.currentPage = page;
            }
        },
        getPageNumbers() {
            const pages = [];
            const total = this.totalPages;
            const current = this.currentPage;
            
            if (total <= 7) {
                for (let i = 1; i <= total; i++) pages.push(i);
            } else {
                pages.push(1);
                if (current > 3) pages.push('...');
                let start = Math.max(2, current - 1);
                let end = Math.min(total - 1, current + 1);
                for (let i = start; i <= end; i++) {
                    if (i !== 1 && i !== total) pages.push(i);
                }
                if (current < total - 2) pages.push('...');
                if (total > 1) pages.push(total);
            }
            return pages;
        },
        
        // Chargement des panneaux
        async loadPanels() {
            if (!this.startDate || !this.endDate) return;
            
            if (new Date(this.endDate) <= new Date(this.startDate)) {
                this.error = 'La date de fin doit être après la date de début';
                return;
            }
            
            this.loading = true;
            this.error = null;
            
            const params = new URLSearchParams({
                start_date: this.startDate,
                end_date: this.endDate,
                exclude_reservation_id: this.excludeId,
            });
            
            if (this.filters.commune_id) params.append('commune_id', this.filters.commune_id);
            if (this.filters.zone_id) params.append('zone_id', this.filters.zone_id);
            if (this.filters.format_id) params.append('format_id', this.filters.format_id);
            if (this.filters.dimensions) params.append('dimensions', this.filters.dimensions);
            if (this.filters.is_lit) params.append('is_lit', this.filters.is_lit);
            
            const url = window.AVAILABLE_PANELS_URL + '?' + params.toString();
            
            try {
                const res = await fetch(url, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                        'Accept': 'application/json',
                    }
                });
                
                if (!res.ok) throw new Error(`Erreur serveur (${res.status})`);
                
                const panels = await res.json();
                this.allPanels = panels;
                this.applyFilters();
                this.currentPage = 1;
                
            } catch (err) {
                this.error = 'Impossible de charger les panneaux : ' + err.message;
                this.allPanels = [];
                this.filteredPanels = [];
            } finally {
                this.loading = false;
            }
        },
        
        applyFilters() {
            let filtered = [...this.allPanels];
            
            if (this.searchTerm) {
                const t = this.searchTerm.toLowerCase();
                filtered = filtered.filter(p =>
                    p.reference?.toLowerCase().includes(t) ||
                    p.name?.toLowerCase().includes(t) ||
                    p.commune?.toLowerCase().includes(t) ||
                    p.zone?.toLowerCase().includes(t)
                );
            }
            
            this.filteredPanels = filtered;
        },
        
        togglePanel(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx === -1) this.selectedIds.push(id);
            else this.selectedIds.splice(idx, 1);
        },
        
        toggleAll() {
            const ids = this.filteredPanels.map(p => p.id);
            const allSelected = ids.every(id => this.selectedIds.includes(id));
            if (allSelected) {
                this.selectedIds = this.selectedIds.filter(id => !ids.includes(id));
            } else {
                ids.forEach(id => {
                    if (!this.selectedIds.includes(id)) this.selectedIds.push(id);
                });
            }
        },
        
        clearSelection() { this.selectedIds = []; },
        
        resetFilters() {
            this.searchTerm = '';
            this.filters = { commune_id: '', zone_id: '', format_id: '', dimensions: '', is_lit: '' };
            this.currentPage = 1;
            this.loadPanels();
        },
        
        getMonths() {
            if (!this.startDate || !this.endDate) return 1;
            const days = (new Date(this.endDate) - new Date(this.startDate)) / (1000 * 60 * 60 * 24);
            return Math.max(Math.ceil(days / 30), 1);
        },
        
        formatPrice(price) {
            return price ? Number(price).toLocaleString('fr-FR') + ' FCFA' : '—';
        },
        
        formatTotal() {
            const m = this.getMonths();
            const total = this.selectedIds.reduce((sum, id) => {
                const p = this.allPanels.find(x => x.id === id);
                return sum + (p ? (parseFloat(p.monthly_rate) || 0) * m : 0);
            }, 0);
            return Math.round(total).toLocaleString('fr-FR');
        },
        
        init() {
            this.loadPanels();
            
            document.getElementById('start-date')?.addEventListener('change', e => {
                this.startDate = e.target.value;
                this.loadPanels();
            });
            document.getElementById('end-date')?.addEventListener('change', e => {
                this.endDate = e.target.value;
                this.loadPanels();
            });
        }
    }));
});
    </script>
    @endpush
</x-admin-layout>