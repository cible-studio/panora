@if($poseTasks->isEmpty())
<div style="text-align:center;padding:60px 20px;color:var(--text3)">
    <div style="opacity:.15;margin-bottom:14px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="display:block;margin:0 auto"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div>
    <div style="font-size:14px;font-weight:700;margin-bottom:6px">Aucune tâche de pose</div>
    <div style="font-size:12px;margin-bottom:18px;color:var(--text3)">Créez une première tâche de pose pour commencer.</div>
    <a href="{{ route('admin.pose-tasks.create') }}" class="btn btn-primary">+ Créer une tâche</a>
</div>
@else
<div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;min-width:900px">
        <thead>
            <tr style="background:var(--surface2);border-bottom:1px solid var(--border)">
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Panneau</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Campagne</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Technicien</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Planifié</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Réalisé</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Pige</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Statut</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Actions</th>
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
            $sIcon = match($task->status) {
                'planifiee' => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
                'en_cours'  => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
                'realisee'  => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
                'annulee'   => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
                default     => '',
            };
            $isLate = $task->status === 'planifiee' && $task->scheduled_at?->isPast();
            $pigeCount = $task->pige_count ?? 0;
            $pigeVerif = $task->pige_verifie_count ?? 0;
            $needsPige = $task->status === 'realisee' && $task->campaign_id && $pigeCount === 0;
            $rowStyle = $isLate ? 'border-left:3px solid rgba(239,68,68,.5);background:rgba(239,68,68,.02)' : ($needsPige ? 'border-left:3px solid rgba(249,115,22,.4);background:rgba(249,115,22,.015)' : '');
        @endphp
        <tr class="trow" style="{{ $rowStyle }}">
            <td style="padding:10px 12px">
                <a href="{{ route('admin.pose-tasks.show', $task) }}" style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent);text-decoration:none;display:block">{{ $task->panel?->reference ?? '—' }}</a>
                <div style="font-size:11px;color:var(--text2);margin-top:1px;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $task->panel?->name }}">{{ $task->panel?->name ?? '—' }}</div>
                @if($task->panel?->commune)<div style="font-size:10px;color:var(--text3)">{{ $task->panel->commune->name }}</div>@endif
            </td>
            <td style="padding:10px 12px;max-width:150px">
                @if($task->campaign)
                <a href="{{ route('admin.campaigns.show', $task->campaign) }}" style="font-size:12px;font-weight:500;color:var(--text);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $task->campaign->name }}">{{ Str::limit($task->campaign->name, 22) }}</a>
                @php $cui = $task->campaign->status->uiConfig(); @endphp
                <div style="font-size:9px;color:{{ $cui['color'] }};margin-top:2px;font-weight:600">{{ $task->campaign->status->label() }}</div>
                @else
                <span style="font-size:11px;color:var(--text3);font-style:italic">Intervention</span>
                @endif
            </td>
            <td style="padding:10px 12px">
                <div style="font-size:12px;color:var(--text)">{{ $task->technicien?->name ?? '—' }}</div>
                @if($task->team_name)<div style="font-size:10px;color:var(--text3)">{{ $task->team_name }}</div>@endif
            </td>
            <td style="padding:10px 12px;white-space:nowrap">
                <div style="font-size:12px;font-weight:500;color:{{ $isLate ? '#ef4444' : 'var(--text)' }}">{{ $task->scheduled_at?->format('d/m/Y') ?? '—' }}</div>
                <div style="font-size:10px;color:{{ $isLate ? '#ef4444' : 'var(--text3)' }}">{{ $task->scheduled_at?->format('H:i') }}@if($isLate)<span style="font-weight:700;margin-left:3px">En retard</span>@endif</div>
            </td>
            <td style="padding:10px 12px;white-space:nowrap">
                @if($task->done_at)<div style="font-size:12px;color:#22c55e;font-weight:500">{{ $task->done_at->format('d/m/Y') }}</div><div style="font-size:10px;color:var(--text3)">{{ $task->done_at->format('H:i') }}</div>@else<span style="color:var(--text3);font-size:12px">—</span>@endif
            </td>
            <td style="padding:10px 12px">
                @if(!$task->campaign_id)<span style="font-size:10px;color:var(--text3)">N/A</span>
                @elseif($needsPige)<a href="{{ route('admin.piges.index', ['campaign_id'=>$task->campaign_id,'panel_id'=>$task->panel_id]) }}" style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);color:#f97316;border-radius:8px;font-size:10px;font-weight:700;text-decoration:none;white-space:nowrap"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg> Ajouter</a>
                @elseif($pigeCount > 0)<a href="{{ route('admin.piges.index', ['campaign_id'=>$task->campaign_id,'panel_id'=>$task->panel_id]) }}" style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);color:#22c55e;border-radius:8px;font-size:10px;font-weight:700;text-decoration:none;white-space:nowrap"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> {{ $pigeCount }}@if($pigeVerif > 0)<span style="font-size:9px;opacity:.65;margin-left:2px">·{{ $pigeVerif }}✓</span>@endif</a>
                @else<span style="font-size:10px;color:var(--text3)">—</span>@endif
            </td>
            <td style="padding:10px 12px">
                <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;white-space:nowrap;background:{{ $sCfg['bg'] }};color:{{ $sCfg['c'] }};border:1px solid {{ $sCfg['bd'] }}">{!! $sIcon !!} {{ $sCfg['l'] }}</span>
            </td>
            <td style="padding:10px 12px">
                <div style="display:flex;gap:4px;align-items:center">
                    @if(!in_array($task->status, ['realisee','annulee']))
                    <button class="action-btn action-btn-success" title="Marquer réalisée">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                    <form method="POST" action="{{ route('admin.pose.complete', $task) }}" style="display:none">@csrf</form>
                    @endif
                    <a href="{{ route('admin.pose-tasks.show', $task) }}" class="action-btn" title="Voir">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    @if(!in_array($task->status, ['realisee','annulee']))
                    <a href="{{ route('admin.pose-tasks.edit', $task) }}" class="action-btn" title="Modifier"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>
                    @endif
                    @if($task->campaign_id && $task->status === 'realisee')
                    <a href="{{ route('admin.piges.index', ['campaign_id'=>$task->campaign_id]) }}" class="action-btn action-btn-accent" title="Piges campagne"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></a>
                    @endif
                </div>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif