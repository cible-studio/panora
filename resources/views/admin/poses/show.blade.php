<x-admin-layout title="Tâche {{ $poseTask->panel?->reference }}">

<x-slot:topbarActions>
    <a href="{{ route('admin.pose-tasks.index') }}" class="btn btn-ghost btn-sm">← Retour</a>
    @if(!in_array($poseTask->status, ['realisee','annulee']))
    <a href="{{ route('admin.pose-tasks.edit', $poseTask) }}" class="btn btn-ghost btn-sm">✏️ Modifier</a>
    <form method="POST" action="{{ route('admin.pose.complete', $poseTask) }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Marquer cette tâche comme réalisée ?')">
            ✅ Marquer réalisée
        </button>
    </form>
    @endif
</x-slot:topbarActions>

@php
$sCfg = match($poseTask->status) {
    'planifiee' => ['c'=>'#e8a020','bg'=>'rgba(232,160,32,.08)','bd'=>'rgba(232,160,32,.3)','icon'=>'📅','l'=>'Planifiée'],
    'en_cours'  => ['c'=>'#3b82f6','bg'=>'rgba(59,130,246,.08)', 'bd'=>'rgba(59,130,246,.3)', 'icon'=>'🔧','l'=>'En cours'],
    'realisee'  => ['c'=>'#22c55e','bg'=>'rgba(34,197,94,.08)',  'bd'=>'rgba(34,197,94,.3)',  'icon'=>'✅','l'=>'Réalisée'],
    'annulee'   => ['c'=>'#ef4444','bg'=>'rgba(239,68,68,.08)',  'bd'=>'rgba(239,68,68,.3)',  'icon'=>'🚫','l'=>'Annulée'],
    default     => ['c'=>'#6b7280','bg'=>'rgba(107,114,128,.08)','bd'=>'rgba(107,114,128,.3)','icon'=>'❓','l'=>$poseTask->status],
};
@endphp

{{-- Alerte retard --}}
@if($isLate)
<div style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.28);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    <div>
        <div style="font-size:13px;font-weight:700;color:#ef4444">Tâche en retard</div>
        <div style="font-size:11px;color:rgba(239,68,68,.7)">
            Planifiée le {{ $poseTask->scheduled_at?->format('d/m/Y à H:i') }}
            ({{ $poseTask->scheduled_at?->diffForHumans() }}) — non réalisée
        </div>
    </div>
</div>
@endif

{{-- Alertes liées --}}
@if($taskAlerts->isNotEmpty())
<div style="margin-bottom:16px;display:flex;flex-direction:column;gap:6px">
    @foreach($taskAlerts as $alert)
    @php
    $ac = match($alert->niveau ?? 'info') {
        'danger'  => ['c'=>'#ef4444','bg'=>'rgba(239,68,68,.07)','bd'=>'rgba(239,68,68,.25)','i'=>'🔴'],
        'warning' => ['c'=>'#f97316','bg'=>'rgba(249,115,22,.07)','bd'=>'rgba(249,115,22,.25)','i'=>'🟠'],
        default   => ['c'=>'#3b82f6','bg'=>'rgba(59,130,246,.07)','bd'=>'rgba(59,130,246,.25)','i'=>'🔵'],
    };
    @endphp
    <div style="background:{{ $ac['bg'] }};border:1px solid {{ $ac['bd'] }};border-radius:10px;padding:10px 14px;display:flex;align-items:flex-start;gap:10px">
        <span style="font-size:14px;flex-shrink:0">{{ $ac['i'] }}</span>
        <div>
            <div style="font-size:12px;font-weight:700;color:{{ $ac['c'] }}">{{ $alert->title }}</div>
            <div style="font-size:11px;color:{{ $ac['c'] }};opacity:.8;margin-top:2px">{{ $alert->message }}</div>
        </div>
        <form method="POST" action="{{ route('admin.alerts.read', $alert) }}" style="margin-left:auto;flex-shrink:0">
            @csrf
            <button type="submit" style="background:none;border:none;color:{{ $ac['c'] }};cursor:pointer;opacity:.6;font-size:12px" title="Marquer comme lu">✓</button>
        </form>
    </div>
    @endforeach
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start">

    {{-- ══ CARTE PRINCIPALE ══ --}}
    <div>

        {{-- Header --}}
        <div class="detail-card" style="margin-bottom:14px">
            <div class="detail-card-header">
                <div>
                    <div style="font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                        Tâche de pose #{{ $poseTask->id }}
                    </div>
                    <div style="font-size:11px;color:var(--text3);margin-top:3px">
                        Panneau <span style="font-family:monospace;color:var(--accent);font-weight:700">{{ $poseTask->panel?->reference }}</span>
                    </div>
                </div>
                <span style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;background:{{ $sCfg['bg'] }};color:{{ $sCfg['c'] }};border:1px solid {{ $sCfg['bd'] }}">
                    {{ $sCfg['icon'] }} {{ $sCfg['l'] }}
                </span>
            </div>

            {{-- Grid infos --}}
            <div style="padding:18px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
                @php
                $fields = [
                    ['PANNEAU',     $poseTask->panel?->reference.' — '.($poseTask->panel?->name ?? '—'), null, route('admin.panels.show', $poseTask->panel)],
                    ['COMMUNE',     $poseTask->panel?->commune?->name ?? '—', null, null],
                    ['CAMPAGNE',    $poseTask->campaign?->name ?? 'Sans campagne', null, $poseTask->campaign ? route('admin.campaigns.show', $poseTask->campaign) : null],
                    ['STATUT CAMP.',($poseTask->campaign?->status?->label() ?? '—'), $poseTask->campaign?->status?->uiConfig()['color'] ?? null, null],
                    ['TECHNICIEN',  $poseTask->technicien?->name ?? 'Non assigné', null, null],
                    ['ÉQUIPE',      $poseTask->team_name ?? '—', null, null],
                    ['PLANIFIÉ LE', $poseTask->scheduled_at?->format('d/m/Y à H:i') ?? '—', $isLate ? '#ef4444' : null, null],
                    ['RÉALISÉ LE',  $poseTask->done_at?->format('d/m/Y à H:i') ?? '—', $poseTask->done_at ? '#22c55e' : null, null],
                ];
                @endphp
                @foreach($fields as [$label, $value, $color, $link])
                <div>
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:4px">{{ $label }}</div>
                    @if($link)
                    <a href="{{ $link }}" style="font-size:13px;font-weight:500;color:{{ $color ?? 'var(--accent)' }};text-decoration:none;line-height:1.4">{{ $value }}</a>
                    @else
                    <div style="font-size:13px;font-weight:500;color:{{ $color ?? 'var(--text)' }};line-height:1.4">{{ $value }}</div>
                    @endif
                </div>
                @endforeach
            </div>

            @if($poseTask->notes)
            <div style="padding:0 18px 18px">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:6px">NOTES</div>
                <div style="font-size:12px;color:var(--text2);background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;line-height:1.6;white-space:pre-wrap">{{ $poseTask->notes }}</div>
            </div>
            @endif
        </div>

        {{-- ══ ÉTAT DES PIGES ══ --}}
        @if($pigeStats)
        <div class="detail-card">
            <div class="detail-card-header">
                <div style="font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Piges photos pour ce panneau
                </div>
                <a href="{{ route('admin.piges.index', ['campaign_id'=>$poseTask->campaign_id,'panel_id'=>$poseTask->panel_id]) }}"
                   style="font-size:11px;color:var(--accent);font-weight:600;text-decoration:none;padding:4px 10px;background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.25);border-radius:8px">
                    Gérer →
                </a>
            </div>
            <div style="padding:16px 18px">
                @if($pigeStats['total'] === 0)
                <div style="text-align:center;padding:16px;color:var(--text3)">
                    @if($poseTask->status === 'realisee')
                    <div style="font-size:13px;font-weight:600;color:#f97316;margin-bottom:8px">⚠️ Aucune pige pour ce panneau</div>
                    <div style="font-size:12px;margin-bottom:14px">La pose est réalisée mais aucune photo de preuve n'a été enregistrée.</div>
                    <a href="{{ route('admin.piges.index', ['campaign_id'=>$poseTask->campaign_id]) }}"
                       style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#e8a020;color:#000;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none">
                        📷 Ajouter une pige maintenant
                    </a>
                    @else
                    <div style="font-size:12px;color:var(--text3)">La pose n'est pas encore réalisée. La pige sera ajoutée après la pose.</div>
                    @endif
                </div>
                @else
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
                    @foreach([['Total','total','#e8a020'],['Vérifiées','verifie','#22c55e'],['En attente','en_attente','#f97316'],['Rejetées','rejete','#ef4444']] as [$l,$k,$c])
                    <div style="text-align:center;padding:12px;background:var(--surface2);border-radius:10px;border:1px solid var(--border)">
                        <div style="font-size:22px;font-weight:800;color:{{ $c }};line-height:1">{{ $pigeStats[$k] }}</div>
                        <div style="font-size:10px;color:var(--text3);margin-top:4px;text-transform:uppercase;letter-spacing:.4px;font-weight:600">{{ $l }}</div>
                    </div>
                    @endforeach
                </div>

                @if($pigeStats['verifie'] > 0)
                <div style="margin-top:10px;padding:10px 14px;background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.2);border-radius:10px;font-size:12px;color:#22c55e;display:flex;align-items:center;gap:8px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    {{ $pigeStats['verifie'] }} pige(s) vérifiée(s) — la pose est correctement documentée.
                </div>
                @endif
                @if($pigeStats['rejete'] > 0)
                <div style="margin-top:8px;padding:10px 14px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:10px;font-size:12px;color:#ef4444">
                    ⚠️ {{ $pigeStats['rejete'] }} pige(s) rejetée(s). Le technicien doit soumettre une nouvelle photo.
                </div>
                @endif
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- ══ SIDEBAR ACTIONS ══ --}}
    <div style="display:flex;flex-direction:column;gap:12px">

        {{-- Actions principales --}}
        <div class="detail-card">
            <div class="detail-card-header"><div style="font-size:13px;font-weight:700">⚡ Actions</div></div>
            <div style="padding:14px 16px;display:flex;flex-direction:column;gap:6px">

                @if($poseTask->panel)
                <a href="{{ route('admin.panels.show', $poseTask->panel) }}" class="sidebar-action">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                    Voir le panneau
                </a>
                @endif

                @if($poseTask->campaign)
                <a href="{{ route('admin.campaigns.show', $poseTask->campaign) }}" class="sidebar-action">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
                    Voir la campagne
                </a>
                @if(!$poseTask->campaign->status->isTerminal())
                <a href="{{ route('admin.piges.index', ['campaign_id'=>$poseTask->campaign_id,'panel_id'=>$poseTask->panel_id]) }}" class="sidebar-action sidebar-action-accent">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Piges de ce panneau
                </a>
                @endif
                @endif

                @if(!in_array($poseTask->status, ['realisee','annulee']))
                <div style="border-top:1px solid var(--border);padding-top:6px;margin-top:2px;display:flex;flex-direction:column;gap:6px">
                    <a href="{{ route('admin.pose-tasks.edit', $poseTask) }}" class="sidebar-action">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                        Modifier la tâche
                    </a>
                    <form method="POST" action="{{ route('admin.pose-tasks.destroy', $poseTask) }}"
                          onsubmit="return confirm('Supprimer définitivement cette tâche ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="sidebar-action sidebar-action-danger" style="width:100%;text-align:left;cursor:pointer">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                            Supprimer
                        </button>
                    </form>
                </div>
                @endif
            </div>
        </div>

        {{-- Méta-informations --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 16px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:10px">Informations</div>
            <div style="display:flex;flex-direction:column;gap:8px">
                @php
                $meta = [
                    ['Créée le', $poseTask->created_at->format('d/m/Y à H:i')],
                    ['Modifiée le', $poseTask->updated_at->format('d/m/Y à H:i')],
                    ['ID', '#'.$poseTask->id],
                ];
                @endphp
                @foreach($meta as [$l,$v])
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:11px;color:var(--text3)">{{ $l }}</span>
                    <span style="font-size:11px;color:var(--text2);font-family:monospace">{{ $v }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Progression campagne --}}
        @if($poseTask->campaign)
        @php
        $prog = $poseTask->campaign->progressPercent();
        $remaining = $poseTask->campaign->daysRemaining();
        $endSoon = $poseTask->campaign->isEndingSoon();
        @endphp
        <div style="background:var(--surface);border:1px solid {{ $endSoon ? 'rgba(249,115,22,.3)' : 'var(--border)' }};border-radius:14px;padding:14px 16px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:10px">Campagne</div>
            <div style="font-size:12px;color:var(--text2);margin-bottom:8px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $poseTask->campaign->name }}">
                {{ Str::limit($poseTask->campaign->name, 26) }}
            </div>
            @if($poseTask->campaign->start_date && $poseTask->campaign->end_date)
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-bottom:6px">
                <span>{{ $poseTask->campaign->start_date->format('d/m/Y') }}</span>
                <span style="color:{{ $endSoon ? '#f97316' : 'var(--text3)' }};font-weight:{{ $endSoon ? '700' : '400' }}">
                    {{ $remaining >= 0 ? $remaining.' j. restants' : abs($remaining).' j. passés' }}
                </span>
                <span>{{ $poseTask->campaign->end_date->format('d/m/Y') }}</span>
            </div>
            <div style="background:var(--surface2);border-radius:20px;height:6px;overflow:hidden">
                <div style="background:{{ $endSoon ? '#f97316' : 'var(--accent)' }};height:100%;width:{{ min(100,$prog) }}%;border-radius:20px;transition:width .3s"></div>
            </div>
            @endif
        </div>
        @endif

    </div>
</div>

<style>
.detail-card { background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden }
.detail-card-header { padding:12px 16px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center }

.sidebar-action { display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;font-size:12px;color:var(--text2);text-decoration:none;background:var(--surface2);border:1px solid var(--border);transition:border-color .15s }
.sidebar-action:hover { border-color:var(--accent);color:var(--accent) }
.sidebar-action-accent { background:rgba(232,160,32,.06);border-color:rgba(232,160,32,.2);color:var(--accent) }
.sidebar-action-danger { background:rgba(239,68,68,.04);border-color:rgba(239,68,68,.15);color:#ef4444 }
.sidebar-action-danger:hover { border-color:rgba(239,68,68,.4) }
</style>
</x-admin-layout>