<x-admin-layout title="Alertes & Notifications">

    <x-slot:topbarActions>
        <button id="btn-clear-read" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px" title="Supprimer toutes les alertes lues (garde les non lues)">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
            </svg>
            Vider les lues
        </button>
    </x-slot:topbarActions>

    {{-- ════ KPI 4 cartes (filtres rapides par niveau) ═══════════════
         Comptes calculés sur les alertes ACTIVES (non archivées), pas
         seulement les non lues — sinon les KPI tomberaient à 0 après le
         mark-all-as-read automatique à l'ouverture. Les chiffres suivent
         les filtres appliqués (type/niveau/non lues) et se mettent à jour
         en AJAX quand l'utilisateur change un filtre. --}}
    @php
        // Carte 'total' = action "tout afficher" (reset des filtres niveau).
        // Cartes danger/warning/info = filtrent la liste sur le niveau.
        $kpis = [
            ['key' => 'total',   'label' => 'Total alertes',   'val' => $summary['total'],   'color' => '#e8a020', 'filter' => 'all',
             'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>'],
            ['key' => 'danger',  'label' => 'Danger',          'val' => $summary['danger'],  'color' => '#ef4444', 'filter' => 'danger',
             'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'],
            ['key' => 'warning', 'label' => 'Avertissements',  'val' => $summary['warning'], 'color' => '#f97316', 'filter' => 'warning',
             'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'],
            ['key' => 'info',    'label' => 'Informations',    'val' => $summary['info'],    'color' => '#3b82f6', 'filter' => 'info',
             'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'],
        ];
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px">
        @foreach ($kpis as $k)
            <button data-filter="{{ $k['filter'] }}"
                    data-kpi="{{ $k['key'] }}"
                    class="kpi-card {{ ($k['filter'] === 'all' && !request('niveau') && !request('type') && !request()->boolean('non_lues')) || request('niveau') === $k['filter'] ? 'active' : '' }}"
                    style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 20px;border-left:4px solid {{ $k['color'] }};cursor:pointer;text-align:left;transition:all .15s">
                <div style="color:{{ $k['color'] }};margin-bottom:8px">{!! $k['icon'] !!}</div>
                <div data-kpi-value style="font-size:28px;font-weight:800;color:{{ $k['color'] }};line-height:1">{{ number_format($k['val']) }}</div>
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3);margin-top:6px">{{ $k['label'] }}</div>
            </button>
        @endforeach
    </div>

    {{-- ════ FILTRES ═════════════════════════════════════════════════ --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 18px;margin-bottom:16px">
        <div class="filter-bar" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
            <div class="filter-group">
                <label class="filter-label">Niveau</label>
                <select id="filter-niveau" class="filter-select" style="width:170px">
                    <option value="">Tous niveaux</option>
                    <option value="danger"  {{ request('niveau') === 'danger'  ? 'selected' : '' }}>🔴 Danger</option>
                    <option value="warning" {{ request('niveau') === 'warning' ? 'selected' : '' }}>🟠 Avertissement</option>
                    <option value="info"    {{ request('niveau') === 'info'    ? 'selected' : '' }}>🔵 Information</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Motif</label>
                <select id="filter-type" class="filter-select" style="width:240px">
                    <option value="">Tous les motifs</option>
                    @php
                        $byGroup = $types->groupBy('group');
                    @endphp
                    @foreach ($byGroup as $groupName => $items)
                        <optgroup label="{{ $groupName }}">
                            @foreach ($items as $t)
                                <option value="{{ $t['code'] }}" {{ request('type') === $t['code'] ? 'selected' : '' }}>
                                    {{ $t['icon'] }} {{ $t['label'] }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label" style="opacity:0">·</label>
                <label style="display:flex;align-items:center;gap:8px;height:38px;cursor:pointer">
                    <input type="checkbox" id="filter-non-lues"
                           style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer"
                           {{ request()->boolean('non_lues') ? 'checked' : '' }}>
                    <span style="font-size:12px;color:var(--text2);font-weight:500">Non lues seulement</span>
                </label>
            </div>

            <div class="filter-group" id="reset-wrapper" style="display:none;">
                <label class="filter-label" style="visibility:hidden;">·</label>
                <button id="btn-reset" class="btn-reset">↺ Réinitialiser</button>
            </div>

            <div class="filter-group" style="margin-left:auto;">
                <label class="filter-label" style="visibility:hidden;">·</label>
                <div class="result-badge">
                    <strong id="result-count">{{ number_format($alertes->total()) }}</strong> alerte(s)
                </div>
            </div>
        </div>
    </div>

    {{-- ════ LISTE ════════════════════════════════════════════════════ --}}
    <div id="alerts-container"
         style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        @include('admin.alertes.partials.alerts-list', ['alertes' => $alertes])
    </div>

    <style>
        .filter-bar { font-size: 12px; }
        .filter-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text3); display: block; margin-bottom: 4px; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-select { height: 38px; padding: 0 12px; background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; font-size: 12px; color: var(--text); outline: none; }
        .filter-select:focus { border-color: var(--accent); }
        .btn-reset { height: 38px; padding: 0 18px; background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; color: var(--text2); font-size: 12px; cursor: pointer; transition: all .15s; }
        .btn-reset:hover { background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.3); color: #ef4444; }
        .result-badge { height: 38px; display: flex; align-items: center; font-size: 12px; color: var(--text3); white-space: nowrap; }

        .kpi-card { cursor: pointer; transition: all .15s; border: 2px solid transparent; }
        .kpi-card:hover { transform: translateY(-2px); }
        .kpi-card.active { box-shadow: 0 0 0 2px var(--accent); }

        @keyframes alertFade { to { opacity: 0; transform: translateX(10px); } }
    </style>

    @push('scripts')
        <script>
        // ════════════════════════════════════════════════════════════════
        // ALERTES — actions et filtrage AJAX
        // ════════════════════════════════════════════════════════════════
        (function () {
            const csrf = '{{ csrf_token() }}';
            let isUpdating = false;
            const filters = {
                niveau:  '{{ request('niveau') }}',
                type:    '{{ request('type') }}',
                non_lues: {{ request()->boolean('non_lues') ? 'true' : 'false' }},
            };

            const $ = id => document.getElementById(id);
            const els = {
                niveau:    $('filter-niveau'),
                type:      $('filter-type'),
                nonLues:   $('filter-non-lues'),
                reset:     $('btn-reset'),
                resetWrap: $('reset-wrapper'),
                count:     $('result-count'),
                container: $('alerts-container'),
                clearRead: $('btn-clear-read'),
            };

            function syncResetVisibility() {
                const has = filters.niveau || filters.type || filters.non_lues;
                if (els.resetWrap) els.resetWrap.style.display = has ? 'flex' : 'none';
            }

            async function applyFilters(extraParams = {}) {
                if (isUpdating) return;
                isUpdating = true;

                const params = new URLSearchParams();
                if (filters.niveau)  params.set('niveau', filters.niveau);
                if (filters.type)    params.set('type',   filters.type);
                if (filters.non_lues) params.set('non_lues', '1');
                Object.entries(extraParams).forEach(([k, v]) => params.set(k, v));
                params.set('ajax', '1');

                if (els.container) els.container.style.opacity = '0.5';

                try {
                    const res = await fetch(`{{ route('admin.alerts.index') }}?${params}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
                    });
                    const data = await res.json();
                    if (els.container) {
                        els.container.innerHTML = data.html;
                        els.container.style.opacity = '1';
                    }
                    if (els.count) els.count.textContent = data.total;

                    // Met à jour les 4 KPI cards avec le summary courant
                    if (data.summary) {
                        document.querySelectorAll('.kpi-card').forEach(card => {
                            const key = card.dataset.kpi;
                            const val = data.summary[key];
                            if (val !== undefined) {
                                const el = card.querySelector('[data-kpi-value]');
                                if (el) el.textContent = new Intl.NumberFormat('fr-FR').format(val);
                            }
                        });
                    }

                    // URL sync (sans rechargement)
                    const url = new URL(location.href);
                    ['niveau', 'type', 'non_lues'].forEach(k => {
                        if (filters[k]) url.searchParams.set(k, filters[k] === true ? '1' : filters[k]);
                        else url.searchParams.delete(k);
                    });
                    history.pushState({}, '', url);

                    // Met à jour active state des KPI
                    //   - "all"  : actif quand AUCUN filtre n'est posé
                    //   - autre  : actif quand son code === filters.niveau
                    const noFilter = !filters.niveau && !filters.type && !filters.non_lues;
                    document.querySelectorAll('.kpi-card').forEach(c => {
                        const f = c.dataset.filter;
                        c.classList.toggle('active',
                            (f === 'all' && noFilter) ||
                            (f !== 'all' && f === filters.niveau)
                        );
                    });
                } catch (e) {
                    console.error('alerts.filter.error', e);
                    if (els.container) els.container.style.opacity = '1';
                } finally {
                    isUpdating = false;
                }
            }

            // ── Listeners filtres ────────────────────────────────────
            els.niveau?.addEventListener('change', e => {
                filters.niveau = e.target.value;
                syncResetVisibility(); applyFilters();
            });
            els.type?.addEventListener('change', e => {
                filters.type = e.target.value;
                syncResetVisibility(); applyFilters();
            });
            els.nonLues?.addEventListener('change', e => {
                filters.non_lues = e.target.checked;
                syncResetVisibility(); applyFilters();
            });

            // KPI cards = filtre rapide niveau
            //   - "all"     : reset complet (niveau + type + non_lues vidés)
            //   - autre     : toggle filter niveau (clique 2 fois = retire)
            document.querySelectorAll('.kpi-card').forEach(card => {
                card.addEventListener('click', () => {
                    const f = card.dataset.filter;
                    if (f === 'all') {
                        filters.niveau   = '';
                        filters.type     = '';
                        filters.non_lues = false;
                        if (els.niveau)  els.niveau.value = '';
                        if (els.type)    els.type.value = '';
                        if (els.nonLues) els.nonLues.checked = false;
                    } else {
                        filters.niveau = (filters.niveau === f) ? '' : f;
                        if (els.niveau) els.niveau.value = filters.niveau;
                    }
                    syncResetVisibility(); applyFilters();
                });
            });

            els.reset?.addEventListener('click', () => {
                filters.niveau = '';
                filters.type = '';
                filters.non_lues = false;
                if (els.niveau)  els.niveau.value = '';
                if (els.type)    els.type.value = '';
                if (els.nonLues) els.nonLues.checked = false;
                syncResetVisibility(); applyFilters();
            });

            // ── Pagination AJAX ──────────────────────────────────────
            document.addEventListener('click', e => {
                const link = e.target.closest('#alerts-container a[href*="page="]');
                if (!link) return;
                e.preventDefault();
                const url = new URL(link.href);
                const page = url.searchParams.get('page');
                if (page) applyFilters({ page });
            });

            // ── Actions par alerte (déléguées sur le container) ──────
            els.container?.addEventListener('click', async e => {
                const readBtn    = e.target.closest('.mark-read-btn');
                const archiveBtn = e.target.closest('.archive-alert-btn');
                const deleteBtn  = e.target.closest('.delete-alert-btn');

                if (readBtn)    return ALERTS.markRead(readBtn.dataset.id, readBtn);
                if (archiveBtn) return ALERTS.archive(archiveBtn.dataset.id, archiveBtn);
                if (deleteBtn)  return ALERTS.destroy(deleteBtn.dataset.id, deleteBtn);
            });

            // ── Vider les alertes lues (toolbar) ─────────────────────
            els.clearRead?.addEventListener('click', () => ALERTS.clearRead());

            syncResetVisibility();

            window.ALERTS = {
                async _do(method, url, btn) {
                    if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; }
                    try {
                        const res = await fetch(url, {
                            method,
                            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }
                        });
                        return res.ok ? res.json() : null;
                    } catch { return null; }
                },
                async markRead(id, btn) {
                    const data = await this._do('POST', `/admin/alerts/${id}/read`, btn);
                    if (data?.success) {
                        const row = document.getElementById('alert-' + id);
                        if (row) {
                            row.style.background = '';
                            row.style.borderLeft = '';
                            row.querySelectorAll('.mark-read-btn').forEach(b => b.remove());
                        }
                        showToast('success', 'Alerte marquée comme lue.');
                    }
                },
                async archive(id, btn) {
                    if (!confirm('Archiver cette alerte ? Elle ne sera plus visible mais l\'historique reste consultable.')) return;
                    const data = await this._do('POST', `/admin/alerts/${id}/archive`, btn);
                    if (data?.success) {
                        this._removeRow(id, 'Alerte archivée.');
                        applyFilters();   // KPI rafraîchis
                    }
                },
                async destroy(id, btn) {
                    if (!confirm('Supprimer définitivement cette alerte ? Cette action est irréversible.')) return;
                    const data = await this._do('DELETE', `/admin/alerts/${id}`, btn);
                    if (data?.success) {
                        this._removeRow(id, 'Alerte supprimée.');
                        applyFilters();
                    }
                },
                async clearRead() {
                    if (!confirm('Supprimer toutes les alertes lues ? Les non lues seront conservées.')) return;
                    const data = await this._do('POST', `{{ route('admin.alerts.clear-read') }}`, null);
                    if (data?.success) {
                        showToast('success', `${data.deleted ?? 0} alerte(s) supprimée(s).`);
                        applyFilters();
                    }
                },
                _removeRow(id, msg) {
                    const row = document.getElementById('alert-' + id);
                    if (row) {
                        row.style.animation = 'alertFade .25s ease forwards';
                        setTimeout(() => row.remove(), 260);
                    }
                    showToast('success', msg);
                },
            };

            function showToast(type, message) {
                const colors = { success: '#22c55e', error: '#ef4444', info: '#3b82f6' };
                const t = document.createElement('div');
                t.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 18px;background:var(--surface);border-left:3px solid ${colors[type] || '#22c55e'};border-radius:10px;font-size:13px;color:var(--text);box-shadow:0 8px 24px rgba(0,0,0,.25);max-width:340px`;
                t.textContent = message;
                document.body.appendChild(t);
                setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 3000);
            }

            // Au chargement de la page : badge cloche → 0 (server l'a déjà fait,
            // mais on force le DOM à rester cohérent même si polling JS arrive).
            const navBadge = document.getElementById('alert-badge');
            if (navBadge) navBadge.style.display = 'none';
        })();
        </script>
    @endpush
</x-admin-layout>
