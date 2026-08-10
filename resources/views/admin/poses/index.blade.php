<x-admin-layout title="Pose OOH">

<x-slot:topbarActions>
    <a href="{{ route('admin.pose-tasks.oubliees') }}" class="btn btn-ghost btn-sm" style="color:#dc2626;border-color:#fecaca" title="Rattraper les poses faites sur le terrain mais oubliées en saisie">
        🕒 <span class="btn-label">Poses oubliées</span>
    </a>
    <a href="{{ route('admin.pose-tasks.techniciens.index') }}" class="btn btn-ghost btn-sm" title="Gérer les techniciens (création / lien public / WhatsApp)">
        🔧 <span class="btn-label">Techniciens</span>
    </a>
    <a href="{{ route('admin.pose-tasks.calendar') }}" class="btn btn-ghost btn-sm" title="Planning hebdomadaire par technicien">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span class="btn-label">Calendrier</span>
    </a>
    <a href="{{ route('admin.pose-tasks.map') }}" class="btn btn-ghost btn-sm" title="Visualiser les poses sur une carte">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <span class="btn-label">Vue Carte</span>
    </a>
    <a href="{{ route('admin.pose-tasks.create') }}" class="btn btn-primary keep-label" title="Créer une nouvelle tâche de pose">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span class="btn-label">Nouvelle tâche</span>
    </a>
</x-slot:topbarActions>

{{-- ════ BANDEAU "FILTRES ACTIFS DEPUIS UNE AUTRE PAGE" ════
     Affiché quand l'utilisateur arrive ici via un lien contextuel
     (KPI pilotage, drill-down perf tech, etc.). Résumé humain des
     filtres actifs + boutons "Effacer" et "← Retour" pour ne pas
     laisser l'admin bloqué sur une liste filtrée sans échappatoire.
     Ajouté 2026-07-01 sur feedback patronne. --}}
@php
    $activeFilters = collect([
        'status' => match(request('status')) {
            'planifiee' => 'Planifiées',
            'en_cours'  => 'En cours',
            'realisee'  => 'Réalisées',
            'annulee'   => 'Annulées',
            default     => null,
        },
        'technicien' => request('technicien_id')
            ? optional(\App\Models\User::find(request('technicien_id')))->name
            : null,
        'campagne'   => request('campaign_id')
            ? optional(\App\Models\Campaign::find(request('campaign_id')))->name
            : null,
        'periode'    => (request('date_from') || request('date_to'))
            ? trim((request('date_from') ? 'du ' . \Carbon\Carbon::parse(request('date_from'))->format('d/m/Y') : '')
                . ' '
                . (request('date_to')   ? 'au ' . \Carbon\Carbon::parse(request('date_to'))->format('d/m/Y') : ''))
            : null,
        'equipe'     => request('team_name'),
    ])->filter();

    // Détecter la page d'origine à partir du referer pour le lien "Retour"
    $referer = url()->previous();
    $refererLabel = null;
    $refererUrl   = null;
    if ($referer && $referer !== url()->current()) {
        $host = parse_url($referer, PHP_URL_HOST);
        $path = parse_url($referer, PHP_URL_PATH) ?? '';
        if ($host && $host === request()->getHost()) {
            if (str_contains($path, '/admin/pilotage')) { $refererLabel = 'Retour au Pilotage terrain'; $refererUrl = $referer; }
            elseif (str_contains($path, '/admin/performance/techniciens')) { $refererLabel = 'Retour à la Performance technicien'; $refererUrl = $referer; }
            elseif (str_contains($path, '/admin/dashboard')) { $refererLabel = 'Retour au Tableau de bord'; $refererUrl = $referer; }
            elseif (str_contains($path, '/admin/rapports')) { $refererLabel = 'Retour aux Rapports'; $refererUrl = $referer; }
        }
    }
@endphp
@if($activeFilters->isNotEmpty())
<div style="background:linear-gradient(90deg,rgba(232,160,32,.08),rgba(232,160,32,.02));border:1px solid rgba(232,160,32,.35);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:200px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2" style="flex-shrink:0">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
        </svg>
        <div>
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;font-weight:700;color:#b45309;margin-bottom:3px">
                Filtres actifs
            </div>
            <div style="font-size:12px;color:var(--text);display:flex;flex-wrap:wrap;gap:6px">
                @foreach($activeFilters as $key => $val)
                    <span style="padding:2px 10px;background:rgba(232,160,32,.15);border-radius:12px;font-weight:600">
                        {{ ucfirst($key) }} : {{ $val }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-shrink:0">
        @if($refererUrl)
            <a href="{{ $refererUrl }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap">
                ← {{ $refererLabel }}
            </a>
        @endif
        <a href="{{ route('admin.pose-tasks.index') }}"
           style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap"
           title="Voir toutes les tâches (retirer tous les filtres)">
            ✕ Effacer les filtres
        </a>
    </div>
</div>
@endif

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
    ['s'=>'en_cours',  'l'=>'En cours',   'c'=>'#3b82f6',       'sub'=>'sur le terrain',    'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>'],
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
                @foreach(['planifiee'=>'📅 Planifiée','en_cours'=>'▶ En cours','realisee'=>'✅ Réalisée','annulee'=>'🚫 Annulée'] as $v => $l)
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

        {{-- Filtre Équipe (2026-06-18 — mission équipes pose tasks) --}}
        <div class="filter-group">
            <label class="filter-label">Équipe</label>
            <select id="filter-team" class="filter-select" style="width:150px">
                <option value="">Toutes</option>
                @foreach(($teams ?? collect()) as $t)
                <option value="{{ $t->name }}">{{ $t->name }}</option>
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

        {{-- Toggle "Campagnes inactives" — poses dont la campagne parente
             est en pause / terminée / annulée / supprimée. Masquées par
             défaut (cache les tâches devenues obsolètes). --}}
        @php $orphActive = request()->boolean('show_orphan'); @endphp
        <div class="filter-group">
            <label class="filter-label">Campagnes inactives</label>
            <button type="button" id="filter-show-orphan-btn"
                    title="Inclure les poses dont la campagne est en pause, terminée, annulée ou supprimée"
                    style="display:inline-flex;align-items:center;gap:8px;height:38px;cursor:pointer;padding:0 14px;border-radius:10px;font-size:13px;font-weight:600;white-space:nowrap;transition:all .15s;
                           border:1px solid {{ $orphActive ? 'var(--accent)' : 'var(--border2)' }};
                           background:{{ $orphActive ? 'var(--accent)' : 'var(--surface2)' }};
                           color:{{ $orphActive ? '#fff' : 'var(--text2)' }};">
                <span>{{ $orphActive ? '✓' : '○' }}</span>
                <span>Inclure</span>
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
    {{-- Card-header normal — visible quand aucune sélection. --}}
    <div class="card-header" id="pose-card-header-default">
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

    {{-- ════ TOOLBAR ACTIONS GROUPÉES — pattern Gmail inline ════
         Remplace le card-header quand au moins une tâche est cochée.
         Pas de modal flottant : la barre s'intègre au flux du document,
         n'obscurcit pas la liste, jamais à déplacer. Disparaît dès que
         la sélection retombe à 0. ════ --}}
    <div id="bulk-bar" class="pose-bulk-toolbar">
        <div class="pose-bulk-header">
            <button type="button" id="bulk-clear" class="pose-bulk-clear" title="Tout désélectionner">✕</button>
            <span class="pose-bulk-count">
                <strong id="bulk-count-badge">0</strong>
                <span id="bulk-count-noun">tâche sélectionnée</span>
            </span>
            <span class="pose-bulk-subtitle">— actions groupées</span>
        </div>

        <div class="pose-bulk-grid">
            {{-- Technicien --}}
            <div class="pose-bulk-field">
                <label class="pose-bulk-label">
                    <span>🧑‍🔧 Technicien</span>
                    <button type="button" id="bulk-suggest-tech" class="pose-bulk-suggest-btn"
                            title="Suggère le meilleur tech selon zone + charge + perf">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 1 1 7.072 0l-.548.547A3.374 3.374 0 0 0 14 18.469V19a2 2 0 1 1-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        Suggérer
                    </button>
                </label>
                <div class="pose-bulk-input-row">
                    <select id="bulk-tech" class="filter-select">
                        <option value="">— Choisir —</option>
                        <option value="__unset__">(retirer l'assignation)</option>
                        @foreach($techniciens as $tech)
                            <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" id="bulk-tech-apply" class="btn btn-sm btn-primary">Appliquer</button>
                </div>
                <div id="bulk-tech-hint" class="pose-bulk-hint"></div>
                <div id="bulk-suggest-result" class="pose-bulk-suggest-result"></div>
            </div>

            {{-- Équipe (2026-06-18) — input texte libre remplacé par un VRAI
                 select alimenté par PoseTeam. Option vide en tête = "détacher"
                 (envoie une chaîne vide → côté serveur on traitera comme null). --}}
            <div class="pose-bulk-field">
                <label class="pose-bulk-label" title="Attribue le mérite pose à une équipe (crédit collectif). Cf. rapport perf équipe."><span>👥 Équipe créditée</span></label>
                <div class="pose-bulk-input-row">
                    {{-- 2026-08-10 : basculé sur pose_team_id via action bulk
                         "assign_team". Le service met à jour pose_team_id ET
                         team_name (snapshot). Option 0 = pose solo. --}}
                    <select id="bulk-team" class="filter-select">
                        <option value="">— Choisir —</option>
                        <option value="0">(retirer — pose solo)</option>
                        @foreach(($teams ?? collect()) as $t)
                            <option value="{{ $t->id }}">👥 {{ $t->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" id="bulk-team-apply" class="btn btn-sm btn-ghost">Appliquer</button>
                </div>
                <div class="pose-bulk-hint" style="font-size:10.5px;color:var(--text3);margin-top:4px;font-style:italic">
                    Attribue à une équipe = crédit collectif dans /performance/équipes.
                </div>
            </div>

            {{-- Replanifier --}}
            <div class="pose-bulk-field">
                <label class="pose-bulk-label"><span>📅 Date / heure</span></label>
                <div class="pose-bulk-input-row">
                    <input type="datetime-local" id="bulk-date" class="filter-input">
                    <button type="button" id="bulk-date-apply" class="btn btn-sm btn-ghost">Replanifier</button>
                </div>
            </div>

            {{-- Statut --}}
            <div class="pose-bulk-field">
                <label class="pose-bulk-label"><span>⚙️ Statut</span></label>
                <div class="pose-bulk-input-row">
                    <select id="bulk-status" class="filter-select">
                        <option value="">— Choisir —</option>
                        <option value="planifiee">📅 Planifiée</option>
                        <option value="en_cours">▶ En cours</option>
                        <option value="annulee">🚫 Annuler</option>
                    </select>
                    <button type="button" id="bulk-status-apply" class="btn btn-sm btn-ghost">Appliquer</button>
                </div>
            </div>

            {{-- Rechange bulk (multi-poses 2026-08-08) — bouton qui ouvre
                 un modal léger avec date + tech + notes + type. Le service
                 vérifie chaque pose (status realisee + non replaced) et
                 ignore silencieusement les non-éligibles. --}}
            <div class="pose-bulk-field" style="grid-column:span 2">
                <label class="pose-bulk-label"><span>🔄 Rechange affiche</span></label>
                <div class="pose-bulk-input-row">
                    <button type="button" id="bulk-rechange-open"
                            class="btn btn-sm"
                            style="background:linear-gradient(135deg,#f59e0b,#b45309);color:#fff;border:0;font-weight:700;box-shadow:0 3px 10px rgba(245,158,11,.3)">
                        Créer rechange sur la sélection…
                    </button>
                    <span style="font-size:11px;color:var(--text3)">
                        seulement les poses <em>réalisées</em> et non déjà remplacées seront traitées
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ MODAL bulk rechange (liste poses) — multi-poses 2026-08-08 ═══
         Utilise les IDs déjà cochés dans la liste (window.selected Set) et
         POST vers admin.pose-tasks.rechange-bulk. Feedback via toast. --}}
    <div id="bulk-rechange-modal"
         style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;align-items:center;justify-content:center;padding:16px"
         onclick="if(event.target===this)document.getElementById('bulk-rechange-modal').style.display='none'">
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;max-width:520px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden">
            <div style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;font-size:18px">🔄</div>
                <div style="flex:1">
                    <h3 style="margin:0;font-size:15px;font-weight:800;color:var(--text)">Rechange en lot</h3>
                    <p style="margin:2px 0 0;font-size:12px;color:var(--text3)">
                        <strong id="bulk-rechange-count">0</strong> pose(s) sélectionnée(s)
                    </p>
                </div>
                <button type="button" onclick="document.getElementById('bulk-rechange-modal').style.display='none'"
                        style="background:transparent;border:0;color:var(--text3);cursor:pointer;padding:4px;line-height:0">✕</button>
            </div>
            <div style="padding:20px 22px">
                <div style="background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.15);border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:12px;color:var(--text2);line-height:1.5">
                    💡 Chaque pose éligible (réalisée + non déjà remplacée) recevra un rechange identique. Les autres seront ignorées.
                </div>
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text2);margin-bottom:6px">Type</label>
                    <select id="bulk-rechange-kind"
                            style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text)">
                        <option value="rechange" selected>🔄 Rechange affiche</option>
                        <option value="retouche">🔧 Retouche</option>
                    </select>
                </div>
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text2);margin-bottom:6px">Date &amp; heure prévues <span style="color:#ef4444">*</span></label>
                    <input type="datetime-local" id="bulk-rechange-date"
                           value="{{ now()->addDay()->format('Y-m-d\TH:i') }}"
                           style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text)">
                </div>
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text2);margin-bottom:6px">Technicien (tous les rechanges)</label>
                    <select id="bulk-rechange-tech"
                            style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text)">
                        <option value="">— Non assigné —</option>
                        @foreach($techniciens as $tech)
                            <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- 2026-08-10 : select équipe pour attribution mérite collectif.
                     Vide = hérite du parent (comportement défaut createRechange). --}}
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text2);margin-bottom:6px">Équipe créditée (facultatif)</label>
                    <select id="bulk-rechange-team"
                            style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text)">
                        <option value="">— Hérite du parent (recommandé) —</option>
                        <option value="0">Aucune (rechange solo)</option>
                        @foreach(($teams ?? collect()) as $t)
                            <option value="{{ $t->id }}">👥 {{ $t->name }} — crédit équipe</option>
                        @endforeach
                    </select>
                    <div style="font-size:10.5px;color:var(--text3);margin-top:4px;font-style:italic;line-height:1.4">
                        Par défaut le rechange conserve le contexte du parent (solo→solo, équipe→équipe).
                    </div>
                </div>
                <div style="margin-bottom:16px">
                    <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text2);margin-bottom:6px">Notes (facultatif)</label>
                    <textarea id="bulk-rechange-notes" rows="2" maxlength="1000"
                              placeholder="Ex: nouveau visuel"
                              style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text);resize:vertical"></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end">
                    <button type="button" onclick="document.getElementById('bulk-rechange-modal').style.display='none'"
                            style="padding:9px 16px;background:var(--surface2);border:1px solid var(--border2);color:var(--text2);border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">
                        Annuler
                    </button>
                    <button type="button" id="bulk-rechange-submit"
                            style="padding:9px 18px;background:linear-gradient(135deg,#f59e0b,#b45309);color:#fff;border:0;border-radius:8px;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 4px 12px rgba(245,158,11,.3)">
                        🔄 Créer les rechanges
                    </button>
                </div>
            </div>
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

<script>
// ════════════════════════════════════════════════════════════════
// TOOLBAR ACTIONS GROUPÉES — pré-remplissage tech + équipe + suggestion
// (refonte modal flottant → toolbar inline qui remplace card-header)
// ════════════════════════════════════════════════════════════════
(function () {
    const bar = document.getElementById('bulk-bar');
    if (!bar) return;

    // ── Pré-remplissage du technicien si tous les cochés ont le même ──
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

        // Équipe : commune ? 2026-08-10 bascule sur data-team-id (FK)
        // au lieu de data-team (name). Fallback name conservé si FK vide.
        const teamIds = [...new Set(checked.map(cb => cb.dataset.teamId || ''))];
        if (teamIds.length === 1 && teamIds[0] !== '' && teamInp && !teamInp.dataset.userTouched) {
            teamInp.value = teamIds[0];
        }
    }

    // Marquer les inputs comme "touchés" pour ne pas écraser la saisie user
    document.getElementById('bulk-tech')?.addEventListener('change', function () { this.dataset.userTouched = '1'; });
    document.getElementById('bulk-team')?.addEventListener('change', function () { this.dataset.userTouched = '1'; });

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

    const bar        = document.getElementById('bulk-bar');
    const badge      = document.getElementById('bulk-count-badge');
    const nounEl     = document.getElementById('bulk-count-noun');
    const cardHeader = document.getElementById('pose-card-header-default');
    const checkAll   = document.getElementById('pose-check-all');
    const clearBtn   = document.getElementById('bulk-clear');

    // Set des IDs sélectionnés — persisté en mémoire JS (perd au reload, OK).
    const selected = new Set();

    function syncBar() {
        const n = selected.size;
        if (badge)  badge.textContent  = String(n);
        if (nounEl) nounEl.textContent = n > 1 ? 'tâches sélectionnées' : 'tâche sélectionnée';
        // Toolbar inline : .visible révèle la barre, le card-header s'efface
        // pendant ce temps (jamais les deux ensemble — pattern Gmail).
        if (bar) bar.classList.toggle('visible', n > 0);
        if (cardHeader) cardHeader.style.display = n > 0 ? 'none' : 'flex';
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
    // Comportement attendu :
    //  • Au chargement initial → toutes les campagnes sont PLIÉES.
    //  • Quand l'utilisateur clique sur un chevron → ce groupe précis
    //    s'ouvre, et il RESTE OUVERT tant que l'utilisateur n'a pas
    //    re-cliqué dessus (même si un refresh AJAX se déclenche).
    //  • Aucune mémoire entre visites (Set JS uniquement).
    const collapsedGroups = new Set();
    // Groupes qui ont déjà reçu l'état initial "plié" — évite de re-plier
    // un groupe que l'utilisateur a explicitement ouvert puis qu'un
    // refresh AJAX remet dans le DOM.
    const initializedGroups = new Set();

    function setGroupCollapsed(cid, collapsed) {
        if (collapsed) collapsedGroups.add(cid);
        else           collapsedGroups.delete(cid);
        document.querySelectorAll(`.trow[data-campaign-group="${cid}"]`).forEach(row => {
            row.style.display = collapsed ? 'none' : '';
        });
        const btn = document.querySelector(`.pose-group-toggle[data-campaign-toggle="${cid}"]`);
        if (btn) {
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            btn.style.transform = collapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
        }
    }

    function applyGroupsState() {
        // Pour chaque groupe présent dans le DOM :
        //  - Si l'utilisateur a ouvert ce groupe (pas dans collapsedGroups
        //    mais déjà initialisé) → on le laisse ouvert.
        //  - Sinon (jamais vu, ou explicitement plié) → on le plie.
        document.querySelectorAll('.pose-group-toggle').forEach(btn => {
            const cid = btn.dataset.campaignToggle;
            if (!cid) return;
            if (!initializedGroups.has(cid)) {
                // Premier passage pour ce groupe → plié par défaut
                initializedGroups.add(cid);
                setGroupCollapsed(cid, true);
            } else {
                // Déjà vu → applique l'état mémorisé (plié ou non)
                setGroupCollapsed(cid, collapsedGroups.has(cid));
            }
        });
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.pose-group-toggle');
        if (!btn) return;
        const cid = btn.dataset.campaignToggle;
        initializedGroups.add(cid); // au cas où on clic avant init
        setGroupCollapsed(cid, !collapsedGroups.has(cid));
    });

    // Application initiale au chargement de la page
    applyGroupsState();

    // Quand la table est rechargée en AJAX (filtre / pagination / polling),
    // on restaure l'état coché des checkboxes ET l'état plié/déplié des
    // groupes que l'utilisateur a manipulés (sans re-plier les ouverts).
    const tableEl = document.getElementById('table-container');
    if (tableEl) {
        const obs = new MutationObserver(() => {
            document.querySelectorAll('.pose-check').forEach(box => {
                if (selected.has(Number(box.value))) box.checked = true;
            });
            // Re-sync les groupes campagne (checkboxes de sélection)
            const seenCids = new Set();
            document.querySelectorAll('.pose-check').forEach(box => {
                const cid = box.dataset.campaignId;
                if (cid && !seenCids.has(cid)) {
                    seenCids.add(cid);
                    syncGroupCheckState(cid);
                }
            });
            syncCheckAllState();
            // Re-applique l'état plié/déplié SANS écraser le choix
            // utilisateur — les groupes qu'il a ouverts restent ouverts.
            applyGroupsState();
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
        const sel   = document.getElementById('bulk-team');
        const val   = (sel.value || '').trim();
        if (val === '') { showToast('warning', 'Choisissez une équipe.', 2500, 'Action groupée'); return; }
        // 2026-08-10 : bascule sur assign_team (pose_team_id FK). Valeur 0 =
        // retirer l'attribution (pose solo). Sinon = ID de l'équipe.
        const label = val === '0'
            ? 'retirer l\'attribution équipe (pose solo)'
            : `attribuer à l'équipe "${sel.options[sel.selectedIndex]?.text || ''}"`;
        postBulk('assign_team', val,
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

    // ═══ Bulk rechange (multi-poses 2026-08-08) ═══
    // Ouvre le modal dédié en injectant le count sélectionné, puis
    // POST vers admin.pose-tasks.rechange-bulk au submit. Toast feedback.
    document.getElementById('bulk-rechange-open')?.addEventListener('click', () => {
        if (selected.size === 0) {
            showToast('warning', 'Sélectionnez au moins une pose.', 2500, 'Rechange');
            return;
        }
        document.getElementById('bulk-rechange-count').textContent = selected.size;
        document.getElementById('bulk-rechange-modal').style.display = 'flex';
    });

    document.getElementById('bulk-rechange-submit')?.addEventListener('click', async () => {
        const dateVal  = document.getElementById('bulk-rechange-date').value;
        const kindVal  = document.getElementById('bulk-rechange-kind').value;
        const techVal  = document.getElementById('bulk-rechange-tech').value;
        const teamVal  = document.getElementById('bulk-rechange-team')?.value ?? '';
        const notesVal = document.getElementById('bulk-rechange-notes').value;
        if (!dateVal) {
            showToast('warning', 'Choisissez une date.', 2500, 'Rechange');
            return;
        }
        if (selected.size === 0) {
            showToast('warning', 'Aucune pose sélectionnée.', 2500, 'Rechange');
            document.getElementById('bulk-rechange-modal').style.display = 'none';
            return;
        }

        const btn = document.getElementById('bulk-rechange-submit');
        btn.disabled = true; btn.style.opacity = '.6';

        try {
            const fd = new FormData();
            selected.forEach(id => fd.append('task_ids[]', id));
            fd.append('scheduled_at', dateVal);
            fd.append('pose_kind', kindVal);
            if (techVal)  fd.append('assigned_user_id', techVal);
            // 2026-08-10 : teamVal '' = hérite parent (défaut), '0' = solo
            // (envoie explicitement une valeur vide pour clear pose_team_id),
            // sinon FK équipe. On envoie uniquement si != '' pour distinguer
            // "hérite" (clé absente) de "explicite" (clé présente = override).
            if (teamVal !== '') fd.append('pose_team_id', teamVal === '0' ? '' : teamVal);
            if (notesVal) fd.append('notes', notesVal);

            const res = await fetch(@json(route('admin.pose-tasks.rechange-bulk')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: fd,
            });
            const data = await res.json().catch(() => ({ ok: false, error: 'Réponse invalide' }));

            document.getElementById('bulk-rechange-modal').style.display = 'none';

            if (data.ok) {
                let msg = `${data.created} rechange(s) créé(s)`;
                if (data.skipped > 0) msg += ` — ${data.skipped} ignoré(s)`;
                showToast('success', msg, 4500, 'Rechange');
                // Reset la sélection + refresh du tableau
                selected.clear();
                if (typeof window._reloadPosesTable === 'function') {
                    window._reloadPosesTable();
                }
            } else {
                showToast('danger', data.error || 'Aucun rechange créé.', 5000, 'Rechange');
            }
        } catch (e) {
            document.getElementById('bulk-rechange-modal').style.display = 'none';
            showToast('danger', 'Erreur réseau.', 4000, 'Rechange');
            console.error(e);
        } finally {
            btn.disabled = false; btn.style.opacity = '1';
        }
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

/* .action-btn et variantes : styles centralises dans le partial
   admin/poses/partials/table-rows.blade.php (charges via Blade once),
   pour que la vue admin.campaigns.poses qui inclut le meme partial
   ait aussi les boutons stylés. */

/* ── Toolbar Actions Groupées — pattern Gmail inline ─────────────────
   Remplace le card-header (display:none côté JS) quand selection > 0.
   Grid responsive 4 colonnes desktop → 2 tablette → 1 mobile.            */
.pose-bulk-toolbar {
    display: none;
    flex-direction: column;
    gap: 12px;
    padding: 14px 18px;
    background: var(--accent-dim);
    border-bottom: 1px solid var(--accent);
    animation: poseBulkSlide .18s ease-out;
}
.pose-bulk-toolbar.visible { display: flex; }
@keyframes poseBulkSlide {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.pose-bulk-header {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.pose-bulk-clear {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text2);
    font-size: 18px;
    padding: 4px 10px;
    border-radius: 6px;
    line-height: 1;
    transition: background .12s, color .12s;
}
.pose-bulk-clear:hover { background: var(--surface); color: #ef4444; }
.pose-bulk-count {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    display: inline-flex;
    align-items: baseline;
    gap: 6px;
}
.pose-bulk-count strong {
    color: var(--accent);
    font-size: 18px;
    font-weight: 800;
}
.pose-bulk-subtitle {
    font-size: 12px;
    color: var(--text3);
    font-weight: 500;
}
.pose-bulk-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}
@media (max-width: 1100px) { .pose-bulk-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .pose-bulk-grid { grid-template-columns: 1fr; } }
.pose-bulk-field { display: flex; flex-direction: column; min-width: 0; }
.pose-bulk-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: var(--text3);
    margin-bottom: 6px;
}
.pose-bulk-input-row {
    display: flex;
    gap: 6px;
    min-width: 0;
}
.pose-bulk-input-row > .filter-select,
.pose-bulk-input-row > .filter-input { flex: 1; min-width: 0; }
.pose-bulk-input-row > .btn { white-space: nowrap; }
.pose-bulk-suggest-btn {
    background: rgba(232,160,32,.12);
    border: 1px solid rgba(232,160,32,.3);
    color: #b45309;
    padding: 2px 8px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: background .12s;
}
.pose-bulk-suggest-btn:hover { background: rgba(232,160,32,.22); }
.pose-bulk-hint {
    font-size: 10px;
    color: var(--text3);
    margin-top: 4px;
    line-height: 1.3;
    display: none;
}
.pose-bulk-suggest-result {
    display: none;
    margin-top: 6px;
    padding: 8px 10px;
    background: rgba(34,197,94,.06);
    border: 1px solid rgba(34,197,94,.25);
    border-radius: 8px;
    font-size: 11px;
    line-height: 1.45;
}
/* .action-btn-success / .action-btn-accent : voir commentaire .action-btn ci-dessus */
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
        team_name: '',
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
        team: document.getElementById('filter-team'),
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
                          currentFilters.team_name ||
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
        if (currentFilters.team_name) params.set('team_name', currentFilters.team_name);
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

    const selectElements = [elements.technicien, elements.team, elements.campaign];
    selectElements.forEach(el => {
        if (el) {
            el.addEventListener('change', () => {
                currentFilters.technicien_id = elements.technicien?.value || '';
                currentFilters.team_name     = elements.team?.value       || '';
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
                team_name: '',
                date_from: '',
                date_to: '',
                show_orphan: false
            };

            if (elements.search) elements.search.value = '';
            if (elements.status) elements.status.value = '';
            if (elements.technicien) elements.technicien.value = '';
            if (elements.team)       elements.team.value = '';
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
    if (urlParams.has('team_name'))    currentFilters.team_name    = urlParams.get('team_name');
    if (urlParams.has('campaign_id')) currentFilters.campaign_id = urlParams.get('campaign_id');
    if (urlParams.has('date_from')) currentFilters.date_from = urlParams.get('date_from');
    if (urlParams.has('date_to')) currentFilters.date_to = urlParams.get('date_to');
    if (urlParams.has('show_orphan')) currentFilters.show_orphan = ['1','true','on'].includes(urlParams.get('show_orphan'));

    if (elements.search && currentFilters.search) elements.search.value = currentFilters.search;
    if (elements.status && currentFilters.status) elements.status.value = currentFilters.status;
    if (elements.technicien && currentFilters.technicien_id) elements.technicien.value = currentFilters.technicien_id;
    if (elements.team       && currentFilters.team_name)     elements.team.value       = currentFilters.team_name;
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