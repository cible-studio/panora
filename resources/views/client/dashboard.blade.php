@extends('client.layout')
@section('title', 'Tableau de bord')
@section('page-title', 'Tableau de bord')

@section('content')

{{-- ══ STATS ══ --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;transition:border-color .2s;cursor:default;"
         onmouseover="this.style.borderColor='rgba(226,6,19,.25)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="margin-bottom:12px;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
        </div>
        <div style="font-size:28px;font-weight:800;color:#e20613;line-height:1;">{{ $stats['propositions_en_attente'] ?? 0 }}</div>
        <div style="font-size:12px;color:var(--text3);margin-top:6px;">Propositions en attente</div>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;transition:border-color .2s;cursor:default;"
         onmouseover="this.style.borderColor='rgba(226,6,19,.25)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="margin-bottom:12px;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            </svg>
        </div>
        <div style="font-size:28px;font-weight:800;color:#fab80b;line-height:1;">{{ $stats['campagnes_actives'] ?? 0 }}</div>
        <div style="font-size:12px;color:var(--text3);margin-top:6px;">Campagnes actives</div>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;transition:border-color .2s;cursor:default;"
         onmouseover="this.style.borderColor='rgba(226,6,19,.25)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="margin-bottom:12px;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3f7fc0" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2"/>
                <path d="M8 21h8M12 17v4"/>
            </svg>
        </div>
        <div style="font-size:28px;font-weight:800;color:#3f7fc0;line-height:1;">{{ $stats['panneaux_actifs'] ?? 0 }}</div>
        <div style="font-size:12px;color:var(--text3);margin-top:6px;">Panneaux actifs</div>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;transition:border-color .2s;cursor:default;"
         onmouseover="this.style.borderColor='rgba(226,6,19,.25)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="margin-bottom:12px;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
                <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
            </svg>
        </div>
        <div style="font-size:28px;font-weight:800;color:#22c55e;line-height:1;">{{ $stats['campagnes_total'] ?? 0 }}</div>
        <div style="font-size:12px;color:var(--text3);margin-top:6px;">Total campagnes</div>
    </div>

</div>

{{-- ══ PROPOSITIONS RÉCENTES ══ --}}
<div style="margin-bottom:32px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
        </svg>
        <h2 style="font-size:15px;font-weight:700;color:var(--text);">Propositions récentes</h2>
    </div>

    @forelse($propositions->take(5) as $prop)
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:8px;transition:border-color .2s;"
         onmouseover="this.style.borderColor='rgba(226,6,19,.2)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;">
            <div>
                <div style="font-family:monospace;font-size:11px;color:#e20613;font-weight:700;margin-bottom:4px;">{{ $prop->reference }}</div>
                <div style="font-size:13px;color:var(--text2);">{{ $prop->start_date->format('d/m/Y') }} → {{ $prop->end_date->format('d/m/Y') }}</div>
                <div style="font-size:11px;color:var(--text3);margin-top:3px;">{{ $prop->panels->count() }} panneau(x)</div>
            </div>
            <a href="{{ route('client.proposition.detail', $prop->proposition_token) }}"
               style="padding:7px 14px;background:rgba(226,6,19,.08);border:1px solid rgba(226,6,19,.2);border-radius:8px;font-size:12px;font-weight:600;color:#e20613;text-decoration:none;transition:all .15s;"
               onmouseover="this.style.background='rgba(226,6,19,.15)'" onmouseout="this.style.background='rgba(226,6,19,.08)'">
                Voir →
            </a>
        </div>
    </div>
    @empty
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:40px;text-align:center;color:var(--text3);font-size:13px;">
        Aucune proposition récente
    </div>
    @endforelse
</div>

{{-- ══ CAMPAGNES ACTIVES ══ --}}
<div>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
        </svg>
        <h2 style="font-size:15px;font-weight:700;color:var(--text);">Campagnes actives</h2>
    </div>

    @forelse($campagnesActives as $camp)
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:8px;transition:border-color .2s;"
         onmouseover="this.style.borderColor='rgba(226,6,19,.2)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;">
            <div>
                <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px;">{{ $camp->name }}</div>
                <div style="font-size:12px;color:var(--text3);">Du {{ $camp->start_date->format('d/m/Y') }} au {{ $camp->end_date->format('d/m/Y') }}</div>
            </div>
            <a href="{{ route('client.campagne.detail', $camp) }}"
               style="padding:7px 14px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:8px;font-size:12px;font-weight:600;color:#22c55e;text-decoration:none;transition:all .15s;"
               onmouseover="this.style.background='rgba(34,197,94,.15)'" onmouseout="this.style.background='rgba(34,197,94,.08)'">
                Détails →
            </a>
        </div>
    </div>
    @empty
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:40px;text-align:center;color:var(--text3);font-size:13px;">
        Aucune campagne active
    </div>
    @endforelse
</div>

@endsection
