@extends('client.layout')
@section('title', 'Mes campagnes')
@section('page-title', 'Mes campagnes')

@section('content')

{{-- ══ FILTRES — auto-submit sur changement ══ --}}
<div style="margin-bottom:20px;">
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        <div style="flex:1;min-width:180px;position:relative;">
            <input type="text" id="filter-search" placeholder="Rechercher une campagne..."
                   value="{{ request('search') }}"
                   style="width:100%;background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:9px 14px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                   onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
        </div>
        <select id="filter-status"
                style="background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:9px 14px;font-size:13px;color:var(--text);outline:none;cursor:pointer;transition:border-color .15s;"
                onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
            <option value="">Tous les statuts</option>
            <option value="actif"    {{ request('status') == 'actif'    ? 'selected' : '' }}>Actif</option>
            <option value="pose"     {{ request('status') == 'pose'     ? 'selected' : '' }}>En pose</option>
            <option value="planifie" {{ request('status') == 'planifie' ? 'selected' : '' }}>Planifiée</option>
            <option value="termine"  {{ request('status') == 'termine'  ? 'selected' : '' }}>Terminée</option>
            <option value="annule"   {{ request('status') == 'annule'   ? 'selected' : '' }}>Annulée</option>
        </select>
        <div id="filter-spinner" style="display:none;width:16px;height:16px;border:2px solid var(--border2);border-top-color:#e20613;border-radius:50%;animation:spin .6s linear infinite;flex-shrink:0;"></div>
        @if(request('search') || request('status'))
        <a href="{{ route('client.campagnes') }}"
           style="padding:9px 16px;background:var(--surface);border:1px solid var(--border2);border-radius:9px;font-size:13px;color:var(--text2);text-decoration:none;transition:all .15s;"
           onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">
            ↺ Effacer
        </a>
        @endif
    </div>
</div>

{{-- ══ TABLEAU ══ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid var(--border2);">
                    <th style="text-align:left;padding:12px 16px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">Nom</th>
                    <th style="text-align:left;padding:12px 16px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">Période</th>
                    <th style="text-align:left;padding:12px 16px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">Panneaux</th>
                    <th style="text-align:left;padding:12px 16px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">Montant</th>
                    <th style="text-align:left;padding:12px 16px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">Statut</th>
                    <th style="padding:12px 16px;width:80px;"></th>
                </tr>
            </thead>
            <tbody id="campagnes-tbody">
                @include('client.partials.campagnes-rows')
            </tbody>
        </table>
    </div>
</div>

@if($campagnes->hasPages())
<div id="campagnes-pagination" style="margin-top:20px;">
    {{ $campagnes->appends(request()->query())->links() }}
</div>
@endif

@push('scripts')
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
<script>
(function () {
    const searchInput  = document.getElementById('filter-search');
    const statusSelect = document.getElementById('filter-status');
    const tbody        = document.getElementById('campagnes-tbody');
    const spinner      = document.getElementById('filter-spinner');
    let debounceTimer;

    async function filterCampagnes() {
        const search = searchInput.value.trim();
        const status = statusSelect.value;
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (status) params.set('status', status);

        spinner.style.display = 'block';

        // Met à jour l'URL sans recharger la page
        history.pushState({}, '', '?' + params.toString());

        try {
            const res = await fetch('/client/campagnes?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error();
            const data = await res.json();
            tbody.innerHTML = data.html;
        } catch {
            tbody.innerHTML = '<tr><td colspan="6" style="padding:40px;text-align:center;color:#ef4444;font-size:13px;">Erreur de chargement — veuillez réessayer.</td></tr>';
        } finally {
            spinner.style.display = 'none';
        }
    }

    // Statut : déclenche immédiatement
    statusSelect.addEventListener('change', filterCampagnes);

    // Recherche : debounce 400ms
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(filterCampagnes, 400);
    });

    // Restaurer les filtres de l'URL au chargement
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('status')) statusSelect.value = urlParams.get('status');
    if (urlParams.get('search')) searchInput.value  = urlParams.get('search');
})();
</script>
@endpush

<style>
.pagination { display:flex;justify-content:center;gap:6px;flex-wrap:wrap; }
.pagination .page-link { display:flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 10px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text2);font-size:13px;transition:all .15s;text-decoration:none; }
.pagination .page-link:hover { background:rgba(226,6,19,.08);border-color:rgba(226,6,19,.25);color:#e20613; }
.pagination .active .page-link { background:#e20613;border-color:#e20613;color:#fff;font-weight:600; }
.pagination .disabled .page-link { opacity:.4;cursor:not-allowed; }
</style>

@endsection
