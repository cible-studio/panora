@extends('client.layout')
@section('title', $campaign->name)
@section('page-title', $campaign->name)

@section('content')

{{-- ══ BREADCRUMB ══ --}}
<div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text3);margin-bottom:20px;">
    <a href="{{ route('client.dashboard') }}" style="color:var(--text3);text-decoration:none;transition:color .15s;" onmouseover="this.style.color='#e20613'" onmouseout="this.style.color='var(--text3)'">Accueil</a>
    <span>›</span>
    <a href="{{ route('client.campagnes') }}" style="color:var(--text3);text-decoration:none;transition:color .15s;" onmouseover="this.style.color='#e20613'" onmouseout="this.style.color='var(--text3)'">Campagnes</a>
    <span>›</span>
    <span style="color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $campaign->name }}</span>
</div>

{{-- ══ HEADER ══ --}}
@php
    $s = $campaign->status->value;
    $badge = match($s) {
        'actif'   => ['bg'=>'rgba(34,197,94,.1)',  'color'=>'#22c55e',  'label'=>'Actif'],
        'pose'    => ['bg'=>'rgba(59,130,246,.1)', 'color'=>'#60a5fa',  'label'=>'En pose'],
        'termine' => ['bg'=>'rgba(250,184,11,.1)', 'color'=>'#fab80b',  'label'=>'Terminé'],
        'annule'  => ['bg'=>'rgba(239,68,68,.1)',  'color'=>'#ef4444',  'label'=>'Annulé'],
        default   => ['bg'=>'rgba(148,163,184,.1)','color'=>'#94a3b8',  'label'=>ucfirst($s)],
    };
    $daysLeft = now()->startOfDay()->diffInDays($campaign->end_date->startOfDay(), false);
    $duration = $campaign->start_date->diffInDays($campaign->end_date);
@endphp

<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:20px;">
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:16px;">
        <div>
            <h1 style="font-size:22px;font-weight:700;color:var(--text);margin-bottom:4px;">{{ $campaign->name }}</h1>
            <div style="font-size:12px;color:var(--text3);">Campagne #{{ $campaign->id }}</div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="font-size:12px;font-weight:600;padding:6px 16px;border-radius:20px;background:{{ $badge['bg'] }};color:{{ $badge['color'] }};">
                {{ $badge['label'] }}
            </span>
            @if($s === 'actif' && $daysLeft >= 0)
            <span style="font-size:11px;color:{{ $daysLeft <= 7 ? '#ef4444' : 'var(--text3)' }};">
                {{ $daysLeft }}j restant(s)
            </span>
            @endif
        </div>
    </div>
</div>

{{-- ══ STATS ══ --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2" style="margin-bottom:8px;"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        <div style="font-size:15px;font-weight:700;color:var(--text);">{{ $campaign->start_date->format('d/m/Y') }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:3px;">Date de début</div>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="margin-bottom:8px;"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        <div style="font-size:15px;font-weight:700;color:var(--text);">{{ $campaign->end_date->format('d/m/Y') }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:3px;">Date de fin</div>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3f7fc0" stroke-width="2" style="margin-bottom:8px;"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        <div style="font-size:22px;font-weight:700;color:#3f7fc0;">{{ $campaign->panels->count() }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:3px;">Panneaux</div>
    </div>

    @if($campaign->total_amount > 0)
    <div style="background:var(--surface);border:1px solid rgba(226,6,19,.2);border-radius:12px;padding:16px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2" style="margin-bottom:8px;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        <div style="font-size:15px;font-weight:700;color:#e20613;">{{ number_format($campaign->total_amount, 0, ',', ' ') }}</div>
        <div style="font-size:10px;color:var(--text3);margin-top:3px;">FCFA total</div>
    </div>
    @else
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" style="margin-bottom:8px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <div style="font-size:15px;font-weight:700;color:var(--text);">{{ $duration }}j</div>
        <div style="font-size:11px;color:var(--text3);margin-top:3px;">Durée totale</div>
    </div>
    @endif

</div>

{{-- ══ PANNEAUX ══ --}}
@if($campaign->panels->isNotEmpty())
<div style="margin-bottom:28px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        <h2 style="font-size:15px;font-weight:700;color:var(--text);">Emplacements</h2>
        <span style="font-size:11px;color:var(--text3);">({{ $campaign->panels->count() }})</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($campaign->panels as $panel)
        @php $photo = $panel->photos->sortBy('ordre')->first(); @endphp
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:border-color .2s;"
             onmouseover="this.style.borderColor='rgba(226,6,19,.25)'" onmouseout="this.style.borderColor='var(--border)'">
            @if($photo)
                <img src="{{ asset('storage/' . ltrim($photo->path, '/')) }}"
                     style="width:100%;height:160px;object-fit:cover;display:block;" alt="{{ $panel->reference }}" loading="lazy">
            @else
                <div style="width:100%;height:160px;background:var(--surface2);display:flex;align-items:center;justify-content:center;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                </div>
            @endif
            <div style="padding:14px;">
                <div style="font-family:monospace;font-size:11px;color:#e20613;font-weight:700;margin-bottom:4px;">{{ $panel->reference }}</div>
                <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $panel->name }}">{{ $panel->name }}</div>
                <div style="display:flex;flex-direction:column;gap:3px;font-size:11px;color:var(--text3);">
                    @if($panel->commune?->name)
                    <div style="display:flex;align-items:center;gap:5px;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        {{ $panel->commune->name }}
                    </div>
                    @endif
                    @if($panel->format?->name)
                    <div style="display:flex;align-items:center;gap:5px;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="1"/></svg>
                        {{ $panel->format->name }}
                    </div>
                    @endif
                    @if($panel->is_lit)
                    <div style="display:flex;align-items:center;gap:5px;color:#fab80b;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        Éclairé
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@else
<div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:60px;text-align:center;margin-bottom:28px;">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="1.5" style="margin:0 auto 16px;display:block;opacity:.4;"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
    <div style="font-size:15px;font-weight:600;color:var(--text2);margin-bottom:4px;">Aucun panneau associé</div>
    <div style="font-size:13px;color:var(--text3);">Cette campagne n'a pas encore de panneaux assignés.</div>
</div>
@endif

{{-- ══ FACTURES ══ --}}
@if($campaign->invoices->isNotEmpty())
<div>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <h2 style="font-size:15px;font-weight:700;color:var(--text);">Factures</h2>
    </div>

    <div style="display:flex;flex-direction:column;gap:8px;">
        @foreach($campaign->invoices as $inv)
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px 20px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;transition:border-color .2s;"
             onmouseover="this.style.borderColor='rgba(226,6,19,.2)'" onmouseout="this.style.borderColor='var(--border)'">
            <div>
                <div style="font-family:monospace;font-size:12px;color:#e20613;font-weight:700;margin-bottom:3px;">{{ $inv->reference ?? '#' . $inv->id }}</div>
                <div style="font-size:11px;color:var(--text3);">Émise le {{ $inv->created_at->format('d/m/Y') }}</div>
            </div>
            <div style="font-size:16px;font-weight:700;color:var(--text);">
                {{ number_format($inv->amount ?? 0, 0, ',', ' ') }} <span style="font-size:11px;font-weight:400;color:var(--text3);">FCFA</span>
            </div>
            @if(!empty($inv->paid_at))
                <span style="font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;background:rgba(34,197,94,.1);color:#22c55e;">✓ Payée</span>
            @else
                <span style="font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;background:rgba(250,184,11,.1);color:#fab80b;">⏳ En attente</span>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

@endsection
