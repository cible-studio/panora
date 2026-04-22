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

{{-- ════ KPI ════ --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
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
<a href="{{ route('admin.pose-tasks.index', array_merge(request()->except(['status','page','q']), ['status'=>$k['s']])) }}"
   style="background:{{ $k['bg'] }};border:1px solid {{ $active ? $k['c'] : 'var(--border)' }};border-radius:14px;padding:16px 18px;text-decoration:none;display:block;transition:all .15s;{{ $active ? 'box-shadow:0 0 0 2px '.$k['c'].'30;' : '' }}"
   onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='{{ $k['c'] }}'"
   onmouseout="this.style.transform='';this.style.borderColor='{{ $active ? $k['c'] : 'var(--border)' }}'">
    <div style="font-size:26px;font-weight:800;color:{{ $k['c'] }};line-height:1;margin-bottom:6px">{{ number_format($k['v']) }}</div>
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3)">{{ $k['l'] }}</div>
    @if($active)<div style="font-size:9px;color:{{ $k['c'] }};margin-top:3px;font-weight:600">Filtre actif ✓</div>@endif
</a>
@endforeach
</div>

{{-- ════ BARRE FILTRES + RECHERCHE ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:12px 16px;margin-bottom:14px">
    <form method="GET" action="{{ route('admin.pose-tasks.index') }}" id="form-filters"
          style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">

        {{-- Recherche texte --}}
        <div style="flex:1;min-width:200px">
            <label class="flbl">Recherche</label>
            <div style="position:relative">
                <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text3);pointer-events:none" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="search-input" name="q" value="{{ request('q') }}"
                       placeholder="Panneau, campagne, technicien, commune…"
                       class="finp" style="padding-left:32px;height:38px"
                       autocomplete="off">
            </div>
        </div>

        <div>
            <label class="flbl">Statut</label>
            <select name="status" class="fsel" onchange="this.form.submit()">
                <option value="">Tous</option>
                @foreach(['planifiee'=>'📅 Planifiée','en_cours'=>'🔧 En cours','realisee'=>'✅ Réalisée','annulee'=>'🚫 Annulée'] as $v => $l)
                <option value="{{ $v }}" {{ request('status')===$v?'selected':'' }}>{{ $l }}</option>
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
            <label class="flbl">Campagne</label>
            <select name="campaign_id" class="fsel" onchange="this.form.submit()">
                <option value="">Toutes</option>
                @foreach($campaigns as $c)
                <option value="{{ $c->id }}" {{ request('campaign_id')==$c->id?'selected':'' }}>
                    {{ $c->status->uiConfig()['icon'] }} {{ Str::limit($c->name, 20) }}
                </option>
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
            @if(request()->hasAny(['q','status','technicien_id','campaign_id','date_from','date_to']))
            <a href="{{ route('admin.pose-tasks.index') }}" class="btn-reset" title="Réinitialiser">↺</a>
            @endif
        </div>

        <div style="margin-left:auto;align-self:flex-end;font-size:11px;color:var(--text3);padding-bottom:2px;white-space:nowrap">
            <strong style="color:var(--text)">{{ number_format($poseTasks->total()) }}</strong> résultat(s)
            @if(request('q')) · "<em>{{ request('q') }}</em>" @endif
        </div>
    </form>
</div>

{{-- ════ TABLEAU ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden" id="table-container">

    <div style="padding:10px 16px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
        <div style="font-weight:600;font-size:13px;display:flex;align-items:center;gap:8px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            Tâches de pose
        </div>
        <div style="display:flex;gap:12px;font-size:10px;color:var(--text3)">
            <span style="display:flex;align-items:center;gap:4px"><span style="width:7px;height:7px;background:#ef4444;border-radius:50%;opacity:.5;display:inline-block"></span>En retard</span>
            <span style="display:flex;align-items:center;gap:4px"><span style="width:7px;height:7px;background:#f97316;border-radius:50%;opacity:.5;display:inline-block"></span>Sans pige</span>
            <span style="display:flex;align-items:center;gap:4px"><span style="width:7px;height:7px;background:#22c55e;border-radius:50%;opacity:.5;display:inline-block"></span>Pigée</span>
        </div>
    </div>

    @if($poseTasks->isEmpty())
    <div style="text-align:center;padding:60px 20px;color:var(--text3)">
        <div style="opacity:.15;margin-bottom:14px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="display:block;margin:0 auto"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div>
        <div style="font-size:14px;font-weight:700;margin-bottom:6px">
            @if(request('q'))Aucun résultat pour "{{ request('q') }}"
            @else Aucune tâche de pose
            @endif
        </div>
        <div style="font-size:12px;margin-bottom:18px;color:var(--text3)">
            @if(request()->hasAny(['q','status','campaign_id','technicien_id']))
            Modifiez vos filtres pour voir plus de résultats.
            @else
            Créez une première tâche de pose pour commencer.
            @endif
        </div>
        @if(!request()->hasAny(['q','status','campaign_id','technicien_id']))
        <a href="{{ route('admin.pose-tasks.create') }}" class="btn btn-primary">+ Créer une tâche</a>
        @endif
    </div>
    @else
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;min-width:900px" id="pose-table">
            <thead>
                <tr style="background:var(--surface2);border-bottom:1px solid var(--border)">
                    @foreach(['Panneau','Campagne','Technicien','Planifié','Réalisé','Pige','Statut','Actions'] as $h)
                    <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody id="pose-tbody">
            @foreach($poseTasks as $task)
            @php
            $sCfg = match($task->status) {
                'planifiee' => ['c'=>'#e8a020','bg'=>'rgba(232,160,32,.1)','bd'=>'rgba(232,160,32,.3)','l'=>'Planifiée'],
                'en_cours'  => ['c'=>'#3b82f6','bg'=>'rgba(59,130,246,.1)','bd'=>'rgba(59,130,246,.3)','l'=>'En cours'],
                'realisee'  => ['c'=>'#22c55e','bg'=>'rgba(34,197,94,.1)', 'bd'=>'rgba(34,197,94,.3)', 'l'=>'Réalisée'],
                'annulee'   => ['c'=>'#ef4444','bg'=>'rgba(239,68,68,.1)', 'bd'=>'rgba(239,68,68,.3)', 'l'=>'Annulée'],
                default     => ['c'=>'#6b7280','bg'=>'rgba(107,114,128,.1)','bd'=>'rgba(107,114,128,.3)','l'=>$task->status],
            };
            // icônes SVG inline — pas d'emoji pour éviter les problèmes d'affichage
            $sIcon = match($task->status) {
                'planifiee' => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
                'en_cours'  => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
                'realisee'  => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
                'annulee'   => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
                default     => '',
            };
            $isLate    = $task->status === 'planifiee' && $task->scheduled_at?->isPast();
            $pigeCount = $task->pige_count ?? 0;
            $pigeVerif = $task->pige_verifie_count ?? 0;
            $needsPige = $task->status === 'realisee' && $task->campaign_id && $pigeCount === 0;
            $rowBl     = $isLate ? 'border-left:3px solid rgba(239,68,68,.5);' : ($needsPige ? 'border-left:3px solid rgba(249,115,22,.4);' : '');
            @endphp
            <tr class="trow" data-search="{{ strtolower(($task->panel?->reference ?? '').' '.($task->panel?->name ?? '').' '.($task->campaign?->name ?? '').' '.($task->technicien?->name ?? '').' '.($task->panel?->commune?->name ?? '')) }}"
                style="{{ $rowBl }}{{ $isLate ? 'background:rgba(239,68,68,.02)' : ($needsPige ? 'background:rgba(249,115,22,.015)' : '') }}">

                {{-- PANNEAU --}}
                <td style="padding:10px 12px">
                    <a href="{{ route('admin.pose-tasks.show', $task) }}"
                       style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent);text-decoration:none;display:block">
                        {{ $task->panel?->reference ?? '—' }}
                    </a>
                    <div style="font-size:11px;color:var(--text2);margin-top:1px;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $task->panel?->name ?? '—' }}</div>
                    @if($task->panel?->commune)
                    <div style="font-size:10px;color:var(--text3)">{{ $task->panel->commune->name }}</div>
                    @endif
                </td>

                {{-- CAMPAGNE --}}
                <td style="padding:10px 12px;max-width:150px">
                    @if($task->campaign)
                    <a href="{{ route('admin.campaigns.show', $task->campaign) }}"
                       style="font-size:12px;font-weight:500;color:var(--text);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                       title="{{ $task->campaign->name }}">{{ Str::limit($task->campaign->name, 22) }}</a>
                    @php $cui = $task->campaign->status->uiConfig(); @endphp
                    <div style="font-size:9px;color:{{ $cui['color'] }};margin-top:2px;font-weight:600">{{ $task->campaign->status->label() }}</div>
                    @else
                    <span style="font-size:11px;color:var(--text3);font-style:italic">Intervention</span>
                    @endif
                </td>

                {{-- TECHNICIEN --}}
                <td style="padding:10px 12px">
                    <div style="font-size:12px;color:var(--text)">{{ $task->technicien?->name ?? '—' }}</div>
                    @if($task->team_name)
                    <div style="font-size:10px;color:var(--text3)">{{ $task->team_name }}</div>
                    @endif
                </td>

                {{-- PLANIFIÉ --}}
                <td style="padding:10px 12px;white-space:nowrap">
                    <div style="font-size:12px;font-weight:500;color:{{ $isLate ? '#ef4444' : 'var(--text)' }}">{{ $task->scheduled_at?->format('d/m/Y') ?? '—' }}</div>
                    <div style="font-size:10px;color:{{ $isLate ? '#ef4444' : 'var(--text3)' }}">
                        {{ $task->scheduled_at?->format('H:i') }}
                        @if($isLate)<span style="font-weight:700;margin-left:3px">En retard</span>@endif
                    </div>
                </td>

                {{-- RÉALISÉ --}}
                <td style="padding:10px 12px;white-space:nowrap">
                    @if($task->done_at)
                    <div style="font-size:12px;color:#22c55e;font-weight:500">{{ $task->done_at->format('d/m/Y') }}</div>
                    <div style="font-size:10px;color:var(--text3)">{{ $task->done_at->format('H:i') }}</div>
                    @else
                    <span style="color:var(--text3);font-size:12px">—</span>
                    @endif
                </td>

                {{-- PIGE --}}
                <td style="padding:10px 12px">
                    @if(!$task->campaign_id)
                    <span style="font-size:10px;color:var(--text3)">N/A</span>
                    @elseif($needsPige)
                    <a href="{{ route('admin.piges.index', ['campaign_id'=>$task->campaign_id,'panel_id'=>$task->panel_id]) }}"
                       style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);color:#f97316;border-radius:8px;font-size:10px;font-weight:700;text-decoration:none;white-space:nowrap">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        Ajouter
                    </a>
                    @elseif($pigeCount > 0)
                    <a href="{{ route('admin.piges.index', ['campaign_id'=>$task->campaign_id,'panel_id'=>$task->panel_id]) }}"
                       style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);color:#22c55e;border-radius:8px;font-size:10px;font-weight:700;text-decoration:none;white-space:nowrap">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        {{ $pigeCount }}@if($pigeVerif > 0)<span style="font-size:9px;opacity:.65;margin-left:2px">·{{ $pigeVerif }}✓</span>@endif
                    </a>
                    @else
                    <span style="font-size:10px;color:var(--text3)">—</span>
                    @endif
                </td>

                {{-- STATUT --}}
                <td style="padding:10px 12px">
                    <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;white-space:nowrap;background:{{ $sCfg['bg'] }};color:{{ $sCfg['c'] }};border:1px solid {{ $sCfg['bd'] }}">
                        {!! $sIcon !!}
                        {{ $sCfg['l'] }}
                    </span>
                </td>

                {{-- ACTIONS --}}
                <td style="padding:10px 12px">
                    <div style="display:flex;gap:4px;align-items:center">
                        @if(!in_array($task->status, ['realisee','annulee']))
                        <button type="submit" class="action-btn action-btn-success" title="Marquer réalisée">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            </button>
                        <form id="form-complete-{{ $task->id }}" method="POST" action="{{ route('admin.pose.complete', $task) }}" style="display:none">@csrf</form>
                        @endif

                        <a href="{{ route('admin.pose-tasks.show', $task) }}" class="action-btn" title="Voir">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>

                        @if(!in_array($task->status, ['realisee','annulee']))
                        <a href="{{ route('admin.pose-tasks.edit', $task) }}" class="action-btn" title="Modifier">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                        </a>
                        @endif

                        @if($task->campaign_id && $task->status === 'realisee')
                        <a href="{{ route('admin.piges.index', ['campaign_id'=>$task->campaign_id]) }}" class="action-btn action-btn-accent" title="Piges campagne">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        </a>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if($poseTasks->hasPages())
    <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
        <div style="font-size:12px;color:var(--text3)">
            {{ $poseTasks->firstItem() }}–{{ $poseTasks->lastItem() }} sur {{ number_format($poseTasks->total()) }}
        </div>
        {{ $poseTasks->links() }}
    </div>
    @endif
    @endif
</div>

{{-- ════ MODAL CONFIRMATION CORRIGÉE ════ --}}
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
.btn-reset { display:flex;align-items:center;justify-content:center;width:38px;height:38px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text3);text-decoration:none;font-size:15px;transition:all .15s }
.btn-reset:hover { border-color:var(--accent);color:var(--accent) }
.trow { transition:background .1s;border-bottom:1px solid var(--border) }
.trow:hover { background:var(--surface2) !important }
.trow:last-child { border-bottom:none }
.action-btn { padding:5px 7px;background:var(--surface2);border:1px solid var(--border);border-radius:7px;color:var(--text2);text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:border-color .1s;line-height:0 }
.action-btn:hover { border-color:var(--accent);color:var(--accent) }
.action-btn-success { background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.25);color:#22c55e }
.action-btn-success:hover { border-color:#22c55e;background:rgba(34,197,94,.15) }
.action-btn-accent  { background:rgba(232,160,32,.08);border-color:rgba(232,160,32,.25);color:var(--accent) }

/* Highlight search */
.search-hl { background:rgba(232,160,32,.25);border-radius:2px;padding:0 1px }

/* Loader inline */
#search-spinner { display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;border:2px solid rgba(255,255,255,.1);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite }
@keyframes spin { to { transform:translateY(-50%) rotate(360deg) } }
</style>

@push('scripts')
<script>
// ════════════════════════════════════════════════════════════
// MODAL CONFIRMATION — remplace confirm() natif partout
// Usage : Confirm.show('Message', 'danger|confirm|warning', callback)
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
        
        // Récupérer les éléments avec les bons IDs
        const iconEl = document.getElementById('modal-confirm-icon');
        const titleEl = document.getElementById('modal-confirm-title');
        const bodyEl = document.getElementById('modal-confirm-body');
        const btnEl = document.getElementById('modal-confirm-btn');
        
        if (iconEl) {
            iconEl.innerHTML = c.icon;
            iconEl.style.background = c.ibg;
        }
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

// Fermer modal au clic extérieur ou Escape
const modal = document.getElementById('modal-confirm');
if (modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) Confirm.cancel();
    });
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') Confirm.cancel(); });

// ════════════════════════════════════════════════════════════
// RECHERCHE DYNAMIQUE — filtre les rows visible en temps réel
// + soumet le formulaire après debounce pour la recherche serveur
// ════════════════════════════════════════════════════════════
(function() {
    const input = document.getElementById('search-input');
    const tbody = document.getElementById('pose-tbody');
    if (!input || !tbody) return;

    let timer = null;
    const DEBOUNCE = 350;

    // Filtrage local instantané (sur les données déjà chargées)
    function filterLocal(q) {
        const lq = q.trim().toLowerCase();
        let visible = 0;
        document.querySelectorAll('#pose-tbody .trow').forEach(row => {
            const data = row.dataset.search || '';
            if (!lq || data.includes(lq)) {
                row.style.display = '';
                visible++;
                // Highlight
                highlightCells(row, lq);
            } else {
                row.style.display = 'none';
            }
        });
        // Mettre à jour le compteur affiché
        const cnt = document.querySelector('[data-result-count]');
        if (cnt && lq) cnt.textContent = visible + ' résultat(s) filtrés';
    }

    function highlightCells(row, q) {
        // Retirer les anciens highlights
        row.querySelectorAll('.search-hl').forEach(el => {
            el.outerHTML = el.textContent;
        });
        if (!q) return;
        // Highlight sur les colonnes texte
        row.querySelectorAll('td').forEach(td => {
            const anchors = td.querySelectorAll('a, div, span:not([style*="monospace"]):not([style*="border-radius"])');
            anchors.forEach(el => {
                if (!el.children.length && el.textContent.toLowerCase().includes(q)) {
                    el.innerHTML = el.textContent.replace(
                        new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi'),
                        '<mark class="search-hl">$1</mark>'
                    );
                }
            });
        });
    }

    input.addEventListener('input', function() {
        const q = this.value;
        // Filtre local immédiat
        filterLocal(q);
        // Soumission serveur après debounce (pour la pagination)
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (q.length === 0 || q.length >= 2) {
                document.getElementById('form-filters').submit();
            }
        }, DEBOUNCE);
    });

    // Initialiser le filtre local si query en cours
    const initialQ = input.value.trim();
    if (initialQ) filterLocal(initialQ);
})();

// ════════════════════════════════════════════════════════════
// GESTION DES BOUTONS "MARQUER RÉALISÉE" AVEC CONFIRMATION
// ════════════════════════════════════════════════════════════
document.querySelectorAll('.action-btn-success').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const form = this.closest('td').querySelector('form');
        if (!form) return;
        
        Confirm.show(
            'Cette action marquera la tâche comme réalisée. Êtes-vous sûr ?',
            'confirm',
            () => form.submit()
        );
    });
});
</script>
@endpush
</x-admin-layout>