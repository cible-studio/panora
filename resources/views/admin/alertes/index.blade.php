<x-admin-layout title="Alertes & Notifications">

<x-slot:topbarActions>
    @if ($totalNonLues > 0)
        <button id="btn-mark-all-read" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
            </svg>
            Tout marquer lu
        </button>
    @endif
</x-slot:topbarActions>

{{-- ════ KPI (filtres AJAX) ════ --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
    @php
        $kpis = [
            ['label' => 'Non lues', 'val' => $totalNonLues, 'color' => '#e8a020', 'filter' => 'non_lues'],
            ['label' => 'Danger', 'val' => $totalDanger, 'color' => '#ef4444', 'filter' => 'danger'],
            ['label' => 'Avertissements', 'val' => $totalWarning, 'color' => '#f97316', 'filter' => 'warning'],
            ['label' => 'Informations', 'val' => $totalInfo, 'color' => '#3b82f6', 'filter' => 'info'],
        ];
    @endphp
    @foreach ($kpis as $k)
        <div data-filter="{{ $k['filter'] }}" class="kpi-card filter-stat {{ request('niveau') === $k['filter'] ? 'active' : '' }}"
            style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 20px;border-left:4px solid {{ $k['color'] }};cursor:pointer;transition:all .15s">
            <div style="color:{{ $k['color'] }};margin-bottom:8px">
                @if($k['label'] == 'Non lues')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                @elseif($k['label'] == 'Danger')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                @elseif($k['label'] == 'Avertissements')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                @else
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                @endif
            </div>
            <div style="font-size:28px;font-weight:800;color:{{ $k['color'] }};line-height:1">{{ number_format($k['val']) }}</div>
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3);margin-top:4px">{{ $k['label'] }}</div>
        </div>
    @endforeach
</div>

{{-- ════ FILTRES AJAX DYNAMIQUES ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 18px;margin-bottom:16px">
    <div class="filter-bar" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="filter-group">
            <label class="filter-label">Niveau</label>
            <select id="filter-niveau" class="filter-select" style="width:150px">
                <option value="">Tous</option>
                <option value="danger" {{ request('niveau') === 'danger' ? 'selected' : '' }}>🔴 Danger</option>
                <option value="warning" {{ request('niveau') === 'warning' ? 'selected' : '' }}>🟠 Avertissement</option>
                <option value="info" {{ request('niveau') === 'info' ? 'selected' : '' }}>🔵 Information</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Module</label>
            <select id="filter-type" class="filter-select" style="width:150px">
                <option value="">Tous</option>
                @foreach ($types as $type)
                    <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label" style="opacity:0">Filtre</label>
            <div style="display:flex;align-items:center;gap:8px;height:38px">
                <input type="checkbox" id="filter-non-lues" style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer" {{ request()->boolean('non_lues') ? 'checked' : '' }}>
                <label for="filter-non-lues" style="font-size:12px;color:var(--text2);cursor:pointer;font-weight:500">Non lues seulement</label>
            </div>
        </div>
        
        <div class="filter-group" id="reset-wrapper" style="display:none;">
            <label class="filter-label" style="visibility:hidden;">Actions</label>
            <button id="btn-reset" class="btn-reset" style="display:flex;align-items:center;gap:4px;">
                ↺ Réinitialiser
            </button>
        </div>

        <div class="filter-group" style="margin-left:auto;">
            <label class="filter-label" style="visibility:hidden;">&nbsp;</label>
            <div class="result-badge">
                <strong id="result-count">{{ number_format($alertes->total()) }}</strong> alerte(s)
            </div>
        </div>
    </div>
</div>

{{-- ════ LISTE ALERTES (AJAX) ════ --}}
<div id="alerts-container" style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
    @include('admin.alertes.partials.alerts-list', ['alertes' => $alertes, 'totalNonLues' => $totalNonLues])
</div>

<style>
    .btn-reset{
height: 40px;
padding: 0 20px;
background: var(--surface2);
border: 1px solid var(--border);
border-radius: 10px;
color: var(--text-muted);
font-size: 12px;
cursor: pointer;
}
.btn-reset:hover { background: var(--surface3); border-color: var(--danger); color: var(--danger); }
.filter-select { height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:12px;color:var(--text);outline:none; }
.filter-select:focus { border-color:var(--accent); }
.filter-label { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:4px; }
.filter-group { display:flex;flex-direction:column; }
.result-badge { height:38px;display:flex;align-items:center;font-size:12px;color:var(--text3);white-space:nowrap; }
.kpi-card { cursor:pointer; transition:all .15s; border:2px solid transparent; }
.kpi-card:hover { transform:translateY(-2px); }
.kpi-card.active { border-color:var(--accent) !important; }
.spinner { display:inline-block;width:20px;height:20px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:8px; }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes alertFade { to { opacity:0; transform:translateX(10px); } }
</style>

@push('scripts')
<script>
// ════════════════════════════════════════════════════════════
// FILTRAGE AJAX DYNAMIQUE
// ════════════════════════════════════════════════════════════
(function() {
    let currentFilters = {
        niveau: '',
        type: '',
        non_lues: false
    };
    let isUpdating = false;

    const elements = {
        niveau: document.getElementById('filter-niveau'),
        type: document.getElementById('filter-type'),
        nonLues: document.getElementById('filter-non-lues'),
        resetBtn: document.getElementById('btn-reset'),
        resetWrapper: document.getElementById('reset-wrapper'),
        resultCount: document.getElementById('result-count'),
        alertsContainer: document.getElementById('alerts-container'),
        markAllReadBtn: document.getElementById('btn-mark-all-read')
    };

    function updateResetButton() {
        const hasFilters = currentFilters.niveau || currentFilters.type || currentFilters.non_lues;
        if (elements.resetWrapper) {
            elements.resetWrapper.style.display = hasFilters ? 'flex' : 'none';
        }
    }

    async function applyFilters() {
        if (isUpdating) return;
        isUpdating = true;

        const params = new URLSearchParams();
        if (currentFilters.niveau) params.set('niveau', currentFilters.niveau);
        if (currentFilters.type) params.set('type', currentFilters.type);
        if (currentFilters.non_lues) params.set('non_lues', '1');
        params.set('ajax', '1');

        if (elements.alertsContainer) {
            elements.alertsContainer.style.opacity = '0.5';
            elements.alertsContainer.style.transition = 'opacity 0.2s';
        }

        try {
            const response = await fetch(`{{ route("admin.alerts.index") }}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const data = await response.json();

            if (data.html && elements.alertsContainer) {
                elements.alertsContainer.innerHTML = data.html;
                elements.alertsContainer.style.opacity = '1';
                reattachEvents();
            }
            
            if (elements.resultCount && data.total) {
                elements.resultCount.textContent = data.total;
            }

            // Mettre à jour le bouton "Tout marquer lu"
            if (elements.markAllReadBtn && data.non_lues > 0) {
                elements.markAllReadBtn.style.display = 'flex';
            } else if (elements.markAllReadBtn) {
                elements.markAllReadBtn.style.display = 'none';
            }

            const url = new URL(window.location.href);
            Object.keys(currentFilters).forEach(key => {
                if (currentFilters[key]) url.searchParams.set(key, currentFilters[key]);
                else url.searchParams.delete(key);
            });
            window.history.pushState({}, '', url);

        } catch (error) {
            console.error('Erreur:', error);
            if (elements.alertsContainer) elements.alertsContainer.style.opacity = '1';
        } finally {
            isUpdating = false;
        }
    }

    // Réattacher les événements après chargement AJAX
    function reattachEvents() {
        // Marquer comme lu
        document.querySelectorAll('.mark-read-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id = btn.dataset.id;
                if (id) ALERTS.markRead(id, btn);
            });
        });
        
        // Supprimer
        document.querySelectorAll('.delete-alert-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id = btn.dataset.id;
                if (id) ALERTS.destroy(id, btn);
            });
        });
        
        // Tout marquer lu
        const markAllBtn = document.getElementById('btn-mark-all-read');
        if (markAllBtn) {
            markAllBtn.onclick = () => ALERTS.markAllRead();
        }
    }

    // Écouteurs d'événements
    if (elements.niveau) {
        elements.niveau.addEventListener('change', () => {
            currentFilters.niveau = elements.niveau.value;
            updateResetButton();
            applyFilters();
            
            document.querySelectorAll('.kpi-card').forEach(card => {
                const filter = card.dataset.filter;
                if (filter === currentFilters.niveau) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }
            });
        });
    }

    if (elements.type) {
        elements.type.addEventListener('change', () => {
            currentFilters.type = elements.type.value;
            updateResetButton();
            applyFilters();
        });
    }

    if (elements.nonLues) {
        elements.nonLues.addEventListener('change', () => {
            currentFilters.non_lues = elements.nonLues.checked;
            updateResetButton();
            applyFilters();
        });
    }

    // Cartes KPI
    document.querySelectorAll('.kpi-card').forEach(card => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            const filter = card.dataset.filter;
            if (filter === 'non_lues') {
                if (elements.nonLues) {
                    elements.nonLues.checked = !elements.nonLues.checked;
                    currentFilters.non_lues = elements.nonLues.checked;
                }
                if (elements.niveau) elements.niveau.value = '';
                currentFilters.niveau = '';
            } else {
                if (elements.nonLues) elements.nonLues.checked = false;
                currentFilters.non_lues = false;
                if (elements.niveau) {
                    elements.niveau.value = filter;
                    currentFilters.niveau = filter;
                }
            }
            updateResetButton();
            applyFilters();
            
            document.querySelectorAll('.kpi-card').forEach(c => {
                if (c.dataset.filter === filter) {
                    c.classList.add('active');
                } else {
                    c.classList.remove('active');
                }
            });
        });
    });

    // Reset button
    if (elements.resetBtn) {
        elements.resetBtn.addEventListener('click', () => {
            currentFilters = { niveau: '', type: '', non_lues: false };
            if (elements.niveau) elements.niveau.value = '';
            if (elements.type) elements.type.value = '';
            if (elements.nonLues) elements.nonLues.checked = false;
            
            document.querySelectorAll('.kpi-card').forEach(card => card.classList.remove('active'));
            
            updateResetButton();
            applyFilters();
        });
    }

    // Initialiser les valeurs depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('niveau')) currentFilters.niveau = urlParams.get('niveau');
    if (urlParams.has('type')) currentFilters.type = urlParams.get('type');
    if (urlParams.has('non_lues')) currentFilters.non_lues = true;
    
    if (elements.niveau && currentFilters.niveau) elements.niveau.value = currentFilters.niveau;
    if (elements.type && currentFilters.type) elements.type.value = currentFilters.type;
    if (elements.nonLues && currentFilters.non_lues) elements.nonLues.checked = true;
    
    updateResetButton();
})();

// ════════════════════════════════════════════════════════════
// ALERTES ACTIONS
// ════════════════════════════════════════════════════════════
window.ALERTS = {
    csrf: '{{ csrf_token() }}',

    async markRead(id, btn) {
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⟳';
        try {
            const res = await fetch(`/admin/alerts/${id}/read`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                const row = document.getElementById('alert-' + id);
                if (row) {
                    row.style.borderLeft = '';
                    row.style.background = '';
                    const newBadge = row.querySelector('[style*="Nouveau"]');
                    if (newBadge) newBadge.remove();
                    btn.remove();
                }
                this.showToast('Alerte marquée comme lue.', 'success');
                setTimeout(() => location.reload(), 500);
            }
        } catch {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    },

    async markAllRead() {
        if (!confirm('Marquer toutes les alertes comme lues ?')) return;
        try {
            const res = await fetch('{{ route("admin.alerts.read-all") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' }
            });
            if (res.ok) {
                this.showToast('Toutes les alertes ont été marquées comme lues.', 'success');
                setTimeout(() => location.reload(), 500);
            }
        } catch {
            this.showToast('Erreur', 'error');
        }
    },

    async destroy(id, btn) {
        if (!confirm('Supprimer cette alerte ?')) return;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        try {
            const res = await fetch(`/admin/alerts/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                const row = document.getElementById('alert-' + id);
                if (row) {
                    row.style.animation = 'alertFade .3s ease forwards';
                    setTimeout(() => row.remove(), 300);
                }
                this.showToast('Alerte supprimée.', 'success');
            }
        } catch {
            btn.disabled = false;
        }
    },

    showToast(message, type) {
        const colors = { success: '#22c55e', error: '#ef4444', info: '#3b82f6' };
        const toast = document.createElement('div');
        toast.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 18px;background:var(--surface);border-left:3px solid ${colors[type] || '#22c55e'};border-radius:10px;font-size:13px;color:var(--text);box-shadow:0 8px 24px rgba(0,0,0,.25);animation:slideIn .3s ease;max-width:320px`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; setTimeout(() => toast.remove(), 300); }, 3000);
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const badge = document.getElementById('alert-badge');
    if (badge) badge.style.display = 'none';
});

// Supprimer les alertes vues quand on quitte la page
const alertIds = @json($alertes->where('is_read', false)->pluck('id'));
window.addEventListener('beforeunload', function() {
    if (alertIds.length === 0) return;
    navigator.sendBeacon('/admin/alerts/delete-seen', JSON.stringify({ ids: alertIds, _token: '{{ csrf_token() }}' }));
});
</script>
@endpush

</x-admin-layout>