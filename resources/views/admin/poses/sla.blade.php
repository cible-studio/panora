<x-admin-layout title="SLA Pose & Pige">

<x-slot:topbarLeft>
    <a href="{{ route('admin.pose-tasks.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour
    </a>
</x-slot:topbarLeft>


<style>
    .sla-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:20px; }
    .sla-card {
        background:var(--surface); border:1px solid var(--border); border-radius:14px;
        padding:16px 18px; border-left:4px solid; display:flex; flex-direction:column; gap:6px;
    }
    .sla-card-label {
        font-size:10px; font-weight:700; text-transform:uppercase;
        letter-spacing:.5px; color:var(--text3);
    }
    .sla-card-value {
        font-size:28px; font-weight:800; line-height:1;
    }
    .sla-card-sub {
        font-size:11px; color:var(--text3); margin-top:auto;
    }
    .sla-section {
        background:var(--surface); border:1px solid var(--border); border-radius:14px;
        padding:18px; margin-bottom:18px;
    }
    .sla-section-title {
        font-size:13px; font-weight:700; color:var(--text);
        text-transform:uppercase; letter-spacing:.5px; margin-bottom:14px;
        display:flex; align-items:center; gap:8px;
    }
    .sla-rank-row {
        display:flex; align-items:center; gap:12px;
        padding:10px 0; border-bottom:1px solid var(--border);
    }
    .sla-rank-row:last-child { border-bottom:none; }
    .sla-rank {
        width:28px; height:28px; border-radius:50%; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
        font-size:12px; font-weight:800; color:#fff;
    }
    .sla-rank-1 { background:linear-gradient(135deg,#ffd700,#ffaa00); }
    .sla-rank-2 { background:linear-gradient(135deg,#c0c0c0,#9e9e9e); }
    .sla-rank-3 { background:linear-gradient(135deg,#cd7f32,#a05a25); }
    .sla-rank-default { background:var(--surface2); color:var(--text2); }
</style>

{{-- ── Barre filtre période ──────────────────────────────────── --}}
<div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
            background:var(--surface);border:1px solid var(--border);border-radius:12px;
            padding:12px 16px;margin-bottom:18px;">
    <div style="display:flex;align-items:center;gap:10px;min-width:0">
        <div style="width:36px;height:36px;border-radius:10px;background:var(--accent-dim);
                    display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div>
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px">Période d'analyse</div>
            <div style="font-size:13px;font-weight:600;color:var(--text);margin-top:1px">SLA Pose &amp; Pige sur les {{ $days }} derniers jours</div>
        </div>
    </div>
    <select onchange="window.location='?period=' + this.value"
            style="height:40px;padding:0 14px;background:var(--surface2);border:1px solid var(--border2);
                   border-radius:10px;font-size:13px;font-weight:600;color:var(--text);outline:none;
                   cursor:pointer;min-width:160px">
        @foreach([7=>'7 derniers jours', 14=>'14 derniers jours', 30=>'30 derniers jours', 60=>'60 derniers jours', 90=>'90 derniers jours'] as $v => $l)
            <option value="{{ $v }}" {{ $days === $v ? 'selected' : '' }}>{{ $l }}</option>
        @endforeach
    </select>
</div>

{{-- ── KPI cards (5) — design KPI unifié ─────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:20px">
    <div class="kpi-card" style="--kpi-color:#22c55e">
        <div class="kpi-card__top-bar" style="background:#22c55e"></div>
        <div class="kpi-card__icon" style="color:#22c55e"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg></div>
        <div class="kpi-card__value" style="color:#22c55e">
            @if($onTimeRate !== null){{ $onTimeRate }}<span style="font-size:14px;font-weight:600">%</span>@else<span style="color:var(--text3)">—</span>@endif
        </div>
        <div class="kpi-card__label">Taux de pose à temps</div>
        <div class="kpi-card__sub">{{ $onTime }} à temps · {{ $late }} en retard</div>
    </div>

    <div class="kpi-card" style="--kpi-color:#f97316">
        <div class="kpi-card__top-bar" style="background:#f97316"></div>
        <div class="kpi-card__icon" style="color:#f97316"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div class="kpi-card__value" style="color:#f97316">
            {{ $medianDelay }}<span style="font-size:14px;font-weight:600">j</span>
        </div>
        <div class="kpi-card__label">Médiane retard</div>
        <div class="kpi-card__sub">Sur les poses en retard ({{ $late }})</div>
    </div>

    <div class="kpi-card" style="--kpi-color:#3b82f6">
        <div class="kpi-card__top-bar" style="background:#3b82f6"></div>
        <div class="kpi-card__icon" style="color:#3b82f6"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="kpi-card__value" style="color:#3b82f6">
            @if($firstTimeRate !== null){{ $firstTimeRate }}<span style="font-size:14px;font-weight:600">%</span>@else<span style="color:var(--text3)">—</span>@endif
        </div>
        <div class="kpi-card__label">Validation 1ère fois</div>
        <div class="kpi-card__sub">{{ $pigesVerifiees }} validées · {{ $pigesRejetees }} rejetées</div>
    </div>

    <div class="kpi-card" style="--kpi-color:#8b5cf6">
        <div class="kpi-card__top-bar" style="background:#8b5cf6"></div>
        <div class="kpi-card__icon" style="color:#8b5cf6"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2v6h.01L6 8.01 10 12l-4 4 .01.01H6V22h12v-5.99h-.01L18 16l-4-4 4-3.99-.01-.01H18V2H6z"/></svg></div>
        <div class="kpi-card__value" style="color:#8b5cf6">
            @if($avgValidationHours !== null){{ $avgValidationHours }}<span style="font-size:14px;font-weight:600">h</span>@else<span style="color:var(--text3)">—</span>@endif
        </div>
        <div class="kpi-card__label">Délai validation</div>
        <div class="kpi-card__sub">Upload tech → validation MP</div>
    </div>

    <div class="kpi-card" style="--kpi-color:var(--accent)">
        <div class="kpi-card__top-bar" style="background:var(--accent)"></div>
        <div class="kpi-card__icon" style="color:var(--accent)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
        <div class="kpi-card__value" style="color:var(--accent)">{{ $totalPoses }}</div>
        <div class="kpi-card__label">Volume total</div>
        <div class="kpi-card__sub">Poses · {{ $pigesTotal }} piges sur {{ $days }}j</div>
    </div>
</div>

{{-- ── 2 colonnes : Top techs + Top communes ─────────────────── --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px" class="sla-cols">
    @push('styles')
    <style>@media (max-width:900px){.sla-cols{grid-template-columns:1fr}}</style>
    @endpush

    {{-- Top techs --}}
    <div class="sla-section">
        <div class="sla-section-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
            Top techniciens ({{ $days }}j)
        </div>
        @if($topTechs->isEmpty())
            <div style="text-align:center;padding:30px 0;color:var(--text3);font-size:12px">
                Aucune donnée sur la période
            </div>
        @else
            @foreach($topTechs as $i => $tech)
            @php $rank = $i + 1; $rankClass = match($rank){1=>'sla-rank-1',2=>'sla-rank-2',3=>'sla-rank-3',default=>'sla-rank-default'}; @endphp
            <div class="sla-rank-row">
                <div class="sla-rank {{ $rankClass }}">{{ $rank }}</div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $tech->name }}</div>
                    <div style="font-size:11px;color:var(--text3);margin-top:2px">
                        {{ $tech->total_poses }} pose(s) · {{ $tech->poses_realisees }} réalisée(s)
                        @if($tech->poses_retard > 0)<span style="color:#ef4444">· {{ $tech->poses_retard }} en retard</span>@endif
                    </div>
                </div>
                @php
                    $efficiency = $tech->total_poses > 0
                        ? round(($tech->poses_realisees / $tech->total_poses) * 100)
                        : 0;
                @endphp
                <div style="text-align:right;flex-shrink:0">
                    <div style="font-size:14px;font-weight:700;color:{{ $efficiency >= 80 ? '#22c55e' : ($efficiency >= 50 ? '#f97316' : '#ef4444') }}">{{ $efficiency }}%</div>
                    <div style="font-size:9px;color:var(--text3);text-transform:uppercase">Réalisation</div>
                </div>
            </div>
            @endforeach
        @endif
    </div>

    {{-- Top communes problématiques --}}
    <div class="sla-section">
        <div class="sla-section-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Communes à fort retard
        </div>
        @if($topCommunes->isEmpty())
            <div style="text-align:center;padding:30px 0;color:#22c55e;font-size:12px">
                ✅ Aucune commune avec retards · belle perf !
            </div>
        @else
            @foreach($topCommunes as $i => $commune)
            <div class="sla-rank-row">
                <div class="sla-rank sla-rank-default" style="background:rgba(239,68,68,.1);color:#ef4444">{{ $i + 1 }}</div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $commune->name }}">📍 {{ $commune->name }}</div>
                </div>
                <div style="text-align:right;flex-shrink:0">
                    <div style="font-size:14px;font-weight:700;color:#ef4444">{{ $commune->nb_retards }}</div>
                    <div style="font-size:9px;color:var(--text3);text-transform:uppercase">Retard{{ $commune->nb_retards > 1 ? 's' : '' }}</div>
                </div>
            </div>
            @endforeach
        @endif
    </div>
</div>

{{-- ── Tendance poses réalisées ─────────────────────────────── --}}
<div class="sla-section">
    <div class="sla-section-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        Tendance : poses réalisées par jour ({{ $days }}j)
    </div>
    @if($trend->isEmpty())
        <div style="text-align:center;padding:30px 0;color:var(--text3);font-size:12px">
            Aucune pose réalisée sur la période
        </div>
    @else
        @php
            $maxNb = $trend->max('nb');
        @endphp
        <div style="display:flex;align-items:flex-end;gap:3px;height:140px;padding:8px 0">
            @foreach($trend as $point)
            @php $h = $maxNb > 0 ? max(2, ($point->nb / $maxNb) * 100) : 0; @endphp
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;min-width:0"
                 title="{{ \Carbon\Carbon::parse($point->day)->format('d/m/Y') }} · {{ $point->nb }} pose(s)">
                <div style="width:100%;max-width:18px;height:{{ $h }}%;background:linear-gradient(180deg,#3b82f6,#1d4ed8);border-radius:3px 3px 0 0;min-height:2px"></div>
            </div>
            @endforeach
        </div>
        <div style="display:flex;justify-content:space-between;font-size:9px;color:var(--text3);margin-top:6px">
            <span>{{ \Carbon\Carbon::parse($trend->first()->day)->format('d/m') }}</span>
            <span style="font-weight:700">Total : {{ $trend->sum('nb') }} poses</span>
            <span>{{ \Carbon\Carbon::parse($trend->last()->day)->format('d/m') }}</span>
        </div>
    @endif
</div>

</x-admin-layout>
