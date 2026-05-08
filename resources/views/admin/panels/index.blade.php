<x-admin-layout>
    <x-slot name="title">Inventaire Panneaux</x-slot>

    <x-slot name="topbarActions">
        <div style="position:relative;display:inline-block;" id="export-wrap">
            <button onclick="document.getElementById('export-dropdown').classList.toggle('hidden')"
                class="btn btn-ghost btn-sm">
                📄 Export PDF ▾
            </button>
            <div id="export-dropdown" class="hidden"
                style="position:absolute;top:calc(100% + 6px);right:0;z-index:200;
                background:var(--surface);border:1px solid var(--border2);
                border-radius:10px;padding:14px;min-width:220px;
                box-shadow:0 8px 24px rgba(0,0,0,.15);">
                <form method="GET" action="{{ route('admin.panels.export.list') }}" target="_blank">
                    <input type="hidden" name="commune_id" value="{{ request('commune_id') }}">
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    <input type="hidden" name="zone_id" value="{{ request('zone_id') }}">
                    <label for="hide-status"
                        style="display:flex;align-items:center;gap:8px;margin-bottom:12px;cursor:pointer;">
                        <input type="checkbox" id="hide-status" name="hide_status" value="1"
                            style="accent-color:var(--accent);width:15px;height:15px;cursor:pointer;">
                        <span style="font-size:13px;color:var(--text2);">Masquer le statut</span>
                    </label>
                    <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">
                        📄 Générer PDF liste
                    </button>
                </form>
            </div>
        </div>
        <a href="{{ route('admin.panels.export.network') }}" class="btn btn-ghost btn-sm">📊 Rapport réseau</a>
        <a href="{{ route('admin.panels.create') }}" class="btn btn-primary btn-sm">＋ Nouveau panneau</a>
    </x-slot>

    {{-- ══ KPI CARDS — cliquables avec bordure latérale colorée
         (style Alertes). Chaque carte applique son filtre (status ou
         source) et bascule l'état "active" pour feedback visuel. ══ --}}
    <style>
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 18px;
            cursor: pointer;
            transition: all .15s;
            text-decoration: none;
            display: block;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.06);
        }
        .kpi-card.active {
            box-shadow: 0 0 0 2px var(--accent-strong, var(--accent));
        }
        .kpi-card .kpi-icon { color: var(--accent); margin-bottom: 6px; }
        .kpi-card .kpi-num { font-size: 28px; font-weight: 800; line-height: 1; }
        .kpi-card .kpi-label {
            font-size: 10px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .6px; color: var(--text3); margin-top: 6px;
        }
    </style>
    <div class="kpi-grid">
        @php
            // Total affiché = exactement ce qui apparaît dans la liste,
            // selon la source courante (cohérent avec le calcul backend).
            $totalShown = match($source ?? 'all') {
                'externe' => $totalExternes,
                'cible'   => $totalPanneaux,
                default   => $totalPanneaux + $totalExternes,
            };
            $hasActiveFilter = request('search') || request('commune_id') || request('zone_id')
                || request('status') || request('kpi') || request('category_id')
                || request('client_id') || (request('source') && request('source') !== 'all');
        @endphp

        {{-- TOTAL → reset filtres. État actif quand AUCUN filtre n'est posé. --}}
        <a href="#" data-kpi="total" data-filter-action="reset"
           class="kpi-card {{ $hasActiveFilter ? '' : 'active' }}"
           style="border-left:4px solid var(--accent);">
            <div class="kpi-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            </div>
            <div class="kpi-num" data-kpi-value="total" style="color:var(--accent);">{{ $totalShown }}</div>
            <div class="kpi-label">Total inventaire</div>
        </a>

        {{-- LIBRES (vert) — kpi=libres → status=libre côté backend --}}
        <a href="#" data-kpi="libres"
           class="kpi-card"
           style="border-left:4px solid #22c55e;">
            <div class="kpi-icon" style="color:#22c55e;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="kpi-num" data-kpi-value="libres" style="color:#22c55e;">{{ $panneauxLibres }}</div>
            <div class="kpi-label">Libres</div>
        </a>

        {{-- OCCUPÉS (orange) — kpi=occupes → whereIn occupe/option/confirme --}}
        <a href="#" data-kpi="occupes"
           class="kpi-card"
           style="border-left:4px solid #f97316;">
            <div class="kpi-icon" style="color:#f97316;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <div class="kpi-num" data-kpi-value="occupes" style="color:#f97316;">{{ $panneauxOccupes }}</div>
            <div class="kpi-label">Occupés</div>
        </a>

        {{-- MAINTENANCE (rouge) --}}
        <a href="#" data-kpi="maintenance"
           class="kpi-card"
           style="border-left:4px solid #ef4444;">
            <div class="kpi-icon" style="color:#ef4444;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            </div>
            <div class="kpi-num" data-kpi-value="maintenance" style="color:#ef4444;">{{ $enMaintenance }}</div>
            <div class="kpi-label">Maintenance</div>
        </a>

        {{-- RÉGIES EXTERNES (violet) — bascule source=externe --}}
        <a href="#" data-kpi="externes" data-filter-source="externe"
           class="kpi-card"
           style="border-left:4px solid #a855f7;">
            <div class="kpi-icon" style="color:#a855f7;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 12h.01M9 15h.01M9 18h.01M15 9h.01M15 12h.01M15 15h.01M15 18h.01"/></svg>
            </div>
            <div class="kpi-num" data-kpi-value="externes" style="color:#a855f7;">{{ $totalExternes }}</div>
            <div class="kpi-label">Régies externes</div>
        </a>
    </div>

    {{-- FILTRE SOURCE --}}
    <div style="display:flex;gap:8px;margin-bottom:16px;">
        <button type="button" data-source="all" class="filter-source-btn btn btn-primary btn-sm">🪧 Tous
            ({{ $totalPanneaux + $totalExternes }})</button>
        <button type="button" data-source="cible" class="filter-source-btn btn btn-ghost btn-sm">✅ CIBLE CI
            ({{ $totalPanneaux }})</button>
        <button type="button" data-source="externe" class="filter-source-btn btn btn-ghost btn-sm"
            style="color:var(--purple);border-color:rgba(168,85,247,0.3);">🏢 Régies externes
            ({{ $totalExternes }})</button>
    </div>

    {{-- FILTRES --}}
    <div class="card" style="margin-bottom:16px;">
        <div class="filter-bar" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;padding:16px;">
            <div class="filter-group">
                <label class="filter-label">Recherche</label>
                <input type="text" id="filter-search" class="filter-input" placeholder="Référence, nom..."
                    style="width:180px;">
            </div>
            <div class="filter-group">
                <label class="filter-label">Commune</label>
                <select id="filter-commune" class="filter-select" style="width:140px;">
                    <option value="">Toutes</option>
                    @foreach ($communes as $commune)
                        <option value="{{ $commune->id }}">{{ $commune->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Zone</label>
                <select id="filter-zone" class="filter-select" style="width:140px;">
                    <option value="">Toutes les zones</option>
                    @foreach ($zones as $zone)
                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Statut</label>
                <select id="filter-status" class="filter-select" style="width:130px;">
                    <option value="">Tous</option>
                    <option value="libre">Libre</option>
                    <option value="occupe">Occupé</option>
                    <option value="option">Option</option>
                    <option value="confirme">Confirmé</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Catégorie</label>
                <select id="filter-category" class="filter-select" style="width:140px;">
                    <option value="">Toutes</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Client</label>
                <select id="filter-client" class="filter-select" style="width:160px;">
                    <option value="">Tous les clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group" id="reset-wrapper" style="display:none;">
                <label class="filter-label" style="visibility:hidden;">Actions</label>
                <button id="btn-reset" class="reset-btn">
                    ↺ Réinitialiser
                </button>
            </div>
        </div>
    </div>

    {{-- TABLEAU --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title" id="result-count">
                @if (($source ?? 'all') === 'externe')
                    🏢 Panneaux Régies externes ({{ $externalPanels->count() }})
                @else
                    🪧 Panneaux CIBLE CI ({{ $panels->total() }})
                @endif
            </div>
            <a href="{{ route('admin.map') }}" class="btn btn-ghost btn-sm">🗺️ Voir carte</a>
        </div>
        <div class="table-wrap">
            <table id="panels-table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Référence</th>
                        <th>Nom</th>
                        <th>Commune</th>
                        <th>Format</th>
                        <th>Faces</th>
                        <th>Adresse / Quartier</th>
                        <th>Tarif/mois</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="table-body">
                    @include('admin.panels.partials.table-rows', [
                        'panels' => $panels,
                        'source' => $source ?? 'all',
                        'externalPanels' => $externalPanels,
                    ])
                </tbody>
            </table>
        </div>
        <div id="pagination-links" style="padding:16px;">
            @if (($source ?? 'all') !== 'externe')
                {{ $panels->links() }}
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('click', function(e) {
                const wrap = document.getElementById('export-wrap');
                if (wrap && !wrap.contains(e.target)) {
                    document.getElementById('export-dropdown').classList.add('hidden');
                }
            });
            (function() {
                let currentFilters = {
                    source: '{{ $source ?? 'all' }}',
                    search: '',
                    commune_id: '',
                    zone_id: '',
                    status: '',
                    category_id: '',
                    client_id: '',
                    // KPI rapide : 'libres' | 'occupes' | 'maintenance'
                    // (le kpi 'externes' bascule plutôt source=externe)
                    kpi: '{{ request('kpi', '') }}',
                };
                let debounceTimer = null;

                const elements = {
                    search: document.getElementById('filter-search'),
                    commune: document.getElementById('filter-commune'),
                    zone: document.getElementById('filter-zone'),
                    status: document.getElementById('filter-status'),
                    category: document.getElementById('filter-category'),
                    client: document.getElementById('filter-client'),
                    resetBtn: document.getElementById('btn-reset'),
                    resetWrapper: document.getElementById('reset-wrapper'),
                    sourceBtns: document.querySelectorAll('.filter-source-btn'),
                    statLinks: document.querySelectorAll('.filter-stat'),
                    kpiCards: document.querySelectorAll('.kpi-card'),
                };

                // Met à jour les valeurs des KPI cards selon la réponse AJAX.
                //   - "total" : nb réellement affiché dans la liste (suit kpi/source/filtres)
                //   - libres/occupes/maintenance/externes : valeurs réelles HORS
                //     filtre KPI (les autres cartes gardent leur sens quand on
                //     clique sur l'une d'elles).
                function updateKpiCards(counts) {
                    if (!counts) return;
                    document.querySelectorAll('[data-kpi-value]').forEach(el => {
                        const k = el.dataset.kpiValue;
                        const v = counts[k] ?? 0;
                        el.textContent = new Intl.NumberFormat('fr-FR').format(v);
                    });
                }

                // Met à jour l'état actif des cards (bordure accent)
                function updateActiveKpi() {
                    const noFilter = !currentFilters.kpi
                        && !currentFilters.status
                        && currentFilters.source === 'all';
                    elements.kpiCards.forEach(c => {
                        const action = c.dataset.filterAction;
                        const kpi    = c.dataset.kpi;
                        const source = c.dataset.filterSource;
                        let active = false;
                        if (action === 'reset') {
                            active = noFilter;
                        } else if (source === 'externe') {
                            active = currentFilters.source === 'externe';
                        } else if (kpi && kpi !== 'total') {
                            active = currentFilters.kpi === kpi;
                        }
                        c.classList.toggle('active', active);
                    });
                }

                function updateResetButton() {
                    const hasFilters = currentFilters.search ||
                        currentFilters.commune_id ||
                        currentFilters.zone_id ||
                        currentFilters.status ||
                        currentFilters.kpi ||
                        currentFilters.category_id ||
                        currentFilters.client_id ||
                        currentFilters.source !== 'all';

                    if (elements.resetWrapper) {
                        elements.resetWrapper.style.display = hasFilters ? 'flex' : 'none';
                    }
                }

                async function applyFilters() {
                    const params = new URLSearchParams();
                    if (currentFilters.source !== 'all') params.set('source', currentFilters.source);
                    if (currentFilters.search) params.set('search', currentFilters.search);
                    if (currentFilters.commune_id) params.set('commune_id', currentFilters.commune_id);
                    if (currentFilters.zone_id) params.set('zone_id', currentFilters.zone_id);
                    if (currentFilters.status) params.set('status', currentFilters.status);
                    if (currentFilters.kpi) params.set('kpi', currentFilters.kpi);
                    if (currentFilters.category_id) params.set('category_id', currentFilters.category_id);
                    if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
                    params.set('ajax', '1');

                    const tbody = document.getElementById('table-body');
                    const originalHtml = tbody.innerHTML;
                    tbody.innerHTML =
                        '<tr><td colspan="10" style="text-align:center;padding:40px;"><div class="spinner"></div> Chargement...</td></tr>';

                    try {
                        const response = await fetch(`{{ route('admin.panels.index') }}?${params}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });
                        const data = await response.json();

                        document.getElementById('table-body').innerHTML = data.html;
                        document.getElementById('result-count').innerHTML = data.stats_html;
                        updateKpiCards(data.counts);
                        updateActiveKpi();

                        const pagContainer = document.getElementById('pagination-links');
                        if (pagContainer) pagContainer.innerHTML = data.pagination || '';

                        const url = new URL(window.location.href);
                        Object.keys(currentFilters).forEach(key => {
                            if (currentFilters[key] && key !== 'source') {
                                url.searchParams.set(key, currentFilters[key]);
                            } else {
                                url.searchParams.delete(key);
                            }
                        });
                        if (currentFilters.source !== 'all') {
                            url.searchParams.set('source', currentFilters.source);
                        } else {
                            url.searchParams.delete('source');
                        }
                        window.history.pushState({}, '', url);

                    } catch (error) {
                        console.error('Erreur:', error);
                        tbody.innerHTML = originalHtml;
                    }
                }

                function debounceApply() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => applyFilters(), 400);
                }

                // Événements
                if (elements.search) {
                    elements.search.addEventListener('input', () => {
                        currentFilters.search = elements.search.value;
                        updateResetButton();
                        debounceApply();
                    });
                }

                [elements.commune, elements.zone, elements.status, elements.category, elements.client].forEach(el => {
                    if (el) {
                        el.addEventListener('change', () => {
                            currentFilters.commune_id = elements.commune?.value || '';
                            currentFilters.zone_id = elements.zone?.value || '';
                            currentFilters.status = elements.status?.value || '';
                            currentFilters.category_id = elements.category?.value || '';
                            currentFilters.client_id = elements.client?.value || '';
                            updateResetButton();
                            updateActiveKpi();
                            applyFilters();
                        });
                    }
                });

                // KPI cards — chaque clic applique un filtre rapide.
                //   - "total"      : reset complet
                //   - "libres"     : kpi=libres (whereIn status=libre côté backend)
                //   - "occupes"    : kpi=occupes (whereIn occupe/option/confirme)
                //   - "maintenance": kpi=maintenance
                //   - "externes"   : bascule source=externe
                // Re-cliquer la même carte la désactive (toggle).
                elements.kpiCards.forEach(card => {
                    card.addEventListener('click', (e) => {
                        e.preventDefault();
                        const action = card.dataset.filterAction;
                        const kpi    = card.dataset.kpi;
                        const source = card.dataset.filterSource;

                        if (action === 'reset') {
                            currentFilters = {
                                source: 'all', search: '', commune_id: '',
                                zone_id: '', status: '', category_id: '', client_id: '',
                                kpi: '',
                            };
                            if (elements.search) elements.search.value = '';
                            if (elements.commune) elements.commune.value = '';
                            if (elements.zone) elements.zone.value = '';
                            if (elements.status) elements.status.value = '';
                            if (elements.category) elements.category.value = '';
                            if (elements.client) elements.client.value = '';
                        } else if (source === 'externe') {
                            const isActive = currentFilters.source === 'externe';
                            currentFilters.source = isActive ? 'all' : 'externe';
                            currentFilters.kpi    = '';
                            currentFilters.status = '';
                            if (elements.status) elements.status.value = '';
                        } else if (kpi && kpi !== 'total') {
                            const isActive = currentFilters.kpi === kpi;
                            currentFilters.kpi    = isActive ? '' : kpi;
                            currentFilters.status = ''; // le KPI gère, pas le select
                            currentFilters.source = 'cible'; // forcer panneaux internes
                            if (elements.status) elements.status.value = '';
                        }
                        updateResetButton();
                        updateActiveKpi();
                        applyFilters();
                    });
                });

                // Boutons source
                elements.sourceBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        const source = btn.dataset.source;
                        currentFilters.source = source;

                        elements.sourceBtns.forEach(b => {
                            if (b.dataset.source === source) {
                                b.classList.remove('btn-ghost');
                                b.classList.add('btn-primary');
                            } else {
                                b.classList.remove('btn-primary');
                                b.classList.add('btn-ghost');
                                if (b.dataset.source === 'externe') {
                                    b.style.color = 'var(--purple)';
                                    b.style.borderColor = 'rgba(168,85,247,0.3)';
                                }
                            }
                        });
                        updateResetButton();
                        applyFilters();
                    });
                });

                // Liens stats
                elements.statLinks.forEach(stat => {
                    stat.addEventListener('click', (e) => {
                        e.preventDefault();
                        const source = stat.dataset.source;
                        const status = stat.dataset.status;

                        if (source) {
                            currentFilters.source = source;
                        } else if (status) {
                            // Si on filtre par statut, forcer source sur "cible" (panneaux internes)
                            currentFilters.source = 'cible';
                        }

                        // Mettre à jour l'apparence des boutons source
                        elements.sourceBtns.forEach(btn => {
                            if (btn.dataset.source === currentFilters.source) {
                                btn.classList.remove('btn-ghost');
                                btn.classList.add('btn-primary');
                            } else {
                                btn.classList.remove('btn-primary');
                                btn.classList.add('btn-ghost');
                                if (btn.dataset.source === 'externe') {
                                    btn.style.color = 'var(--purple)';
                                    btn.style.borderColor = 'rgba(168,85,247,0.3)';
                                }
                            }
                        });

                        if (status) {
                            currentFilters.status = status;
                            if (elements.status) elements.status.value = status;
                        }
                        updateResetButton();
                        applyFilters();
                    });
                });

                // Reset button
                if (elements.resetBtn) {
                    elements.resetBtn.addEventListener('click', () => {
                        currentFilters = {
                            source: 'all',
                            search: '',
                            commune_id: '',
                            zone_id: '',
                            status: '',
                            category_id: '',
                            client_id: '',
                            kpi: '',
                        };

                        // Réinitialiser tous les champs
                        if (elements.search) elements.search.value = '';
                        if (elements.commune) elements.commune.value = '';
                        if (elements.zone) elements.zone.value = '';
                        if (elements.status) elements.status.value = '';
                        if (elements.category) elements.category.value = '';
                        if (elements.client) elements.client.value = '';

                        // Réinitialiser l'apparence des boutons source
                        elements.sourceBtns.forEach(btn => {
                            if (btn.dataset.source === 'all') {
                                btn.classList.remove('btn-ghost');
                                btn.classList.add('btn-primary');
                            } else {
                                btn.classList.remove('btn-primary');
                                btn.classList.add('btn-ghost');
                                if (btn.dataset.source === 'externe') {
                                    btn.style.color = 'var(--purple)';
                                    btn.style.borderColor = 'rgba(168,85,247,0.3)';
                                }
                            }
                        });

                        updateResetButton();
                        applyFilters();
                    });
                }
                // Intercepter les clics sur la pagination
                document.addEventListener('click', function(e) {
                    const link = e.target.closest('#pagination-links a');
                    if (!link) return;
                    e.preventDefault();

                    const url = new URL(link.href);
                    const page = url.searchParams.get('page');
                    if (!page) return;

                    const params = new URLSearchParams();
                    if (currentFilters.source !== 'all') params.set('source', currentFilters.source);
                    if (currentFilters.search) params.set('search', currentFilters.search);
                    if (currentFilters.commune_id) params.set('commune_id', currentFilters.commune_id);
                    if (currentFilters.zone_id) params.set('zone_id', currentFilters.zone_id);
                    if (currentFilters.status) params.set('status', currentFilters.status);
                    if (currentFilters.category_id) params.set('category_id', currentFilters.category_id);
                    if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
                    params.set('page', page);
                    params.set('ajax', '1');

                    const tbody = document.getElementById('table-body');
                    tbody.innerHTML =
                        '<tr><td colspan="10" style="text-align:center;padding:40px;"><div class="spinner"></div> Chargement...</td></tr>';

                    fetch(`{{ route('admin.panels.index') }}?${params}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        })
                        .then(r => r.json())
                        .then(data => {
                            document.getElementById('table-body').innerHTML = data.html;
                            document.getElementById('result-count').innerHTML = data.stats_html;
                            const pagContainer = document.getElementById('pagination-links');
                            if (pagContainer) pagContainer.innerHTML = data.pagination || '';
                            window.scrollTo({
                                top: 0,
                                behavior: 'smooth'
                            });
                        })
                        .catch(err => console.error('Pagination error:', err));
                });
                // Initialisation
                updateResetButton();
            })();
        </script>

        <style>
            .reset-btn {
                height: 40px;
                padding: 0 20px;
                background: var(--surface2);
                border: 1px solid var(--border);
                border-radius: 10px;
                color: var(--text-muted);
                font-size: 12px;
                cursor: pointer;
            }

            .reset-btn:hover {
                background: var(--surface3);
                border-color: var(--danger);
                color: var(--danger);
            }

            .spinner {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 2px solid var(--border);
                border-top-color: var(--accent);
                border-radius: 50%;
                animation: spin 0.6s linear infinite;
                vertical-align: middle;
                margin-right: 8px;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }

            .filter-stat {
                cursor: pointer;
                transition: all 0.2s;
            }

            .filter-stat:hover {
                transform: translateY(-2px);
            }
        </style>
    @endpush

</x-admin-layout>
