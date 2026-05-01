@extends('client.layout')
@section('title', "Piges d'affichage")
@section('page-title', "Piges d'affichage")

@section('content')

{{-- ══ RETOUR ══ --}}
<a href="{{ route('client.dashboard') }}"
   style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text3);text-decoration:none;padding:6px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface);transition:all .15s;margin-bottom:18px;"
   onmouseover="this.style.color='var(--text)';this.style.borderColor='var(--border2)';this.style.background='var(--surface2)'"
   onmouseout="this.style.color='var(--text3)';this.style.borderColor='var(--border)';this.style.background='var(--surface)'">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Tableau de bord
</a>

{{-- ── Info ─────────────────────────────────────────────────── --}}
<div style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.2);border-radius:12px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;">
    <svg width="14" height="14" style="flex-shrink:0;margin-top:1px" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <div style="font-size:12px;color:#4ade80;line-height:1.5;">
        <strong>Piges d'affichage :</strong> Ces photos ont été prises sur le terrain par nos équipes de vérification et validées par un superviseur. Elles constituent la preuve que votre visuel est bien diffusé conformément à votre commande.
    </div>
</div>

{{-- ── KPI ─────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;">
    @php
    $kpis2 = [
        ['v'=>(int)($kpi->verifiees??0),       'l'=>'Photos vérifiées',      'c'=>'#22c55e', 'ico'=>'<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>'],
        ['v'=>(int)($kpi->panneaux_piges??0),  'l'=>'Panneaux documentés',   'c'=>'#0ea5e9', 'ico'=>'<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>'],
        ['v'=>(int)($kpi->campagnes_avec_pige??0),'l'=>'Campagnes couvertes','c'=>'#fab80b', 'ico'=>'<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>'],
    ];
    @endphp
    @foreach($kpis2 as $k)
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
    <form method="GET" action="{{ route('client.piges') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
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
            <a href="{{ route('client.piges') }}" style="height:36px;display:flex;align-items:center;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:13px;color:var(--text2);text-decoration:none;">↺</a>
            @endif
        </div>
        <div style="margin-left:auto;align-self:flex-end;font-size:11px;color:var(--text3);padding-bottom:2px;">
            <strong style="color:var(--text);">{{ number_format($piges->total()) }}</strong> pige(s) disponible(s)
        </div>
    </form>
</div>

{{-- ── Grille photos ────────────────────────────────────────── --}}
@if($piges->isEmpty())
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:60px;text-align:center;color:var(--text3);">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="display:block;margin:0 auto 16px;opacity:.2;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
    <div style="font-size:14px;font-weight:600;color:var(--text2);margin-bottom:6px;">Aucune pige disponible</div>
    <div style="font-size:12px;line-height:1.5;">
        @if(request()->hasAny(['q','campaign_id','date_from','date_to']))
        Modifiez les filtres pour voir plus de résultats.
        @else
        Les photos de vérification d'affichage apparaîtront ici dès qu'elles auront été validées par notre équipe.
        @endif
    </div>
</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;" id="pige-grid">
    @foreach($piges as $pige)
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:border-color .15s,transform .15s;"
         onmouseover="this.style.borderColor='rgba(34,197,94,.3)';this.style.transform='translateY(-2px)'"
         onmouseout="this.style.borderColor='var(--border)';this.style.transform=''">

        {{-- Photo cliquable --}}
        <a href="{{ Storage::url($pige->photo_path) }}" target="_blank"
           style="display:block;aspect-ratio:16/10;background:var(--surface2);overflow:hidden;position:relative;">
            <img src="{{ Storage::url($pige->photo_thumb ?? $pige->photo_path) }}"
                 alt="Pige {{ $pige->panel?->reference }}"
                 style="width:100%;height:100%;object-fit:cover;transition:transform .3s;"
                 loading="lazy"
                 onmouseover="this.style.transform='scale(1.04)'"
                 onmouseout="this.style.transform=''"
                 onerror="this.closest('a').innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;color:var(--text3);font-size:12px;flex-direction:column;gap:6px\'><svg width=24 height=24 viewBox=\'0 0 24 24\' fill=none stroke=currentColor stroke-width=1.5><path d=\'M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z\'/><circle cx=12 cy=13 r=4/></svg>Photo non disponible</div>'">

            {{-- Badge vérifié --}}
            <div style="position:absolute;top:8px;right:8px;padding:2px 8px;border-radius:20px;font-size:9px;font-weight:700;background:rgba(34,197,94,.85);color:#fff;backdrop-filter:blur(4px);">
                ✓ Vérifiée
            </div>

            {{-- GPS badge --}}
            @if($pige->gps_lat && $pige->gps_lng)
            <a href="https://maps.google.com/?q={{ $pige->gps_lat }},{{ $pige->gps_lng }}" target="_blank"
               onclick="event.stopPropagation()"
               style="position:absolute;bottom:8px;right:8px;padding:3px 8px;border-radius:8px;font-size:9px;font-weight:600;background:rgba(0,0,0,.7);color:#fff;text-decoration:none;display:flex;align-items:center;gap:4px;backdrop-filter:blur(4px);">
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                GPS
            </a>
            @endif
        </a>

        {{-- Infos --}}
        <div style="padding:12px 14px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:6px;">
                <div style="min-width:0;">
                    <div style="font-family:monospace;font-size:12px;font-weight:700;color:#22c55e;">{{ $pige->panel?->reference }}</div>
                    <div style="font-size:12px;color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $pige->panel?->name }}</div>
                </div>
                <a href="{{ Storage::url($pige->photo_path) }}" target="_blank"
                   style="flex-shrink:0;padding:4px 8px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:7px;font-size:10px;color:#22c55e;text-decoration:none;white-space:nowrap;">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:2px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    Voir
                </a>
            </div>

            @if($pige->campaign)
            <div style="font-size:10px;color:var(--text3);margin-bottom:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                {{ $pige->campaign->name }}
            </div>
            @endif

            @if($pige->panel?->commune)
            <div style="font-size:10px;color:var(--text3);display:flex;align-items:center;gap:3px;margin-bottom:5px;">
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $pige->panel->commune->name }}
            </div>
            @endif

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;padding-top:6px;border-top:1px solid var(--border);">
                <div style="font-size:10px;color:var(--text3);">
                    @if($pige->taken_at)
                    Prise {{ $pige->taken_at->format('d/m/Y') }}
                    @endif
                </div>
                <div style="font-size:10px;color:#22c55e;font-weight:600;">
                    Vérifié {{ $pige->verified_at?->format('d/m/Y') }}
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Pagination --}}
@if($piges->hasPages())
<div style="margin-top:20px;display:flex;justify-content:space-between;align-items:center;">
    <div style="font-size:12px;color:var(--text3);">{{ $piges->firstItem() }}–{{ $piges->lastItem() }} sur {{ number_format($piges->total()) }}</div>
    {{ $piges->links() }}
</div>
@endif
@endif

<style>
.pagination { display:flex;justify-content:center;gap:6px;flex-wrap:wrap;margin:0; }
.pagination .page-link { display:flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text2);font-size:12px;transition:all .15s;text-decoration:none; }
.pagination .page-link:hover { background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.25);color:#22c55e; }
.pagination .active .page-link { background:#22c55e;border-color:#22c55e;color:#fff;font-weight:700; }
.pagination .disabled .page-link { opacity:.35;cursor:not-allowed; }
</style>

@endsection