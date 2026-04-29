<x-admin-layout title="Modifier {{ $reservation->reference }}">
    <x-slot:topbarActions>
        <a href="{{ route('admin.reservations.show', $reservation) }}" class="btn btn-ghost btn-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Retour
        </a>
    </x-slot:topbarActions>

    {{-- URL AJAX --}}
    <script>
        window.AVAILABLE_PANELS_URL = '{{ route("admin.reservations.available-panels") }}';
    </script>

    <div id="edit-reservation-app" x-data="reservationEdit()" x-init="init()">
        <form method="POST" action="{{ route('admin.reservations.update', $reservation) }}">
            @csrf @method('PUT')
            <input type="hidden" name="last_updated_at" value="{{ $reservation->updated_at->timestamp }}">

            {{-- ══ INFOS GÉNÉRALES ══ --}}
            <div class="card">
                <div class="card-header">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(232,160,32,.12)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#e8a020">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold" style="color:var(--text)">Informations générales</h3>
                            <p class="text-xs mt-0.5" style="color:var(--text3)">Modifiez les dates et le client de la réservation</p>
                        </div>
                    </div>
                    <div class="badge-info">{{ $reservation->reference }} · {{ $reservation->status->label() }}</div>
                </div>

                <div class="card-body">
                    @if($errors->any())
                    <div class="alert-danger mb-4">
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div>
                                @foreach($errors->all() as $e)
                                <div class="text-sm">{{ $e }}</div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="form-group">
                        <label class="form-label">Client</label>
                        <select name="client_id" required class="form-select">
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id', $reservation->client_id) == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">Date de début</label>
                            <input type="date" name="start_date" id="start-date"
                                   value="{{ old('start_date', $reservation->start_date->format('Y-m-d')) }}"
                                   class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date de fin</label>
                            <input type="date" name="end_date" id="end-date"
                                   value="{{ old('end_date', $reservation->end_date->format('Y-m-d')) }}"
                                   class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="3" class="form-textarea"
                                  placeholder="Remarques, instructions particulières…">{{ old('notes', $reservation->notes) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- ══ PANNEAUX DISPONIBLES ══ --}}
            <div class="card mt-4">
                <div class="card-header">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(232,160,32,.12)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#e8a020">
                                <rect x="2" y="3" width="20" height="14" rx="2"/>
                                <path d="M8 21h8M12 17v4"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold" style="color:var(--text)">Panneaux disponibles</h3>
                            <p class="text-xs mt-0.5" style="color:var(--text3)">Sélectionnez les panneaux à réserver</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="stats-badge" x-text="selectedCount + ' sélectionné(s) / ' + totalDisplayed + ' disponible(s)'"></span>
                        <button type="button" class="btn-reset-filters" @click="resetFilters" x-show="hasActiveFilters">
                            ↺ Réinitialiser
                        </button>
                    </div>
                </div>

                {{-- Filtres --}}
                <div class="filters-section">
                    <div class="search-wrapper">
                        <svg class="search-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
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

                {{-- États --}}
                <div x-show="loading" class="loader-container">
                    <div class="loader-spinner"></div>
                    <span>Chargement des panneaux disponibles...</span>
                </div>

                <div x-show="error" class="alert-danger m-4" x-text="error"></div>

                <div x-show="!loading && !error && (!startDate || !endDate)" class="empty-state">
                    <div class="text-5xl mb-3">📅</div>
                    <div class="text-sm font-medium" style="color:var(--text2)">Saisissez les dates pour voir les panneaux disponibles</div>
                </div>

                <div x-show="!loading && !error && startDate && endDate && filteredPanels.length === 0" class="empty-state">
                    <div class="text-5xl mb-3">🔍</div>
                    <div class="text-sm font-medium" style="color:var(--text2)">Aucun panneau disponible sur cette période</div>
                    <div class="text-xs mt-1" style="color:var(--text3)">Modifiez les dates ou les filtres</div>
                </div>

                {{-- Tableau --}}
                <div x-show="!loading && !error && filteredPanels.length > 0" class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="w-10"><input type="checkbox" @change="toggleAll" :checked="allSelected" class="checkbox"></th>
                                <th>Référence</th>
                                <th>Nom</th>
                                <th>Commune / Zone</th>
                                <th>Format / Dim.</th>
                                <th class="w-16">💡</th>
                                <th>Disponibilité</th>
                                <th class="text-right">Tarif / mois</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="panel in paginatedPanels" :key="panel.id">
                                <tr @click="togglePanel(panel.id)" :class="{ 'selected': selectedIds.includes(panel.id) }">
                                    <td @click.stop><input type="checkbox" :checked="selectedIds.includes(panel.id)" @change="togglePanel(panel.id)" class="checkbox"></td>
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
                                            <span class="status-libre">✅ Libre</span>
                                        </template>
                                        <template x-if="!panel.available && panel.release_date">
                                            <div>
                                                <span class="status-occupe">🔒 Occupé</span>
                                                <div class="release-info" x-text="'Libre le ' + panel.release_date"></div>
                                            </div>
                                        </template>
                                        <template x-if="!panel.available && !panel.release_date">
                                            <span class="status-occupe">🔒 Occupé</span>
                                        </template>
                                    </td>
                                    <td class="text-right price" x-text="formatPrice(panel.monthly_rate)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    {{-- Pagination --}}
                    <div class="pagination-wrapper" x-show="totalPages > 1">
                        <button type="button" @click="prevPage" :disabled="currentPage === 1" class="page-btn">← Précédent</button>
                        <div class="page-numbers">
                            <template x-for="page in getPageNumbers()" :key="page">
                                <button type="button" @click="goToPage(page)" 
                                        :class="{'active-page': currentPage === page}" 
                                        class="page-number" x-text="page"></button>
                            </template>
                        </div>
                        <span class="page-info">Page <strong x-text="currentPage"></strong> / <strong x-text="totalPages"></strong></span>
                        <button type="button" @click="nextPage" :disabled="currentPage === totalPages" class="page-btn">Suivant →</button>
                    </div>

                    <div class="per-page-selector" x-show="totalPages > 1">
                        <span class="text-xs" style="color:var(--text3)">Afficher :</span>
                        <select x-model="perPage" @change="currentPage = 1">
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                        <span class="text-xs" style="color:var(--text3)">par page</span>
                    </div>
                </div>

                {{-- Hidden inputs --}}
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="panel_ids[]" :value="id">
                </template>

                {{-- Barre de sélection --}}
                <div x-show="selectedIds.length > 0" class="selection-bar">
                    <div class="flex items-center gap-3">
                        <span class="selection-count" x-text="selectedIds.length"></span>
                        <span class="text-sm" style="color:var(--text2)">panneau(x) sélectionné(s)</span>
                        <span class="total-price">Total : <strong x-text="formatTotal()"></strong> FCFA</span>
                    </div>
                    <button type="button" class="btn-clear" @click="clearSelection">✕ Vider</button>
                </div>
            </div>

            {{-- Actions --}}
            <div class="form-actions">
                <span class="info-text">⚠️ La modification recalcule le montant automatiquement</span>
                <div class="flex gap-3">
                    <a href="{{ route('admin.reservations.show', $reservation) }}" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary" :disabled="selectedIds.length === 0">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Enregistrer les modifications
                    </button>
                </div>
            </div>
        </form>
    </div>

    <style>
        /* Styles harmonisés avec la charte Panora */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            background: var(--surface2);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-body {
            padding: 20px;
        }

        .badge-info {
            background: rgba(232, 160, 32, 0.12);
            color: #e8a020;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-family: monospace;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text3);
            margin-bottom: 6px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            color: var(--text);
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #e8a020;
            box-shadow: 0 0 0 2px rgba(232, 160, 32, 0.1);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.25);
            border-radius: 10px;
            padding: 12px 16px;
            color: #f87171;
        }

        /* Filtres */
        .filters-section {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .search-wrapper {
            position: relative;
            margin-bottom: 16px;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text3);
        }

        .search-input {
            width: 100%;
            padding: 10px 12px 10px 36px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            color: var(--text);
        }

        .search-input:focus {
            outline: none;
            border-color: #e8a020;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .filter-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text3);
        }

        .filter-select {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 12px;
            color: var(--text);
            cursor: pointer;
        }

        .btn-reset-filters {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 4px 12px;
            font-size: 11px;
            color: var(--text3);
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-reset-filters:hover {
            border-color: #ef4444;
            color: #ef4444;
        }

        .stats-badge {
            font-size: 12px;
            color: var(--text2);
            background: var(--surface2);
            padding: 4px 10px;
            border-radius: 20px;
        }

        /* Loader */
        .loader-container {
            padding: 60px;
            text-align: center;
            color: var(--text3);
        }

        .loader-spinner {
            width: 32px;
            height: 32px;
            border: 3px solid var(--border);
            border-top-color: #e8a020;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 12px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--text3);
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 12px 16px;
            background: var(--surface);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text3);
            border-bottom: 1px solid var(--border);
        }

        .data-table td {
            padding: 12px 16px;
            font-size: 13px;
            color: var(--text2);
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.15s;
        }

        .data-table tr:hover td {
            background: var(--surface2);
        }

        .data-table tr.selected td {
            background: rgba(232, 160, 32, 0.08);
        }

        .checkbox {
            accent-color: #e8a020;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .reference {
            font-family: monospace;
            background: var(--surface2);
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #e8a020;
        }

        .price {
            font-weight: 600;
            color: #e8a020;
            white-space: nowrap;
        }

        .text-muted {
            font-size: 10px;
            color: var(--text3);
        }

        .lit-badge {
            color: #e8a020;
            font-size: 12px;
        }

        .non-lit-badge {
            color: var(--text3);
            font-size: 12px;
        }

        .status-libre {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(34, 197, 94, 0.12);
            color: #22c55e;
        }

        .status-occupe {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(239, 68, 68, 0.12);
            color: #f87171;
        }

        .release-info {
            font-size: 10px;
            color: #e8a020;
            margin-top: 3px;
        }

        /* Pagination */
        .pagination-wrapper {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .page-btn {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 12px;
            color: var(--text2);
            cursor: pointer;
            transition: all 0.2s;
        }

        .page-btn:hover:not(:disabled) {
            border-color: #e8a020;
            color: #e8a020;
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-numbers {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .page-number {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            color: var(--text2);
            cursor: pointer;
            transition: all 0.2s;
            min-width: 36px;
        }

        .page-number:hover:not(.active-page) {
            border-color: #e8a020;
            color: #e8a020;
        }

        .active-page {
            background: #e8a020;
            border-color: #e8a020;
            color: #000;
            font-weight: 600;
        }

        .page-info {
            font-size: 13px;
            color: var(--text2);
        }

        .per-page-selector {
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            background: var(--surface);
        }

        .per-page-selector select {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 4px 8px;
            color: var(--text2);
            font-size: 12px;
            cursor: pointer;
        }

        /* Selection bar */
        .selection-bar {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            background: rgba(232, 160, 32, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .selection-count {
            font-size: 20px;
            font-weight: 800;
            color: #e8a020;
        }

        .total-price {
            font-size: 14px;
            color: var(--text2);
        }

        .total-price strong {
            color: #e8a020;
            font-size: 16px;
        }

        .btn-clear {
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 12px;
            color: var(--text3);
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-clear:hover {
            border-color: #ef4444;
            color: #ef4444;
        }

        /* Form actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 24px;
        }

        .info-text {
            font-size: 12px;
            color: var(--text3);
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
            background: #e8a020;
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
            border-color: #e8a020;
            color: #e8a020;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
        .mt-4 { margin-top: 16px; }
        .mb-4 { margin-bottom: 16px; }
        .mx-4 { margin-left: 16px; margin-right: 16px; }
        .p-4 { padding: 16px; }
        .m-4 { margin: 16px; }
        .w-4 { width: 16px; }
        .h-4 { height: 16px; }
        .w-8 { width: 32px; }
        .h-8 { height: 32px; }
        .w-10 { width: 40px; }
        .text-right { text-align: right; }
        .text-sm { font-size: 13px; }
        .text-xs { font-size: 11px; }
        .text-base { font-size: 15px; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .mr-1 { margin-right: 4px; }
        .mt-0\.5 { margin-top: 2px; }
        .mt-1 { margin-top: 4px; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .justify-between { justify-content: space-between; }
        .flex-shrink-0 { flex-shrink: 0; }
        .overflow-x-auto { overflow-x: auto; }
    </style>

    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('reservationEdit', () => ({
            startDate: '{{ $reservation->start_date->format('Y-m-d') }}',
            endDate: '{{ $reservation->end_date->format('Y-m-d') }}',
            allPanels: [],
            filteredPanels: [],
            selectedIds: @json(old('panel_ids', $selectedPanelIds)),
            loading: false,
            error: null,
            excludeId: {{ $reservation->id }},
            searchTerm: '',
            filters: { commune_id: '', zone_id: '', format_id: '', dimensions: '', is_lit: '' },
            currentPage: 1,
            perPage: 20,
            dimensionsList: @json($dimensions ?? []),

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

            prevPage() { if (this.currentPage > 1) this.currentPage--; },
            nextPage() { if (this.currentPage < this.totalPages) this.currentPage++; },
            goToPage(page) { if (typeof page === 'number') this.currentPage = page; },

            getPageNumbers() {
                const pages = [], total = this.totalPages, current = this.currentPage;
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
                    ids.forEach(id => { if (!this.selectedIds.includes(id)) this.selectedIds.push(id); });
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