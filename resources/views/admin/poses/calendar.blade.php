<x-admin-layout title="Calendrier hebdomadaire des poses">

<x-slot:topbarLeft>
    <a href="{{ route('admin.pose-tasks.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour
    </a>
</x-slot:topbarLeft>

<x-slot:topbarActions>
    @php $prev = $weekStart->copy()->subWeek()->format('Y-m-d'); $next = $weekStart->copy()->addWeek()->format('Y-m-d'); @endphp
    <a href="?week={{ $prev }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:5px" title="Semaine précédente">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Précédente
    </a>
    <a href="{{ route('admin.pose-tasks.calendar') }}" class="btn btn-ghost btn-sm">Aujourd'hui</a>
    <a href="?week={{ $next }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:5px" title="Semaine suivante">
        Suivante
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
</x-slot:topbarActions>

<style>
    .cal-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:12px }
    .cal-period { font-size:15px;font-weight:700;color:var(--text) }
    .cal-period small { font-size:11px;color:var(--text3);font-weight:500;margin-left:8px }

    .cal-table-wrap { background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:auto }
    .cal-table { width:100%;border-collapse:separate;border-spacing:0;min-width:1000px }
    .cal-table th, .cal-table td {
        padding:8px;
        vertical-align:top;
        border-right:1px solid var(--border);
        border-bottom:1px solid var(--border);
    }
    .cal-table th { background:var(--surface2);font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--text3);font-weight:700;text-align:center;position:sticky;top:0;z-index:5 }
    .cal-table th.cal-day-today { color:var(--accent);background:rgba(232,160,32,.08) }
    .cal-tech-cell {
        width:170px;min-width:170px;
        background:var(--surface2);position:sticky;left:0;z-index:3;
        padding:10px 12px !important;
        font-weight:600;font-size:12px;color:var(--text);
    }
    .cal-tech-cell.cal-tech-none { color:var(--text3);font-style:italic }
    .cal-day-col { width:13.5%;min-width:130px;background:var(--surface) }
    .cal-day-col.cal-day-today-col { background:rgba(232,160,32,.03) }
    .cal-task {
        display:block;
        padding:5px 8px;margin-bottom:4px;
        border-radius:6px;font-size:11px;text-decoration:none;
        border-left:3px solid;line-height:1.3;
    }
    .cal-task:hover { transform:translateX(1px) }
    .cal-task-ref { font-family:ui-monospace,Menlo,Consolas,monospace;font-weight:700 }
    .cal-task-time { color:var(--text3);font-size:9px;margin-top:1px }
    .cal-empty { color:var(--text3);font-size:10px;text-align:center;padding:6px 0;opacity:.5 }
    .cal-load-pill {
        display:inline-flex;align-items:center;gap:3px;
        padding:2px 6px;border-radius:8px;
        font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;
    }
</style>

<div class="cal-header">
    <div class="cal-period">
        📅 Semaine du {{ $weekStart->locale('fr')->isoFormat('dddd D MMMM') }}
        <small>au {{ $weekEnd->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</small>
    </div>
    @php
        $totalWeek = collect($grid)->flatten(2)->count();
    @endphp
    <div style="font-size:12px;color:var(--text3)">
        <strong style="color:var(--text)">{{ $totalWeek }}</strong> pose{{ $totalWeek > 1 ? 's' : '' }} planifiée{{ $totalWeek > 1 ? 's' : '' }} cette semaine
    </div>
</div>

<div class="cal-table-wrap">
    <table class="cal-table">
        <thead>
            <tr>
                <th class="cal-tech-cell" style="text-align:left;">Technicien</th>
                @foreach($days as $day)
                @php $isToday = $day->isToday(); @endphp
                <th class="cal-day-col {{ $isToday ? 'cal-day-today' : '' }}">
                    <div style="font-size:11px">{{ $day->locale('fr')->isoFormat('ddd') }}</div>
                    <div style="font-size:14px;color:var(--text);margin-top:2px">{{ $day->format('d/m') }}</div>
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php
                $rows = $techniciens->push((object)['id'=>'none', 'name'=>'— Non assignées —', 'whatsapp_number'=>null]);
            @endphp
            @foreach($rows as $tech)
                @php
                    $techGrid = $grid[$tech->id] ?? [];
                    $techTotal = collect($techGrid)->flatten()->count();
                @endphp
                @if($tech->id === 'none' && $techTotal === 0) @continue @endif
                <tr>
                    <td class="cal-tech-cell {{ $tech->id === 'none' ? 'cal-tech-none' : '' }}">
                        <div style="display:flex;align-items:center;gap:8px">
                            @if($tech->id !== 'none')
                                @php
                                    $initials = mb_strtoupper(mb_substr(collect(explode(' ', $tech->name))->map(fn($w)=>$w[0] ?? '')->take(2)->implode(''), 0, 2));
                                @endphp
                                <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#e8a020,#fab80b);color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0">{{ $initials }}</div>
                            @else
                                <div style="width:26px;height:26px;border-radius:50%;background:var(--surface);border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0">⚠</div>
                            @endif
                            <div style="min-width:0">
                                <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $tech->name }}</div>
                                @if($techTotal > 0)
                                    @php
                                        $load = $techTotal;
                                        $loadColor = $load >= 15 ? '#ef4444' : ($load >= 8 ? '#f97316' : '#22c55e');
                                        $loadLabel = $load >= 15 ? 'Surchargé' : ($load >= 8 ? 'Chargé' : 'OK');
                                    @endphp
                                    <span class="cal-load-pill" style="background:{{ $loadColor }}1a;color:{{ $loadColor }};border:1px solid {{ $loadColor }}40;margin-top:3px">
                                        {{ $load }} · {{ $loadLabel }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </td>
                    @foreach($days as $day)
                        @php
                            $dayKey = $day->format('Y-m-d');
                            $dayTasks = $techGrid[$dayKey] ?? [];
                            $isToday = $day->isToday();
                        @endphp
                        <td class="cal-day-col {{ $isToday ? 'cal-day-today-col' : '' }}">
                            @if(empty($dayTasks))
                                <div class="cal-empty">·</div>
                            @else
                                @foreach($dayTasks as $task)
                                    @php
                                        $tCfg = match($task->status) {
                                            'planifiee' => ['c'=>'#e8a020','bg'=>'rgba(232,160,32,.08)'],
                                            'en_cours'  => ['c'=>'#3b82f6','bg'=>'rgba(59,130,246,.08)'],
                                            'realisee'  => ['c'=>'#22c55e','bg'=>'rgba(34,197,94,.08)'],
                                            'annulee'   => ['c'=>'#ef4444','bg'=>'rgba(239,68,68,.08)'],
                                            default     => ['c'=>'#6b7280','bg'=>'rgba(107,114,128,.08)'],
                                        };
                                        $isLate = $task->status === 'planifiee' && $task->scheduled_at?->isPast();
                                    @endphp
                                    <a href="{{ route('admin.pose-tasks.show', $task) }}"
                                       class="cal-task"
                                       style="background:{{ $tCfg['bg'] }};border-left-color:{{ $isLate ? '#ef4444' : $tCfg['c'] }};color:var(--text)"
                                       title="{{ $task->panel?->name }}{{ $task->campaign ? ' · ' . $task->campaign->name : '' }}">
                                        <div class="cal-task-ref" style="color:{{ $tCfg['c'] }}">
                                            {{ $task->panel?->reference ?? '#'.$task->id }}
                                            @if($isLate)<span style="color:#ef4444">⚠</span>@endif
                                        </div>
                                        <div class="cal-task-time">{{ $task->scheduled_at?->format('H:i') }} · {{ Str::limit($task->panel?->commune?->name, 12) }}</div>
                                    </a>
                                @endforeach
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            @if($techniciens->isEmpty())
                <tr>
                    <td colspan="8" style="text-align:center;padding:40px;color:var(--text3)">
                        Aucun technicien actif.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>

<div style="margin-top:14px;display:flex;gap:14px;flex-wrap:wrap;font-size:11px;color:var(--text3)">
    <span style="display:inline-flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#e8a020;border-radius:2px"></span> Planifiée</span>
    <span style="display:inline-flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#3b82f6;border-radius:2px"></span> En cours</span>
    <span style="display:inline-flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#22c55e;border-radius:2px"></span> Réalisée</span>
    <span style="display:inline-flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#ef4444;border-radius:2px"></span> Retard / Annulée</span>
    <span style="margin-left:auto;font-size:10px">
        Charge :
        <span style="color:#22c55e;font-weight:700">≤7 OK</span> ·
        <span style="color:#f97316;font-weight:700">8-14 Chargé</span> ·
        <span style="color:#ef4444;font-weight:700">≥15 Surchargé</span>
    </span>
</div>

</x-admin-layout>
