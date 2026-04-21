@extends('client.layout')
@section('title', 'Suivi des poses')
@section('page-title', 'Suivi des poses')

@section('content')

{{-- ── Info ─────────────────────────────────────────────────── --}}
<div style="background:rgba(139,92,246,.06);border:1px solid rgba(139,92,246,.2);border-radius:12px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;">
    <svg width="14" height="14" style="flex-shrink:0;margin-top:1px" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <div style="font-size:12px;color:#a78bfa;line-height:1.5;">
        <strong>Suivi de pose :</strong> Ces informations confirment que vos visuels ont bien été installés sur les emplacements réservés. Chaque pose réalisée est horodatée par nos équipes terrain.
    </div>
</div>

{{-- ── KPI ─────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;">
    @php
    $kpis = [
        ['v'=>(int)($kpi->realisees??0), 'l'=>'Poses réalisées',  'c'=>'#22c55e', 'ico'=>'<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
        ['v'=>(int)($kpi->planifiees??0),'l'=>'Poses planifiées', 'c'=>'#8b5cf6', 'ico'=>'<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
        ['v'=>(int)($kpi->en_cours??0),  'l'=>'En cours',         'c'=>'#3b82f6', 'ico'=>'<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
    ];
    @endphp
    @foreach($kpis as $k)
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;">
        <div style="margin-bottom:10px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="{{ $k['c'] }}" stroke-width="2">{!! $k['ico'] !!}</svg>
        </div>
        <div style="font-size:28px;font-weight:800;color:{{ $k['c'] }};line-height:1;">{{ number_format($k['v']) }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:5px;">{{ $k['l'] }}</div>
    </div>
    @endforeach
</div>

{{-- ── Filtres ─────────────────────────────────────────────── --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:16px;">
    <form method="GET" action="{{ route('client.poses') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
        <div style="flex:1;min-width:160px;">
            <label style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:4px;">Recherche</label>
            <div style="position:relative;">
                <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text3);pointer-events:none" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Référence ou nom panneau…"
                       style="width:100%;height:36px;padding:0 10px 0 30px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:12px;color:var(--text);outline:none;box-sizing:border-box;transition:border-color .15s"
                       onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
            </div>
        </div>
        <div>
            <label style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:4px;">Campagne</label>
            <select name="campaign_id" style="height:36px;padding:0 10px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:12px;color:var(--text);cursor:pointer;outline:none;" onchange="this.form.submit()">
                <option value="">Toutes</option>
                @foreach($campaigns as $c)
                <option value="{{ $c->id }}" {{ request('campaign_id')==$c->id?'selected':'' }}>{{ Str::limit($c->name,22) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:4px;">Du</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" style="height:36px;padding:0 10px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:12px;color:var(--text);outline:none;" onchange="this.form.submit()">
        </div>
        <div>
            <label style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:4px;">Au</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" style="height:36px;padding:0 10px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:12px;color:var(--text);outline:none;" onchange="this.form.submit()">
        </div>
        <div style="align-self:flex-end;display:flex;gap:6px;">
            <button type="submit" style="height:36px;padding:0 16px;background:#e20613;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">Filtrer</button>
            @if(request()->hasAny(['q','campaign_id','date_from','date_to']))
            <a href="{{ route('client.poses') }}" style="height:36px;display:flex;align-items:center;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:13px;color:var(--text2);text-decoration:none;">↺</a>
            @endif
        </div>
        <div style="margin-left:auto;align-self:flex-end;font-size:11px;color:var(--text3);padding-bottom:2px;">
            <strong style="color:var(--text);">{{ number_format($poses->total()) }}</strong> pose(s) réalisée(s)
        </div>
    </form>
</div>

{{-- ── Liste ────────────────────────────────────────────────── --}}
@if($poses->isEmpty())
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:60px;text-align:center;color:var(--text3);">
    <div style="font-size:40px;margin-bottom:12px;opacity:.3;">🔧</div>
    <div style="font-size:14px;font-weight:600;color:var(--text2);margin-bottom:6px;">Aucune pose trouvée</div>
    <div style="font-size:12px;">{{ request()->hasAny(['q','campaign_id']) ? 'Modifiez les filtres pour voir plus de résultats.' : 'Les poses terrain seront affichées ici une fois réalisées.' }}</div>
</div>
@else
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:600px;">
            <thead>
                <tr style="background:var(--surface2);border-bottom:1px solid var(--border);">
                    @foreach(['Panneau','Commune','Campagne','Date de pose','Statut'] as $h)
                    <th style="padding:10px 14px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap;">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @foreach($poses as $pose)
            <tr style="border-bottom:1px solid var(--border);transition:background .1s;"
                onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
                <td style="padding:12px 14px;">
                    <div style="font-family:monospace;font-size:12px;font-weight:700;color:#8b5cf6;">{{ $pose->panel?->reference }}</div>
                    <div style="font-size:11px;color:var(--text2);margin-top:2px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $pose->panel?->name }}</div>
                </td>
                <td style="padding:12px 14px;font-size:12px;color:var(--text2);">{{ $pose->panel?->commune?->name ?? '—' }}</td>
                <td style="padding:12px 14px;font-size:12px;color:var(--text2);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $pose->campaign?->name ?? '—' }}</td>
                <td style="padding:12px 14px;font-size:12px;color:#22c55e;font-weight:500;white-space:nowrap;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"/></svg>
                    {{ $pose->done_at?->format('d/m/Y') ?? '—' }}
                    <div style="font-size:10px;color:var(--text3);margin-top:1px;margin-left:15px;">{{ $pose->done_at?->format('H:i') }}</div>
                </td>
                <td style="padding:12px 14px;">
                    <span style="padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);">
                        ✓ Réalisée
                    </span>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($poses->hasPages())
    <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <div style="font-size:12px;color:var(--text3);">{{ $poses->firstItem() }}–{{ $poses->lastItem() }} sur {{ number_format($poses->total()) }}</div>
        {{ $poses->links() }}
    </div>
    @endif
</div>
@endif

<style>
.pagination { display:flex;justify-content:center;gap:6px;flex-wrap:wrap;margin:0; }
.pagination .page-link { display:flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text2);font-size:12px;transition:all .15s;text-decoration:none; }
.pagination .page-link:hover { background:rgba(226,6,19,.08);border-color:rgba(226,6,19,.25);color:#e20613; }
.pagination .active .page-link { background:#8b5cf6;border-color:#8b5cf6;color:#fff;font-weight:700; }
.pagination .disabled .page-link { opacity:.35;cursor:not-allowed; }
</style>

@endsection