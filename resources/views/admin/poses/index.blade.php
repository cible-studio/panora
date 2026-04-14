<x-admin-layout title="Pose OOH">

<x-slot:topbarActions>
    <a href="{{ route('admin.pose-tasks.create') }}" class="btn btn-primary" style="display:flex;align-items:center;gap:6px">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle tâche
    </a>
</x-slot:topbarActions>

{{-- ════ ALERTES ACTIVITÉ ════ --}}
@if($overdueTasks->isNotEmpty() || $posesSansPige > 0)
<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:18px">

    @if($overdueTasks->isNotEmpty())
    <div class="alert-banner alert-danger">
        <div class="alert-banner-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:700;color:#ef4444">
                {{ $overdueTasks->count() }} tâche(s) en retard
                <span style="font-size:11px;font-weight:400;color:rgba(239,68,68,.7);margin-left:6px">— Date de pose dépassée sans réalisation</span>
            </div>
            <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px">
                @foreach($overdueTasks->take(5) as $t)
                <a href="{{ route('admin.pose-tasks.show', $t) }}"
                   style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:20px;font-size:11px;color:#ef4444;text-decoration:none;font-weight:600;white-space:nowrap">
                    <span style="font-family:monospace">{{ $t->panel?->reference }}</span>
                    <span style="opacity:.6">{{ $t->scheduled_at?->format('d/m') }}</span>
                </a>
                @endforeach
                @if($overdueTasks->count() > 5)
                <span style="padding:3px 10px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);border-radius:20px;font-size:11px;color:#ef4444">
                    +{{ $overdueTasks->count() - 5 }} autres
                </span>
                @endif
            </div>
        </div>
        <a href="{{ route('admin.pose-tasks.index', ['status' => 'planifiee']) }}"
           style="flex-shrink:0;font-size:11px;color:#ef4444;font-weight:700;text-decoration:none;padding:6px 12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;white-space:nowrap">
            Tout voir →
        </a>
    </div>
    @endif

    @if($posesSansPige > 0)
    <div class="alert-banner alert-warning">
        <div class="alert-banner-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </div>
        <div style="flex:1">
            <div style="font-size:13px;font-weight:700;color:#f97316">{{ $posesSansPige }} pose(s) réalisée(s) sans pige photo</div>
            <div style="font-size:11px;color:rgba(249,115,22,.7);margin-top:2px">Aucune preuve d'affichage enregistrée — le client ne peut pas être facturé</div>
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
<a href="{{ route('admin.pose-tasks.index', array_merge(request()->except(['status','page']), ['status'=>$k['s']])) }}"
   style="background:{{ $k['bg'] }};border:1px solid {{ $active ? $k['c'] : 'var(--border)' }};border-radius:14px;padding:16px 18px;text-decoration:none;display:block;transition:all .15s;{{ $active ? 'box-shadow:0 0 0 2px '.$k['c'].'30;' : '' }}"
   onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='{{ $k['c'] }}'"
   onmouseout="this.style.transform='';this.style.borderColor='{{ $active ? $k['c'] : 'var(--border)' }}'">
    <div style="font-size:26px;font-weight:800;color:{{ $k['c'] }};line-height:1;margin-bottom:6px">{{ number_format($k['v']) }}</div>
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3)">{{ $k['l'] }}</div>
    @if($active)<div style="font-size:9px;color:{{ $k['c'] }};margin-top:3px;font-weight:600">Filtre actif ✓</div>@endif
</a>
@endforeach
</div>

{{-- ════ FILTRES ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:12px 16px;margin-bottom:14px">
    <form method="GET" action="{{ route('admin.pose-tasks.index') }}" id="form-filters"
          style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
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
            @if(request()->hasAny(['status','technicien_id','campaign_id','date_from','date_to']))
            <a href="{{ route('admin.pose-tasks.index') }}" class="btn-reset" title="Réinitialiser">↺</a>
            @endif
        </div>
        <div style="margin-left:auto;align-self:flex-end;font-size:12px;color:var(--text3);padding-bottom:2px">
            <strong style="color:var(--text)">{{ number_format($poseTasks->total()) }}</strong> tâche(s) ·
            page {{ $poseTasks->currentPage() }}/{{ $poseTasks->lastPage() }}
        </div>
    </form>
</div>

{{-- ════ TABLEAU ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">

    {{-- Header tableau --}}
    <div style="padding:10px 16px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
        <div style="font-weight:600;font-size:13px;display:flex;align-items:center;gap:8px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            Tâches de pose
        </div>
        {{-- Légende colonne Pige --}}
        <div style="display:flex;gap:10px;font-size:10px;color:var(--text3)">
            <span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;background:rgba(249,115,22,.4);border-radius:50%;display:inline-block"></span>Sans pige</span>
            <span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;background:rgba(34,197,94,.4);border-radius:50%;display:inline-block"></span>Pigée</span>
            <span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;background:rgba(239,68,68,.3);border-radius:50%;display:inline-block"></span>En retard</span>
        </div>
    </div>

    @if($poseTasks->isEmpty())
    <div style="text-align:center;padding:60px 20px;color:var(--text3)">
        <div style="opacity:.15;margin-bottom:16px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="display:block;margin:0 auto"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div>
        <div style="font-size:14px;font-weight:700;margin-bottom:6px">Aucune tâche trouvée</div>
        <div style="font-size:12px;margin-bottom:18px">{{ request()->hasAny(['status','campaign_id','technicien_id']) ? 'Modifiez les filtres pour voir plus de résultats.' : 'Créez une tâche de pose pour commencer.' }}</div>
        @if(!request()->hasAny(['status','campaign_id','technicien_id']))
        <a href="{{ route('admin.pose-tasks.create') }}" class="btn btn-primary">+ Créer une tâche</a>
        @endif
    </div>
    @else
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;min-width:900px">
            <thead>
                <tr style="background:var(--surface2);border-bottom:1px solid var(--border)">
                    @foreach(['Panneau','Campagne','Technicien','Planifié','Réalisé','Pige','Statut','Actions'] as $h)
                    <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @foreach($poseTasks as $task)
            @php
            $sCfg = match($task->status) {
                'planifiee' => ['c'=>'#e8a020','bg'=>'rgba(232,160,32,.1)','bd'=>'rgba(232,160,32,.3)','l'=>'📅 Planifiée'],
                'en_cours'  => ['c'=>'#3b82f6','bg'=>'rgba(59,130,246,.1)','bd'=>'rgba(59,130,246,.3)','l'=>'🔧 En cours'],
                'realisee'  => ['c'=>'#22c55e','bg'=>'rgba(34,197,94,.1)', 'bd'=>'rgba(34,197,94,.3)', 'l'=>'✅ Réalisée'],
                'annulee'   => ['c'=>'#ef4444','bg'=>'rgba(239,68,68,.1)', 'bd'=>'rgba(239,68,68,.3)', 'l'=>'🚫 Annulée'],
                default     => ['c'=>'#6b7280','bg'=>'rgba(107,114,128,.1)','bd'=>'rgba(107,114,128,.3)','l'=>$task->status],
            };
            $isLate    = $task->status === 'planifiee' && $task->scheduled_at?->isPast();
            $pigeCount = $task->pige_count ?? 0;
            $pigeVerif = $task->pige_verifie_count ?? 0;
            $needsPige = $task->status === 'realisee' && $task->campaign_id && $pigeCount === 0;
            $rowBg     = $isLate ? 'rgba(239,68,68,.025)' : ($needsPige ? 'rgba(249,115,22,.015)' : '');
            @endphp
            <tr class="trow" style="{{ $rowBg ? 'background:'.$rowBg.';' : '' }}{{ $isLate ? 'border-left:2px solid rgba(239,68,68,.4);' : ($needsPige ? 'border-left:2px solid rgba(249,115,22,.3);' : '') }}">

                {{-- PANNEAU --}}
                <td style="padding:10px 12px">
                    <a href="{{ route('admin.pose-tasks.show', $task) }}"
                       style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent);text-decoration:none;display:block">
                        {{ $task->panel?->reference ?? '—' }}
                    </a>
                    <div style="font-size:11px;color:var(--text2);margin-top:1px;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                         title="{{ $task->panel?->name }}">{{ $task->panel?->name ?? '—' }}</div>
                    @if($task->panel?->commune)
                    <div style="font-size:10px;color:var(--text3)">{{ $task->panel->commune->name }}</div>
                    @endif
                </td>

                {{-- CAMPAGNE --}}
                <td style="padding:10px 12px;max-width:160px">
                    @if($task->campaign)
                    <a href="{{ route('admin.campaigns.show', $task->campaign) }}"
                       style="font-size:12px;font-weight:500;color:var(--text);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                       title="{{ $task->campaign->name }}">{{ Str::limit($task->campaign->name, 22) }}</a>
                    @php $cui = $task->campaign->status->uiConfig(); @endphp
                    <div style="font-size:9px;color:{{ $cui['color'] }};margin-top:2px;font-weight:600">{{ $cui['icon'] }} {{ $task->campaign->status->label() }}</div>
                    @else
                    <span style="font-size:11px;color:var(--text3);font-style:italic">Intervention</span>
                    @endif
                </td>

                {{-- TECHNICIEN --}}
                <td style="padding:10px 12px">
                    <div style="font-size:12px;color:var(--text)">{{ $task->technicien?->name ?? '—' }}</div>
                    @if($task->team_name)
                    <div style="font-size:10px;color:var(--text3)">👥 {{ $task->team_name }}</div>
                    @endif
                </td>

                {{-- PLANIFIÉ --}}
                <td style="padding:10px 12px;white-space:nowrap">
                    <div style="font-size:12px;font-weight:500;color:{{ $isLate ? '#ef4444' : 'var(--text)' }}">
                        {{ $task->scheduled_at?->format('d/m/Y') ?? '—' }}
                    </div>
                    <div style="font-size:10px;color:{{ $isLate ? '#ef4444' : 'var(--text3)' }}">
                        {{ $task->scheduled_at?->format('H:i') }}
                        @if($isLate) <span style="font-weight:700">⚠</span>@endif
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
                        📷 Ajouter
                    </a>
                    @elseif($pigeCount > 0)
                    <a href="{{ route('admin.piges.index', ['campaign_id'=>$task->campaign_id,'panel_id'=>$task->panel_id]) }}"
                       style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);color:#22c55e;border-radius:8px;font-size:10px;font-weight:700;text-decoration:none;white-space:nowrap">
                        📸 {{ $pigeCount }}@if($pigeVerif > 0)<span style="opacity:.65;font-size:9px"> ·{{ $pigeVerif }}✓</span>@endif
                    </a>
                    @else
                    <span style="font-size:10px;color:var(--text3)">—</span>
                    @endif
                </td>

                {{-- STATUT --}}
                <td style="padding:10px 12px">
                    <span style="padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;white-space:nowrap;background:{{ $sCfg['bg'] }};color:{{ $sCfg['c'] }};border:1px solid {{ $sCfg['bd'] }}">
                        {{ $sCfg['l'] }}
                    </span>
                </td>

                {{-- ACTIONS --}}
                <td style="padding:10px 12px">
                    <div style="display:flex;gap:4px;align-items:center">
                        @if(!in_array($task->status, ['realisee','annulee']))
                        <form method="POST" action="{{ route('admin.pose.complete', $task) }}" onsubmit="return confirm('Marquer réalisée ?')" style="line-height:0">
                            @csrf
                            <button type="submit" class="action-btn action-btn-success" title="Marquer réalisée">✅</button>
                        </form>
                        @endif
                        <a href="{{ route('admin.pose-tasks.show', $task) }}" class="action-btn" title="Voir">👁</a>
                        @if(!in_array($task->status, ['realisee','annulee']))
                        <a href="{{ route('admin.pose-tasks.edit', $task) }}" class="action-btn" title="Modifier">✏️</a>
                        @endif
                        @if($task->campaign_id && $task->status === 'realisee')
                        <a href="{{ route('admin.piges.index', ['campaign_id'=>$task->campaign_id]) }}" class="action-btn action-btn-accent" title="Piges campagne">📷</a>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($poseTasks->hasPages())
    <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
        <div style="font-size:12px;color:var(--text3)">
            Affichage {{ $poseTasks->firstItem() }}–{{ $poseTasks->lastItem() }} sur {{ number_format($poseTasks->total()) }}
        </div>
        {{ $poseTasks->links() }}
    </div>
    @endif
    @endif
</div>

<style>
.flbl { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:4px }
.finp { height:38px;padding:0 10px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:12px;color:var(--text);outline:none;box-sizing:border-box }
.fsel { height:38px;padding:0 10px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:12px;color:var(--text);cursor:pointer;outline:none }

.alert-banner { border-radius:12px;padding:12px 14px;display:flex;align-items:flex-start;gap:12px }
.alert-danger  { background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.25) }
.alert-warning { background:rgba(249,115,22,.07);border:1px solid rgba(249,115,22,.25) }
.alert-banner-icon { width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0 }
.alert-danger .alert-banner-icon  { background:rgba(239,68,68,.15) }
.alert-warning .alert-banner-icon { background:rgba(249,115,22,.15) }

.trow { transition:background .1s }
.trow:hover { background:var(--surface2) !important }
.trow:not(:last-child) td { border-bottom:1px solid var(--border) }

.action-btn { padding:4px 7px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;font-size:11px;color:var(--text2);text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;transition:border-color .1s }
.action-btn:hover { border-color:var(--accent);color:var(--accent) }
.action-btn-success { background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.25);color:#22c55e }
.action-btn-accent  { background:rgba(232,160,32,.08);border-color:rgba(232,160,32,.25);color:var(--accent) }

.btn-reset { display:flex;align-items:center;justify-content:center;width:38px;height:38px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text3);text-decoration:none;font-size:14px }
.btn-reset:hover { border-color:var(--accent);color:var(--accent) }
</style>
</x-admin-layout>