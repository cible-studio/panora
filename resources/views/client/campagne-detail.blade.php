@extends('client.layout')
@section('title', $campaign->name)
@section('page-title', $campaign->name)

@section('content')

{{-- ══ RETOUR ══ --}}
<a href="{{ route('client.campagnes') }}"
   style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text3);text-decoration:none;padding:6px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface);transition:all .15s;margin-bottom:18px;"
   onmouseover="this.style.color='var(--text)';this.style.borderColor='var(--border2)';this.style.background='var(--surface2)'"
   onmouseout="this.style.color='var(--text3)';this.style.borderColor='var(--border)';this.style.background='var(--surface)'">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Mes campagnes
</a>

@php
use Illuminate\Support\Facades\Storage;

$s = $campaign->status->value;
$badge = match($s) {
    'actif'   => ['bg'=>'rgba(34,197,94,.1)',  'color'=>'#22c55e',  'label'=>'Actif',    'bd'=>'rgba(34,197,94,.25)'],
    'pose'    => ['bg'=>'rgba(139,92,246,.1)', 'color'=>'#8b5cf6',  'label'=>'En pose',  'bd'=>'rgba(139,92,246,.25)'],
    'termine' => ['bg'=>'rgba(250,184,11,.1)', 'color'=>'#fab80b',  'label'=>'Terminé',  'bd'=>'rgba(250,184,11,.25)'],
    'annule'  => ['bg'=>'rgba(239,68,68,.1)',  'color'=>'#ef4444',  'label'=>'Annulé',   'bd'=>'rgba(239,68,68,.25)'],
    default   => ['bg'=>'rgba(148,163,184,.1)','color'=>'#94a3b8',  'label'=>ucfirst($s),'bd'=>'rgba(148,163,184,.25)'],
};
$daysLeft = now()->startOfDay()->diffInDays($campaign->end_date->startOfDay(), false);
$duration = max(1, $campaign->start_date->diffInDays($campaign->end_date));
$elapsed  = min($duration, $campaign->start_date->diffInDays(now()));
$progress = round(($elapsed / $duration) * 100);
$coverageColor = $coveragePercent >= 80 ? '#22c55e' : ($coveragePercent >= 50 ? '#fab80b' : '#ef4444');
@endphp

{{-- ══ BREADCRUMB ══ --}}
<nav style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text3);margin-bottom:18px;flex-wrap:wrap;">
    <a href="{{ route('client.dashboard') }}" style="color:var(--text3);text-decoration:none;transition:color .15s;" onmouseover="this.style.color='#e20613'" onmouseout="this.style.color='var(--text3)'">Accueil</a>
    <span>›</span>
    <a href="{{ route('client.campagnes') }}" style="color:var(--text3);text-decoration:none;transition:color .15s;" onmouseover="this.style.color='#e20613'" onmouseout="this.style.color='var(--text3)'">Campagnes</a>
    <span>›</span>
    <span style="color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px;" title="{{ $campaign->name }}">{{ $campaign->name }}</span>
</nav>

{{-- ══ HEADER CAMPAGNE ══ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px 24px;margin-bottom:16px;">
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:14px;">
        <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                <h1 style="font-size:20px;font-weight:700;color:var(--text);">{{ $campaign->name }}</h1>
                <span style="font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;background:{{ $badge['bg'] }};color:{{ $badge['color'] }};border:1px solid {{ $badge['bd'] }};flex-shrink:0;">
                    {{ $badge['label'] }}
                </span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:16px;font-size:12px;color:var(--text3);">
                <span style="display:flex;align-items:center;gap:5px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Du <strong style="color:var(--text2);">{{ $campaign->start_date->format('d/m/Y') }}</strong> au <strong style="color:var(--text2);">{{ $campaign->end_date->format('d/m/Y') }}</strong>
                </span>
                <span>·</span>
                <span style="display:flex;align-items:center;gap:5px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                    <strong style="color:var(--text2);">{{ $totalPanneaux }}</strong> panneau(x)
                </span>
                @if($campaign->total_amount > 0)
                <span>·</span>
                <span style="display:flex;align-items:center;gap:5px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <strong style="color:#e20613;">{{ number_format($campaign->total_amount, 0, ',', ' ') }} FCFA</strong>
                </span>
                @endif
            </div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
            @if($daysLeft > 0 && in_array($s, ['actif','pose']))
            <div style="font-size:24px;font-weight:800;color:{{ $daysLeft <= 7 ? '#ef4444' : ($daysLeft <= 14 ? '#f97316' : 'var(--text)') }};line-height:1;">{{ $daysLeft }}</div>
            <div style="font-size:10px;color:var(--text3);margin-top:2px;">jours restants</div>
            @elseif($daysLeft <= 0 && $s !== 'annule')
            <div style="font-size:12px;font-weight:600;color:var(--text3);">Campagne terminée</div>
            @endif
        </div>
    </div>

    {{-- Barre progression temporelle --}}
    @if(in_array($s, ['actif','pose']))
    <div style="margin-top:14px;">
        <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-bottom:5px;">
            <span>Début</span>
            <span style="font-weight:600;color:var(--text2);">{{ $progress }}% de la durée écoulée</span>
            <span>Fin</span>
        </div>
        <div style="background:var(--surface2);border-radius:20px;height:6px;overflow:hidden;">
            <div style="background:{{ $daysLeft <= 7 ? 'linear-gradient(90deg,#ef4444,#f87171)' : 'linear-gradient(90deg,#e20613,#fab80b)' }};height:100%;width:{{ $progress }}%;border-radius:20px;transition:width .5s;"></div>
        </div>
    </div>
    @endif
</div>

{{-- ══ KPI GRILLE 4 + barre couverture ══ --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:16px;">

    {{-- Poses réalisées --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;">
        <div style="margin-bottom:8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div style="font-size:24px;font-weight:800;color:#8b5cf6;line-height:1;">{{ $posesCount }}</div>
        <div style="font-size:10px;color:var(--text3);margin-top:4px;">Poses réalisées</div>
        @if($totalPanneaux > 0)
        <div style="font-size:10px;color:var(--text3);margin-top:2px;">sur {{ $totalPanneaux }}</div>
        @endif
    </div>

    {{-- Piges vérifiées --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;">
        <div style="margin-bottom:8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </div>
        <div style="font-size:24px;font-weight:800;color:#22c55e;line-height:1;">{{ $pigesVerif->flatten()->count() }}</div>
        <div style="font-size:10px;color:var(--text3);margin-top:4px;">Piges d'affichage</div>
        <div style="font-size:10px;color:var(--text3);margin-top:2px;">{{ $pigesCount }} panneau(x) couverts</div>
    </div>

    {{-- Panneaux couverts --}}
    <div style="background:var(--surface);border:1px solid {{ $coverageColor }}33;border-radius:12px;padding:16px;">
        <div style="margin-bottom:8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="{{ $coverageColor }}" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div style="font-size:24px;font-weight:800;color:{{ $coverageColor }};line-height:1;">{{ $coveragePercent }}%</div>
        <div style="font-size:10px;color:var(--text3);margin-top:4px;">Couverture pige</div>
        <div style="font-size:10px;color:var(--text3);margin-top:2px;">{{ $pigesCount }}/{{ $totalPanneaux }} panneaux</div>
    </div>

    {{-- Durée --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;">
        <div style="margin-bottom:8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div style="font-size:24px;font-weight:800;color:#fab80b;line-height:1;">{{ $duration }}j</div>
        <div style="font-size:10px;color:var(--text3);margin-top:4px;">Durée totale</div>
    </div>
</div>

{{-- ══ BARRE COUVERTURE PIGE ══ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
        <div style="font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="{{ $coverageColor }}" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Taux de vérification d'affichage
        </div>
        <div style="display:flex;align-items:center;gap:14px;font-size:11px;color:var(--text3);">
            <span>
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#8b5cf6;margin-right:4px;vertical-align:middle;"></span>
                Posé : <strong style="color:var(--text);">{{ $posesCount }}</strong>
            </span>
            <span>
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:4px;vertical-align:middle;"></span>
                Pigé : <strong style="color:var(--text);">{{ $pigesCount }}</strong>
            </span>
            <span>
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--surface2);border:1px solid var(--border2);margin-right:4px;vertical-align:middle;"></span>
                Total : <strong style="color:var(--text);">{{ $totalPanneaux }}</strong>
            </span>
        </div>
    </div>
    <div style="background:var(--surface2);border-radius:20px;height:10px;overflow:hidden;margin-bottom:8px;">
        <div style="background:{{ $coveragePercent >= 80 ? 'linear-gradient(90deg,#22c55e,#4ade80)' : ($coveragePercent >= 50 ? 'linear-gradient(90deg,#fab80b,#fde047)' : 'linear-gradient(90deg,#ef4444,#f87171)') }};height:100%;width:{{ $coveragePercent }}%;border-radius:20px;transition:width .6s ease;"></div>
    </div>
    <div style="font-size:11px;color:var(--text3);line-height:1.5;">
        @if($coveragePercent >= 80)
        <span style="color:#22c55e;">✓ Excellente couverture</span> — La majorité de vos emplacements ont été photographiquement vérifiés.
        @elseif($coveragePercent >= 50)
        <span style="color:#fab80b;">⚡ Couverture partielle</span> — Des vérifications sont encore en cours sur certains emplacements.
        @elseif($coveragePercent > 0)
        <span style="color:#f97316;">⏳ Début de couverture</span> — Les piges d'affichage arrivent progressivement.
        @else
        <span style="color:var(--text3);">⏳ Vérifications en attente</span> — Les piges d'affichage apparaîtront ici dès leur validation par notre équipe.
        @endif
    </div>
</div>

@php
// Flat array for lightbox — indexed by position, mapped by pige ID
$allPigesLightbox = [];
$pigeIndexMap     = [];
foreach ($pigesVerif as $panelId => $panelPigeGroup) {
    $panelObj = $campaign->panels->firstWhere('id', $panelId);
    foreach ($panelPigeGroup as $pg) {
        $pigeIndexMap[$pg->id] = count($allPigesLightbox);
        $allPigesLightbox[] = [
            'id'           => $pg->id,
            'photo_url'    => Storage::url($pg->photo_path),
            'verified_at'  => $pg->verified_at?->format('d/m/Y') ?? '—',
            'panel_ref'    => $panelObj?->reference ?? '—',
            'panel_name'   => $panelObj?->name ?? '—',
            'download_url' => route('client.pige.download', $pg->id),
        ];
    }
}
@endphp

{{-- ══ PANNEAUX — liste avec statuts pose + pige ══ --}}
<div style="margin-bottom:28px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
        <div style="display:flex;align-items:center;gap:8px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            <span style="font-size:15px;font-weight:700;color:var(--text);">Emplacements</span>
            <span style="font-size:11px;color:var(--text3);">({{ $totalPanneaux }})</span>
        </div>
        {{-- Légende --}}
        <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:10px;color:var(--text3);">
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#8b5cf6;display:inline-block;"></span> Posé</span>
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block;"></span> Pigé</span>
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:var(--surface2);border:1px solid var(--border2);display:inline-block;"></span> En attente</span>
        </div>
    </div>

    @if($campaign->panels->isEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:60px;text-align:center;color:var(--text3);">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="display:block;margin:0 auto 16px;opacity:.2;"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        <div style="font-size:14px;font-weight:600;color:var(--text2);margin-bottom:4px;">Aucun panneau associé</div>
        <div style="font-size:12px;">Cette campagne n'a pas encore de panneaux assignés.</div>
    </div>
    @else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
        @foreach($campaign->panels as $panel)
        @php
            $photo      = $panel->photos->sortBy('ordre')->first();
            $panelPose  = $poses[$panel->id] ?? null;
            $panelPiges = $pigesVerif[$panel->id] ?? collect();
            $isPosed    = !is_null($panelPose);
            $hasProof   = $panelPiges->isNotEmpty();
            // Couleur bordure : vert si pigé, violet si posé, grise sinon
            $borderClr  = $hasProof ? 'rgba(34,197,94,.35)' : ($isPosed ? 'rgba(139,92,246,.35)' : 'var(--border)');
        @endphp

        <div style="background:var(--surface);border:1px solid {{ $borderClr }};border-radius:14px;overflow:hidden;transition:border-color .2s,transform .15s;"
             onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='{{ $hasProof ? 'rgba(34,197,94,.5)' : ($isPosed ? 'rgba(139,92,246,.5)' : 'rgba(226,6,19,.25)') }}'"
             onmouseout="this.style.transform='';this.style.borderColor='{{ $borderClr }}'">

            {{-- Photo du panneau --}}
            <div style="position:relative;height:160px;background:var(--surface2);overflow:hidden;">
                @if($photo)
                <img src="{{ asset('storage/' . ltrim($photo->path, '/')) }}"
                     style="width:100%;height:100%;object-fit:cover;" alt="{{ $panel->reference }}" loading="lazy"
                     onerror="this.closest('div').innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%\'><svg width=32 height=32 viewBox=\'0 0 24 24\' fill=none stroke=var(--text3) stroke-width=1.5 opacity=.3><rect x=2 y=3 width=20 height=14 rx=2/><path d=\'M8 21h8M12 17v4\'/></svg></div>'">
                @else
                <div style="display:flex;align-items:center;justify-content:center;height:100%;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="1.3" style="opacity:.3;"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                </div>
                @endif

                {{-- Indicateur statut coin haut gauche --}}
                <div style="position:absolute;top:8px;left:8px;display:flex;gap:4px;">
                    @if($isPosed)
                    <span style="padding:2px 7px;border-radius:20px;font-size:9px;font-weight:700;background:rgba(139,92,246,.85);color:#fff;backdrop-filter:blur(4px);">
                        ✓ Posé
                    </span>
                    @endif
                    @if($hasProof)
                    <span style="padding:2px 7px;border-radius:20px;font-size:9px;font-weight:700;background:rgba(34,197,94,.85);color:#fff;backdrop-filter:blur(4px);">
                        📸 {{ $panelPiges->count() }}
                    </span>
                    @endif
                </div>

                {{-- Référence coin bas gauche --}}
                <div style="position:absolute;bottom:0;left:0;right:0;padding:6px 10px;background:linear-gradient(transparent,rgba(0,0,0,.7));font-family:monospace;font-size:11px;font-weight:700;color:#fff;">
                    {{ $panel->reference }}
                </div>
            </div>

            {{-- Infos --}}
            <div style="padding:12px 14px;">
                <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $panel->name }}">
                    {{ $panel->name }}
                </div>

                <div style="display:flex;flex-direction:column;gap:3px;font-size:11px;color:var(--text3);margin-bottom:10px;">
                    @if($panel->commune?->name)
                    <div style="display:flex;align-items:center;gap:5px;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        {{ $panel->commune->name }}
                    </div>
                    @endif
                    @if($panel->format?->name)
                    <div style="display:flex;align-items:center;gap:5px;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="1"/></svg>
                        {{ $panel->format->name }}
                        @if($panel->format->width && $panel->format->height)
                        <span style="opacity:.6;">· {{ rtrim(rtrim(number_format($panel->format->width,2,'.','.'), '0'), '.') }}×{{ rtrim(rtrim(number_format($panel->format->height,2,'.','.'), '0'), '.') }}m</span>
                        @endif
                    </div>
                    @endif
                    @if($panel->is_lit)
                    <div style="display:flex;align-items:center;gap:5px;color:#fab80b;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        Éclairé
                    </div>
                    @endif
                </div>

                {{-- Statut pose --}}
                <div style="border-top:1px solid var(--border);padding-top:10px;margin-bottom:8px;">
                    @if($isPosed)
                    <div style="display:flex;align-items:center;gap:6px;font-size:11px;">
                        <div style="width:20px;height:20px;background:rgba(139,92,246,.12);border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div>
                            <div style="font-weight:600;color:#8b5cf6;">Pose réalisée</div>
                            @if($panelPose->done_at)
                            <div style="font-size:10px;color:var(--text3);">Le {{ $panelPose->done_at->format('d/m/Y à H:i') }}</div>
                            @endif
                        </div>
                    </div>
                    @else
                    <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--text3);">
                        <div style="width:20px;height:20px;background:var(--surface2);border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div>Pose en attente de réalisation</div>
                    </div>
                    @endif
                </div>

                {{-- Piges d'affichage vérifiées --}}
                @if($hasProof)
                <div style="background:rgba(34,197,94,.04);border:1px solid rgba(34,197,94,.15);border-radius:10px;padding:8px 10px;">
                    <div style="font-size:10px;font-weight:700;color:#22c55e;margin-bottom:6px;display:flex;align-items:center;gap:4px;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        {{ $panelPiges->count() }} pige(s) d'affichage
                    </div>
                    <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">
                        @foreach($panelPiges->take(4) as $pg)
                        <button onclick="openLightbox({{ $pigeIndexMap[$pg->id] ?? 0 }})"
                                style="width:44px;height:44px;border-radius:7px;overflow:hidden;border:1.5px solid rgba(34,197,94,.3);flex-shrink:0;display:block;transition:border-color .15s;padding:0;background:none;cursor:pointer;"
                                onmouseover="this.style.borderColor='rgba(34,197,94,.7)'" onmouseout="this.style.borderColor='rgba(34,197,94,.3)'"
                                title="Pige du {{ $pg->verified_at?->format('d/m/Y') }} — cliquer pour agrandir">
                            <img src="{{ Storage::url($pg->photo_thumb ?? $pg->photo_path) }}"
                                 style="width:100%;height:100%;object-fit:cover;" loading="lazy"
                                 onerror="this.closest('button').innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;background:var(--surface2)\'><svg width=14 height=14 viewBox=\'0 0 24 24\' fill=none stroke=currentColor stroke-width=1.5 opacity=.4><path d=\'M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z\'/><circle cx=12 cy=13 r=4/></svg></div>'">
                        </button>
                        @endforeach
                        @if($panelPiges->count() > 4)
                        <div style="width:44px;height:44px;border-radius:7px;background:var(--surface2);border:1.5px solid var(--border2);display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--text3);font-weight:700;">
                            +{{ $panelPiges->count() - 4 }}
                        </div>
                        @endif
                        <div style="margin-left:4px;font-size:9px;color:var(--text3);line-height:1.3;">
                            Dernière<br>{{ $panelPiges->first()?->verified_at?->format('d/m/Y') }}
                        </div>
                    </div>
                </div>
                @else
                <div style="background:var(--surface2);border-radius:10px;padding:8px 10px;font-size:11px;color:var(--text3);display:flex;align-items:center;gap:6px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Pige d'affichage en attente de validation
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- ══ FACTURES ══ --}}
@if($campaign->invoices->isNotEmpty())
<div style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <span style="font-size:15px;font-weight:700;color:var(--text);">Factures</span>
        <span style="font-size:11px;color:var(--text3);">({{ $campaign->invoices->count() }})</span>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;">
        @foreach($campaign->invoices as $i => $inv)
        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;padding:16px 20px;{{ $i < $campaign->invoices->count()-1 ? 'border-bottom:1px solid var(--border)' : '' }};transition:background .1s;"
             onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
            <div>
                <div style="font-family:monospace;font-size:12px;font-weight:700;color:#e20613;margin-bottom:3px;">{{ $inv->reference ?? 'FAC-'.$inv->id }}</div>
                <div style="font-size:11px;color:var(--text3);">Émise le {{ $inv->created_at->format('d/m/Y') }}</div>
            </div>
            <div style="font-size:18px;font-weight:700;color:var(--text);">
                {{ number_format($inv->amount ?? 0, 0, ',', ' ') }}<span style="font-size:11px;font-weight:400;color:var(--text3);margin-left:4px;">FCFA</span>
            </div>
            @if(!empty($inv->paid_at))
            <span style="font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);">
                ✓ Payée le {{ \Carbon\Carbon::parse($inv->paid_at)->format('d/m/Y') }}
            </span>
            @else
            <span style="font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;background:rgba(250,184,11,.1);color:#fab80b;border:1px solid rgba(250,184,11,.2);">
                ⏳ En attente
            </span>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ══ INTERLOCUTEUR ══ --}}
@if($campaign->user)
@php
    $interlocuteur = $campaign->user;
    $initials = collect(explode(' ', $interlocuteur->name))
        ->map(fn($w) => strtoupper($w[0] ?? ''))->filter()->take(2)->implode('');
@endphp
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
    <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#e20613,#fab80b);display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:800;color:#fff;flex-shrink:0;letter-spacing:-.5px;">
        {{ $initials }}
    </div>
    <div style="flex:1;min-width:140px;">
        <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Votre interlocuteur</div>
        <div style="font-size:15px;font-weight:700;color:var(--text);">{{ $interlocuteur->name }}</div>
        <div style="font-size:12px;color:var(--text3);margin-top:2px;">{{ $interlocuteur->role?->label() ?? '—' }}</div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;min-width:0;">
        <a href="mailto:{{ $interlocuteur->email }}"
           style="display:inline-flex;align-items:center;gap:7px;font-size:12px;color:var(--text2);text-decoration:none;transition:color .15s;white-space:nowrap;"
           onmouseover="this.style.color='#e20613'" onmouseout="this.style.color='var(--text2)'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            {{ $interlocuteur->email }}
        </a>
        @if($interlocuteur->whatsapp_number)
        <a href="https://wa.me/{{ preg_replace('/\D/', '', $interlocuteur->whatsapp_number) }}"
           target="_blank" rel="noopener"
           style="display:inline-flex;align-items:center;gap:7px;font-size:12px;color:var(--text2);text-decoration:none;transition:color .15s;white-space:nowrap;"
           onmouseover="this.style.color='#22c55e'" onmouseout="this.style.color='var(--text2)'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            {{ $interlocuteur->whatsapp_number }}
        </a>
        @endif
    </div>
</div>
@endif

{{-- ══ SATISFACTION ══ --}}
@if($campaign->status->value === 'termine' && $campaign->satisfactionSurvey)
@php $survey = $campaign->satisfactionSurvey; @endphp
@if($survey->isCompleted())
<div style="background:rgba(34,197,94,.04);border:1px solid rgba(34,197,94,.2);border-radius:14px;padding:20px 24px;margin-bottom:20px;">
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:16px;">
        <div style="width:48px;height:48px;border-radius:14px;background:rgba(34,197,94,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </div>
        <div style="flex:1;min-width:0;">
            <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Votre avis</div>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span style="font-size:22px;font-weight:800;color:#22c55e;">{{ number_format($survey->averageScore() ?? 0, 1) }}<span style="font-size:13px;color:var(--text3);font-weight:400;">/5</span></span>
                <div style="display:flex;flex-direction:column;gap:2px;">
                    @foreach([
                        ['label'=>'Qualité',          'score'=>$survey->score_qualite],
                        ['label'=>'Délais',            'score'=>$survey->score_delais],
                        ['label'=>'Communication',     'score'=>$survey->score_communication],
                        ['label'=>'Rapport qualité/prix','score'=>$survey->score_rapport_qualite_prix],
                    ] as $crit)
                    @if($crit['score'])
                    <div style="display:flex;align-items:center;gap:8px;font-size:11px;">
                        <span style="color:var(--text3);min-width:140px;">{{ $crit['label'] }}</span>
                        <div style="width:80px;height:4px;background:var(--surface2);border-radius:2px;overflow:hidden;">
                            <div style="height:100%;width:{{ ($crit['score']/5)*100 }}%;background:#22c55e;border-radius:2px;"></div>
                        </div>
                        <span style="color:var(--text2);font-weight:600;">{{ $crit['score'] }}/5</span>
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>
            @if($survey->commentaire)
            <div style="margin-top:10px;font-size:12px;color:var(--text2);font-style:italic;padding:10px 14px;background:var(--surface2);border-radius:8px;">
                "{{ $survey->commentaire }}"
            </div>
            @endif
        </div>
        <div style="font-size:10px;color:var(--text3);flex-shrink:0;">
            Évalué le {{ $survey->completed_at?->format('d/m/Y') }}
        </div>
    </div>
</div>
@else
<div style="background:rgba(250,184,11,.04);border:1px solid rgba(250,184,11,.2);border-radius:14px;padding:20px 24px;margin-bottom:20px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px;">
    <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(250,184,11,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </div>
        <div>
            <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:2px;">Donnez votre avis</div>
            <div style="font-size:12px;color:var(--text3);">Votre retour nous aide à améliorer nos services — 1 minute seulement.</div>
        </div>
    </div>
    <a href="{{ $survey->publicUrl() }}"
       style="display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:#fab80b;color:#000;font-weight:700;border-radius:10px;font-size:13px;text-decoration:none;white-space:nowrap;transition:opacity .15s;"
       onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        Évaluer cette campagne
    </a>
</div>
@endif
@endif

{{-- ══ LIEN VERS PIGES CAMPAGNE ══ --}}
@if($pigesVerif->isNotEmpty())
<div style="background:rgba(34,197,94,.04);border:1px solid rgba(34,197,94,.2);border-radius:14px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;background:rgba(34,197,94,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </div>
        <div>
            <div style="font-size:13px;font-weight:700;color:var(--text);">{{ $pigesVerif->flatten()->count() }} pige(s) d'affichage disponible(s)</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px;">Vérifiées et validées par notre équipe terrain</div>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('client.piges', ['campaign_id' => $campaign->id]) }}"
           style="padding:8px 18px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);border-radius:10px;font-size:12px;font-weight:700;color:#22c55e;text-decoration:none;white-space:nowrap;transition:all .15s;"
           onmouseover="this.style.background='rgba(34,197,94,.2)'" onmouseout="this.style.background='rgba(34,197,94,.12)'">
            Voir toutes →
        </a>
        <a href="{{ route('client.campagne.piges.zip', $campaign) }}"
           style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);border-radius:10px;font-size:12px;font-weight:700;color:#60a5fa;text-decoration:none;white-space:nowrap;transition:all .15s;"
           onmouseover="this.style.background='rgba(59,130,246,.2)'" onmouseout="this.style.background='rgba(59,130,246,.1)'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Télécharger ZIP
        </a>
    </div>
</div>
@endif

{{-- ══ LIGHTBOX PIGES ══ --}}
@if($pigesVerif->isNotEmpty())
<div id="lightbox-pige"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.93);backdrop-filter:blur(8px);z-index:9999;align-items:center;justify-content:center;"
     onclick="if(event.target===this)closeLightbox()">

    {{-- Barre haute --}}
    <div style="position:absolute;top:0;left:0;right:0;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(to bottom,rgba(0,0,0,.65),transparent);z-index:2;gap:10px;">
        <div style="min-width:0;">
            <div id="lb-ref" style="font-family:monospace;font-size:11px;font-weight:700;color:#22c55e;"></div>
            <div id="lb-name" style="font-size:13px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px;"></div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
            <span id="lb-counter" style="font-size:11px;color:rgba(255,255,255,.5);"></span>
            <a id="lb-download" href="#" download
               style="display:inline-flex;align-items:center;gap:5px;padding:6px 13px;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.4);border-radius:8px;font-size:11px;font-weight:700;color:#22c55e;text-decoration:none;transition:background .15s;white-space:nowrap;"
               onmouseover="this.style.background='rgba(34,197,94,.3)'" onmouseout="this.style.background='rgba(34,197,94,.15)'">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Télécharger
            </a>
            <button onclick="closeLightbox()"
                    style="width:34px;height:34px;border-radius:8px;background:rgba(255,255,255,.1);border:none;color:#fff;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background .15s;flex-shrink:0;"
                    onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.1)'">✕</button>
        </div>
    </div>

    {{-- Flèche gauche --}}
    <button id="lb-prev" onclick="lightboxPrev()"
            style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:10px;background:rgba(255,255,255,.1);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;z-index:2;"
            onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.1)'">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
    </button>

    {{-- Image --}}
    <img id="lb-img" src="" alt=""
         style="max-width:calc(100vw - 120px);max-height:calc(100vh - 100px);object-fit:contain;border-radius:10px;display:block;box-shadow:0 8px 40px rgba(0,0,0,.6);">

    {{-- Flèche droite --}}
    <button id="lb-next" onclick="lightboxNext()"
            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:10px;background:rgba(255,255,255,.1);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;z-index:2;"
            onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.1)'">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
    </button>

    {{-- Barre basse --}}
    <div style="position:absolute;bottom:0;left:0;right:0;padding:10px 16px;background:linear-gradient(to top,rgba(0,0,0,.6),transparent);text-align:center;">
        <div id="lb-date" style="font-size:11px;color:rgba(255,255,255,.5);"></div>
    </div>
</div>

<script>
const allPigesData    = @json($allPigesLightbox ?? []);
let   currentLbIndex  = 0;

function openLightbox(index) {
    if (!allPigesData.length) return;
    currentLbIndex = ((index % allPigesData.length) + allPigesData.length) % allPigesData.length;
    renderLightbox();
    const lb = document.getElementById('lightbox-pige');
    lb.style.display = 'flex';
    document.getElementById('lb-prev').style.display = allPigesData.length > 1 ? 'flex' : 'none';
    document.getElementById('lb-next').style.display = allPigesData.length > 1 ? 'flex' : 'none';
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox-pige').style.display = 'none';
    document.body.style.overflow = '';
}

function lightboxPrev() {
    currentLbIndex = (currentLbIndex - 1 + allPigesData.length) % allPigesData.length;
    renderLightbox();
}

function lightboxNext() {
    currentLbIndex = (currentLbIndex + 1) % allPigesData.length;
    renderLightbox();
}

function renderLightbox() {
    const p = allPigesData[currentLbIndex];
    if (!p) return;
    document.getElementById('lb-img').src          = p.photo_url;
    document.getElementById('lb-ref').textContent  = p.panel_ref;
    document.getElementById('lb-name').textContent = p.panel_name;
    document.getElementById('lb-date').textContent = 'Vérifié le ' + p.verified_at;
    document.getElementById('lb-counter').textContent = (currentLbIndex + 1) + ' / ' + allPigesData.length;
    document.getElementById('lb-download').href    = p.download_url;
}

document.addEventListener('keydown', function (e) {
    if (document.getElementById('lightbox-pige').style.display === 'none') return;
    if (e.key === 'ArrowLeft')  lightboxPrev();
    if (e.key === 'ArrowRight') lightboxNext();
    if (e.key === 'Escape')     closeLightbox();
});
</script>
@endif

@endsection