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
                border-radius:10px;padding:14px;min-width:240px;
                box-shadow:0 8px 24px rgba(0,0,0,.15);">
                <form method="GET" action="{{ route('admin.panels.export.list') }}">
                    <input type="hidden" name="commune_id" value="{{ request('commune_id') }}">
                    <input type="hidden" name="status"     value="{{ request('status') }}">
                    <input type="hidden" name="zone_id"    value="{{ request('zone_id') }}">

                    <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px">Format</div>
                    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:14px">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:6px 8px;border-radius:8px;background:var(--surface2)">
                            <input type="radio" name="mode" value="list" checked
                                   style="accent-color:var(--accent);cursor:pointer">
                            <span style="font-size:13px;color:var(--text)">Liste</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:6px 8px;border-radius:8px;background:var(--surface2)">
                            <input type="radio" name="mode" value="images"
                                   style="accent-color:var(--accent);cursor:pointer">
                            <span style="font-size:13px;color:var(--text)">Images</span>
                        </label>
                    </div>

                    <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px">Options</div>
                    <label for="hide-status"
                        style="display:flex;align-items:center;gap:8px;margin-bottom:8px;cursor:pointer;">
                        <input type="checkbox" id="hide-status" name="hide_status" value="1"
                            style="accent-color:var(--accent);width:15px;height:15px;cursor:pointer;">
                        <span style="font-size:13px;color:var(--text2);">Masquer le statut</span>
                    </label>
                    <label for="hide-price"
                        style="display:flex;align-items:center;gap:8px;margin-bottom:14px;cursor:pointer;">
                        <input type="checkbox" id="hide-price" name="hide_price" value="1"
                            style="accent-color:var(--accent);width:15px;height:15px;cursor:pointer;">
                        <span style="font-size:13px;color:var(--text2);">Masquer le tarif</span>
                    </label>

                    <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">
                        Générer le PDF
                    </button>
                </form>
            </div>
        </div>
        <a href="{{ route('admin.panels.export.network') }}" class="btn btn-ghost btn-sm">📊 Rapport réseau</a>
        <a href="{{ route('admin.panels.create') }}" class="btn btn-primary btn-sm">＋ Nouveau panneau</a>
    </x-slot>

    {{-- STATS — design KPI unifié --}}
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:20px">
        <a href="#" data-source="all" class="kpi-card filter-stat" style="--kpi-color:var(--accent)"
           onmouseenter="this.style.borderColor='var(--accent)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.12)'"
           onmouseleave="this.style.borderColor='';this.style.transform='';this.style.boxShadow=''">
            <div class="kpi-card__top-bar" style="background:var(--accent)"></div>
            <div class="kpi-card__icon" style="color:var(--accent)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div>
            <div class="kpi-card__value" style="color:var(--accent)">{{ $totalPanneaux }}</div>
            <div class="kpi-card__label">Total CIBLE CI</div>
            <div class="kpi-card__sub">parc complet</div>
            <div class="kpi-card__arrow" style="color:var(--accent)">→</div>
        </a>
        <a href="#" data-status="libre" class="kpi-card filter-stat" style="--kpi-color:var(--green)"
           onmouseenter="this.style.borderColor='var(--green)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.12)'"
           onmouseleave="this.style.borderColor='';this.style.transform='';this.style.boxShadow=''">
            <div class="kpi-card__top-bar" style="background:var(--green)"></div>
            <div class="kpi-card__icon" style="color:var(--green)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div class="kpi-card__value" style="color:var(--green)">{{ $panneauxLibres }}</div>
            <div class="kpi-card__label">Libres</div>
            <div class="kpi-card__sub">disponibles à la réservation</div>
            <div class="kpi-card__arrow" style="color:var(--green)">→</div>
        </a>
        <a href="#" data-source="occupes" class="kpi-card filter-stat" style="--kpi-color:#ef4444"
           onmouseenter="this.style.borderColor='#ef4444';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.12)'"
           onmouseleave="this.style.borderColor='';this.style.transform='';this.style.boxShadow=''">
            <div class="kpi-card__top-bar" style="background:#ef4444"></div>
            <div class="kpi-card__icon" style="color:#ef4444"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/></svg></div>
            <div class="kpi-card__value" style="color:#ef4444">{{ $panneauxOccupes }}</div>
            <div class="kpi-card__label">Occupés</div>
            <div class="kpi-card__sub">en affichage / option</div>
            <div class="kpi-card__arrow" style="color:#ef4444">→</div>
        </a>
        <a href="#" data-status="maintenance" class="kpi-card filter-stat" style="--kpi-color:var(--red)"
           onmouseenter="this.style.borderColor='var(--red)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.12)'"
           onmouseleave="this.style.borderColor='';this.style.transform='';this.style.boxShadow=''">
            <div class="kpi-card__top-bar" style="background:var(--red)"></div>
            <div class="kpi-card__icon" style="color:var(--red)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
            <div class="kpi-card__value" style="color:var(--red)">{{ $enMaintenance }}</div>
            <div class="kpi-card__label">Maintenance</div>
            <div class="kpi-card__sub">panneaux indisponibles</div>
            <div class="kpi-card__arrow" style="color:var(--red)">→</div>
        </a>
        <a href="#" data-source="externe" class="kpi-card filter-stat" style="--kpi-color:var(--purple)"
           onmouseenter="this.style.borderColor='var(--purple)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.12)'"
           onmouseleave="this.style.borderColor='';this.style.transform='';this.style.boxShadow=''">
            <div class="kpi-card__top-bar" style="background:var(--purple)"></div>
            <div class="kpi-card__icon" style="color:var(--purple)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
            <div class="kpi-card__value" style="color:var(--purple)">{{ $totalExternes }}</div>
            <div class="kpi-card__label">Régies externes</div>
            <div class="kpi-card__sub">panneaux partenaires</div>
            <div class="kpi-card__arrow" style="color:var(--purple)">→</div>
        </a>
    </div>

    {{-- FILTRE SOURCE --}}
    <div style="display:flex;gap:8px;margin-bottom:16px;">
        <button type="button" data-source="all" class="filter-source-btn btn btn-primary btn-sm">🪧 Tous
            ({{ $totalPanneaux + $totalExternes }})</button>
        <button type="button" data-source="cible" class="filter-source-btn btn btn-ghost btn-sm">✅ CIBLE CI
            ({{ $totalPanneaux }})</button>
        <button type="button" data-source="occupes" class="filter-source-btn btn btn-ghost btn-sm"
            style="color:#ef4444;border-color:rgba(239,68,68,0.3);">🔴 Occupés
            ({{ $panneauxOccupes }})</button>
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
                <label class="filter-label">Format</label>
                <select id="filter-format" class="filter-select" style="width:140px;">
                    <option value="">Tous</option>
                    @foreach ($formats as $fmt)
                        @php
                            $dim = ($fmt->width && $fmt->height) ? ' (' . rtrim(rtrim(number_format($fmt->width, 1, ',', ''), '0'), ',') . '×' . rtrim(rtrim(number_format($fmt->height, 1, ',', ''), '0'), ',') . 'm)' : '';
                        @endphp
                        <option value="{{ $fmt->id }}">{{ $fmt->name }}{{ $dim }}</option>
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

        {{-- Légende statuts — déplacée EN HAUT (au-dessus de la table)
             pour éviter à l'utilisateur de scroller toute la liste avant
             de comprendre les couleurs/statuts. Demande terrain réunion 20/05. --}}
        <div style="padding:12px 18px;border-bottom:1px solid var(--border);background:var(--surface2)">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:8px">
                Légende statuts panneaux
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:14px;font-size:11px">
                @foreach(\App\Enums\PanelStatus::cases() as $st)
                    @php $cfg = $st->uiConfig(); @endphp
                    <div title="{{ $cfg['description'] }}"
                         style="display:flex;align-items:flex-start;gap:7px;max-width:220px;cursor:help">
                        <span style="width:8px;height:8px;border-radius:50%;background:{{ $cfg['color'] }};margin-top:5px;flex-shrink:0;box-shadow:0 0 0 2px {{ $cfg['color'] }}22"></span>
                        <div>
                            <div style="font-weight:700;color:{{ $cfg['color'] }};line-height:1.2">{{ $st->label() }}</div>
                            <div style="color:var(--text3);font-size:10px;margin-top:2px;line-height:1.3">{{ $cfg['description'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
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
                        'showOccupants' => $showOccupants ?? false,
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

                // Lot 6 : ferme tout menu de statut panneau ouvert hors clic
                document.querySelectorAll('.panel-status-menu').forEach(menu => {
                    if (!menu.parentElement.contains(e.target)) {
                        menu.style.display = 'none';
                    }
                });
            });

            // ══════════════════════════════
            // PANEL STATUS — toggle maintenance inline (Lot 6)
            // ══════════════════════════════
            window.PanelStatus = (function () {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

                function showToast(message, type = 'success') {
                    let host = document.getElementById('panel-toast-host');
                    if (!host) {
                        host = document.createElement('div');
                        host.id = 'panel-toast-host';
                        host.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
                        document.body.appendChild(host);
                    }
                    const colors = type === 'error'
                        ? { bg: '#fee2e2', fg: '#991b1b', bd: '#fca5a5' }
                        : { bg: '#dcfce7', fg: '#166534', bd: '#86efac' };
                    const t = document.createElement('div');
                    t.textContent = message;
                    t.style.cssText = `padding:10px 14px;background:${colors.bg};color:${colors.fg};border:1px solid ${colors.bd};border-radius:8px;font-size:13px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.08);min-width:240px;max-width:380px;`;
                    host.appendChild(t);
                    setTimeout(() => { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; }, 2700);
                    setTimeout(() => t.remove(), 3100);
                }

                return {
                    toggleMenu(panelId) {
                        // Ferme tous les autres avant d'ouvrir le mien
                        document.querySelectorAll('.panel-status-menu').forEach(m => {
                            if (m.id !== `panel-menu-${panelId}`) m.style.display = 'none';
                        });
                        const menu = document.getElementById(`panel-menu-${panelId}`);
                        if (!menu) return;
                        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                    },
                    async set(panelId, newStatus) {
                        document.getElementById(`panel-menu-${panelId}`).style.display = 'none';

                        try {
                            const r = await fetch(`/admin/panels/${panelId}/status`, {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({ status: newStatus, _token: csrf }),
                            });
                            const data = await r.json();
                            if (!r.ok || !data.ok) {
                                showToast(data.message || 'Erreur lors du changement de statut.', 'error');
                                return;
                            }

                            // MAJ in-place du badge + reconstruction du menu adapté
                            const wrap = document.querySelector(`.panel-status-wrap[data-panel-id="${panelId}"]`);
                            if (wrap) {
                                const trigger = wrap.querySelector('.panel-status-trigger');
                                trigger.className = 'badge ' + data.class + ' panel-status-trigger';
                                wrap.querySelector('.panel-status-label').textContent = data.label;

                                // Reconstruit le menu avec l'action inverse
                                const menu = document.getElementById(`panel-menu-${panelId}`);
                                menu.innerHTML = newStatus === 'maintenance'
                                    ? `<button type="button" class="panel-status-action" data-status="libre"
                                              onclick="PanelStatus.set(${panelId}, 'libre')"
                                              style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 10px;background:none;border:none;border-radius:6px;cursor:pointer;font-size:12px;color:var(--text);text-align:left;"
                                              onmouseenter="this.style.background='rgba(34,197,94,.1)'" onmouseleave="this.style.background='none'">
                                          ✅ Sortir de maintenance
                                       </button>`
                                    : `<button type="button" class="panel-status-action" data-status="maintenance"
                                              onclick="PanelStatus.set(${panelId}, 'maintenance')"
                                              style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 10px;background:none;border:none;border-radius:6px;cursor:pointer;font-size:12px;color:var(--text);text-align:left;"
                                              onmouseenter="this.style.background='rgba(239,68,68,.1)'" onmouseleave="this.style.background='none'">
                                          🛠 Mettre en maintenance
                                       </button>`;
                            }
                            showToast(data.message);
                        } catch (e) {
                            console.error(e);
                            showToast('Erreur réseau.', 'error');
                        }
                    },
                };
            })();
            (function() {
                let currentFilters = {
                    source: '{{ $source ?? 'all' }}',
                    search: '',
                    commune_id: '',
                    zone_id: '',
                    status: '',
                    category_id: '',
                    format_id: '',
                    client_id: ''
                };
                let debounceTimer = null;

                const elements = {
                    search: document.getElementById('filter-search'),
                    commune: document.getElementById('filter-commune'),
                    zone: document.getElementById('filter-zone'),
                    status: document.getElementById('filter-status'),
                    category: document.getElementById('filter-category'),
                    format: document.getElementById('filter-format'),
                    client: document.getElementById('filter-client'),
                    resetBtn: document.getElementById('btn-reset'),
                    resetWrapper: document.getElementById('reset-wrapper'),
                    sourceBtns: document.querySelectorAll('.filter-source-btn'),
                    statLinks: document.querySelectorAll('.filter-stat')
                };

                function updateResetButton() {
                    const hasFilters = currentFilters.search ||
                        currentFilters.commune_id ||
                        currentFilters.zone_id ||
                        currentFilters.status ||
                        currentFilters.category_id ||
                        currentFilters.format_id ||
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
                    if (currentFilters.category_id) params.set('category_id', currentFilters.category_id);
                    if (currentFilters.format_id) params.set('format_id', currentFilters.format_id);
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

                [elements.commune, elements.zone, elements.status, elements.category, elements.format, elements.client].forEach(el => {
                    if (el) {
                        el.addEventListener('change', () => {
                            currentFilters.commune_id = elements.commune?.value || '';
                            currentFilters.zone_id = elements.zone?.value || '';
                            currentFilters.status = elements.status?.value || '';
                            currentFilters.category_id = elements.category?.value || '';
                            currentFilters.format_id = elements.format?.value || '';
                            currentFilters.client_id = elements.client?.value || '';
                            updateResetButton();
                            applyFilters();
                        });
                    }
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
                                b.style.color = '';
                                b.style.borderColor = '';
                            } else {
                                b.classList.remove('btn-primary');
                                b.classList.add('btn-ghost');
                                if (b.dataset.source === 'externe') {
                                    b.style.color = 'var(--purple)';
                                    b.style.borderColor = 'rgba(168,85,247,0.3)';
                                } else if (b.dataset.source === 'occupes') {
                                    b.style.color = '#ef4444';
                                    b.style.borderColor = 'rgba(239,68,68,0.3)';
                                } else {
                                    b.style.color = '';
                                    b.style.borderColor = '';
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
                                btn.style.color = '';
                                btn.style.borderColor = '';
                            } else {
                                btn.classList.remove('btn-primary');
                                btn.classList.add('btn-ghost');
                                if (btn.dataset.source === 'externe') {
                                    btn.style.color = 'var(--purple)';
                                    btn.style.borderColor = 'rgba(168,85,247,0.3)';
                                } else if (btn.dataset.source === 'occupes') {
                                    btn.style.color = '#ef4444';
                                    btn.style.borderColor = 'rgba(239,68,68,0.3)';
                                } else {
                                    btn.style.color = '';
                                    btn.style.borderColor = '';
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
                            format_id: '',
                            client_id: ''
                        };

                        // Réinitialiser tous les champs
                        if (elements.search) elements.search.value = '';
                        if (elements.commune) elements.commune.value = '';
                        if (elements.zone) elements.zone.value = '';
                        if (elements.status) elements.status.value = '';
                        if (elements.category) elements.category.value = '';
                        if (elements.format) elements.format.value = '';
                        if (elements.client) elements.client.value = '';

                        // Réinitialiser l'apparence des boutons source
                        elements.sourceBtns.forEach(btn => {
                            if (btn.dataset.source === 'all') {
                                btn.classList.remove('btn-ghost');
                                btn.classList.add('btn-primary');
                                btn.style.color = '';
                                btn.style.borderColor = '';
                            } else {
                                btn.classList.remove('btn-primary');
                                btn.classList.add('btn-ghost');
                                if (btn.dataset.source === 'externe') {
                                    btn.style.color = 'var(--purple)';
                                    btn.style.borderColor = 'rgba(168,85,247,0.3)';
                                } else if (btn.dataset.source === 'occupes') {
                                    btn.style.color = '#ef4444';
                                    btn.style.borderColor = 'rgba(239,68,68,0.3)';
                                } else {
                                    btn.style.color = '';
                                    btn.style.borderColor = '';
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
                    if (currentFilters.format_id) params.set('format_id', currentFilters.format_id);
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
