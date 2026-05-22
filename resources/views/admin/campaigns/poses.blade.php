<x-admin-layout title="Poses — {{ $campaign->name }}">

<x-slot:topbarLeft>
    <a href="{{ route('admin.campaigns.show', $campaign) }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour à la campagne
    </a>
</x-slot:topbarLeft>

{{-- ════ Bandeau campagne ══════════════════════════════════════ --}}
@php $cui = $campaign->status->uiConfig(); @endphp
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="width:54px;height:54px;border-radius:13px;background:rgba(232,160,32,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:24px">
        🪧
    </div>
    <div style="flex:1;min-width:240px">
        <div style="font-size:10px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:1.2px;margin-bottom:4px">Poses OOH — campagne</div>
        <div style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:4px">{{ $campaign->name }}</div>
        <div style="font-size:12px;color:var(--text3);display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            @if($campaign->client)
                <span>👤 <a href="{{ route('admin.clients.show', $campaign->client) }}" style="color:var(--accent);text-decoration:none">{{ $campaign->client->name }}</a></span>
                <span>·</span>
            @endif
            <span>📅 {{ $campaign->start_date->format('d/m/Y') }} → {{ $campaign->end_date->format('d/m/Y') }}</span>
            <span>·</span>
            <span class="badge" style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;background:{{ $cui['bg'] ?? 'var(--surface2)' }};color:{{ $cui['color'] }};border:1px solid {{ $cui['border'] ?? 'var(--border)' }}">
                {{ $cui['icon'] }} {{ $campaign->status->label() }}
            </span>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="{{ route('admin.pose-tasks.create', ['campaign_id' => $campaign->id]) }}" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:6px">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nouvelle tâche de pose
        </a>
        <a href="{{ route('admin.piges.index', ['campaign_id' => $campaign->id]) }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            Piges photos
        </a>
    </div>
</div>

{{-- ════ KPI cards spécifiques à cette campagne ════════════════ --}}
@php
    $kpis = [
        ['key'=>'total',     'label'=>'Total',      'sub'=>'toutes les poses',  'value'=>$stats['total'],     'color'=>'var(--accent)',   'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>'],
        ['key'=>'planifiee', 'label'=>'Planifiées', 'sub'=>'à venir',            'value'=>$stats['planifiee'], 'color'=>'#e8a020',          'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'],
        ['key'=>'en_cours',  'label'=>'En cours',   'sub'=>'interventions',      'value'=>$stats['en_cours'],  'color'=>'#3b82f6',          'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>'],
        ['key'=>'realisee',  'label'=>'Réalisées',  'sub'=>'terminées',          'value'=>$stats['realisee'],  'color'=>'#22c55e',          'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>'],
    ];
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:20px">
    @foreach($kpis as $k)
    <div class="kpi-card" style="--kpi-color:{{ $k['color'] }}">
        <div class="kpi-card__top-bar" style="background:{{ $k['color'] }}"></div>
        <div class="kpi-card__icon" style="color:{{ $k['color'] }}">{!! $k['svg'] !!}</div>
        <div class="kpi-card__value" style="color:{{ $k['color'] }}">{{ number_format($k['value']) }}</div>
        <div class="kpi-card__label">{{ $k['label'] }}</div>
        <div class="kpi-card__sub">{{ $k['sub'] }}</div>
    </div>
    @endforeach
</div>

{{-- ════ Tableau des poses de cette campagne ══════════════════ --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">🔧 Tâches de pose · {{ $poseTasks->total() }}</div>
    </div>

    @if($poseTasks->isEmpty())
        <div style="text-align:center;padding:60px 20px;color:var(--text3)">
            <div style="opacity:.2;margin-bottom:12px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="display:block;margin:0 auto"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div>
            <div style="font-size:14px;font-weight:700;margin-bottom:6px">Aucune tâche de pose pour cette campagne</div>
            <div style="font-size:12px;margin-bottom:18px;color:var(--text3)">Créez une première tâche.</div>
            <a href="{{ route('admin.pose-tasks.create', ['campaign_id' => $campaign->id]) }}" class="btn btn-primary btn-sm">+ Créer une tâche</a>
        </div>
    @else
        @include('admin.poses.partials.table-rows', ['poseTasks' => $poseTasks])

        @if($poseTasks->hasPages())
        <div style="padding:14px 18px;border-top:1px solid var(--border)">
            {{ $poseTasks->links() }}
        </div>
        @endif
    @endif
</div>

</x-admin-layout>
