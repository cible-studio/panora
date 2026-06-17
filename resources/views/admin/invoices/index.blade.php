<x-admin-layout>
<x-slot name="title">Facturation</x-slot>

<x-slot name="topbarActions">
    {{-- Les exports reprennent les filtres en cours pour ne pas avoir à
         refiltrer côté Excel/PDF — utile pour transmettre un sous-ensemble
         (1 client, 1 mois, 1 statut) au comptable. --}}
    <a href="{{ route('admin.invoices.export.pdf', request()->only(['client_id','status','date_from','date_to'])) }}"
       class="btn btn-ghost btn-sm" title="Export PDF du listing filtré">
        📄 PDF
    </a>
    <a href="{{ route('admin.invoices.export.excel', request()->only(['client_id','status','date_from','date_to'])) }}"
       class="btn btn-ghost btn-sm" title="Export Excel (.xlsx) du listing filtré">
        📊 Excel
    </a>
    <a href="{{ route('admin.invoices.create') }}" class="btn btn-primary btn-sm">＋ Nouvelle facture</a>
</x-slot>

{{-- Bandeau contexte campagne (deeplinking depuis la fiche campagne :
     /admin/invoices?campaign_id=42).
     Layout : actions à gauche (sortir du contexte rapidement),
     puis l'icône + le titre. --}}
@if(!empty($contextCampaign))
    <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;background:linear-gradient(135deg,rgba(58,168,53,.08),rgba(232,160,32,.08));border:1px solid var(--border);border-radius:10px;margin-bottom:14px">
        <div style="display:flex;gap:6px;flex-shrink:0">
            <a href="{{ route('admin.campaigns.show', $contextCampaign) }}" class="btn btn-ghost btn-sm" style="font-size:11px">← Retour campagne</a>
            <a href="{{ route('admin.invoices.index') }}" class="btn btn-ghost btn-sm" style="font-size:11px">✕ Retirer le filtre</a>
        </div>
        <div style="display:flex;align-items:center;gap:12px;min-width:0;flex:1">
            <div style="font-size:22px;flex-shrink:0">📢</div>
            <div style="min-width:0">
                <div style="font-size:12.5px;color:var(--text3)">Factures de la campagne</div>
                <div style="font-size:15px;font-weight:800;color:var(--text)">
                    {{ $contextCampaign->name }}
                    @if($contextCampaign->client)
                        <span style="font-weight:400;color:var(--text3);font-size:12.5px">— {{ $contextCampaign->client->name }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif

{{-- STATS CLIQUABLES (filtres AJAX)
     Mission D — chaque KPI card filtre la liste par statut/contexte et
     affiche son compteur live. Les 4 cartes de base couvrent le flux
     courant (Brouillon / Envoyée / Soldée / CA), et 4 cartes secondaires
     compactes couvrent les états spéciaux (Partielle / En retard /
     Litige / Annulée). Les compteurs s'actualisent à chaque chargement. --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:10px">
    <a href="?status=brouillon" data-status="brouillon"
       class="kpi-card filter-stat {{ request('status') === 'brouillon' ? 'is-active' : '' }}"
       style="--kpi-color:#6b7280"
       onmouseenter="this.style.borderColor='#6b7280';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#6b7280"></div>
        <div class="kpi-card__icon" style="color:#6b7280"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg></div>
        <div class="kpi-card__value" data-kpi="brouillon" data-kpi-value="brouillon" style="color:#6b7280">{{ $totalBrouillons }}</div>
        <div class="kpi-card__label">Brouillons</div>
        <div class="kpi-card__sub">à finaliser</div>
        <div class="kpi-card__arrow" style="color:#6b7280">→</div>
    </a>
    <a href="?status=envoyee" data-status="envoyee"
       class="kpi-card filter-stat {{ request('status') === 'envoyee' ? 'is-active' : '' }}"
       style="--kpi-color:#3b82f6"
       onmouseenter="this.style.borderColor='#3b82f6';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#3b82f6"></div>
        <div class="kpi-card__icon" style="color:#3b82f6"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></div>
        <div class="kpi-card__value" data-kpi="envoyee" data-kpi-value="envoyee" style="color:#3b82f6">{{ $totalEnvoyees }}</div>
        <div class="kpi-card__label">Envoyées</div>
        <div class="kpi-card__sub">en attente paiement</div>
        <div class="kpi-card__arrow" style="color:#3b82f6">→</div>
    </a>
    <a href="?status=payee" data-status="payee"
       class="kpi-card filter-stat {{ request('status') === 'payee' ? 'is-active' : '' }}"
       style="--kpi-color:#22c55e"
       onmouseenter="this.style.borderColor='#22c55e';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#22c55e"></div>
        <div class="kpi-card__icon" style="color:#22c55e"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
        <div class="kpi-card__value" data-kpi="payee" data-kpi-value="payee" style="color:#22c55e">{{ $totalPayees }}</div>
        <div class="kpi-card__label">Soldées</div>
        <div class="kpi-card__sub">factures encaissées</div>
        <div class="kpi-card__arrow" style="color:#22c55e">→</div>
    </a>
    <a href="?" data-status=""
       class="kpi-card filter-stat {{ !request('status') ? '' : '' }}"
       style="--kpi-color:var(--accent)"
       onmouseenter="this.style.borderColor='var(--accent)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:var(--accent)"></div>
        <div class="kpi-card__icon" style="color:var(--accent)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div class="kpi-card__value" data-kpi="ca" data-kpi-value="ca" style="color:var(--accent);font-size:18px">{{ number_format($montantTotal, 0, ',', ' ') }}</div>
        <div class="kpi-card__label">CA Encaissé</div>
        <div class="kpi-card__sub">FCFA · Restant : {{ number_format($montantRestantDu, 0, ',', ' ') }}</div>
        <div class="kpi-card__arrow" style="color:var(--accent)">→</div>
    </a>
</div>

{{-- Bandeau états spéciaux (compact) — Partielle / En retard / Litige / Annulée.
     Affichage en grille 4 colonnes, chaque pastille est un raccourci de filtre. --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px">
    <a href="?status=partiellement_payee"
       class="kpi-card filter-stat {{ request('status') === 'partiellement_payee' ? 'is-active' : '' }}"
       style="--kpi-color:#f59e0b;padding:14px 16px"
       onmouseenter="this.style.borderColor='#f59e0b';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#f59e0b"></div>
        <div style="display:flex;justify-content:space-between;align-items:center">
            <div>
                <div style="font-size:11px;font-weight:700;color:#b45309;text-transform:uppercase;letter-spacing:.4px">⏳ Partielles</div>
                <div style="font-size:11px;color:var(--text3);margin-top:2px">acomptes en cours</div>
            </div>
            <div style="font-size:24px;font-weight:800;color:#b45309">{{ $totalPartielles }}</div>
        </div>
    </a>
    <a href="?status=en_retard"
       class="kpi-card filter-stat {{ request('status') === 'en_retard' ? 'is-active' : '' }}"
       style="--kpi-color:#ef4444;padding:14px 16px"
       onmouseenter="this.style.borderColor='#ef4444';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#ef4444"></div>
        <div style="display:flex;justify-content:space-between;align-items:center">
            <div>
                <div style="font-size:11px;font-weight:700;color:#b91c1c;text-transform:uppercase;letter-spacing:.4px">🔴 En retard</div>
                <div style="font-size:11px;color:var(--text3);margin-top:2px">échéance dépassée</div>
            </div>
            <div style="font-size:24px;font-weight:800;color:#b91c1c">{{ $totalEnRetard }}</div>
        </div>
    </a>
    <a href="?status=litige"
       class="kpi-card filter-stat {{ request('status') === 'litige' ? 'is-active' : '' }}"
       style="--kpi-color:#dc2626;padding:14px 16px"
       onmouseenter="this.style.borderColor='#dc2626';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#dc2626"></div>
        <div style="display:flex;justify-content:space-between;align-items:center">
            <div>
                <div style="font-size:11px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:.4px">⚖️ Litige</div>
                <div style="font-size:11px;color:var(--text3);margin-top:2px">contentieux ouvert</div>
            </div>
            <div style="font-size:24px;font-weight:800;color:#991b1b">{{ $totalLitige }}</div>
        </div>
    </a>
    <a href="?status=annulee"
       class="kpi-card filter-stat {{ request('status') === 'annulee' ? 'is-active' : '' }}"
       style="--kpi-color:#6b7280;padding:14px 16px"
       onmouseenter="this.style.borderColor='#6b7280';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#6b7280"></div>
        <div style="display:flex;justify-content:space-between;align-items:center">
            <div>
                <div style="font-size:11px;font-weight:700;color:#4b5563;text-transform:uppercase;letter-spacing:.4px">🚫 Annulées</div>
                <div style="font-size:11px;color:var(--text3);margin-top:2px">historique figé</div>
            </div>
            <div style="font-size:24px;font-weight:800;color:#4b5563">{{ $totalAnnulees }}</div>
        </div>
    </a>
</div>

{{-- FILTRES AJAX DYNAMIQUES --}}
<div class="card" style="margin-bottom:16px;">
    <div class="filter-bar" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;padding:16px;">
        <div class="filter-group">
            <label class="filter-label">Client</label>
            <select id="filter-client" class="filter-select" style="width:200px;">
                <option value="">Tous</option>
                @foreach($clients as $client)
                <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Statut facture</label>
            {{-- Mission D — dropdown enrichi : 9 statuts du cahier + compteur
                 live entre parenthèses. Avant : seulement 4 statuts visibles. --}}
            <select id="filter-status" class="filter-select" style="width:200px;">
                <option value="">Tous les statuts</option>
                <option value="brouillon"           {{ request('status') === 'brouillon' ? 'selected' : '' }}>📝 Brouillon ({{ $totalBrouillons }})</option>
                <option value="generee"             {{ request('status') === 'generee'   ? 'selected' : '' }}>📋 Générée ({{ $totalGenerees }})</option>
                <option value="validee"             {{ request('status') === 'validee'   ? 'selected' : '' }}>🔒 Validée ({{ $totalValidees }})</option>
                <option value="envoyee"             {{ request('status') === 'envoyee'   ? 'selected' : '' }}>📤 Envoyée ({{ $totalEnvoyees }})</option>
                <option value="partiellement_payee" {{ request('status') === 'partiellement_payee' ? 'selected' : '' }}>⏳ Partiellement soldée ({{ $totalPartielles }})</option>
                <option value="payee"               {{ request('status') === 'payee'     ? 'selected' : '' }}>✅ Soldée ({{ $totalPayees }})</option>
                <option value="en_retard"           {{ request('status') === 'en_retard' ? 'selected' : '' }}>🔴 En retard ({{ $totalEnRetard }})</option>
                <option value="litige"              {{ request('status') === 'litige'    ? 'selected' : '' }}>⚖️ Litige ({{ $totalLitige }})</option>
                <option value="annulee"             {{ request('status') === 'annulee'   ? 'selected' : '' }}>🚫 Annulée ({{ $totalAnnulees }})</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Statut paiement</label>
            <select id="filter-pay-status" class="filter-select" style="width:150px;" onchange="window.location.href = '?pay_status=' + this.value">
                <option value="">Tous</option>
                <option value="non_payee" {{ request('pay_status') === 'non_payee' ? 'selected' : '' }}>❌ Non soldée</option>
                <option value="partielle" {{ request('pay_status') === 'partielle' ? 'selected' : '' }}>⏳ Partielle</option>
                <option value="soldee"    {{ request('pay_status') === 'soldee'    ? 'selected' : '' }}>✅ Soldée</option>
                <option value="en_retard" {{ in_array(request('pay_status'), ['en_retard', 'overdue']) ? 'selected' : '' }}>🔴 En retard</option>
            </select>
        </div>
        
        <div class="filter-group" id="reset-wrapper" style="display:none;">
            <label class="filter-label" style="visibility:hidden;">Actions</label>
            <button id="btn-reset" class="reset-btn" style="display:flex;align-items:center;gap:4px;">
                ↺ Réinitialiser
            </button>
        </div>

        <div class="filter-group" style="margin-left:auto;">
            <label class="filter-label" style="visibility:hidden;">&nbsp;</label>
            <div class="result-badge">
                <strong id="result-count">{{ number_format($invoices->total()) }}</strong> facture(s)
            </div>
        </div>
    </div>
</div>

{{-- TABLEAU --}}
<div id="table-container" class="card">
    <div class="card-header">
        <div class="card-title">💰 Factures <span id="title-count">({{ $invoices->total() }})</span></div>
    </div>
    <div class="table-wrap">
        <table id="invoices-table">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Client / Campagne</th>
                    <th style="text-align:right">Total à payer</th>
                    <th style="text-align:right">Payé</th>
                    <th style="text-align:right">Reste à payer</th>
                    <th>Statut paiement</th>
                    <th>Prochaine échéance</th>
                    <th>Relances & observations</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="table-body">
                @include('admin.invoices.partials.table-rows', ['invoices' => $invoices])
            </tbody>
        </table>
    </div>
    <div id="pagination-container" style="padding:16px;">
        {{ $invoices->links() }}
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
/* ── Filtres : selects natifs harmonisés avec le design system. ─────
   Avant : aspect navigateur brut (flèche système, hauteur incohérente,
   pas de hover/focus visible) — visuellement cassé à côté des cartes
   KPI. On désactive l'apparence native + chevron SVG custom + transition
   sur hover/focus. */
.filter-select, .filter-input {
    height: 40px;
    padding: 0 12px;
    background: var(--surface2);
    border: 1px solid var(--border2, var(--border));
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text);
    outline: none;
    transition: border-color .15s, box-shadow .15s, background .15s;
    cursor: pointer;
}
.filter-select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    /* Chevron SVG inline (down-arrow) — couleur var(--text3) en hex */
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}
.filter-select:hover, .filter-input:hover { background: var(--surface3, var(--surface2)); border-color: var(--text3); }
.filter-select:focus, .filter-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(232,160,32,.15); background: var(--surface2); }
.filter-select option { background: var(--surface); color: var(--text); padding: 8px; }
.filter-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--text3); display: block; margin-bottom: 6px; }
.filter-group { display: flex; flex-direction: column; }
.result-badge { height:38px;display:flex;align-items:center;font-size:12px;color:var(--text3);white-space:nowrap; }
.spinner { display:inline-block;width:20px;height:20px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:8px; }
@keyframes spin { to { transform: rotate(360deg); } }
.btn-blue { background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);color:#3b82f6; }
.btn-blue:hover { background:rgba(59,130,246,.2); }
</style>

@push('scripts')
{{-- Select2 — recherche live sur le filtre client. CDN partagé avec
     les autres vues qui en ont besoin (campaigns/create, piges/create,
     invoices/partials/_form-fne). --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<style>
/* Harmonise la pillule Select2 avec les autres filter-select natifs
   (hauteur 40px, padding/radius identiques) — sinon le client picker
   tranche visuellement avec les dropdowns à côté. */
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
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 38px !important;
}
.select2-dropdown { border-radius: 10px !important; border-color: var(--border2, var(--border)) !important; }
.select2-search--dropdown .select2-search__field {
    border-radius: 8px !important;
    border-color: var(--border2, var(--border)) !important;
    padding: 8px 10px !important;
    font-size: 13px !important;
}
.select2-results__option--highlighted {
    background: var(--accent) !important;
    color: #fff !important;
}
</style>

<script>
// ════════════════════════════════════════════════════════════
// FILTRAGE AJAX DYNAMIQUE
// ════════════════════════════════════════════════════════════
(function() {
    let currentFilters = {
        client_id: '',
        status: ''
    };
    let debounceTimer = null;
    let isUpdating = false;

    const elements = {
        client: document.getElementById('filter-client'),
        status: document.getElementById('filter-status'),
        resetBtn: document.getElementById('btn-reset'),
        resetWrapper: document.getElementById('reset-wrapper'),
        resultCount: document.getElementById('result-count'),
        titleCount: document.getElementById('title-count'),
        tableBody: document.getElementById('table-body'),
        paginationContainer: document.getElementById('pagination-container')
    };

    function updateResetButton() {
        const hasFilters = currentFilters.client_id || currentFilters.status;
        if (elements.resetWrapper) {
            elements.resetWrapper.style.display = hasFilters ? 'flex' : 'none';
        }
    }

    async function applyFilters() {
        if (isUpdating) return;
        isUpdating = true;

        const params = new URLSearchParams();
        if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
        if (currentFilters.status) params.set('status', currentFilters.status);
        params.set('ajax', '1');

        if (elements.tableBody) {
            elements.tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:60px;"><div class="spinner"></div> Chargement...</td></tr>';
        }

        try {
            const response = await fetch(`{{ route("admin.invoices.index") }}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const data = await response.json();

            if (data.html && elements.tableBody) {
                elements.tableBody.innerHTML = data.html;
            }
            
            if (elements.resultCount && data.total) {
                elements.resultCount.textContent = data.total;
            }
            if (elements.titleCount && data.total) {
                elements.titleCount.textContent = `(${data.total})`;
            }
            
            if (elements.paginationContainer && data.pagination) {
                elements.paginationContainer.innerHTML = data.pagination;
            }

            const url = new URL(window.location.href);
            Object.keys(currentFilters).forEach(key => {
                if (currentFilters[key]) url.searchParams.set(key, currentFilters[key]);
                else url.searchParams.delete(key);
            });
            window.history.pushState({}, '', url);

        } catch (error) {
            console.error('Erreur:', error);
            if (elements.tableBody) {
                elements.tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:60px;color:#ef4444;">Erreur de chargement</td></tr>';
            }
        } finally {
            isUpdating = false;
        }
    }

    // Écouteurs d'événements
    if (elements.client) {
        // Init Select2 + câblage du change via jQuery .on() — Select2 4.x
        // dispatche ses 'change' via le système d'événements jQuery qui
        // n'est pas systématiquement capté par addEventListener natif.
        // Fallback addEventListener si jQuery/Select2 indisponible.
        const onClientChange = () => {
            currentFilters.client_id = elements.client.value;
            updateResetButton();
            applyFilters();
        };

        const hasSelect2 = window.jQuery && window.jQuery.fn && window.jQuery.fn.select2;
        if (hasSelect2) {
            window.jQuery(elements.client).select2({
                placeholder: '🔍 Rechercher un client…',
                allowClear: true,
                width: '220px',
                language: {
                    noResults: () => 'Aucun client trouvé',
                    searching: () => 'Recherche…',
                },
            }).on('change', onClientChange);
        } else {
            elements.client.addEventListener('change', onClientChange);
        }
    }

    if (elements.status) {
        elements.status.addEventListener('change', () => {
            currentFilters.status = elements.status.value;
            updateResetButton();
            applyFilters();
            
            // is-active uniquement pour le statut précis filtré.
            // La carte "Total" (data-status="") reste neutre.
            document.querySelectorAll('.kpi-card[data-kpi]').forEach(card => {
                const status = card.dataset.status;
                if (status && status === currentFilters.status) {
                    card.classList.add('is-active');
                } else {
                    card.classList.remove('is-active');
                }
            });
        });
    }

    // Cartes stats
    document.querySelectorAll('.kpi-card[data-kpi]').forEach(card => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            const status = card.dataset.status;
            if (elements.status) {
                elements.status.value = status;
                currentFilters.status = status;
                updateResetButton();
                applyFilters();

                document.querySelectorAll('.kpi-card[data-kpi]').forEach(c => {
                    if (status && c.dataset.status === status) {
                        c.classList.add('is-active');
                    } else {
                        c.classList.remove('is-active');
                    }
                });
            }
        });
    });

    // Reset button
    if (elements.resetBtn) {
        elements.resetBtn.addEventListener('click', () => {
            currentFilters = { client_id: '', status: '' };
            if (elements.client) elements.client.value = '';
            if (elements.status) elements.status.value = '';

            document.querySelectorAll('.kpi-card[data-kpi]').forEach(card => card.classList.remove('is-active'));
            
            updateResetButton();
            applyFilters();
        });
    }

    // Initialiser les valeurs depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('client_id')) currentFilters.client_id = urlParams.get('client_id');
    if (urlParams.has('status')) currentFilters.status = urlParams.get('status');

    if (elements.client && currentFilters.client_id) elements.client.value = currentFilters.client_id;
    if (elements.status && currentFilters.status) elements.status.value = currentFilters.status;

    updateResetButton();
})();

// ════════════════════════════════════════════════════════════
// ACTIONS INLINE (send / pay / cancel / revert) — AJAX
// ════════════════════════════════════════════════════════════
(function () {
    const tableBody = document.getElementById('table-body');
    if (!tableBody) return;

    function showToast(message, type = 'success') {
        let host = document.getElementById('invoice-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'invoice-toast-host';
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

    function updateKPIs(counts) {
        if (!counts) return;
        const map = { brouillon: counts.brouillon, envoyee: counts.envoyee, payee: counts.payee };
        for (const [k, v] of Object.entries(map)) {
            const el = document.querySelector(`[data-kpi="${k}"]`);
            if (el && v !== undefined) el.textContent = v;
        }
        const ca = document.querySelector('[data-kpi="ca"]');
        if (ca && counts.ca !== undefined) {
            ca.textContent = new Intl.NumberFormat('fr-FR').format(Math.round(counts.ca));
        }
    }

    tableBody.addEventListener('submit', async (event) => {
        const form = event.target.closest('form.invoice-action');
        if (!form) return;
        event.preventDefault();

        const confirmText = form.dataset.confirm;
        if (confirmText && !window.confirm(confirmText)) return;

        const button = form.querySelector('button[type="submit"]');
        if (button) { button.disabled = true; button.dataset.prev = button.innerHTML; button.innerHTML = '⏳'; }

        try {
            const fd = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: fd,
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.success) {
                showToast(data.message || 'Action impossible.', 'error');
                if (button) { button.disabled = false; button.innerHTML = button.dataset.prev || '↺'; }
                return;
            }

            // Remplacer la ligne par la version fraîche renvoyée par le serveur
            const row = form.closest('tr[data-invoice-row]');
            if (row && data.row_html) {
                const wrapper = document.createElement('tbody');
                wrapper.innerHTML = data.row_html.trim();
                const newRow = wrapper.firstElementChild;
                if (newRow) row.replaceWith(newRow);
            }

            updateKPIs(data.counts);
            showToast(data.message || 'Action effectuée.');
        } catch (err) {
            console.error(err);
            showToast('Erreur réseau. Réessayez.', 'error');
            if (button) { button.disabled = false; button.innerHTML = button.dataset.prev || '↺'; }
        }
    });
})();
</script>
@endpush
</x-admin-layout>