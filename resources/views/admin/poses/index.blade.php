<x-admin-layout title="Pose OOH">

<x-slot:topbarActions>
    <a href="{{ route('admin.pose-tasks.calendar') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px" title="Planning hebdomadaire par technicien">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Calendrier
    </a>
    <a href="{{ route('admin.pose-tasks.map') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px" title="Visualiser les poses sur une carte">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        Vue Carte
    </a>
    <a href="{{ route('admin.pose-tasks.create') }}" class="btn btn-primary" style="display:flex;align-items:center;gap:6px">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle tâche
    </a>
</x-slot:topbarActions>

{{-- ════ ALERTES ACTIVITÉ DU MODULE ════
     Dismissibles par utilisateur (localStorage). La signature inclut
     l'identité utilisateur + un hash du contenu (IDs des tâches en
     retard / nb sans pige) → si une NOUVELLE tâche entre en retard ou
     qu'une nouvelle pose sans pige apparaît, le hash change et l'alerte
     ressort. Les détails restent toujours accessibles via "Voir tout"
     et la fiche détail de chaque tâche. ════ --}}
@if($overdueTasks->isNotEmpty() || $posesSansPige > 0)
@php
    $alertUserKey = (string) (auth()->id() ?? 'anon');
    $overdueSig   = 'pose-alert.overdue.' . $alertUserKey . '.' .
                    md5($overdueTasks->pluck('id')->sort()->values()->join(','));
    $missingSig   = 'pose-alert.missing-piges.' . $alertUserKey . '.' . md5((string) $posesSansPige);
@endphp
<div id="pose-alerts-host" style="display:flex;flex-direction:column;gap:8px;margin-bottom:18px">

    @if($overdueTasks->isNotEmpty())
    <div class="pose-alert" data-alert-key="{{ $overdueSig }}"
         style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.25);border-radius:12px;padding:12px 16px">
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
            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;align-self:flex-start">
                <a href="{{ route('admin.pose-tasks.index', ['status'=>'planifiee']) }}"
                   style="font-size:11px;color:#ef4444;font-weight:700;text-decoration:none;padding:6px 12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;white-space:nowrap">
                    Voir tout →
                </a>
                <button type="button" class="pose-alert-dismiss"
                        onclick="dismissPoseAlert('{{ $overdueSig }}')"
                        title="Masquer cette alerte (réapparaîtra si une nouvelle tâche tombe en retard)"
                        aria-label="Masquer"
                        style="width:30px;height:30px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:50%;cursor:pointer;color:#ef4444;display:inline-flex;align-items:center;justify-content:center;padding:0;transition:background .15s,border-color .15s;"
                        onmouseenter="this.style.background='rgba(239,68,68,.2)';this.style.borderColor='rgba(239,68,68,.5)'"
                        onmouseleave="this.style.background='rgba(239,68,68,.08)';this.style.borderColor='rgba(239,68,68,.25)'">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
    </div>
    @endif

    @if($posesSansPige > 0)
    <div class="pose-alert" data-alert-key="{{ $missingSig }}"
         style="background:rgba(249,115,22,.07);border:1px solid rgba(249,115,22,.25);border-radius:12px;padding:12px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="width:34px;height:34px;background:rgba(249,115,22,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </div>
        <div style="flex:1;min-width:200px">
            <div style="font-size:13px;font-weight:700;color:#f97316">{{ $posesSansPige }} pose(s) réalisée(s) sans pige photo</div>
            <div style="font-size:11px;color:rgba(249,115,22,.75);margin-top:2px">Aucune preuve d'affichage — impossible de facturer le client</div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <a href="{{ route('admin.piges.index') }}"
               style="font-size:11px;color:#f97316;font-weight:700;text-decoration:none;padding:6px 12px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);border-radius:8px;white-space:nowrap">
                Ajouter piges →
            </a>
            <button type="button" class="pose-alert-dismiss"
                    onclick="dismissPoseAlert('{{ $missingSig }}')"
                    title="Masquer cette alerte"
                    aria-label="Masquer"
                    style="width:30px;height:30px;background:rgba(249,115,22,.08);border:1px solid rgba(249,115,22,.25);border-radius:50%;cursor:pointer;color:#f97316;display:inline-flex;align-items:center;justify-content:center;padding:0;transition:background .15s,border-color .15s;"
                    onmouseenter="this.style.background='rgba(249,115,22,.2)';this.style.borderColor='rgba(249,115,22,.5)'"
                    onmouseleave="this.style.background='rgba(249,115,22,.08)';this.style.borderColor='rgba(249,115,22,.25)'">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
    </div>
    @endif
</div>
<script>
// ── Dismiss persistant via localStorage ──
// La clé inclut user_id + hash du contenu : si le contenu change (nouvelle
// tâche en retard, nouvelle pose sans pige), la clé change et l'alerte
// réapparaît. Les détails restent toujours accessibles via "Voir tout"
// et les fiches détail.
(function () {
    const STORE_KEY = 'pose_alerts_dismissed';
    let dismissed;
    try { dismissed = JSON.parse(localStorage.getItem(STORE_KEY) || '[]'); }
    catch (e) { dismissed = []; }
    if (!Array.isArray(dismissed)) dismissed = [];

    // Cache au chargement les alertes déjà dismissed
    document.querySelectorAll('.pose-alert').forEach(el => {
        if (dismissed.includes(el.dataset.alertKey)) {
            el.style.display = 'none';
        }
    });

    // Si toutes les alertes sont cachées, on cache le wrapper
    function cleanupHost() {
        const host = document.getElementById('pose-alerts-host');
        if (!host) return;
        const visible = Array.from(host.querySelectorAll('.pose-alert'))
            .some(el => el.style.display !== 'none');
        if (!visible) host.style.display = 'none';
    }
    cleanupHost();

    window.dismissPoseAlert = function (key) {
        const el = document.querySelector(`.pose-alert[data-alert-key="${key}"]`);
        if (el) {
            el.style.transition = 'opacity .2s,transform .2s';
            el.style.opacity = '0';
            el.style.transform = 'translateX(8px)';
            setTimeout(() => { el.style.display = 'none'; cleanupHost(); }, 200);
        }
        try {
            const list = JSON.parse(localStorage.getItem(STORE_KEY) || '[]');
            if (!list.includes(key)) {
                list.push(key);
                // Garde-fou taille : on tronque les plus vieux au-delà de 50 entrées
                while (list.length > 50) list.shift();
                localStorage.setItem(STORE_KEY, JSON.stringify(list));
            }
        } catch (e) { /* localStorage indispo : pas critique */ }
    };
})();
</script>
@endif

{{-- ════ KPI cards (pattern unifié projet : bordure latérale colorée,
     toggle, état actif, counts qui gardent leur valeur indépendamment
     du filtre KPI courant). ════ --}}
@php
$kpis = [
    ['s'=>'total',     'l'=>'Total',      'c'=>'var(--accent)', 'sub'=>'toutes les tâches', 'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>'],
    ['s'=>'planifiee', 'l'=>'Planifiées', 'c'=>'#f97316',       'sub'=>'à venir',           'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'],
    ['s'=>'en_cours',  'l'=>'En cours',   'c'=>'#3b82f6',       'sub'=>'sur le terrain',    'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>'],
    ['s'=>'realisee',  'l'=>'Réalisées',  'c'=>'#22c55e',       'sub'=>'poses terminées',   'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>'],
    ['s'=>'annulee',   'l'=>'Annulées',   'c'=>'#ef4444',       'sub'=>'tâches annulées',   'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/></svg>'],
];
$activeStatus = request('status');
$hasAnyFilter = request('q') || request('status') || request('technicien_id')
              || request('campaign_id') || request('date_from') || request('date_to');
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:20px">
@foreach($kpis as $k)
@php
    $isTotal  = $k['s'] === 'total';
    $isActive = !$isTotal && $activeStatus === $k['s'];
@endphp
<a href="#" data-kpi="{{ $k['s'] }}" data-status="{{ $isTotal ? '' : $k['s'] }}"
   class="kpi-card filter-stat {{ $isActive ? 'is-active' : '' }}"
   style="--kpi-color:{{ $k['c'] }};{{ $isActive ? 'border-color:'.$k['c'].';' : '' }}"
   onmouseenter="if(!this.classList.contains('is-active')){this.style.borderColor='{{ $k['c'] }}';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.12)'}"
   onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
    <div class="kpi-card__top-bar" style="background:{{ $k['c'] }}"></div>
    <div class="kpi-card__icon" style="color:{{ $k['c'] }}">{!! $k['icon'] !!}</div>
    <div data-kpi-value="{{ $k['s'] }}" class="kpi-card__value" style="color:{{ $k['c'] }}">{{ number_format($stats[$k['s']] ?? 0) }}</div>
    <div class="kpi-card__label">{{ $k['l'] }}</div>
    <div class="kpi-card__sub">{{ $k['sub'] }}</div>
    <div class="kpi-card__arrow" style="color:{{ $k['c'] }}">→</div>
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

        {{-- Toggle orphelines (campagnes supprimées / annulées / terminées) --}}
        @php $orphActive = request()->boolean('show_orphan'); @endphp
        <div class="filter-group">
            <label class="filter-label">Orphelines</label>
            <button type="button" id="filter-show-orphan-btn"
                    style="display:inline-flex;align-items:center;gap:8px;height:38px;cursor:pointer;padding:0 14px;border-radius:10px;font-size:13px;font-weight:600;white-space:nowrap;transition:all .15s;
                           border:1px solid {{ $orphActive ? 'var(--accent)' : 'var(--border2)' }};
                           background:{{ $orphActive ? 'var(--accent)' : 'var(--surface2)' }};
                           color:{{ $orphActive ? '#fff' : 'var(--text2)' }};">
                <span>{{ $orphActive ? '✓' : '○' }}</span>
                <span>Afficher</span>
            </button>
            {{-- Input caché : pilote du bouton ci-dessus pour préserver le JS existant --}}
            <input type="checkbox" id="filter-show-orphan"
                   {{ $orphActive ? 'checked' : '' }} style="display:none">
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

{{-- ════ MODAL D'ACTIONS GROUPÉES — déplaçable (drag handle en haut)
     Pré-remplit le technicien si toutes les tâches sélectionnées ont
     le même tech assigné. Idem pour l'équipe. ════ --}}
<div id="bulk-bar"
     style="display:none;position:fixed;top:120px;right:20px;
            background:var(--surface);border:1px solid var(--border);border-radius:14px;
            box-shadow:0 12px 36px rgba(0,0,0,.35);z-index:60;width:380px;max-width:95vw;
            overflow:hidden;box-sizing:border-box;">

    {{-- Drag handle (header) --}}
    <div id="bulk-drag-handle"
         style="display:flex;align-items:center;justify-content:space-between;gap:10px;
                padding:12px 14px;background:linear-gradient(135deg,rgba(232,160,32,.10),rgba(232,160,32,.04));
                border-bottom:1px solid var(--border);cursor:grab;user-select:none;white-space:nowrap;">
        <div style="display:flex;align-items:center;gap:9px;min-width:0;flex:1;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2.5" style="flex-shrink:0">
                <circle cx="9" cy="6"  r="1.2"/><circle cx="15" cy="6"  r="1.2"/>
                <circle cx="9" cy="12" r="1.2"/><circle cx="15" cy="12" r="1.2"/>
                <circle cx="9" cy="18" r="1.2"/><circle cx="15" cy="18" r="1.2"/>
            </svg>
            <span id="bulk-count-badge"
                  style="background:var(--accent);color:#000;font-weight:800;font-size:12px;
                         padding:3px 9px;border-radius:999px;line-height:1;flex-shrink:0;">0</span>
            <span style="font-size:12.5px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;">
                sélectionnée(s)
            </span>
        </div>
        <button type="button" id="bulk-clear"
                style="background:transparent;border:none;color:var(--text3);cursor:pointer;
                       padding:4px 9px;border-radius:6px;font-size:15px;line-height:1;flex-shrink:0"
                onmouseover="this.style.background='rgba(239,68,68,.1)';this.style.color='#ef4444'"
                onmouseout="this.style.background='transparent';this.style.color='var(--text3)'"
                title="Tout désélectionner">✕</button>
    </div>

    {{-- Corps du modal --}}
    <div style="padding:14px;display:flex;flex-direction:column;gap:12px;">

        {{-- Technicien --}}
        <div>
            <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text3);margin-bottom:5px;display:flex;justify-content:space-between;align-items:center">
                <span>🧑‍🔧 Technicien</span>
                <button type="button" id="bulk-suggest-tech"
                        style="background:rgba(232,160,32,.12);border:1px solid rgba(232,160,32,.3);color:#b45309;
                               padding:2px 8px;border-radius:6px;cursor:pointer;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;
                               display:inline-flex;align-items:center;gap:4px"
                        title="Suggère le meilleur tech selon zone + charge + perf">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 1 1 7.072 0l-.548.547A3.374 3.374 0 0 0 14 18.469V19a2 2 0 1 1-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    Suggérer
                </button>
            </label>
            <div style="display:flex;gap:6px;">
                <select id="bulk-tech" class="filter-select" style="flex:1;min-width:0">
                    <option value="">— Choisir —</option>
                    <option value="__unset__">(retirer l'assignation)</option>
                    @foreach($techniciens as $tech)
                        <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                    @endforeach
                </select>
                <button type="button" id="bulk-tech-apply" class="btn btn-sm btn-primary" style="white-space:nowrap">Appliquer</button>
            </div>
            <div id="bulk-tech-hint" style="font-size:10px;color:var(--text3);margin-top:4px;line-height:1.3;display:none;"></div>
            <div id="bulk-suggest-result" style="display:none;margin-top:6px;padding:8px 10px;background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.25);border-radius:8px;font-size:11px;line-height:1.45"></div>
        </div>

        {{-- Équipe --}}
        <div>
            <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text3);margin-bottom:5px">
                👥 Nom d'équipe
            </label>
            <div style="display:flex;gap:6px;">
                <input type="text" id="bulk-team" class="filter-input"
                       placeholder="Équipe nord, Pro-pose..." maxlength="100"
                       style="flex:1;min-width:0;">
                <button type="button" id="bulk-team-apply" class="btn btn-sm btn-ghost" style="white-space:nowrap">Appliquer</button>
            </div>
        </div>

        {{-- Replanifier --}}
        <div>
            <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text3);margin-bottom:5px">
                📅 Date / heure planifiée
            </label>
            <div style="display:flex;gap:6px;">
                <input type="datetime-local" id="bulk-date" class="filter-input" style="flex:1;min-width:0">
                <button type="button" id="bulk-date-apply" class="btn btn-sm btn-ghost" style="white-space:nowrap">Replanifier</button>
            </div>
        </div>

        {{-- Statut --}}
        <div>
            <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text3);margin-bottom:5px">
                ⚙️ Statut
            </label>
            <div style="display:flex;gap:6px;">
                <select id="bulk-status" class="filter-select" style="flex:1;min-width:0">
                    <option value="">— Choisir —</option>
                    <option value="planifiee">📅 Planifiée</option>
                    <option value="en_cours">🔧 En cours</option>
                    <option value="annulee">🚫 Annuler</option>
                </select>
                <button type="button" id="bulk-status-apply" class="btn btn-sm btn-ghost" style="white-space:nowrap">Appliquer</button>
            </div>
        </div>
    </div>
</div>

<script>
// ════════════════════════════════════════════════════════════════
// MODAL ACTIONS GROUPÉES — drag + pré-remplissage tech assigné
// ════════════════════════════════════════════════════════════════
(function () {
    const modal = document.getElementById('bulk-bar');
    if (!modal) return;
    const handle = document.getElementById('bulk-drag-handle');

    // ── 1. Drag & drop du modal ───────────────────────────────
    let dragging = false, offsetX = 0, offsetY = 0;
    const STORAGE_KEY = 'pose_bulk_modal_pos';

    // Restaure la position sauvegardée (si dans le viewport)
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        if (saved && typeof saved.top === 'number' && typeof saved.left === 'number') {
            const vw = window.innerWidth, vh = window.innerHeight;
            if (saved.left >= 0 && saved.left < vw - 100 && saved.top >= 0 && saved.top < vh - 100) {
                modal.style.top  = saved.top + 'px';
                modal.style.left = saved.left + 'px';
                modal.style.right = 'auto';
            }
        }
    } catch (e) { /* localStorage indispo */ }

    handle.addEventListener('mousedown', (e) => {
        // Ne pas drag si clic sur le bouton ✕
        if (e.target.closest('button')) return;
        dragging = true;
        const rect = modal.getBoundingClientRect();
        offsetX = e.clientX - rect.left;
        offsetY = e.clientY - rect.top;
        handle.style.cursor = 'grabbing';
        modal.style.userSelect = 'none';
        e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
        if (!dragging) return;
        const newLeft = Math.max(0, Math.min(window.innerWidth  - modal.offsetWidth,  e.clientX - offsetX));
        const newTop  = Math.max(0, Math.min(window.innerHeight - modal.offsetHeight, e.clientY - offsetY));
        modal.style.left  = newLeft + 'px';
        modal.style.top   = newTop  + 'px';
        modal.style.right = 'auto';
    });

    document.addEventListener('mouseup', () => {
        if (!dragging) return;
        dragging = false;
        handle.style.cursor = 'grab';
        modal.style.userSelect = '';
        // Sauvegarde la position
        try {
            const rect = modal.getBoundingClientRect();
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ top: rect.top, left: rect.left }));
        } catch (e) { /* localStorage indispo */ }
    });

    // ── 2. Pré-remplissage du technicien si tous les cochés ont le même ──
    function syncBulkPrefill() {
        const checked = Array.from(document.querySelectorAll('.pose-check:checked'));
        if (checked.length === 0) return;

        const techSel = document.getElementById('bulk-tech');
        const teamInp = document.getElementById('bulk-team');
        const techHint = document.getElementById('bulk-tech-hint');

        // Tech : commun à toutes les tâches cochées ?
        const techIds = [...new Set(checked.map(cb => cb.dataset.techId || ''))];
        if (techIds.length === 1 && techIds[0] !== '') {
            if (techSel && !techSel.dataset.userTouched) {
                techSel.value = techIds[0];
            }
            if (techHint) {
                const opt = techSel?.querySelector(`option[value="${techIds[0]}"]`);
                techHint.textContent = '✓ Technicien commun aux tâches sélectionnées : ' + (opt?.textContent || '').trim();
                techHint.style.display = 'block';
                techHint.style.color = '#22c55e';
            }
        } else if (techIds.length > 1) {
            if (techSel && !techSel.dataset.userTouched) techSel.value = '';
            if (techHint) {
                techHint.textContent = '⚠ Plusieurs techniciens différents — choisissez celui à appliquer';
                techHint.style.display = 'block';
                techHint.style.color = '#f97316';
            }
        } else {
            if (techHint) techHint.style.display = 'none';
        }

        // Équipe : commune ?
        const teams = [...new Set(checked.map(cb => cb.dataset.team || ''))];
        if (teams.length === 1 && teams[0] !== '' && teamInp && !teamInp.dataset.userTouched) {
            teamInp.value = teams[0];
        }
    }

    // Marquer les inputs comme "touchés" pour ne pas écraser la saisie user
    document.getElementById('bulk-tech')?.addEventListener('change', function () { this.dataset.userTouched = '1'; });
    document.getElementById('bulk-team')?.addEventListener('input',  function () { this.dataset.userTouched = '1'; });

    // Observer les changements de selection
    document.addEventListener('change', (e) => {
        if (e.target.classList?.contains('pose-check') || e.target.id === 'pose-check-all') {
            setTimeout(syncBulkPrefill, 50);
        }
    });

    // Reset des marqueurs "userTouched" sur clic clear
    document.getElementById('bulk-clear')?.addEventListener('click', () => {
        const techSel = document.getElementById('bulk-tech');
        const teamInp = document.getElementById('bulk-team');
        if (techSel) { delete techSel.dataset.userTouched; techSel.value = ''; }
        if (teamInp) { delete teamInp.dataset.userTouched; teamInp.value = ''; }
        const hint = document.getElementById('bulk-tech-hint');
        if (hint) hint.style.display = 'none';
        const suggest = document.getElementById('bulk-suggest-result');
        if (suggest) suggest.style.display = 'none';
    });

    // ── 3. Suggestion intelligente de tech ───────────────────
    const SUGGEST_URL = @json(route('admin.pose-tasks.suggest-tech'));
    document.getElementById('bulk-suggest-tech')?.addEventListener('click', async () => {
        const checked = Array.from(document.querySelectorAll('.pose-check:checked'));
        if (checked.length === 0) {
            alert('Sélectionne au moins une tâche pour obtenir une suggestion.');
            return;
        }
        const resultEl = document.getElementById('bulk-suggest-result');
        resultEl.style.display = 'block';
        resultEl.innerHTML = '<span style="color:var(--text3)">⏳ Analyse en cours…</span>';

        const params = new URLSearchParams();
        checked.forEach(cb => params.append('task_ids[]', cb.value));

        try {
            const res = await fetch(SUGGEST_URL + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();

            if (data.message) {
                resultEl.innerHTML = `<span style="color:#ef4444">${data.message}</span>`;
                return;
            }
            const s = data.suggestion;
            if (!s) {
                resultEl.innerHTML = '<span style="color:#ef4444">Aucune suggestion disponible.</span>';
                return;
            }

            resultEl.innerHTML = `
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px">
                    <strong style="color:#16a34a;font-size:12px">✨ ${s.name}</strong>
                    <span style="background:#22c55e;color:#fff;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:800">${s.score}%</span>
                </div>
                <div style="color:var(--text3);font-size:10.5px">${s.reason}</div>
                <button type="button" class="btn btn-sm btn-ghost" style="margin-top:6px;width:100%;font-size:11px"
                        onclick="(function(){const sel=document.getElementById('bulk-tech');sel.value='${s.id}';sel.dataset.userTouched='1';})()">
                    ➤ Pré-sélectionner ce tech
                </button>
            `;
        } catch (e) {
            resultEl.innerHTML = '<span style="color:#ef4444">Erreur de suggestion — réessaie.</span>';
        }
    });
})();
</script>

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

{{-- ════ SÉLECTION MULTIPLE + ACTIONS GROUPÉES ════ --}}
<script>
(function () {
    'use strict';
    const ENDPOINT = "{{ route('admin.pose-tasks.bulk-update') }}";
    const CSRF     = document.querySelector('meta[name=csrf-token]')?.content || '';

    const bar       = document.getElementById('bulk-bar');
    const badge     = document.getElementById('bulk-count-badge');
    const checkAll  = document.getElementById('pose-check-all');
    const clearBtn  = document.getElementById('bulk-clear');

    // Set des IDs sélectionnés — persisté en mémoire JS (perd au reload, OK).
    const selected = new Set();

    function syncBar() {
        const n = selected.size;
        if (badge) badge.textContent = String(n);
        // Modal vertical (refonte) : display:block pour que le header
        // et le corps s'empilent verticalement (avant c'était 'flex'
        // pour la barre horizontale fixée en bas, devenu invalide).
        if (bar) bar.style.display = n > 0 ? 'block' : 'none';
        syncCheckAllState();
    }

    function syncCheckAllState() {
        if (!checkAll) return;
        const enabledBoxes = Array.from(document.querySelectorAll('.pose-check:not([disabled])'));
        if (enabledBoxes.length === 0) {
            checkAll.checked = false;
            checkAll.indeterminate = false;
            return;
        }
        const checkedCount = enabledBoxes.filter(b => b.checked).length;
        checkAll.checked       = checkedCount === enabledBoxes.length;
        checkAll.indeterminate = checkedCount > 0 && checkedCount < enabledBoxes.length;
    }

    // Délégation : les checkbox sont régénérées au filtre AJAX, on capture
    // au niveau du container parent.
    document.addEventListener('change', (e) => {
        const t = e.target;
        if (t.classList.contains('pose-check') && !t.disabled) {
            const id = Number(t.value);
            if (t.checked) selected.add(id);
            else           selected.delete(id);
            syncBar();
            syncGroupCheckState(t.dataset.campaignId);
        }
        if (t.id === 'pose-check-all') {
            document.querySelectorAll('.pose-check:not([disabled])').forEach(box => {
                box.checked = t.checked;
                const id = Number(box.value);
                if (t.checked) selected.add(id);
                else           selected.delete(id);
            });
            document.querySelectorAll('.pose-group-check').forEach(g => {
                g.checked = t.checked;
                g.indeterminate = false;
            });
            syncBar();
        }
        // ── NOUVEAU : sélection par groupe campagne ─────────────────
        if (t.classList.contains('pose-group-check')) {
            const cid = t.dataset.campaignId;
            const groupBoxes = document.querySelectorAll(
                `.pose-check[data-campaign-id="${cid}"]:not([disabled])`
            );
            groupBoxes.forEach(box => {
                box.checked = t.checked;
                const id = Number(box.value);
                if (t.checked) selected.add(id);
                else           selected.delete(id);
            });
            syncBar();
            syncCheckAllState();
            t.indeterminate = false;
        }
    });

    /**
     * Synchronise l'état du checkbox de groupe campagne :
     * coché si toutes les poses du groupe sont cochées,
     * indéterminé si partiellement, non coché si aucune.
     */
    function syncGroupCheckState(campaignId) {
        if (!campaignId) return;
        const groupCheck = document.querySelector(
            `.pose-group-check[data-campaign-id="${campaignId}"]`
        );
        if (!groupCheck) return;
        const boxes = document.querySelectorAll(
            `.pose-check[data-campaign-id="${campaignId}"]:not([disabled])`
        );
        if (boxes.length === 0) {
            groupCheck.checked = false;
            groupCheck.indeterminate = false;
            return;
        }
        const checkedCount = Array.from(boxes).filter(b => b.checked).length;
        if (checkedCount === 0) {
            groupCheck.checked = false;
            groupCheck.indeterminate = false;
        } else if (checkedCount === boxes.length) {
            groupCheck.checked = true;
            groupCheck.indeterminate = false;
        } else {
            groupCheck.checked = false;
            groupCheck.indeterminate = true;
        }
    }

    // ── Plier / déplier les panneaux d'une campagne ────────────
    // État persistant par campagne dans sessionStorage pour rester
    // cohérent après refresh AJAX (filtre / pagination).
    //
    // Comportement par DÉFAUT : à la 1ère visite, toutes les
    // campagnes sont PLIÉES. L'utilisateur déplie celles qu'il veut
    // consulter. Les sessions suivantes restaurent ses choix.
    //
    // On reconnaît la "1ère visite" via une clé dédiée `pose_groups_initialized`
    // (boolean). Tant qu'elle n'est pas posée, on plie tout. Posée → on
    // respecte le sessionStorage (qui peut être vide = tout déplié si
    // l'utilisateur a explicitement tout ouvert).
    const POSE_INIT_KEY = 'pose_groups_initialized';
    let collapsedGroups;
    if (!sessionStorage.getItem(POSE_INIT_KEY)) {
        // 1ère visite de la session → on plie toutes les campagnes affichées
        const allIds = Array.from(document.querySelectorAll('.pose-group-toggle'))
            .map(b => b.dataset.campaignToggle)
            .filter(Boolean);
        collapsedGroups = new Set(allIds);
        sessionStorage.setItem('pose_collapsed_groups', JSON.stringify([...collapsedGroups]));
        sessionStorage.setItem(POSE_INIT_KEY, '1');
    } else {
        collapsedGroups = new Set(JSON.parse(sessionStorage.getItem('pose_collapsed_groups') || '[]'));
    }
    function persistCollapsedGroups() {
        sessionStorage.setItem('pose_collapsed_groups', JSON.stringify([...collapsedGroups]));
    }

    // Mémorise les campagnes que l'utilisateur a EXPLICITEMENT ouvertes
    // pendant cette session. Sert à ne PAS les re-plier automatiquement
    // après un refresh AJAX (filtre / pagination).
    const explicitlyExpanded = new Set(
        JSON.parse(sessionStorage.getItem('pose_explicitly_expanded') || '[]')
    );
    function persistExplicitExpanded() {
        sessionStorage.setItem('pose_explicitly_expanded', JSON.stringify([...explicitlyExpanded]));
    }
    function applyCollapsedState(cid) {
        const isCollapsed = collapsedGroups.has(cid);
        document.querySelectorAll(`.trow[data-campaign-group="${cid}"]`).forEach(row => {
            row.style.display = isCollapsed ? 'none' : '';
        });
        const btn = document.querySelector(`.pose-group-toggle[data-campaign-toggle="${cid}"]`);
        if (btn) {
            btn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            btn.style.transform = isCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
        }
    }
    function refreshAllGroupsCollapse() {
        // Pour chaque header campagne présent dans le DOM, applique l'état stocké
        document.querySelectorAll('.pose-group-toggle').forEach(btn => {
            applyCollapsedState(btn.dataset.campaignToggle);
        });
    }
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.pose-group-toggle');
        if (!btn) return;
        const cid = btn.dataset.campaignToggle;
        if (collapsedGroups.has(cid)) {
            // L'utilisateur OUVRE cette campagne explicitement.
            collapsedGroups.delete(cid);
            explicitlyExpanded.add(cid);
        } else {
            // L'utilisateur la REFERME — on retire aussi de la liste
            // "explicitement ouvert" pour que le prochain refresh la
            // garde fermée par défaut.
            collapsedGroups.add(cid);
            explicitlyExpanded.delete(cid);
        }
        persistCollapsedGroups();
        persistExplicitExpanded();
        applyCollapsedState(cid);
    });
    // Applique l'état au chargement
    refreshAllGroupsCollapse();

    // Quand la table est rechargée en AJAX (filtre / pagination), on
    // restaure l'état coché des checkboxes encore présentes.
    const tableEl = document.getElementById('table-container');
    if (tableEl) {
        const obs = new MutationObserver(() => {
            document.querySelectorAll('.pose-check').forEach(box => {
                if (selected.has(Number(box.value))) box.checked = true;
            });
            // Re-sync les groupes campagne
            const seenCids = new Set();
            document.querySelectorAll('.pose-check').forEach(box => {
                const cid = box.dataset.campaignId;
                if (cid && !seenCids.has(cid)) {
                    seenCids.add(cid);
                    syncGroupCheckState(cid);
                }
            });
            syncCheckAllState();
            // Auto-plie les NOUVELLES campagnes apparues après filtre/pagination
            // (pas encore connues dans collapsedGroups). Préserve les choix
            // utilisateur sur les campagnes déjà ouvertes.
            const knownAfterRefresh = new Set();
            document.querySelectorAll('.pose-group-toggle').forEach(btn => {
                const cid = btn.dataset.campaignToggle;
                if (cid) {
                    knownAfterRefresh.add(cid);
                    if (!collapsedGroups.has(cid) && !explicitlyExpanded.has(cid)) {
                        collapsedGroups.add(cid);
                    }
                }
            });
            persistCollapsedGroups();
            refreshAllGroupsCollapse();
        });
        obs.observe(tableEl, { childList: true, subtree: true });
    }

    // Bouton effacer rapide
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            selected.clear();
            document.querySelectorAll('.pose-check').forEach(b => { b.checked = false; });
            document.querySelectorAll('.pose-group-check').forEach(g => {
                g.checked = false;
                g.indeterminate = false;
            });
            syncBar();
        });
    }

    // ── Helpers bulk submit ────────────────────────────────────────
    function showFeedback(result) {
        if (typeof window.showToast !== 'function') {
            alert(result.error || (`${result.updated} mise(s) à jour, ${result.skipped} ignorée(s)`));
            return;
        }
        if (!result.ok) {
            showToast('error', result.error || 'Action impossible.', 4000, 'Action groupée');
            return;
        }
        let msg = `${result.updated} tâche(s) mise(s) à jour`;
        if (result.skipped) msg += ` · ${result.skipped} ignorée(s)`;
        // Feedback batch notif WhatsApp — 1 seul lien envoyé au tech
        // par campagne, pas N liens. Précisé dans le toast pour rassurer
        // l'admin que la notification a bien été déclenchée.
        if (result.notified > 0) {
            msg += ` · 📲 ${result.notified} notif(s) WhatsApp envoyée(s)`;
        }
        showToast('success', msg, 4000, 'Action groupée');
    }

    async function postBulk(action, value, confirmMsg) {
        if (selected.size === 0) {
            showToast('warning', 'Aucune tâche sélectionnée.', 2500, 'Action groupée');
            return;
        }
        if (confirmMsg && !confirm(confirmMsg)) return;

        const fd = new FormData();
        selected.forEach(id => fd.append('task_ids[]', id));
        fd.append('action', action);
        if (value !== null && value !== undefined) fd.append('value', value);

        try {
            const r = await fetch(ENDPOINT, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':    CSRF,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':          'application/json',
                },
                body: fd,
            });
            const data = await r.json().catch(() => ({ ok: false, error: 'Réponse invalide.' }));
            showFeedback(data);

            if (data.ok && data.updated > 0) {
                // Recharge la table en réutilisant le filtre AJAX existant.
                // window.S est exposé par le bloc filtres ; sinon reload.
                if (typeof window._reloadPosesTable === 'function') {
                    window._reloadPosesTable();
                } else {
                    // Fallback : reload douce
                    setTimeout(() => location.reload(), 1200);
                }
                // Garder la sélection des IDs encore présents après le
                // reload table — le MutationObserver s'en charge.
            }
        } catch (e) {
            showToast('error', 'Échec réseau : ' + e.message, 4000, 'Action groupée');
        }
    }

    // ── Wiring boutons ────────────────────────────────────────────
    document.getElementById('bulk-tech-apply')?.addEventListener('click', () => {
        const sel = document.getElementById('bulk-tech');
        const val = sel.value;
        if (!val) { showToast('warning', 'Choisissez un technicien.', 2500, 'Action groupée'); return; }
        const apiVal = val === '__unset__' ? '' : val;
        const label  = val === '__unset__' ? 'retirer l\'assignation'
                       : 'assigner ' + (sel.options[sel.selectedIndex]?.text || 'ce technicien');
        postBulk('assign_tech', apiVal,
            `Confirmer : ${label} sur ${selected.size} tâche(s) ?`);
    });

    document.getElementById('bulk-team-apply')?.addEventListener('click', () => {
        const input = document.getElementById('bulk-team');
        const val   = (input.value || '').trim();
        const label = val ? `équipe "${val}"` : 'retirer le nom d\'équipe';
        postBulk('rename_team', val,
            `Confirmer : ${label} sur ${selected.size} tâche(s) ?`);
    });

    document.getElementById('bulk-status-apply')?.addEventListener('click', () => {
        const sel = document.getElementById('bulk-status');
        const val = sel.value;
        if (!val) { showToast('warning', 'Choisissez un statut.', 2500, 'Action groupée'); return; }
        const label = sel.options[sel.selectedIndex]?.text || val;
        postBulk('change_status', val,
            `Confirmer : passer ${selected.size} tâche(s) en « ${label} » ?`);
    });

    document.getElementById('bulk-date-apply')?.addEventListener('click', () => {
        const input = document.getElementById('bulk-date');
        const val   = input.value;
        if (!val) { showToast('warning', 'Choisissez une date.', 2500, 'Action groupée'); return; }
        postBulk('reschedule', val,
            `Confirmer : replanifier ${selected.size} tâche(s) au ${val.replace('T', ' à ')} ?`);
    });

    // Init initial
    syncBar();
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

.action-btn {
    display:inline-flex;align-items:center;justify-content:center;
    width:34px;height:34px;border-radius:9px;
    border:1px solid var(--border);background:var(--surface2);
    color:var(--text2);text-decoration:none;cursor:pointer;
    transition:all .15s;flex-shrink:0;
}
.action-btn:hover { background:var(--surface3);border-color:var(--border2);color:var(--text); }
.action-btn-success { border-color:rgba(34,197,94,.3);background:rgba(34,197,94,.08);color:#22c55e; }
.action-btn-success:hover { background:rgba(34,197,94,.18);border-color:rgba(34,197,94,.5); }
.action-btn-accent { border-color:rgba(232,160,32,.3);background:rgba(232,160,32,.08);color:var(--accent); }
.action-btn-accent:hover { background:rgba(232,160,32,.18);border-color:rgba(232,160,32,.5); }
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
        date_to: '',
        show_orphan: false,
        page: 1,
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
        showOrphan: document.getElementById('filter-show-orphan'),
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

    // Exposé pour permettre au bloc "actions groupées" de recharger la
    // table après un bulk update sans dupliquer la logique de fetch.
    window._reloadPosesTable = () => applyFilters(1);

    // Interception des liens de pagination → AJAX au lieu de rechargement
    // complet, ce qui préserve la sélection en mémoire JS.
    document.addEventListener('click', (e) => {
        const link = e.target.closest('#pagination-container a');
        if (!link) return;
        e.preventDefault();
        try {
            const page = parseInt(new URL(link.href).searchParams.get('page') || '1', 10);
            applyFilters(page);
        } catch (_) {
            applyFilters(1);
        }
    });

    async function applyFilters(page = 1) {
        currentFilters.page = page;
        if (isUpdating) return;
        isUpdating = true;

        const params = new URLSearchParams();
        if (currentFilters.search) params.set('q', currentFilters.search);
        if (currentFilters.status) params.set('status', currentFilters.status);
        if (currentFilters.technicien_id) params.set('technicien_id', currentFilters.technicien_id);
        if (currentFilters.campaign_id) params.set('campaign_id', currentFilters.campaign_id);
        if (currentFilters.date_from) params.set('date_from', currentFilters.date_from);
        if (currentFilters.date_to) params.set('date_to', currentFilters.date_to);
        if (currentFilters.show_orphan) params.set('show_orphan', '1');
        if (currentFilters.page > 1) params.set('page', currentFilters.page);
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

            if (elements.resultCount && data.total !== undefined) {
                elements.resultCount.textContent = new Intl.NumberFormat('fr-FR').format(data.total);
            }

            if (elements.paginationContainer && data.pagination) {
                elements.paginationContainer.innerHTML = data.pagination;
            }

            // Met à jour les KPI cards (counts par statut + total)
            if (data.stats) {
                updateKpiCards(data.stats);
                updateActiveKpi();
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
            
            // is-active UNIQUEMENT pour le statut précis filtré.
            // "Total" (data-status="") reste neutre.
            document.querySelectorAll('.kpi-card.filter-stat').forEach(card => {
                const status = card.dataset.status;
                if (currentFilters.status && status === currentFilters.status) {
                    card.classList.add('is-active');
                } else {
                    card.classList.remove('is-active');
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

    if (elements.showOrphan) {
        elements.showOrphan.addEventListener('change', () => {
            currentFilters.show_orphan = elements.showOrphan.checked;
            syncOrphanBtnUI();
            applyFilters();
        });
    }
    // Bouton toggle "Orphelines / Afficher" — pilote le checkbox caché
    const orphBtn = document.getElementById('filter-show-orphan-btn');
    if (orphBtn && elements.showOrphan) {
        orphBtn.addEventListener('click', () => {
            elements.showOrphan.checked = !elements.showOrphan.checked;
            elements.showOrphan.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
    function syncOrphanBtnUI() {
        if (!orphBtn || !elements.showOrphan) return;
        const a = !!elements.showOrphan.checked;
        orphBtn.style.background  = a ? 'var(--accent)' : 'var(--surface2)';
        orphBtn.style.borderColor = a ? 'var(--accent)' : 'var(--border2)';
        orphBtn.style.color       = a ? '#fff' : 'var(--text2)';
        const ic = orphBtn.querySelector('span:first-child');
        if (ic) ic.textContent = a ? '✓' : '○';
    }

    // Cartes KPI — toggle (re-cliquer = retire le filtre).
    // La carte "total" reset le filtre status.
    document.querySelectorAll('.kpi-card.filter-stat').forEach(card => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            const kpi    = card.dataset.kpi;
            const status = card.dataset.status;

            if (kpi === 'total') {
                currentFilters.status = '';
                if (elements.status) elements.status.value = '';
            } else if (status && elements.status) {
                const isActive = currentFilters.status === status;
                currentFilters.status = isActive ? '' : status;
                elements.status.value = currentFilters.status;
            }
            updateResetButton();
            applyFilters();
            updateActiveKpi();
        });
    });

    function updateActiveKpi() {
        document.querySelectorAll('.kpi-card.filter-stat').forEach(c => {
            const k = c.dataset.kpi;
            const s = c.dataset.status;
            // is-active uniquement pour le statut précis filtré.
            // "Total" reste neutre dans tous les cas.
            const active = k !== 'total' && !!currentFilters.status && currentFilters.status === s;
            c.classList.toggle('is-active', active);
        });
    }

    // Met à jour les valeurs des KPI après un fetch AJAX
    function updateKpiCards(stats) {
        if (!stats) return;
        document.querySelectorAll('[data-kpi-value]').forEach(el => {
            const k = el.dataset.kpiValue;
            if (stats[k] !== undefined) {
                el.textContent = new Intl.NumberFormat('fr-FR').format(stats[k]);
            }
        });
    }

    // Reset button
    if (elements.resetBtn) {
        elements.resetBtn.addEventListener('click', () => {
            currentFilters = {
                search: '',
                status: '',
                technicien_id: '',
                campaign_id: '',
                date_from: '',
                date_to: '',
                show_orphan: false
            };

            if (elements.search) elements.search.value = '';
            if (elements.status) elements.status.value = '';
            if (elements.technicien) elements.technicien.value = '';
            if (elements.campaign) elements.campaign.value = '';
            if (elements.dateFrom) elements.dateFrom.value = '';
            if (elements.dateTo) elements.dateTo.value = '';
            if (elements.showOrphan) elements.showOrphan.checked = false;

            document.querySelectorAll('.kpi-card.filter-stat').forEach(card => card.classList.remove('is-active'));

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
    if (urlParams.has('show_orphan')) currentFilters.show_orphan = ['1','true','on'].includes(urlParams.get('show_orphan'));

    if (elements.search && currentFilters.search) elements.search.value = currentFilters.search;
    if (elements.status && currentFilters.status) elements.status.value = currentFilters.status;
    if (elements.technicien && currentFilters.technicien_id) elements.technicien.value = currentFilters.technicien_id;
    if (elements.campaign && currentFilters.campaign_id) elements.campaign.value = currentFilters.campaign_id;
    if (elements.dateFrom && currentFilters.date_from) elements.dateFrom.value = currentFilters.date_from;
    if (elements.dateTo && currentFilters.date_to) elements.dateTo.value = currentFilters.date_to;
    if (elements.showOrphan) elements.showOrphan.checked = currentFilters.show_orphan;
    
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