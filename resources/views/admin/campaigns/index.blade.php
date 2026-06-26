<x-admin-layout title="Campagnes">
    <x-slot:topbarActions>
        {{-- Exports (préservent les filtres URL courants) --}}
        <a href="{{ route('admin.campaigns.export.excel', request()->query()) }}"
           class="btn btn-ghost btn-sm" title="Exporter la liste filtrée en Excel">
            📊 Excel
        </a>
        <a href="{{ route('admin.campaigns.export.pdf', request()->query()) }}"
           class="btn btn-ghost btn-sm" title="Exporter la liste filtrée en PDF">
            📄 PDF
        </a>
        @can('create', App\Models\Campaign::class)
        <a href="{{ route('admin.campaigns.import-pdf.form') }}" class="btn btn-ghost btn-sm" title="Importer une campagne depuis un PDF « Liste des panneaux commandes »">
            📥 Import PDF
        </a>
        <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary btn-sm">
            + Nouvelle campagne
        </a>
        @endcan
    </x-slot:topbarActions>

    {{-- ══ ALERTE FIN PROCHE — dismissible (localStorage 24h) ══ --}}
    @if(($endingSoonCount ?? 0) > 0)
    <div class="alert-warning-bar" id="ending-soon-banner" data-banner-key="ending-soon-{{ $endingSoonCount }}">
        <span class="alert-icon">⚠️</span>
        <span class="alert-text">{{ $endingSoonCount }} campagne(s) se terminent dans moins de 14 jours</span>
        <a href="{{ route('admin.campaigns.index', ['status' => 'actif', 'date_to' => now()->addDays(14)->format('Y-m-d')]) }}" class="alert-link">
            Voir →
        </a>
        <button type="button" class="alert-close"
                onclick="(function(b){
                    try { localStorage.setItem('dismissed-' + b.dataset.bannerKey, Date.now()); } catch(e) {}
                    b.style.display='none';
                })(this.parentElement)"
                title="Masquer cette alerte (24h)"
                aria-label="Fermer">✕</button>
    </div>
    <script>
        // Vérifie le localStorage au load : si l'alerte a été fermée il y a
        // moins de 24h, on la cache. Le compteur change le data-banner-key
        // donc une nouvelle alerte réapparaît même si l'ancienne était dismiss.
        (function() {
            const b = document.getElementById('ending-soon-banner');
            if (!b) return;
            const key = 'dismissed-' + b.dataset.bannerKey;
            try {
                const ts = parseInt(localStorage.getItem(key) || '0', 10);
                if (ts && (Date.now() - ts) < 24 * 3600 * 1000) {
                    b.style.display = 'none';
                }
            } catch(e) { /* localStorage indisponible : on laisse visible */ }
        })();
    </script>
    @endif

    {{-- ══ KPI cards (pattern unifié projet : bordure latérale colorée,
         toggle, état actif, counts qui suivent les filtres) ══ --}}
    @php
    $statCards = [
        ['key'=>'all',      'label'=>'Total',      'sub'=>'toutes campagnes',    'color'=>'var(--accent)', 'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>'],
        ['key'=>'planifie', 'label'=>'Planifiées', 'sub'=>'à démarrer',           'color'=>'#f97316',       'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'],
        ['key'=>'actif',    'label'=>'En cours',   'sub'=>'en affichage',         'color'=>'#22c55e',       'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>'],
        ['key'=>'pause',    'label'=>'En pause',   'sub'=>'temporairement gelées','color'=>'#f59e0b',       'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>'],
        ['key'=>'termine',  'label'=>'Terminées',  'sub'=>'achevées',             'color'=>'#3b82f6',       'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>'],
        ['key'=>'annule',   'label'=>'Annulées',   'sub'=>'campagnes annulées',   'color'=>'#ef4444',       'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/></svg>'],
    ];
    $activeStatus = request('status');
    $hasAnyFilter = request('search') || request('status') || request('client_id')
                  || request('date_from') || request('date_to') || request('date_debut')
                  || request('date_fin') || request('non_facturee')
                  || request('commune_id') || request('zone_id');
    @endphp
    {{-- KPI cards — design unifié --}}
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-bottom:20px">
        @foreach($statCards as $sc)
        @php
            $isAll    = $sc['key'] === 'all';
            $isActive = !$isAll && $activeStatus === $sc['key'];
            $val      = $isAll ? $campaigns->total() : ($counts[$sc['key']] ?? 0);
        @endphp
        <a href="#" class="kpi-card {{ $isActive ? 'is-active' : '' }}"
           data-filter="status" data-kpi="{{ $sc['key'] }}" data-value="{{ $isAll ? '' : $sc['key'] }}"
           style="--kpi-color:{{ $sc['color'] }};{{ $isActive ? 'border-color:'.$sc['color'].';' : '' }}"
           onmouseenter="if(!this.classList.contains('is-active')){this.style.borderColor='{{ $sc['color'] }}';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.12)'}"
           onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
            <div class="kpi-card__top-bar" style="background:{{ $sc['color'] }}"></div>
            <div class="kpi-card__icon" style="color:{{ $sc['color'] }}">{!! $sc['icon'] !!}</div>
            <div class="kpi-card__value" data-kpi-value="{{ $sc['key'] }}" style="color:{{ $sc['color'] }}">{{ number_format($val) }}</div>
            <div class="kpi-card__label">{{ $sc['label'] }}</div>
            <div class="kpi-card__sub">{{ $sc['sub'] }}</div>
            <div class="kpi-card__arrow" style="color:{{ $sc['color'] }}">→</div>
        </a>
        @endforeach
    </div>

    {{-- Responsive : sur petits écrans, 3 cartes par ligne au lieu de 6 --}}
    <style>
        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }
        }
        @media (max-width: 540px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }
    </style>

    {{-- ══ FILTRES DYNAMIQUES (sans bouton) ══ --}}
    <div class="filters-card">
        <div class="filters-grid">
            <div class="filter-group">
                <label class="filter-label">🔍 Recherche</label>
                <input type="text" id="filter-search" class="filter-input" 
                    placeholder="Nom de campagne…"
                    value="{{ request('search') }}"
                    data-filter="search">
            </div>

            <div class="filter-group">
                <label class="filter-label">👤 Client</label>
                <select id="filter-client" class="filter-select" data-filter="client_id">
                    <option value="">Tous</option>
                    @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ request('client_id') == $c->id ? 'selected' : '' }}>
                        {{ $c->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">📊 Statut</label>
                <select id="filter-status" class="filter-select" data-filter="status">
                    <option value="">Tous</option>
                    @foreach(\App\Enums\CampaignStatus::cases() as $s)
                    <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>
                        {{ $s->label() }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Filtre commercial assigné — RÉSERVÉ admin + Media Planner
                 (suivi du portefeuille des commerciaux). Le commercial lui-même
                 ne voit que ses campagnes via le scope auth (redondant), et le
                 comptable n'a pas l'info de pilotage portefeuille.
                 Réutilise la même logique d'appartenance (assigné direct, via
                 résa, ou créateur) que le scope commercial natif. --}}
            @if(in_array(auth()->user()?->role?->value, ['admin', 'mediaplanner'], true))
            <div class="filter-group">
                <label class="filter-label">💼 Commercial</label>
                <select id="filter-commercial" class="filter-select" data-filter="commercial_user_id">
                    <option value="">Tous</option>
                    @foreach($commerciaux ?? [] as $com)
                    <option value="{{ $com->id }}" {{ request('commercial_user_id') == $com->id ? 'selected' : '' }}>
                        {{ $com->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="filter-group">
                <label class="filter-label">📅 Du</label>
                <input type="date" id="filter-date-from" class="filter-input" 
                    value="{{ request('date_from') }}" data-filter="date_from">
            </div>

            <div class="filter-group">
                <label class="filter-label">📅 Au</label>
                <input type="date" id="filter-date-to" class="filter-input" 
                    value="{{ request('date_to') }}" data-filter="date_to">
            </div>

            <div class="filter-group">
                <label class="filter-label">📍 Commune</label>
                <select id="filter-commune" class="filter-select" data-filter="commune_id">
                    <option value="">Toutes les communes</option>
                    @foreach($communes as $commune)
                    <option value="{{ $commune->id }}" {{ request('commune_id') == $commune->id ? 'selected' : '' }}>
                        {{ $commune->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">📍 Zone</label>
                <select id="filter-zone" class="filter-select" data-filter="zone_id">
                    <option value="">Toutes les zones</option>
                    @foreach($zones as $zone)
                    <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                        {{ $zone->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group" id="reset-wrapper" style="display:none;">
                <label class="filter-label" style="visibility:hidden;">Actions</label>
                <button id="btn-reset" class="reset-btn">↺ Réinitialiser</button>
            </div>
        </div>
    </div>

    {{-- ══ TABLEAU DES CAMPAGNES ══ --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">Campagnes</span>
            <div class="stats-info">
                <span id="total-count" class="total-count">{{ $campaigns->total() }} résultat(s)</span>
                @if(($nonFactureesCount ?? 0) > 0)
                <span class="new-badge">💰 {{ $nonFactureesCount }} non facturée(s)</span>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            @php
                // Facturation : visible pour admin + commercial (le commercial
                // suit le règlement de son portefeuille). Cachée pour MP
                // (matrice FACTURES — MP ne facture pas).
                $authRole = auth()->user()?->role?->value;
                $canSeeBilling = in_array($authRole, ['admin', 'commercial'], true);
            @endphp
            <table class="data-table" id="campaigns-table">
                <thead>
                    <tr>
                        @if(in_array($authRole, ['admin', 'mediaplanner'], true))
                        <th style="width:36px;text-align:center;">
                            <input type="checkbox" data-bulk-select-all aria-label="Tout sélectionner">
                        </th>
                        @endif
                        {{-- Phase audit 8E refonte UI — passage de 10 à 7 colonnes
                             pour supprimer le scroll horizontal. Campagne + Client
                             + Commercial fusionnés dans une colonne 'Campagne /
                             Client'. Période + Durée fusionnés. Panneaux + Montant
                             fusionnés. Facturation cachée pour MP (déjà fait). --}}
                        <th>Campagne / Client</th>
                        <th>Période</th>
                        <th class="text-center">Panneaux / Montant</th>
                        <th>Statut</th>
                        @if($canSeeBilling)
                        <th class="text-center" style="width:120px">Facturation</th>
                        @endif
                        <th style="width:120px">Commercial</th>
                        <th style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody id="table-body">
                    @include('admin.campaigns.partials.table-rows', ['campaigns' => $campaigns])
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div id="pagination-container" class="pagination-container">
            {{ $campaigns->links() }}
        </div>
    </div>

    {{-- ── Barre d'action groupée campagnes (admin + MP) ──────────────
         Actions contextuelles : chaque action n'apparaît que si au moins
         une campagne sélectionnée y est éligible (selon son statut), avec
         un badge indiquant le nombre concerné. Inspiré des barres
         d'action de Linear / Notion. --}}
    @if(in_array($authRole, ['admin', 'mediaplanner'], true))
    <div class="camp-selbar" id="camp-selbar" aria-live="polite">
        <div class="camp-selbar-count">
            <span class="camp-selbar-num" id="camp-sel-num">0</span>
            <span class="camp-selbar-word">sélectionnée(s)</span>
        </div>
        <div class="camp-selbar-sep"></div>
        <div class="camp-selbar-actions">
            <button type="button" class="csa csa-go"     data-act="start"  data-verb="Démarrer">▶<span>Démarrer</span><i data-badge></i></button>
            <button type="button" class="csa"            data-act="pause"  data-verb="Mettre en pause">⏸<span>Pause</span><i data-badge></i></button>
            <button type="button" class="csa csa-go"     data-act="resume" data-verb="Reprendre">▶<span>Reprendre</span><i data-badge></i></button>
            <button type="button" class="csa csa-warn"   data-act="cancel" data-verb="Annuler">🚫<span>Annuler</span><i data-badge></i></button>
            <button type="button" class="csa csa-danger" data-act="delete" data-verb="Supprimer">🗑<span>Supprimer</span><i data-badge></i></button>
            <div class="csa-export" id="csa-export">
                <button type="button" class="csa" id="csa-export-btn">⬇<span>Exporter</span>▾</button>
                <div class="csa-export-menu" id="csa-export-menu">
                    <button type="button" data-export="pdf">📄 PDF de la sélection</button>
                    <button type="button" data-export="excel">📊 Excel de la sélection</button>
                </div>
            </div>
        </div>
        <button type="button" class="camp-selbar-close" id="camp-sel-clear" title="Tout désélectionner">✕</button>
    </div>

    <style>
        .camp-selbar {
            position: fixed; bottom: 24px; left: 50%;
            transform: translateX(-50%) translateY(180%);
            z-index: 1000; display: flex; align-items: center; gap: 12px;
            background: rgba(17, 24, 39, 0.97);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px; padding: 8px 10px 8px 16px;
            box-shadow: 0 16px 48px rgba(0,0,0,0.45);
            opacity: 0; transition: transform .3s cubic-bezier(.16,1,.3,1), opacity .2s ease;
            max-width: calc(100vw - 24px); flex-wrap: wrap;
        }
        .camp-selbar.open { transform: translateX(-50%) translateY(0); opacity: 1; }
        .camp-selbar-count { display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; }
        .camp-selbar-num {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 26px; height: 26px; padding: 0 8px;
            background: var(--accent); color: #fff;
            font-size: 13px; font-weight: 800; border-radius: 999px; line-height: 1;
        }
        .camp-selbar-word { font-size: 12.5px; color: rgba(255,255,255,0.65); font-weight: 600; }
        .camp-selbar-sep { width: 1px; height: 26px; background: rgba(255,255,255,0.12); }
        .camp-selbar-actions { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
        .csa {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.06); color: #fff;
            border: 1px solid rgba(255,255,255,0.05);
            padding: 8px 13px; border-radius: 9px;
            font-size: 12.5px; font-weight: 600; cursor: pointer; font-family: inherit;
            transition: background .13s, transform .08s, opacity .13s;
        }
        .csa:hover { background: rgba(255,255,255,0.15); }
        .csa:active { transform: translateY(1px); }
        .csa[hidden] { display: none; }
        .csa i[data-badge] {
            font-style: normal; font-size: 11px; font-weight: 800;
            background: rgba(255,255,255,0.2); color: #fff;
            min-width: 17px; height: 17px; padding: 0 4px; border-radius: 999px;
            display: inline-flex; align-items: center; justify-content: center; line-height: 1;
        }
        .csa-go:hover    { background: rgba(34,197,94,0.22); }
        .csa-go i[data-badge]   { background: rgba(34,197,94,0.4); }
        .csa-warn        { color: #fbbf24; }
        .csa-warn:hover  { background: rgba(245,158,11,0.18); }
        .csa-danger      { color: #f87171; }
        .csa-danger:hover{ background: rgba(239,68,68,0.18); }
        .csa-export { position: relative; }
        .csa-export-menu {
            display: none; position: absolute; bottom: calc(100% + 8px); right: 0;
            background: #1f2937; border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px; padding: 5px; min-width: 200px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.5);
        }
        .csa-export-menu.open { display: block; }
        .csa-export-menu button {
            display: block; width: 100%; text-align: left;
            background: none; border: none; color: #fff; cursor: pointer;
            padding: 9px 12px; border-radius: 7px; font-size: 12.5px; font-family: inherit;
        }
        .csa-export-menu button:hover { background: rgba(255,255,255,0.1); }
        .camp-selbar-close {
            background: none; border: none; color: rgba(255,255,255,0.5);
            cursor: pointer; font-size: 15px; padding: 6px 10px; border-radius: 8px;
            transition: background .13s, color .13s;
        }
        .camp-selbar-close:hover { background: rgba(255,255,255,0.1); color: #fff; }
        /* Surlignage ligne sélectionnée (cohérent accent) */
        #campaigns-table tr.bulk-selected td { background: var(--accent-dim, rgba(232,160,32,0.08)) !important; }
        #campaigns-table tr.bulk-selected td:first-child { box-shadow: inset 3px 0 0 var(--accent); }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const bar      = document.getElementById('camp-selbar');
        if (!bar) return;
        const tableSel = '#campaigns-table .bulk-checkbox';
        const selectAll = document.querySelector('#campaigns-table [data-bulk-select-all]');
        const numEl    = document.getElementById('camp-sel-num');
        const clearBtn = document.getElementById('camp-sel-clear');
        const exportBtn = document.getElementById('csa-export-btn');
        const exportMenu = document.getElementById('csa-export-menu');
        const BULK_URL = @json(route('admin.campaigns.bulk'));
        const EXPORT_PDF = @json(route('admin.campaigns.export.pdf'));
        const EXPORT_XLS = @json(route('admin.campaigns.export.excel'));
        const CSRF = document.querySelector('meta[name=csrf-token]')?.content || '';

        // Statuts éligibles par action (miroir exact du backend bulkAction).
        const ELIGIBLE = {
            start:  ['planifie'],
            pause:  ['actif'],
            resume: ['pause'],
            cancel: ['planifie', 'actif', 'pause'],
            delete: ['planifie', 'actif', 'pause', 'termine', 'annule'], // le service filtre finement
        };

        const boxes = () => Array.from(document.querySelectorAll(tableSel));
        const checked = () => boxes().filter(cb => cb.checked);

        function refresh() {
            const sel = checked();
            const n = sel.length;
            numEl.textContent = n;
            bar.classList.toggle('open', n > 0);

            // Highlight lignes
            boxes().forEach(cb => cb.closest('tr')?.classList.toggle('bulk-selected', cb.checked));

            // Compte par statut
            const byStatus = {};
            sel.forEach(cb => {
                const s = cb.dataset.status || '';
                byStatus[s] = (byStatus[s] || 0) + 1;
            });

            // Affiche/masque chaque action selon éligibilité + badge count
            bar.querySelectorAll('.csa[data-act]').forEach(btn => {
                const act = btn.dataset.act;
                const eligibleCount = (ELIGIBLE[act] || [])
                    .reduce((sum, st) => sum + (byStatus[st] || 0), 0);
                btn.hidden = eligibleCount === 0;
                const badge = btn.querySelector('[data-badge]');
                if (badge) badge.textContent = eligibleCount;
            });

            // État select-all
            if (selectAll) {
                const total = boxes().length;
                selectAll.checked = n === total && total > 0;
                selectAll.indeterminate = n > 0 && n < total;
            }
        }

        function selectedIds() {
            return checked().map(cb => cb.value);
        }

        // ── Délégation : checkboxes + select-all (survit aux refresh AJAX) ──
        document.addEventListener('change', (e) => {
            if (e.target.matches(tableSel)) refresh();
            if (e.target.matches('#campaigns-table [data-bulk-select-all]')) {
                boxes().forEach(cb => cb.checked = e.target.checked);
                refresh();
            }
        });

        clearBtn.addEventListener('click', () => {
            boxes().forEach(cb => cb.checked = false);
            refresh();
        });

        // ── Actions de statut ──────────────────────────────────────────
        bar.querySelectorAll('.csa[data-act]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const act  = btn.dataset.act;
                const verb = btn.dataset.verb;
                const ids  = selectedIds();
                if (ids.length === 0) return;
                const eligible = (ELIGIBLE[act] || []);
                const concerned = checked().filter(cb => eligible.includes(cb.dataset.status || ''));
                if (concerned.length === 0) return;

                let reason = '';
                if (act === 'cancel') {
                    reason = prompt(`Motif d'annulation (optionnel) pour ${concerned.length} campagne(s) :`) ?? null;
                    if (reason === null) return; // annulé
                }
                if (!confirm(`Confirmer : ${verb} ${concerned.length} campagne(s) ?`)) return;

                const fd = new FormData();
                concerned.forEach(cb => fd.append('ids[]', cb.value));
                fd.append('action', act);
                if (reason) fd.append('cancel_reason', reason);

                try {
                    const r = await fetch(BULK_URL, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                        body: fd,
                    });
                    if (r.redirected) { window.location = r.url; return; }
                    const data = await r.json().catch(() => ({}));
                    if (data.ok === false) { alert(data.error || 'Action impossible.'); return; }
                    window.location.reload();
                } catch (e) { alert('Erreur réseau : ' + e.message); }
            });
        });

        // ── Export de la sélection (PDF / Excel via GET + ids[]) ─────────
        exportBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            exportMenu.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (!exportMenu.contains(e.target) && e.target !== exportBtn) exportMenu.classList.remove('open');
        });
        exportMenu.querySelectorAll('[data-export]').forEach(btn => {
            btn.addEventListener('click', () => {
                const ids = selectedIds();
                if (ids.length === 0) return;
                const base = btn.dataset.export === 'pdf' ? EXPORT_PDF : EXPORT_XLS;
                const qs = ids.map(id => 'ids[]=' + encodeURIComponent(id)).join('&');
                // Téléchargement silencieux : le serveur renvoie
                // Content-Disposition: attachment → un lien <a download>
                // déclenche le download sans ouvrir d'onglet ni recharger
                // la page (la sélection reste intacte).
                const a = document.createElement('a');
                a.href = base + '?' + qs;
                a.download = '';
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                a.remove();
                exportMenu.classList.remove('open');
            });
        });

        // Re-sync après refresh AJAX du tbody (filtres / pagination)
        const tbody = document.getElementById('table-body');
        if (tbody) new MutationObserver(refresh).observe(tbody, { childList: true, subtree: true });

        refresh();
    });
    </script>
    @endif

    {{-- ══ MODAL SUPPRESSION ══ --}}
    <div id="modal-delete-campaign" class="modal-overlay" style="display:none;">
        <div class="modal" style="max-width:420px;">
            <div class="modal-header">
                <div class="modal-title" style="color:var(--red);">🗑 Supprimer la campagne</div>
                <button class="modal-close" onclick="closeDeleteCampaign()">✕</button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <div class="text-5xl mb-3">🗑</div>
                    <div class="font-bold text-lg mb-2">
                        Supprimer <span id="del-campaign-name" class="text-accent"></span> ?
                    </div>
                    <div class="text-sm text-gray-400 mb-4">
                        Suppression définitive. Les panneaux liés seront libérés si aucune reservation en cours, sinon annulez la reservation associée.
                    </div>
                    <div class="warning-box">
                        ⚠️ Uniquement possible si la campagne est annulée.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeDeleteCampaign()">Annuler</button>
                <form id="del-campaign-form" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger">🗑 Supprimer</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ══ MODAL FACTURATION RAPIDE ══
         Hotfix 2026-06-22 : refonte affichage demandée patronne. Avant :
         le STATUT ressemblait à un input désactivé (pas de flèche
         dropdown visible) → on croyait qu'il était readonly. Maintenant :
         chevron custom + libellés clairs + helpers + section "Aperçu"
         pour rappeler le total campagne. --}}
    <div id="modal-billing" class="modal-overlay" style="display:none;">
        <div class="modal" style="max-width:500px;width:100%;">
            <div class="modal-header">
                <div class="modal-title">💰 Facturation rapide — <span id="bill-campaign-name" style="font-size:15px;font-weight:500;"></span></div>
                <button class="modal-close" onclick="closeBillingModal()">✕</button>
            </div>
            <div class="modal-body">
                {{-- Aperçu : total théorique de la campagne pour repère --}}
                <div style="background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.25);border-radius:10px;padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
                    <div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text3);margin-bottom:2px">Total théorique campagne</div>
                        <div id="bill-total-display" style="font-size:14px;font-weight:800;color:var(--accent);font-variant-numeric:tabular-nums">— FCFA</div>
                    </div>
                    <button type="button" onclick="resetBillAmount()" style="background:none;border:1px solid var(--border);border-radius:7px;padding:5px 11px;font-size:11px;font-weight:600;color:var(--text2);cursor:pointer">↺ Pré-remplir</button>
                </div>

                <div style="display:grid;gap:14px;">
                    <div>
                        <label for="bill-status" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text3);display:block;margin-bottom:6px">
                            Statut <span style="color:#dc2626">*</span>
                        </label>
                        {{-- appearance:none + chevron SVG inline pour que la liste
                             déroulante soit explicitement signalée comme cliquable. --}}
                        <select id="bill-status" onchange="onBillStatusChange()"
                                style="width:100%;height:42px;padding:0 38px 0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;font-weight:600;color:var(--text);cursor:pointer;-webkit-appearance:none;-moz-appearance:none;appearance:none;background-image:url(&quot;data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>&quot;);background-repeat:no-repeat;background-position:right 12px center">
                            <option value="brouillon">📝 Brouillon — à finaliser plus tard</option>
                            <option value="envoyee">📤 Envoyée — en attente de paiement</option>
                            <option value="payee">✅ Payée — clôt la facture</option>
                            <option value="annulee">🚫 Annulée — historique conservé</option>
                        </select>
                        <small id="bill-status-hint" style="display:block;font-size:10.5px;color:var(--text3);margin-top:4px"></small>
                    </div>
                    <div>
                        <label for="bill-amount" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text3);display:block;margin-bottom:6px">
                            Montant TTC <span style="color:#dc2626">*</span>
                        </label>
                        <div style="position:relative">
                            <input type="number" id="bill-amount" step="1" min="0" placeholder="Ex : 3 540 000" oninput="updateBillAmountFormatted()"
                                   style="width:100%;height:42px;padding:0 56px 0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;font-weight:600;color:var(--text);box-sizing:border-box;font-variant-numeric:tabular-nums">
                            <span style="position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:11px;font-weight:700;color:var(--text3);pointer-events:none">FCFA</span>
                        </div>
                        <small id="bill-amount-formatted" style="display:block;font-size:10.5px;color:var(--text3);margin-top:4px;font-variant-numeric:tabular-nums"></small>
                    </div>
                    <div id="bill-paid-at-group">
                        <label for="bill-paid-at" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text3);display:block;margin-bottom:6px">
                            Date de paiement <span style="color:#dc2626">*</span>
                        </label>
                        <input type="date" id="bill-paid-at"
                               style="width:100%;height:42px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;font-weight:600;color:var(--text);box-sizing:border-box">
                        <small style="display:block;font-size:10.5px;color:var(--text3);margin-top:4px">Visible uniquement pour le statut « Payée ».</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeBillingModal()">Annuler</button>
                <button class="btn btn-primary" id="bill-submit-btn" onclick="submitBilling()">✅ Enregistrer</button>
            </div>
        </div>
    </div>

    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: var(--surface);
            border-radius: 12px;
            padding: 12px 14px;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .stat-card.active { box-shadow: 0 0 0 2px var(--accent); }
        .stat-icon { font-size: 20px; margin-bottom: 4px; }
        .stat-number { font-size: 22px; font-weight: 800; line-height: 1; }
        .stat-label { font-size: 10px; color: var(--text3); font-weight: 600; letter-spacing: 0.4px; margin-top: 4px; }

        .alert-warning-bar {
            background: rgba(232,160,32,0.08);
            border: 1px solid rgba(232,160,32,0.3);
            border-radius: 10px;
            padding: 10px 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-icon { font-size: 18px; }
        .alert-text { font-size: 13px; color: var(--accent); font-weight: 600; flex: 1; }
        .alert-link {
            font-size: 11px;
            color: var(--accent);
            text-decoration: none;
            padding: 4px 10px;
            border: 1px solid rgba(232,160,32,0.4);
            border-radius: 6px;
        }
        .alert-close {
            background: transparent;
            border: none;
            color: var(--accent);
            font-size: 16px;
            font-weight: 600;
            line-height: 1;
            padding: 4px 8px;
            margin-left: 4px;
            cursor: pointer;
            opacity: 0.7;
            border-radius: 6px;
            transition: opacity 0.15s, background 0.15s;
        }
        .alert-close:hover {
            opacity: 1;
            background: rgba(232,160,32,0.15);
        }

        .filters-card {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            align-items: end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); }
        .filter-input, .filter-select, .ci-select {
            height: 40px;
            padding: 0 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            color: var(--text);
        }
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
        .reset-btn:hover { background: var(--surface3); border-color: var(--danger); color: var(--danger); }

        .card {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .card-header {
            padding: 14px 20px;
            background: var(--surface2);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-title { font-size: 16px; font-weight: 600; }
        .stats-info { display: flex; align-items: center; gap: 12px; }
        .total-count { font-size: 12px; color: var(--text2); }
        .new-badge { font-size: 11px; font-weight: 600; color: var(--warning); background: rgba(232,160,32,0.1); padding: 3px 10px; border-radius: 20px; }

        /* Phase audit 8E refonte : pas de scroll horizontal.
           overflow-x: hidden + table-layout fixed + widths calés
           pour que la table tienne dans 1280px sans déborder. */
        .table-responsive { overflow-x: hidden; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }
        .data-table th {
            text-align: left;
            padding: 9px 8px;
            background: var(--surface2);
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }
        .data-table td {
            padding: 9px 8px;
            font-size: 12.5px;
            border-bottom: 1px solid var(--border);
            transition: background 0.12s;
            vertical-align: top;
        }
        .data-table tr:hover td { background: var(--surface2); }

        /* Colonne fusionnée Campagne / Client : largeur souple max,
           ellipsis sur le nom de campagne uniquement. Le client est en
           sous-titre déjà tronqué via Str::limit côté Blade. */
        .col-campaign { max-width: 280px; }
        .col-campaign .campaign-name {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .col-commercial {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .date-range { font-size: 11.5px; line-height: 1.35; white-space: nowrap; }
        .text-center { text-align: center; }

        .campaign-name { font-weight: 700; color: var(--text); text-decoration: none; }
        .client-link { color: var(--text); text-decoration: none; font-weight: 500; }
        .badge-panels { background: rgba(59,130,246,0.1); color: #60a5fa; padding: 2px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .amount { font-weight: 700; color: var(--accent); white-space: nowrap; }
        .status-badge { padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; border: 1px solid; }
        .progress-bar { margin-top: 5px; background: var(--surface3); border-radius: 3px; height: 3px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 3px; }
        .days-left { font-size: 9px; color: var(--text3); margin-top: 2px; }

        .pagination-container {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
        }
        .pagination { display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; }
        .pagination li a, .pagination li span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 12px;
            color: var(--text);
            text-decoration: none;
        }
        .pagination li.active span { background: var(--accent); border-color: var(--accent); color: #000; }
        .pagination li a:hover { background: var(--surface3); border-color: var(--accent); }

        .actions { display: flex; gap: 6px; }
        .btn-icon {
            background: transparent;
            border: none;
            font-size: 16px;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 6px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-icon:hover { background: var(--surface3); transform: scale(1.05); }
        .btn-delete { color: var(--danger); }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal {
            background: var(--surface);
            border-radius: 20px;
            border: 1px solid var(--border);
            max-width: 90%;
            overflow: hidden;
        }
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-title { font-size: 18px; font-weight: 600; }
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-muted);
        }
        .modal-body { padding: 20px; }
        .modal-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            
        }
        .warning-box {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.2);
            border-radius: 8px;
            padding: 10px;
            font-size: 12px;
            color: var(--red);
            display: flex;
            gap: 8px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
        }
        .btn-primary { background: var(--accent); color: #000; }
        .btn-danger { background: var(--danger); color: red; }
        .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text-dim); }
        .text-center { text-align: center; }
        .text-5xl { font-size: 48px; }
        .font-bold { font-weight: 700; }
        .text-lg { font-size: 18px; }
        .text-sm { font-size: 13px; }
        .text-gray-400 { color: #9ca3af; }
        .text-accent { color: var(--accent); }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .filters-grid { grid-template-columns: 1fr; }
            .data-table { font-size: 12px; }
            .data-table th, .data-table td { padding: 8px 10px; }
        }
    </style>

    @push('scripts')
{{-- Select2 — recherche live sur les filtres Client + Commercial.
     CDN partagé avec les autres vues qui en ont besoin
     (invoices/index, campaigns/create, etc.). --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<style>
/* Harmonise la pillule Select2 avec les autres filter-select natifs. */
.select2-container--default .select2-selection--single {
    height: 40px !important;
    padding: 0 4px !important;
    background: var(--surface2) !important;
    border: 1px solid var(--border2, var(--border)) !important;
    border-radius: 10px !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 40px !important;
    color: var(--text) !important;
    font-size: 13px !important;
    font-weight: 500 !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px !important; }
.select2-dropdown { border-radius: 10px !important; border-color: var(--border2, var(--border)) !important; }
.select2-search--dropdown .select2-search__field {
    border-radius: 8px !important;
    border-color: var(--border2, var(--border)) !important;
    padding: 8px 10px !important;
    font-size: 13px !important;
}
.select2-results__option--highlighted { background: var(--accent) !important; color: #fff !important; }
</style>

<script>
// ══ MODAL FACTURATION RAPIDE ══
let _billCampaignId = null;
let _billTotalCampaign = 0; // Total théorique mémorisé pour "Pré-remplir"

// Hotfix 2026-06-22 — helpers UX modale facturation rapide.
function _billFmtFcfa(n) {
    n = Math.round(Number(n) || 0);
    return n.toLocaleString('fr-FR').replace(/ /g, ' ');
}

function updateBillAmountFormatted() {
    const v = parseInt(document.getElementById('bill-amount').value, 10) || 0;
    document.getElementById('bill-amount-formatted').textContent =
        v > 0 ? '= ' + _billFmtFcfa(v) + ' FCFA' : '';
}

function resetBillAmount() {
    document.getElementById('bill-amount').value = Math.round(_billTotalCampaign || 0);
    updateBillAmountFormatted();
}

function openBillingModal(id, name, totalAmount, currentStatus, currentAmount, paidAt) {
    _billCampaignId = id;
    _billTotalCampaign = Number(totalAmount) || 0;
    document.getElementById('bill-campaign-name').textContent = name;
    document.getElementById('bill-status').value = currentStatus || 'brouillon';
    document.getElementById('bill-amount').value = currentAmount ? parseInt(String(currentAmount).replace(/\s/g,''), 10) : Math.round(_billTotalCampaign);
    document.getElementById('bill-paid-at').value = paidAt || '';
    document.getElementById('bill-total-display').textContent = _billFmtFcfa(_billTotalCampaign) + ' FCFA';
    updateBillAmountFormatted();
    onBillStatusChange();
    document.getElementById('modal-billing').style.display = 'flex';
}

function closeBillingModal() {
    document.getElementById('modal-billing').style.display = 'none';
    _billCampaignId = null;
    _billTotalCampaign = 0;
}

function onBillStatusChange() {
    const status = document.getElementById('bill-status').value;
    const group  = document.getElementById('bill-paid-at-group');
    const hint   = document.getElementById('bill-status-hint');
    group.style.display = status === 'payee' ? 'block' : 'none';
    if (status === 'payee' && !document.getElementById('bill-paid-at').value) {
        document.getElementById('bill-paid-at').value = new Date().toISOString().split('T')[0];
    }
    const hintMap = {
        brouillon: '📝 La facture reste modifiable. Aucun email envoyé au client.',
        envoyee:   '📤 La facture est figée et compte dans le « Reste à payer » du client.',
        payee:     '✅ Clôt la facture. Indique la date de paiement.',
        annulee:   '🚫 Statut historique conservé pour audit, sortie des KPIs créances.',
    };
    if (hint) hint.textContent = hintMap[status] || '';
}

// Toast réutilisable pour la page campagnes (succès / erreur, avec lien optionnel)
function showCampaignToast(message, type = 'success', linkUrl = null, linkLabel = null) {
    let host = document.getElementById('campaign-toast-host');
    if (!host) {
        host = document.createElement('div');
        host.id = 'campaign-toast-host';
        host.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
        document.body.appendChild(host);
    }
    const colors = type === 'error'
        ? { bg: '#fee2e2', fg: '#991b1b', bd: '#fca5a5' }
        : { bg: '#dcfce7', fg: '#166534', bd: '#86efac' };

    const t = document.createElement('div');
    t.style.cssText = `padding:12px 16px;background:${colors.bg};color:${colors.fg};border:1px solid ${colors.bd};border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 6px 16px rgba(0,0,0,.1);min-width:260px;max-width:420px;display:flex;flex-direction:column;gap:6px;`;

    const msg = document.createElement('div');
    msg.textContent = message;
    t.appendChild(msg);

    if (linkUrl && linkLabel) {
        const a = document.createElement('a');
        a.href = linkUrl;
        a.textContent = linkLabel + ' →';
        a.style.cssText = `color:${colors.fg};text-decoration:underline;font-size:12px;font-weight:700;`;
        t.appendChild(a);
    }

    host.appendChild(t);
    setTimeout(() => { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; }, 4500);
    setTimeout(() => t.remove(), 4900);
}

async function submitBilling() {
    if (!_billCampaignId) return;
    const btn = document.getElementById('bill-submit-btn');
    btn.disabled = true;
    btn.textContent = '…';

    const body = new FormData();
    body.append('_method', 'PATCH');
    body.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}');
    body.append('status', document.getElementById('bill-status').value);
    body.append('amount_ttc', document.getElementById('bill-amount').value);
    const paidAt = document.getElementById('bill-paid-at').value;
    if (paidAt) body.append('paid_at', paidAt);

    try {
        const res = await fetch(`/admin/campaigns/${_billCampaignId}/billing-quick`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body,
        });

        // Erreurs de validation (422) — Laravel renvoie {message, errors:{field:[...]}}
        if (res.status === 422) {
            const data = await res.json().catch(() => ({}));
            const firstError = data.errors
                ? Object.values(data.errors).flat()[0]
                : (data.message || 'Données invalides.');
            showCampaignToast(firstError, 'error');
            return;
        }

        const data = await res.json();
        if (!data.ok) {
            showCampaignToast(data.message || 'Erreur lors de la mise à jour.', 'error');
            return;
        }

        closeBillingModal();

        // Toast avec lien direct vers la fiche facture
        showCampaignToast(
            data.message || 'Facture mise à jour.',
            'success',
            data.invoice_url || null,
            'Voir la facture'
        );

        // Mise à jour in-place de la ligne — pas de fetchData() ni de
        // spinner, le bouton facturation reflète instantanément le nouveau
        // statut. Fallback sur fetchData() si le serveur n'a pas renvoyé
        // de row_html (compat anciennes réponses).
        if (data.row_html && data.campaign_id) {
            const oldRow = document.querySelector(`tr[data-campaign-row="${data.campaign_id}"]`);
            if (oldRow) {
                const wrapper = document.createElement('tbody');
                wrapper.innerHTML = data.row_html.trim();
                const newRow = wrapper.firstElementChild;
                if (newRow) oldRow.replaceWith(newRow);
            } else if (typeof fetchData === 'function') {
                fetchData();
            }
        } else if (typeof fetchData === 'function') {
            fetchData();
        }
    } catch(e) {
        console.error(e);
        showCampaignToast('Erreur réseau. Réessayez.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '✅ Enregistrer';
    }
}

// ══ MODAL SUPPRESSION ══
function openDeleteCampaign(id, name) {
    document.getElementById('del-campaign-name').textContent = name;
    document.getElementById('del-campaign-form').action = `/admin/campaigns/${id}`;
    document.getElementById('modal-delete-campaign').style.display = 'flex';
}
function closeDeleteCampaign() {
    document.getElementById('modal-delete-campaign').style.display = 'none';
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeDeleteCampaign();
});

// ══ FILTRES DYNAMIQUES ══
(function() {
    let currentFilters = {
        search: '',
        client_id: '',
        status: '',
        date_from: '',
        date_to: '',
        non_facturee: '',
        commune_id: '',
        zone_id: '',
        commercial_user_id: '',
        page: 1
    };
    let isLoading = false;
    let currentUrl = '{{ route("admin.campaigns.index") }}';
    let debounceTimer = null;

    function init() {
        const urlParams = new URLSearchParams(window.location.search);
        currentFilters.search = urlParams.get('search') || '';
        currentFilters.client_id = urlParams.get('client_id') || '';
        currentFilters.status = urlParams.get('status') || '';
        currentFilters.date_from = urlParams.get('date_from') || '';
        currentFilters.date_to = urlParams.get('date_to') || '';
        currentFilters.non_facturee = urlParams.get('non_facturee') || '';
        currentFilters.commune_id = urlParams.get('commune_id') || '';
        currentFilters.zone_id = urlParams.get('zone_id') || '';
        currentFilters.commercial_user_id = urlParams.get('commercial_user_id') || '';

        // Remplir les champs
        document.getElementById('filter-search').value = currentFilters.search;
        document.getElementById('filter-client').value = currentFilters.client_id;
        document.getElementById('filter-status').value = currentFilters.status;
        document.getElementById('filter-date-from').value = currentFilters.date_from;
        document.getElementById('filter-date-to').value = currentFilters.date_to;

        const factureSelect = document.getElementById('filter-facture');
        if (factureSelect) factureSelect.value = currentFilters.non_facturee;

        document.getElementById('filter-commune').value = currentFilters.commune_id;
        document.getElementById('filter-zone').value = currentFilters.zone_id;
        const commercialSelect = document.getElementById('filter-commercial');
        if (commercialSelect) commercialSelect.value = currentFilters.commercial_user_id;

        // Init Select2 sur les filtres avec beaucoup d'options (client +
        // commercial). IMPORTANT — Select2 4.x dispatch ses 'change' via
        // le système d'événements jQuery qui n'est PAS systématiquement
        // capté par addEventListener natif. On câble donc directement via
        // jQuery .on('change', applyFilters) sur ces 2 selects pour
        // garantir le déclenchement de la requête AJAX à chaque sélection.
        const hasSelect2 = window.jQuery && window.jQuery.fn && window.jQuery.fn.select2;
        if (hasSelect2) {
            window.jQuery('#filter-client').select2({
                placeholder: '🔍 Rechercher un client…',
                allowClear: true,
                width: '200px',
                language: {
                    noResults: () => 'Aucun client trouvé',
                    searching: () => 'Recherche…',
                },
            }).on('change', applyFilters);

            if (commercialSelect) {
                window.jQuery('#filter-commercial').select2({
                    placeholder: '🔍 Rechercher un commercial…',
                    allowClear: true,
                    width: '180px',
                    language: {
                        noResults: () => 'Aucun commercial',
                        searching: () => 'Recherche…',
                    },
                }).on('change', applyFilters);
            }
        }

        updateActiveStat();
        updateResetButton();

        // Événements (selects non Select2 + inputs). Pour client et
        // commercial le change est déjà câblé via jQuery .on() ci-dessus
        // si Select2 est dispo ; on garde addEventListener en fallback.
        document.getElementById('filter-search').addEventListener('input', debounce(applyFilters, 400));
        if (!hasSelect2) {
            document.getElementById('filter-client').addEventListener('change', applyFilters);
            if (commercialSelect) commercialSelect.addEventListener('change', applyFilters);
        }
        document.getElementById('filter-status').addEventListener('change', applyFilters);
        document.getElementById('filter-date-from').addEventListener('change', applyFilters);
        document.getElementById('filter-date-to').addEventListener('change', applyFilters);

        const factureFilter = document.getElementById('filter-facture');
        if (factureFilter) factureFilter.addEventListener('change', applyFilters);

        document.getElementById('filter-commune').addEventListener('change', applyFilters);
        document.getElementById('filter-zone').addEventListener('change', applyFilters);
        
        // Cartes stats
        document.querySelectorAll('.kpi-card[data-filter]').forEach(card => {
            card.addEventListener('click', (e) => {
                e.preventDefault();
                const filterValue = card.dataset.value;
                const statusSelect = document.getElementById('filter-status');
                statusSelect.value = filterValue;
                applyFilters();
            });
        });

        const resetBtn = document.getElementById('btn-reset');
        if (resetBtn) resetBtn.addEventListener('click', resetFilters);
        
        // Gestion historique navigateur
        window.addEventListener('popstate', function() {
            const params = new URLSearchParams(window.location.search);
            currentFilters.search = params.get('search') || '';
            currentFilters.client_id = params.get('client_id') || '';
            currentFilters.status = params.get('status') || '';
            currentFilters.date_from = params.get('date_from') || '';
            currentFilters.date_to = params.get('date_to') || '';
            currentFilters.commune_id = params.get('commune_id') || '';
            currentFilters.zone_id = params.get('zone_id') || '';
            
            // Mettre à jour les champs
            document.getElementById('filter-search').value = currentFilters.search;
            document.getElementById('filter-client').value = currentFilters.client_id;
            document.getElementById('filter-status').value = currentFilters.status;
            document.getElementById('filter-date-from').value = currentFilters.date_from;
            document.getElementById('filter-date-to').value = currentFilters.date_to;
            document.getElementById('filter-commune').value = currentFilters.commune_id;
            document.getElementById('filter-zone').value = currentFilters.zone_id;
            
            updateResetButton();
            updateActiveStat();
            fetchData();
        });
    }

    function applyFilters() {
        currentFilters.search = document.getElementById('filter-search').value;
        currentFilters.client_id = document.getElementById('filter-client').value;
        currentFilters.status = document.getElementById('filter-status').value;
        currentFilters.date_from = document.getElementById('filter-date-from').value;
        currentFilters.date_to = document.getElementById('filter-date-to').value;

        const factureSelect = document.getElementById('filter-facture');
        currentFilters.non_facturee = factureSelect ? factureSelect.value : '';

        currentFilters.commune_id = document.getElementById('filter-commune').value;
        currentFilters.zone_id = document.getElementById('filter-zone').value;
        const commercialSelect = document.getElementById('filter-commercial');
        currentFilters.commercial_user_id = commercialSelect ? commercialSelect.value : '';
        currentFilters.page = 1;

        updateResetButton();
        updateActiveStat();
        fetchData();
    }

    function resetFilters() {
        currentFilters = {
            search: '', client_id: '', status: '',
            date_from: '', date_to: '', non_facturee: '',
            commune_id: '', zone_id: '',
            commercial_user_id: '',
            page: 1
        };

        document.getElementById('filter-search').value = '';
        // Pour les <select> initialisés en Select2, on doit déclencher
        // .trigger('change') sinon le widget garde l'ancien affichage.
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery('#filter-client').val('').trigger('change');
            const $com = window.jQuery('#filter-commercial');
            if ($com.length) $com.val('').trigger('change');
        } else {
            document.getElementById('filter-client').value = '';
        }
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-date-from').value = '';
        document.getElementById('filter-date-to').value = '';

        const factureSelect = document.getElementById('filter-facture');
        if (factureSelect) factureSelect.value = '';

        document.getElementById('filter-commune').value = '';
        document.getElementById('filter-zone').value = '';
        const commercialSelect = document.getElementById('filter-commercial');
        if (commercialSelect) commercialSelect.value = '';

        updateResetButton();
        updateActiveStat();
        fetchData();
    }

    function updateActiveStat() {
        const activeStatus = currentFilters.status;
        document.querySelectorAll('.kpi-card[data-filter]').forEach(card => {
            const cardValue = card.dataset.value;
            // is-active UNIQUEMENT pour le statut précis filtré. Carte
            // "Tous" (cardValue vide) reste neutre.
            if (activeStatus && cardValue === activeStatus) {
                card.classList.add('is-active');
            } else {
                card.classList.remove('is-active');
            }
        });
    }

    function updateResetButton() {
        const hasFilters = currentFilters.search || currentFilters.client_id ||
                           currentFilters.status || currentFilters.date_from ||
                           currentFilters.date_to || currentFilters.non_facturee ||
                           currentFilters.commune_id || currentFilters.zone_id ||
                           currentFilters.commercial_user_id;
        const resetWrapper = document.getElementById('reset-wrapper');
        if (resetWrapper) {
            resetWrapper.style.display = hasFilters ? 'flex' : 'none';
        }
    }

    async function fetchData() {
        if (isLoading) return;
        isLoading = true;
        
        // Afficher un indicateur de chargement
        const tableBody = document.getElementById('table-body');
        const originalHtml = tableBody.innerHTML;
        tableBody.innerHTML = '<tr><td colspan="10" class="text-center"><div class="spinner"></div> Chargement...</td></tr>';
        
        const params = new URLSearchParams();
        if (currentFilters.search) params.set('search', currentFilters.search);
        if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
        if (currentFilters.status) params.set('status', currentFilters.status);
        if (currentFilters.date_from) params.set('date_from', currentFilters.date_from);
        if (currentFilters.date_to) params.set('date_to', currentFilters.date_to);
        if (currentFilters.non_facturee) params.set('non_facturee', currentFilters.non_facturee);
        if (currentFilters.commune_id) params.set('commune_id', currentFilters.commune_id);
        if (currentFilters.zone_id) params.set('zone_id', currentFilters.zone_id);
        if (currentFilters.commercial_user_id) params.set('commercial_user_id', currentFilters.commercial_user_id);
        params.set('ajax', '1');
        params.set('page', currentFilters.page);

        try {
            const response = await fetch(`${currentUrl}?${params}`, {
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) throw new Error('Erreur réseau');
            
            const data = await response.json();
            document.getElementById('table-body').innerHTML = data.html;
            document.getElementById('pagination-container').innerHTML = data.pagination;
            
            if (data.stats && data.stats.total !== undefined) {
                document.getElementById('total-count').textContent = data.stats.total + ' résultat(s)';

                // Met à jour le KPI "Total" (carte data-value vide = 'all')
                document.querySelectorAll('.kpi-card[data-filter]').forEach(card => {
                    const numEl = card.querySelector('.kpi-card__value');
                    if (!numEl) return;
                    const v = card.dataset.value;
                    if (!v) {
                        // Carte 'all' = total filtré
                        numEl.textContent = data.stats.total;
                    } else if (data.stats.counts && data.stats.counts[v] !== undefined) {
                        numEl.textContent = data.stats.counts[v];
                    }
                });
            }
            
            // Mettre à jour l'URL sans recharger
            const newUrl = buildUrl();
            window.history.pushState({}, '', newUrl);
            
            // Réattacher les événements aux nouveaux boutons
            attachPaginationEvents();
            
        } catch (error) {
            console.error('Erreur:', error);
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-red">Erreur de chargement</td></tr>';
        } finally {
            isLoading = false;
        }
    }
    
    function attachPaginationEvents() {
        document.querySelectorAll('.pagination a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = new URL(this.href);
                const page = url.searchParams.get('page');
                if (page) {
                    currentFilters.page = parseInt(page);
                    fetchData();
                }
            });
        });
    }

    function buildUrl() {
        const params = new URLSearchParams();
        if (currentFilters.search) params.set('search', currentFilters.search);
        if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
        if (currentFilters.status) params.set('status', currentFilters.status);
        if (currentFilters.date_from) params.set('date_from', currentFilters.date_from);
        if (currentFilters.date_to) params.set('date_to', currentFilters.date_to);
        if (currentFilters.commune_id) params.set('commune_id', currentFilters.commune_id);
        if (currentFilters.zone_id) params.set('zone_id', currentFilters.zone_id);
        if (currentFilters.commercial_user_id) params.set('commercial_user_id', currentFilters.commercial_user_id);
        if (currentFilters.non_facturee) params.set('non_facturee', currentFilters.non_facturee);
        if (currentFilters.page > 1) params.set('page', currentFilters.page);

        const query = params.toString();
        return query ? `${currentUrl}?${query}` : currentUrl;
    }

    function debounce(func, wait) {
        return function(...args) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => func(...args), wait);
        };
    }

    // Démarrer
    init();
})();
</script>

<style>
.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    margin-right: 8px;
    vertical-align: middle;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.text-center { text-align: center; }
.text-red { color: #ef4444; }
</style>
@endpush
</x-admin-layout>