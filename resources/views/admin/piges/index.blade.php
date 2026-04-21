<x-admin-layout title="Piges Photos">

<x-slot:topbarActions>
    <a href="{{ route('admin.piges.create') }}" class="btn btn-primary" style="display:flex;align-items:center;gap:6px">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Uploader des piges
    </a>
</x-slot:topbarActions>

{{-- Breadcrumb contextuel si filtre panneau/campagne --}}
@if($filterPanel || $filterCampaign)
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:12px;color:var(--text3)">
    <a href="{{ route('admin.piges.index') }}" style="color:var(--text3);text-decoration:none">Toutes les piges</a>
    @if($filterCampaign)
    <span>/</span>
    <span style="color:var(--accent);font-weight:600">{{ $filterCampaign->name }}</span>
    @endif
    @if($filterPanel)
    <span>/</span>
    <span style="color:var(--accent);font-weight:600;font-family:monospace">{{ $filterPanel->reference }}</span>
    @endif
</div>
@endif

{{-- ════ KPI ════ --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
@php
$kpis = [
    ['s'=>'','l'=>'Total',      'v'=>$stats['total']      ,'c'=>'#e8a020','bg'=>'rgba(232,160,32,.08)'],
    ['s'=>'en_attente','l'=>'En attente','v'=>$stats['en_attente'],'c'=>'#f97316','bg'=>'rgba(249,115,22,.08)'],
    ['s'=>'verifie',   'l'=>'Vérifiées', 'v'=>$stats['verifie']   ,'c'=>'#22c55e','bg'=>'rgba(34,197,94,.08)'],
    ['s'=>'rejete',    'l'=>'Rejetées',  'v'=>$stats['rejete']    ,'c'=>'#ef4444','bg'=>'rgba(239,68,68,.08)'],
];
@endphp
@foreach($kpis as $k)
@php $active = request('status') === $k['s'] && $k['s'] !== ''; @endphp
<a href="{{ $k['s'] ? route('admin.piges.index', array_merge(request()->except(['status','page']), ['status'=>$k['s']])) : route('admin.piges.index', request()->except(['status','page'])) }}"
   style="background:{{ $k['bg'] }};border:1px solid {{ $active ? $k['c'] : 'var(--border)' }};border-radius:14px;padding:16px 18px;text-decoration:none;display:block;transition:all .15s"
   onmouseover="this.style.borderColor='{{ $k['c'] }}';this.style.transform='translateY(-2px)'"
   onmouseout="this.style.borderColor='{{ $active ? $k['c'] : 'var(--border)' }}';this.style.transform=''">
    <div style="font-size:26px;font-weight:800;color:{{ $k['c'] }};line-height:1;margin-bottom:6px">{{ number_format($k['v']) }}</div>
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3)">{{ $k['l'] }}</div>
    @if($active)<div style="font-size:9px;color:{{ $k['c'] }};margin-top:3px;font-weight:600">Filtre actif ✓</div>@endif
</a>
@endforeach
</div>

{{-- Alerte piges en attente --}}
@if($stats['en_attente'] > 0)
<div style="background:rgba(249,115,22,.07);border:1px solid rgba(249,115,22,.25);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
    <div style="width:34px;height:34px;background:rgba(249,115,22,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
    <div style="flex:1">
        <div style="font-size:13px;font-weight:700;color:#f97316">{{ $stats['en_attente'] }} pige(s) en attente de vérification</div>
        <div style="font-size:11px;color:rgba(249,115,22,.75);margin-top:2px">Filtrez par "En attente" pour les traiter rapidement</div>
    </div>
    <div style="display:flex;gap:8px;flex-shrink:0">
        <a href="{{ route('admin.piges.index', ['status'=>'en_attente']) }}"
           style="font-size:11px;color:#f97316;font-weight:700;text-decoration:none;padding:6px 12px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);border-radius:8px;white-space:nowrap">
            Voir les piges →
        </a>
        @if($stats['en_attente'] > 1)
        <button type="button" onclick="PigeActions.verifyAll()"
                style="font-size:11px;color:#22c55e;font-weight:700;border:none;padding:6px 12px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);border-radius:8px;cursor:pointer;white-space:nowrap">
            ✓ Tout vérifier
        </button>
        @endif
    </div>
</div>
@endif

{{-- ════ FILTRES ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:12px 16px;margin-bottom:14px">
    <form method="GET" action="{{ route('admin.piges.index') }}" id="form-filters"
          style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">

        <div style="flex:1;min-width:180px">
            <label class="flbl">Recherche</label>
            <div style="position:relative">
                <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text3);pointer-events:none" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Panneau, campagne, technicien…"
                       class="finp" style="padding-left:30px" autocomplete="off"
                       id="search-input" oninput="filterLocal(this.value)">
            </div>
        </div>

        <div>
            <label class="flbl">Statut</label>
            <select name="status" class="fsel" onchange="this.form.submit()">
                <option value="">Tous</option>
                @foreach(['en_attente'=>'⏳ En attente','verifie'=>'✓ Vérifiée','rejete'=>'✗ Rejetée'] as $v => $l)
                <option value="{{ $v }}" {{ request('status')===$v?'selected':'' }}>{{ $l }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="flbl">Campagne</label>
            <select name="campaign_id" class="fsel" onchange="this.form.submit()">
                <option value="">Toutes</option>
                @foreach($campaigns as $c)
                <option value="{{ $c->id }}" {{ request('campaign_id')==$c->id?'selected':'' }}>{{ Str::limit($c->name,22) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="flbl">Technicien</label>
            <select name="technicien_id" class="fsel" onchange="this.form.submit()">
                <option value="">Tous</option>
                @foreach($techniciens as $t)
                <option value="{{ $t->id }}" {{ request('technicien_id')==$t->id?'selected':'' }}>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="flbl">Du</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="finp" onchange="this.form.submit()">
        </div>
        <div>
            <label class="flbl">Au</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="finp" onchange="this.form.submit()">
        </div>

        <div style="align-self:flex-end;display:flex;gap:6px">
            <!-- <button type="submit" style="height:38px;padding:0 14px;background:var(--accent);color:#000;border:none;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer">Filtrer</button> -->
            @if(request()->hasAny(['q','status','campaign_id','technicien_id','date_from','date_to','panel_id']))
            <a href="{{ route('admin.piges.index') }}" class="btn-reset" title="Réinitialiser">↺</a>
            @endif
        </div>

        @if($filterPanel)<input type="hidden" name="panel_id" value="{{ $filterPanel->id }}">@endif

        <div style="margin-left:auto;align-self:flex-end;font-size:11px;color:var(--text3)">
            <strong style="color:var(--text)">{{ number_format($piges->total()) }}</strong> pige(s)
        </div>
    </form>
</div>

{{-- ════ GRILLE PHOTOS ════ --}}
@if($piges->isEmpty())
<div style="text-align:center;padding:60px 20px;background:var(--surface);border:1px solid var(--border);border-radius:14px;color:var(--text3)">
    <div style="font-size:48px;opacity:.2;margin-bottom:16px">📸</div>
    <div style="font-size:14px;font-weight:700;margin-bottom:6px">
        @if(request()->hasAny(['q','status','campaign_id','technicien_id']))
        Aucune pige trouvée avec ces filtres
        @else Aucune pige uploadée pour le moment
        @endif
    </div>
    <div style="font-size:12px;margin-bottom:18px">
        @if(!request()->hasAny(['q','status','campaign_id','technicien_id']))
        Les preuves photos d'affichage apparaîtront ici après upload.
        @endif
    </div>
    @if(!request()->hasAny(['q','status','campaign_id','technicien_id']))
    <a href="{{ route('admin.piges.create') }}" class="btn btn-primary">+ Uploader des piges</a>
    @endif
</div>
@else

{{-- Actions batch (si piges en attente visibles) --}}
@php $hasEnAttente = $piges->where('status','en_attente')->count() > 0; @endphp
@if($hasEnAttente)
<div id="batch-bar" style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:10px 16px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:10px">
    <div style="display:flex;align-items:center;gap:10px">
        <input type="checkbox" id="chk-all" style="accent-color:var(--accent);width:15px;height:15px;cursor:pointer" onchange="PigeActions.toggleAll(this.checked)">
        <label for="chk-all" style="font-size:12px;color:var(--text2);cursor:pointer">Tout sélectionner</label>
        <span id="selected-count" style="font-size:11px;color:var(--text3)"></span>
    </div>
    <button id="btn-verify-selected" onclick="PigeActions.verifySelected()" style="display:none;padding:6px 14px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer">
        ✓ Vérifier la sélection
    </button>
</div>
@endif

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px" id="pige-grid">
@foreach($piges as $pige)
@php $sc = $pige->getStatusConfig(); @endphp
<div class="pige-card"
     data-search="{{ strtolower(($pige->panel?->reference ?? '').' '.($pige->panel?->name ?? '').' '.($pige->campaign?->name ?? '').' '.($pige->technicien?->name ?? '')) }}"
     data-status="{{ $pige->status }}"
     data-id="{{ $pige->id }}">

    {{-- Photo --}}
    <div style="position:relative;aspect-ratio:16/10;background:var(--surface2);border-radius:10px 10px 0 0;overflow:hidden">
        <a href="{{ $pige->getPhotoUrl() }}" target="_blank" style="display:block;height:100%">
            <img src="{{ $pige->getThumbUrl() }}" alt="Pige {{ $pige->panel?->reference }}"
                 style="width:100%;height:100%;object-fit:cover;transition:transform .3s"
                 loading="lazy"
                 onmouseover="this.style.transform='scale(1.04)'"
                 onmouseout="this.style.transform=''"
                 onerror="this.closest('div').innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;color:var(--text3);font-size:12px;flex-direction:column;gap:8px\'><svg width=24 height=24 viewBox=\'0 0 24 24\' fill=none stroke=currentColor stroke-width=1.5><path d=\'M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z\'/><circle cx=12 cy=13 r=4/></svg>Photo indisponible</div>'">
        </a>

        {{-- Badge statut --}}
        <div style="position:absolute;top:8px;right:8px;padding:3px 9px;border-radius:20px;font-size:9px;font-weight:700;background:{{ $sc['bg'] }};color:{{ $sc['color'] }};border:1px solid {{ $sc['bd'] }};backdrop-filter:blur(4px)">
            {{ $sc['label'] }}
        </div>

        {{-- GPS badge --}}
        @if($pige->hasGps())
        <a href="{{ $pige->getGoogleMapsUrl() }}" target="_blank"
           style="position:absolute;bottom:8px;right:8px;padding:3px 7px;border-radius:8px;font-size:9px;font-weight:600;background:rgba(0,0,0,.6);color:#fff;text-decoration:none;display:flex;align-items:center;gap:3px;backdrop-filter:blur(4px)">
            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            GPS
        </a>
        @endif

        {{-- Checkbox batch (si en attente) --}}
        @if($pige->isEnAttente())
        <div style="position:absolute;top:8px;left:8px">
            <input type="checkbox" class="pige-chk" data-id="{{ $pige->id }}"
                   style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer;opacity:.85"
                   onchange="PigeActions.onCheck()">
        </div>
        @endif
    </div>

    {{-- Info --}}
    <div style="padding:10px 12px">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:6px">
            <div style="min-width:0">
                <a href="{{ route('admin.panels.show', $pige->panel) }}"
                   style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent);text-decoration:none;display:block">
                    {{ $pige->panel?->reference ?? '—' }}
                </a>
                <div style="font-size:11px;color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $pige->panel?->name ?? '—' }}</div>
                @if($pige->panel?->commune)
                <div style="font-size:10px;color:var(--text3)">📍 {{ $pige->panel->commune->name }}</div>
                @endif
            </div>
            <a href="{{ route('admin.piges.show', $pige) }}"
               style="flex-shrink:0;padding:4px 7px;background:var(--surface2);border:1px solid var(--border);border-radius:7px;font-size:10px;color:var(--text3);text-decoration:none">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </a>
        </div>

        @if($pige->campaign)
        <div style="font-size:10px;color:var(--text3);margin-bottom:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <a href="{{ route('admin.campaigns.show', $pige->campaign) }}" style="color:var(--text3);text-decoration:none">{{ $pige->campaign->name }}</a>
        </div>
        @endif

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <div style="font-size:10px;color:var(--text3)">
                <div>Par {{ $pige->technicien?->name ?? '—' }}</div>
                <div>{{ $pige->taken_at?->format('d/m/Y H:i') ?? $pige->created_at->format('d/m/Y H:i') }}</div>
            </div>
            @if($pige->isVerifiee() && $pige->verificateur)
            <div style="font-size:9px;color:#22c55e;text-align:right">
                <div style="font-weight:600">Vérifiée par</div>
                <div>{{ $pige->verificateur->name }}</div>
                <div>{{ $pige->verified_at?->format('d/m/Y') }}</div>
            </div>
            @endif
        </div>

        {{-- Motif rejet --}}
        @if($pige->isRejetee() && $pige->rejection_reason)
        <div style="padding:6px 8px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:7px;font-size:10px;color:#ef4444;margin-bottom:8px">
            <span style="font-weight:700">Rejet :</span> {{ Str::limit($pige->rejection_reason, 60) }}
        </div>
        @endif

        {{-- Actions --}}
        @if($pige->isEnAttente())
        <div style="display:flex;gap:6px">
            <button type="button"
                    onclick="PigeActions.verify({{ $pige->id }}, '{{ $pige->panel?->reference }}')"
                    style="flex:1;padding:7px 0;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Vérifier
            </button>
            <button type="button"
                    onclick="PigeActions.showRejectModal({{ $pige->id }}, '{{ $pige->panel?->reference }}')"
                    style="flex:1;padding:7px 0;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:#ef4444;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Rejeter
            </button>
        </div>
        @elseif(!$pige->isVerifiee())
        <div style="display:flex;gap:6px">
            <button type="button"
                    onclick="PigeActions.verify({{ $pige->id }}, '{{ $pige->panel?->reference }}')"
                    style="flex:1;padding:7px 0;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer">
                Re-vérifier
            </button>
            <button type="button"
                    onclick="Confirm.show('Supprimer cette pige de <strong>{{ $pige->panel?->reference }}</strong> ? Action irréversible.','danger',function(){ document.getElementById(\'del-{{ $pige->id }}\').submit(); })"
                    style="padding:7px 10px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);color:#ef4444;border-radius:8px;font-size:11px;cursor:pointer">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
            <form id="del-{{ $pige->id }}" method="POST" action="{{ route('admin.piges.destroy', $pige) }}" style="display:none">@csrf @method('DELETE')</form>
        </div>
        @else
        {{-- Vérifiée : seulement supprimer si non vérifiée (service refusera) - afficher info --}}
        <div style="font-size:11px;color:#22c55e;display:flex;align-items:center;gap:5px">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Pige validée — archivée
        </div>
        @endif
    </div>
</div>
@endforeach
</div>

{{-- Pagination --}}
@if($piges->hasPages())
<div style="margin-top:16px;display:flex;justify-content:space-between;align-items:center">
    <div style="font-size:12px;color:var(--text3)">
        {{ $piges->firstItem() }}–{{ $piges->lastItem() }} sur {{ number_format($piges->total()) }}
    </div>
    {{ $piges->links() }}
</div>
@endif
@endif

{{-- ════ MODAL REJET ════ --}}
<div id="modal-reject" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:18px;width:100%;max-width:440px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.4)">
        <div style="padding:18px 20px;background:rgba(239,68,68,.06);border-bottom:1px solid rgba(239,68,68,.2);display:flex;align-items:center;gap:10px">
            <div style="width:36px;height:36px;background:rgba(239,68,68,.12);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
            <div>
                <div style="font-size:14px;font-weight:700;color:#ef4444">Rejeter la pige</div>
                <div id="modal-reject-panel" style="font-size:11px;color:rgba(239,68,68,.7)"></div>
            </div>
        </div>
        <div style="padding:18px 20px">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:6px">Motif de rejet *</label>
            <textarea id="reject-reason-input" rows="3"
                      placeholder="Ex: Photo floue, mauvais panneau, date incorrecte, visuel non conforme…"
                      style="width:100%;padding:10px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);resize:none;outline:none;box-sizing:border-box;transition:border-color .2s"
                      onfocus="this.style.borderColor='#ef4444'"
                      onblur="this.style.borderColor='var(--border)'"></textarea>
            <div id="reject-error" style="font-size:11px;color:#ef4444;margin-top:4px;display:none">Le motif de rejet est obligatoire.</div>

            {{-- Motifs rapides --}}
            <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:5px">
                @foreach(['Photo floue','Mauvais panneau','Visuel non conforme','Date incorrecte','Photo trop sombre','Angle incorrect'] as $m)
                <button type="button" onclick="document.getElementById('reject-reason-input').value='{{ $m }}'"
                        style="font-size:10px;padding:3px 9px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;cursor:pointer;color:var(--text3)">
                    {{ $m }}
                </button>
                @endforeach
            </div>
        </div>
        <div style="padding:12px 20px 18px;display:flex;gap:8px;justify-content:flex-end">
            <button onclick="PigeActions.closeRejectModal()" style="padding:8px 18px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text2);cursor:pointer;font-weight:500">Annuler</button>
            <button onclick="PigeActions.submitReject()" style="padding:8px 20px;background:#ef4444;border:none;border-radius:10px;font-size:13px;font-weight:700;color:#fff;cursor:pointer">Rejeter la pige</button>
        </div>
    </div>
</div>

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
.flbl { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:4px }
.finp { height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:12px;color:var(--text);outline:none;box-sizing:border-box;width:100%;transition:border-color .2s }
.finp:focus { border-color:var(--accent) }
.fsel { height:38px;padding:0 10px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:12px;color:var(--text);cursor:pointer;outline:none }
.btn-reset { display:flex;align-items:center;justify-content:center;width:38px;height:38px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text3);text-decoration:none;font-size:15px }
.btn-reset:hover { border-color:var(--accent);color:var(--accent) }

.pige-card { background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:box-shadow .15s,transform .15s }
.pige-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.15);transform:translateY(-2px) }
.pige-card.hidden { display:none }
</style>

@push('scripts')
<script>
// ════════════════════════════════════════════════════════════
// MODAL CONFIRM
// ════════════════════════════════════════════════════════════
window.Confirm = {
    _cb:null,
    show(body,type='confirm',cb){
        this._cb=cb;
        const cfg={
            confirm:{icon:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>',ibg:'rgba(59,130,246,.12)',btnBg:'#3b82f6',btnTxt:'Confirmer',title:"Confirmer l'action"},
            danger:{icon:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',ibg:'rgba(239,68,68,.12)',btnBg:'#ef4444',btnTxt:'Supprimer',title:'Confirmer la suppression'},
            warning:{icon:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',ibg:'rgba(249,115,22,.12)',btnBg:'#f97316',btnTxt:'Confirmer',title:"Confirmer l'action"},
        };
        const c=cfg[type]||cfg.confirm;
        const el=id=>document.getElementById('modal-confirm-'+id);
        el('icon').innerHTML=c.icon;el('icon').style.background=c.ibg;
        el('title').textContent=c.title;el('body').innerHTML=body;
        el('btn').textContent=c.btnTxt;el('btn').style.background=c.btnBg;el('btn').style.color='#fff';
        el('btn').onclick=()=>{this.cancel();cb?.();};
        document.getElementById('modal-confirm').style.display='flex';
        setTimeout(()=>el('btn').focus(),50);
    },
    cancel(){document.getElementById('modal-confirm').style.display='none';this._cb=null;},
};
document.getElementById('modal-confirm').addEventListener('click',function(e){if(e.target===this)Confirm.cancel();});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){Confirm.cancel();PigeActions.closeRejectModal();}});

// ════════════════════════════════════════════════════════════
// PIGE ACTIONS — verify, reject, batch
// ════════════════════════════════════════════════════════════
const CSRF = document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}';

window.PigeActions = {
    _rejectId: null,
    _rejectRef: null,

    // ── Vérifier une pige ─────────────────────────────────
    verify(pigeId, ref) {
        Confirm.show(
            `Marquer la pige du panneau <strong>${ref}</strong> comme vérifiée ?`,
            'confirm',
            async () => {
                try {
                    const res = await fetch(`/admin/piges/${pigeId}/verify`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    });
                    const data = await res.json();
                    if (data.success) {
                        this._updateCard(pigeId, 'verifie');
                        this._showToast(data.message, 'success');
                    } else {
                        this._showToast(data.message, 'error');
                    }
                } catch { this._showToast('Erreur de connexion.', 'error'); }
            }
        );
    },

    // ── Modal rejet ───────────────────────────────────────
    showRejectModal(pigeId, ref) {
        this._rejectId  = pigeId;
        this._rejectRef = ref;
        document.getElementById('reject-reason-input').value = '';
        document.getElementById('reject-error').style.display = 'none';
        document.getElementById('modal-reject-panel').textContent = `Panneau ${ref}`;
        document.getElementById('modal-reject').style.display = 'flex';
        setTimeout(() => document.getElementById('reject-reason-input').focus(), 50);
    },

    closeRejectModal() {
        document.getElementById('modal-reject').style.display = 'none';
        this._rejectId = null;
    },

    async submitReject() {
        const reason = document.getElementById('reject-reason-input').value.trim();
        if (!reason) {
            document.getElementById('reject-error').style.display = 'block';
            return;
        }
        document.getElementById('reject-error').style.display = 'none';

        try {
            const res = await fetch(`/admin/piges/${this._rejectId}/reject`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ rejection_reason: reason }),
            });
            const data = await res.json();
            if (data.success) {
                this._updateCard(this._rejectId, 'rejete', reason);
                this.closeRejectModal();
                this._showToast(data.message || 'Pige rejetée.', 'warning');
            } else {
                this._showToast(data.message, 'error');
            }
        } catch { this._showToast('Erreur de connexion.', 'error'); }
    },

    // ── Tout vérifier (batch serveur) ─────────────────────
    verifyAll() {
        const count = {{ $stats['en_attente'] }};
        Confirm.show(
            `Vérifier les <strong>${count} piges en attente</strong> en une seule action ?`,
            'confirm',
            async () => {
                const ids = [...document.querySelectorAll('.pige-card[data-status="en_attente"]')].map(c=>parseInt(c.dataset.id));
                if (!ids.length) return;
                try {
                    const res = await fetch('/admin/piges/verify-batch', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ pige_ids: ids }),
                    });
                    const data = await res.json();
                    if (data.success) {
                        ids.forEach(id => this._updateCard(id, 'verifie'));
                        this._showToast(data.message, 'success');
                    }
                } catch { this._showToast('Erreur.', 'error'); }
            }
        );
    },

    // ── Sélection + vérification en batch ─────────────────
    toggleAll(checked) {
        document.querySelectorAll('.pige-chk').forEach(c => {
            c.checked = checked;
        });
        this.onCheck();
    },

    onCheck() {
        const selected = document.querySelectorAll('.pige-chk:checked');
        const btn = document.getElementById('btn-verify-selected');
        const cnt = document.getElementById('selected-count');
        if (btn) btn.style.display = selected.length > 0 ? 'block' : 'none';
        if (cnt) cnt.textContent = selected.length > 0 ? `${selected.length} sélectionnée(s)` : '';
    },

    verifySelected() {
        const ids = [...document.querySelectorAll('.pige-chk:checked')].map(c => parseInt(c.dataset.id));
        Confirm.show(
            `Vérifier <strong>${ids.length} pige(s)</strong> sélectionnée(s) ?`,
            'confirm',
            async () => {
                try {
                    const res = await fetch('/admin/piges/verify-batch', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ pige_ids: ids }),
                    });
                    const data = await res.json();
                    if (data.success) {
                        ids.forEach(id => this._updateCard(id, 'verifie'));
                        this._showToast(data.message, 'success');
                        this.onCheck();
                    }
                } catch { this._showToast('Erreur.', 'error'); }
            }
        );
    },

    // ── Mettre à jour la carte sans reload ────────────────
    _updateCard(pigeId, newStatus, reason = '') {
        const card = document.querySelector(`.pige-card[data-id="${pigeId}"]`);
        if (!card) return;
        card.dataset.status = newStatus;

        const cfgMap = {
            verifie:    {label:'Vérifiée',    color:'#22c55e', bg:'rgba(34,197,94,.1)',  bd:'rgba(34,197,94,.3)'},
            rejete:     {label:'Rejetée',      color:'#ef4444', bg:'rgba(239,68,68,.1)',  bd:'rgba(239,68,68,.3)'},
            en_attente: {label:'En attente',   color:'#f97316', bg:'rgba(249,115,22,.1)', bd:'rgba(249,115,22,.3)'},
        };
        const cfg = cfgMap[newStatus] || cfgMap.en_attente;

        // Mettre à jour le badge
        const badge = card.querySelector('[style*="position:absolute;top:8px;right:8px"]');
        if (badge) {
            badge.textContent = cfg.label;
            badge.style.background = cfg.bg;
            badge.style.color = cfg.color;
            badge.style.borderColor = cfg.bd;
        }

        // Remplacer les boutons d'action
        const actionsDiv = card.querySelector('[style*="display:flex;gap:6px"]');
        if (actionsDiv) {
            if (newStatus === 'verifie') {
                actionsDiv.innerHTML = '<div style="font-size:11px;color:#22c55e;display:flex;align-items:center;gap:5px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Pige validée — archivée</div>';
            }
        }

        // Retirer la checkbox batch
        const chk = card.querySelector('.pige-chk');
        if (chk) chk.closest('div').remove();
    },

    // ── Toast notification ────────────────────────────────
    _showToast(msg, type) {
        const colors = { success:'#22c55e', error:'#ef4444', warning:'#f97316' };
        const t = document.createElement('div');
        t.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 18px;background:var(--surface);border:1px solid ${colors[type]||'var(--border)'};border-radius:12px;font-size:13px;color:${colors[type]||'var(--text)'};font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.25);transition:all .3s;max-width:320px;line-height:1.4`;
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity='0'; t.style.transform='translateY(8px)'; setTimeout(()=>t.remove(),300); }, 3500);
    },
};

// ════════════════════════════════════════════════════════════
// FILTRE LOCAL INSTANTANÉ
// ════════════════════════════════════════════════════════════
function filterLocal(q) {
    const lq = q.trim().toLowerCase();
    document.querySelectorAll('.pige-card').forEach(card => {
        const match = !lq || (card.dataset.search || '').includes(lq);
        card.classList.toggle('hidden', !match);
    });
}

// Fermer modals
document.getElementById('modal-reject').addEventListener('click', function(e) {
    if (e.target === this) PigeActions.closeRejectModal();
});

// Initialisation
const initQ = document.getElementById('search-input')?.value;
if (initQ) filterLocal(initQ);
</script>
@endpush
</x-admin-layout>