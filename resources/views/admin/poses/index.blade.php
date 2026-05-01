<x-admin-layout title="Pose OOH">

<x-slot:topbarActions>
    <a href="{{ route('admin.pose-tasks.create') }}" class="btn btn-primary" style="display:flex;align-items:center;gap:6px">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle tâche
    </a>
</x-slot:topbarActions>

{{-- ════ ALERTES ACTIVITÉ DU MODULE ════ --}}
@if($overdueTasks->isNotEmpty() || $posesSansPige > 0)
<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:18px">

    @if($overdueTasks->isNotEmpty())
    <div style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.25);border-radius:12px;padding:12px 16px">
        <div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div style="width:34px;height:34px;background:rgba(239,68,68,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div style="flex:1;min-width:200px">
                <div style="font-size:13px;font-weight:700;color:#ef4444;margin-bottom:6px">
                    {{ $overdueTasks->count() }} tâche(s) en retard — Date de pose dépassée
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:6px">
                    @foreach($overdueTasks->take(6) as $t)
                    <a href="{{ route('admin.pose-tasks.show', $t) }}"
                       style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:20px;font-size:11px;color:#ef4444;text-decoration:none;font-weight:600">
                        <span style="font-family:monospace">{{ $t->panel?->reference }}</span>
                        <span style="opacity:.6;font-size:10px">{{ $t->scheduled_at?->format('d/m') }}</span>
                    </a>
                    @endforeach
                    @if($overdueTasks->count() > 6)
                    <span style="padding:3px 10px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);border-radius:20px;font-size:11px;color:#ef4444">+{{ $overdueTasks->count()-6 }} autres</span>
                    @endif
                </div>
            </div>
            <a href="{{ route('admin.pose-tasks.index', ['status'=>'planifiee']) }}"
               style="flex-shrink:0;font-size:11px;color:#ef4444;font-weight:700;text-decoration:none;padding:6px 12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;white-space:nowrap;align-self:flex-start">
                Voir tout →
            </a>
        </div>
    </div>
    @endif

    @if($posesSansPige > 0)
    <div style="background:rgba(249,115,22,.07);border:1px solid rgba(249,115,22,.25);border-radius:12px;padding:12px 16px;display:flex;align-items:center;gap:12px">
        <div style="width:34px;height:34px;background:rgba(249,115,22,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </div>
        <div style="flex:1">
            <div style="font-size:13px;font-weight:700;color:#f97316">{{ $posesSansPige }} pose(s) réalisée(s) sans pige photo</div>
            <div style="font-size:11px;color:rgba(249,115,22,.75);margin-top:2px">Aucune preuve d'affichage — impossible de facturer le client</div>
        </div>
        <a href="{{ route('admin.piges.index') }}"
           style="flex-shrink:0;font-size:11px;color:#f97316;font-weight:700;text-decoration:none;padding:6px 12px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);border-radius:8px;white-space:nowrap">
            Ajouter piges →
        </a>
    </div>
    @endif
</div>
@endif

{{-- ════ KPI avec filtres dynamiques ════ --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px" class="stats-grid">
@php
$kpis = [
    ['s'=>'planifiee','l'=>'Planifiées','v'=>$stats['planifiee']??0,'c'=>'#e8a020','bg'=>'rgba(232,160,32,.08)'],
    ['s'=>'en_cours', 'l'=>'En cours',  'v'=>$stats['en_cours'] ??0,'c'=>'#3b82f6','bg'=>'rgba(59,130,246,.08)'],
    ['s'=>'realisee', 'l'=>'Réalisées', 'v'=>$stats['realisee'] ??0,'c'=>'#22c55e','bg'=>'rgba(34,197,94,.08)'],
    ['s'=>'annulee',  'l'=>'Annulées',  'v'=>$stats['annulee']  ??0,'c'=>'#ef4444','bg'=>'rgba(239,68,68,.08)'],
];
@endphp
@foreach($kpis as $k)
@php $active = request('status') === $k['s']; @endphp
<a href="#" data-status="{{ $k['s'] }}"
   class="stat-card filter-stat {{ $active ? 'active' : '' }}"
   style="background:{{ $k['bg'] }};border:1px solid {{ $active ? $k['c'] : 'var(--border)' }};border-radius:14px;padding:16px 18px;text-decoration:none;display:block;transition:all .15s;{{ $active ? 'box-shadow:0 0 0 2px '.$k['c'].'30;' : '' }}">
    <div style="font-size:26px;font-weight:800;color:{{ $k['c'] }};line-height:1;margin-bottom:6px">{{ number_format($k['v']) }}</div>
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3)">{{ $k['l'] }}</div>
    @if($active)<div style="font-size:9px;color:{{ $k['c'] }};margin-top:3px;font-weight:600">Filtre actif ✓</div>@endif
</a>
@endforeach
</div>

{{-- ════ BARRE FILTRES + RECHERCHE (AJAX sans rechargement) ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:12px 16px;margin-bottom:14px">
    <div class="filter-bar" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        
        {{-- Recherche texte --}}
        <div class="filter-group" style="flex:1;min-width:200px">
            <label class="filter-label">Recherche</label>
            <div style="position:relative">
                <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text3);pointer-events:none" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="filter-search" class="filter-input" placeholder="Panneau, campagne, technicien, commune…"
                       style="padding-left:32px;height:38px;width:100%" autocomplete="off">
            </div>
        </div>

        {{-- Statut --}}
        <div class="filter-group">
            <label class="filter-label">Statut</label>
            <select id="filter-status" class="filter-select" style="width:130px">
                <option value="">Tous</option>
                @foreach(['planifiee'=>'📅 Planifiée','en_cours'=>'🔧 En cours','realisee'=>'✅ Réalisée','annulee'=>'🚫 Annulée'] as $v => $l)
                <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </select>
        </div>

        {{-- Technicien --}}
        <div class="filter-group">
            <label class="filter-label">Technicien</label>
            <select id="filter-technicien" class="filter-select" style="width:150px">
                <option value="">Tous</option>
                @foreach($techniciens as $t)
                <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Campagne --}}
        <div class="filter-group">
            <label class="filter-label">Campagne</label>
            <select id="filter-campaign" class="filter-select" style="width:180px">
                <option value="">Toutes</option>
                @foreach($campaigns as $c)
                <option value="{{ $c->id }}">{{ $c->status->uiConfig()['icon'] }} {{ Str::limit($c->name, 25) }}</option>
                @endforeach
            </select>
        </div>

        {{-- Dates --}}
        <div class="filter-group">
            <label class="filter-label">Du</label>
            <input type="date" id="filter-date-from" class="filter-input" style="width:130px">
        </div>
        <div class="filter-group">
            <label class="filter-label">Au</label>
            <input type="date" id="filter-date-to" class="filter-input" style="width:130px">
        </div>

        {{-- Actions --}}
        <div class="filter-group" id="reset-wrapper" style="display:none;">
            <label class="filter-label" style="visibility:hidden;">Actions</label>
            <button id="btn-reset" class="btn-reset" style="display:flex;align-items:center;gap:4px;">
                ↺ Réinitialiser
            </button>
        </div>

        {{-- Compteur --}}
        <div class="filter-group" style="margin-left:auto;">
            <label class="filter-label" style="visibility:hidden;">&nbsp;</label>
            <div class="result-badge">
                <strong id="result-count">{{ number_format($poseTasks->total()) }}</strong> résultat(s)
            </div>
        </div>
    </div>
</div>

{{-- ════ TABLEAU ════ --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            Tâches de pose
        </div>
        <div class="legend">
            <span><span class="legend-dot" style="background:#ef4444;"></span>En retard</span>
            <span><span class="legend-dot" style="background:#f97316;"></span>Sans pige</span>
            <span><span class="legend-dot" style="background:#22c55e;"></span>Pigée</span>
        </div>
    </div>

    <div id="table-container">
        @include('admin.poses.partials.table-rows', ['poseTasks' => $poseTasks])
    </div>

    @if($poseTasks->hasPages())
    <div id="pagination-container" style="padding:16px;">
        {{ $poseTasks->links() }}
    </div>
    @endif
</div>

{{-- ════ POLLING TEMPS RÉEL : progression des poses ════ --}}
<script>
(function () {
    const POLL_URL      = "{{ route('admin.pose-tasks.progress') }}";
    const POLL_INTERVAL = 30_000; // 30 s

    function getVisibleTaskIds() {
        return Array.from(document.querySelectorAll('tr[data-pose-id]'))
            .map(tr => Number(tr.dataset.poseId))
            .filter(Boolean);
    }

    function colorFor(p) {
        p = Number(p);
        if (p >= 100) return '#22c55e';
        if (p >=  67) return '#3b82f6';
        if (p >=  34) return '#f59e0b';
        return '#ef4444';
    }

    async function poll() {
        const ids = getVisibleTaskIds();
        if (!ids.length) return;

        try {
            const url = new URL(POLL_URL, window.location.origin);
            ids.forEach(id => url.searchParams.append('ids[]', id));

            const res = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data = await res.json();

            (data.tasks || []).forEach(t => {
                const fill = document.querySelector(`[data-pose-progress="${t.id}"]`);
                if (fill) {
                    fill.style.width = t.percent + '%';
                    fill.style.background = t.color || colorFor(t.percent);
                    const textEl = fill.closest('td')?.querySelector('.pose-progress-text');
                    if (textEl) textEl.textContent = t.percent + '%';
                }

                // Si le statut a changé, signaler visuellement (subtil — pas d'overlay agressif)
                const row = document.querySelector(`tr[data-pose-id="${t.id}"]`);
                if (row) {
                    if (t.is_done) row.dataset.poseStatus = 'realisee';
                    else if (t.is_running) row.dataset.poseStatus = 'en_cours';
                }
            });
        } catch (e) {
            // Silencieux — réseau instable
        }
    }

    // Démarre le polling après 5s (pour ne pas charger la page initiale + polling en concurrence)
    setTimeout(poll, 5000);
    setInterval(poll, POLL_INTERVAL);
})();
</script>

{{-- ════ MODAL CONFIRMATION ════ --}}
<div id="modal-confirm" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:18px;width:100%;max-width:400px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.4)">
        <div style="padding:20px 22px 16px">
            <div id="modal-confirm-icon" style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:14px"></div>
            <div id="modal-confirm-title" style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:8px"></div>
            <div id="modal-confirm-body" style="font-size:13px;color:var(--text2);line-height:1.5"></div>
        </div>
        <div style="padding:14px 22px 20px;display:flex;gap:8px;justify-content:flex-end">
            <button onclick="Confirm.cancel()" style="padding:8px 18px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text2);cursor:pointer;font-weight:500">Annuler</button>
            <button id="modal-confirm-btn" style="padding:8px 20px;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer"></button>
        </div>
    </div>
</div>

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
.reset-btn:hover { background: var(--surface3); border-color: var(--danger); color: var(--danger); }

.filter-select, .filter-input { height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:12px;color:var(--text);outline:none; }
.filter-select:focus, .filter-input:focus { border-color:var(--accent); }
.filter-label { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:4px; }
.filter-group { display:flex;flex-direction:column; }
.result-badge { height:38px;display:flex;align-items:center;font-size:12px;color:var(--text3);white-space:nowrap; }
.legend { display:flex;gap:16px;font-size:10px;color:var(--text3); }
.legend-dot { width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:5px; }
.stat-card { cursor:pointer; transition:all .15s; }
.stat-card:hover { transform:translateY(-2px); }
.stat-card.active { border-width:2px !important; }
.spinner { display:inline-block;width:20px;height:20px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:8px; }
@keyframes spin { to { transform: rotate(360deg); } }
.btn-reset { display:flex;align-items:center;justify-content:center;height:38px;padding:0 16px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text3);text-decoration:none;font-size:12px;transition:all .15s;cursor:pointer;font-weight:500; }
.btn-reset:hover { border-color:var(--accent);color:var(--accent); }
</style>

@push('scripts')
<script>
// ════════════════════════════════════════════════════════════
// MODAL CONFIRMATION
// ════════════════════════════════════════════════════════════
window.Confirm = {
    _cb: null,
    show(body, type = 'confirm', callback) {
        this._cb = callback;
        const cfg = {
            confirm: { icon:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>', ibg:'rgba(59,130,246,.12)', btnBg:'#3b82f6', btnTxt:'Confirmer', title:'Confirmer l\'action' },
            danger:  { icon:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>', ibg:'rgba(239,68,68,.12)', btnBg:'#ef4444', btnTxt:'Supprimer', title:'Confirmation de suppression' },
            warning: { icon:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>', ibg:'rgba(249,115,22,.12)', btnBg:'#f97316', btnTxt:'Confirmer', title:'Confirmer l\'action' },
        };
        const c = cfg[type] || cfg.confirm;

        const iconEl = document.getElementById('modal-confirm-icon');
        const titleEl = document.getElementById('modal-confirm-title');
        const bodyEl = document.getElementById('modal-confirm-body');
        const btnEl = document.getElementById('modal-confirm-btn');

        if (iconEl) { iconEl.innerHTML = c.icon; iconEl.style.background = c.ibg; }
        if (titleEl) titleEl.textContent = c.title;
        if (bodyEl) bodyEl.innerHTML = body;
        if (btnEl) {
            btnEl.textContent = c.btnTxt;
            btnEl.style.background = c.btnBg;
            btnEl.style.color = '#fff';
            btnEl.onclick = () => { this.cancel(); if (callback) callback(); };
        }

        const modal = document.getElementById('modal-confirm');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => btnEl?.focus(), 50);
        }
    },
    cancel() {
        const modal = document.getElementById('modal-confirm');
        if (modal) modal.style.display = 'none';
        this._cb = null;
    },
};

document.getElementById('modal-confirm')?.addEventListener('click', function(e) {
    if (e.target === this) Confirm.cancel();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') Confirm.cancel(); });

// ════════════════════════════════════════════════════════════
// FILTRAGE AJAX DYNAMIQUE
// ════════════════════════════════════════════════════════════
(function() {
    let currentFilters = {
        search: '',
        status: '',
        technicien_id: '',
        campaign_id: '',
        date_from: '',
        date_to: ''
    };
    let debounceTimer = null;
    let isUpdating = false;

    const elements = {
        search: document.getElementById('filter-search'),
        status: document.getElementById('filter-status'),
        technicien: document.getElementById('filter-technicien'),
        campaign: document.getElementById('filter-campaign'),
        dateFrom: document.getElementById('filter-date-from'),
        dateTo: document.getElementById('filter-date-to'),
        resetBtn: document.getElementById('btn-reset'),
        resetWrapper: document.getElementById('reset-wrapper'),
        resultCount: document.getElementById('result-count'),
        tableContainer: document.getElementById('table-container'),
        paginationContainer: document.getElementById('pagination-container')
    };

    function updateResetButton() {
        const hasFilters = currentFilters.search ||
                          currentFilters.status ||
                          currentFilters.technicien_id ||
                          currentFilters.campaign_id ||
                          currentFilters.date_from ||
                          currentFilters.date_to;
        if (elements.resetWrapper) {
            elements.resetWrapper.style.display = hasFilters ? 'flex' : 'none';
        }
    }

    async function applyFilters() {
        if (isUpdating) return;
        isUpdating = true;

        const params = new URLSearchParams();
        if (currentFilters.search) params.set('q', currentFilters.search);
        if (currentFilters.status) params.set('status', currentFilters.status);
        if (currentFilters.technicien_id) params.set('technicien_id', currentFilters.technicien_id);
        if (currentFilters.campaign_id) params.set('campaign_id', currentFilters.campaign_id);
        if (currentFilters.date_from) params.set('date_from', currentFilters.date_from);
        if (currentFilters.date_to) params.set('date_to', currentFilters.date_to);
        params.set('ajax', '1');

        // Afficher le loader
        if (elements.tableContainer) {
            elements.tableContainer.style.opacity = '0.5';
            elements.tableContainer.style.transition = 'opacity 0.2s';
        }

        try {
            const response = await fetch(`{{ route("admin.pose-tasks.index") }}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const data = await response.json();

            if (data.html && elements.tableContainer) {
                elements.tableContainer.innerHTML = data.html;
                elements.tableContainer.style.opacity = '1';
            }
            
            if (elements.resultCount && data.total) {
                elements.resultCount.textContent = data.total;
            }
            
            if (elements.paginationContainer && data.pagination) {
                elements.paginationContainer.innerHTML = data.pagination;
            }

            // Mettre à jour l'URL sans recharger
            const url = new URL(window.location.href);
            Object.keys(currentFilters).forEach(key => {
                const value = currentFilters[key];
                if (value) url.searchParams.set(key === 'search' ? 'q' : key, value);
                else url.searchParams.delete(key === 'search' ? 'q' : key);
            });
            window.history.pushState({}, '', url);

        } catch (error) {
            console.error('Erreur:', error);
            if (elements.tableContainer) {
                elements.tableContainer.style.opacity = '1';
            }
        } finally {
            isUpdating = false;
        }
    }

    function debounceApply() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => applyFilters(), 400);
    }

    // Écouteurs d'événements
    if (elements.search) {
        elements.search.addEventListener('input', () => {
            currentFilters.search = elements.search.value;
            updateResetButton();
            debounceApply();
        });
    }

    if (elements.status) {
        elements.status.addEventListener('change', () => {
            currentFilters.status = elements.status.value;
            updateResetButton();
            applyFilters();
            
            // Mettre à jour l'apparence des cartes KPI
            document.querySelectorAll('.stat-card').forEach(card => {
                const status = card.dataset.status;
                if (status === currentFilters.status || (status && !currentFilters.status)) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }
            });
        });
    }

    const selectElements = [elements.technicien, elements.campaign];
    selectElements.forEach(el => {
        if (el) {
            el.addEventListener('change', () => {
                currentFilters.technicien_id = elements.technicien?.value || '';
                currentFilters.campaign_id = elements.campaign?.value || '';
                updateResetButton();
                applyFilters();
            });
        }
    });

    const dateElements = [elements.dateFrom, elements.dateTo];
    dateElements.forEach(el => {
        if (el) {
            el.addEventListener('change', () => {
                currentFilters.date_from = elements.dateFrom?.value || '';
                currentFilters.date_to = elements.dateTo?.value || '';
                updateResetButton();
                applyFilters();
            });
        }
    });

    // Cartes KPI
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            const status = card.dataset.status;
            if (status && elements.status) {
                elements.status.value = status;
                currentFilters.status = status;
                updateResetButton();
                applyFilters();
                
                document.querySelectorAll('.stat-card').forEach(c => {
                    if (c.dataset.status === status) {
                        c.classList.add('active');
                    } else {
                        c.classList.remove('active');
                    }
                });
            }
        });
    });

    // Reset button
    if (elements.resetBtn) {
        elements.resetBtn.addEventListener('click', () => {
            currentFilters = {
                search: '',
                status: '',
                technicien_id: '',
                campaign_id: '',
                date_from: '',
                date_to: ''
            };
            
            if (elements.search) elements.search.value = '';
            if (elements.status) elements.status.value = '';
            if (elements.technicien) elements.technicien.value = '';
            if (elements.campaign) elements.campaign.value = '';
            if (elements.dateFrom) elements.dateFrom.value = '';
            if (elements.dateTo) elements.dateTo.value = '';
            
            document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active'));
            
            updateResetButton();
            applyFilters();
        });
    }

    // Initialiser les valeurs depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('q')) currentFilters.search = urlParams.get('q');
    if (urlParams.has('status')) currentFilters.status = urlParams.get('status');
    if (urlParams.has('technicien_id')) currentFilters.technicien_id = urlParams.get('technicien_id');
    if (urlParams.has('campaign_id')) currentFilters.campaign_id = urlParams.get('campaign_id');
    if (urlParams.has('date_from')) currentFilters.date_from = urlParams.get('date_from');
    if (urlParams.has('date_to')) currentFilters.date_to = urlParams.get('date_to');
    
    if (elements.search && currentFilters.search) elements.search.value = currentFilters.search;
    if (elements.status && currentFilters.status) elements.status.value = currentFilters.status;
    if (elements.technicien && currentFilters.technicien_id) elements.technicien.value = currentFilters.technicien_id;
    if (elements.campaign && currentFilters.campaign_id) elements.campaign.value = currentFilters.campaign_id;
    if (elements.dateFrom && currentFilters.date_from) elements.dateFrom.value = currentFilters.date_from;
    if (elements.dateTo && currentFilters.date_to) elements.dateTo.value = currentFilters.date_to;
    
    updateResetButton();
})();

// ════════════════════════════════════════════════════════════
// BOUTONS "MARQUER RÉALISÉE" AVEC CONFIRMATION
// ════════════════════════════════════════════════════════════
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.action-btn-success');
    if (!btn) return;
    
    e.preventDefault();
    e.stopPropagation();
    
    const form = btn.closest('td')?.querySelector('form');
    if (!form) return;
    
    Confirm.show(
        'Cette action marquera la tâche comme réalisée. Êtes-vous sûr ?',
        'confirm',
        () => form.submit()
    );
});
</script>
@endpush
</x-admin-layout>