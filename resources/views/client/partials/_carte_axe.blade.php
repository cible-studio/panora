{{-- Portail client — carte d'un AXE (2026-11-12, décision du 12/11) : une
     carte pour l'axe, ses pieds dedans, chacun avec sa pose et sa photo
     validée. Les compteurs restent en pieds (« 3/5 pieds pigés »).
     Même habillage que la carte d'un panneau de campagne-detail.

     Paramètres : $entree (AxeService::grouperParAxe : axe + pieds),
     $poses (poses réalisées par panneau), $pigesVerif (photos validées par
     panneau), $pigeIndexMap (index de la visionneuse). --}}
@php
    $axe   = $entree['axe'];
    $pieds = $entree['items'];
    $nb    = $pieds->count();
    $photoAxe = $axe->photos->first();
    $nbPoses = $pieds->filter(fn ($p) => isset($poses[$p->id]))->count();
    $nbPiges = $pieds->filter(fn ($p) => ($pigesVerif[$p->id] ?? collect())->isNotEmpty())->count();
    $complet = $nb > 0 && $nbPiges === $nb;
    $borderClr = $complet ? 'rgba(34,197,94,.35)' : ($nbPoses > 0 ? 'rgba(139,92,246,.35)' : 'var(--border)');
    $nomAxe = app(\App\Services\Axes\AxeService::class)->nomCourt($axe->name);
    $commune = $pieds->first()?->commune?->name ?? $axe->commune?->name;
    $format = $pieds->first()?->format;
@endphp
<div style="background:var(--surface);border:1px solid {{ $borderClr }};border-radius:14px;overflow:hidden;">
    <div style="position:relative;height:160px;background:var(--surface2);overflow:hidden;">
        @if($photoAxe)
            <img src="{{ asset('storage/' . ltrim($photoAxe->path, '/')) }}" style="width:100%;height:100%;object-fit:cover;" alt="{{ $axe->reference }}" loading="lazy">
        @else
            <div style="display:flex;align-items:center;justify-content:center;height:100%;">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="1.3" style="opacity:.3;"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            </div>
        @endif
        <div style="position:absolute;top:8px;left:8px;display:flex;gap:4px;flex-wrap:wrap;">
            <span style="padding:2px 7px;border-radius:20px;font-size:9px;font-weight:700;background:rgba(17,24,39,.8);color:#fff;">⛓ Axe · {{ $nb }} pied{{ $nb > 1 ? 's' : '' }}</span>
            @if($nbPoses > 0)
                <span style="padding:2px 7px;border-radius:20px;font-size:9px;font-weight:700;background:rgba(139,92,246,.85);color:#fff;">✓ {{ $nbPoses }}/{{ $nb }} posés</span>
            @endif
            @if($nbPiges > 0)
                <span style="padding:2px 7px;border-radius:20px;font-size:9px;font-weight:700;background:rgba(34,197,94,.85);color:#fff;">📸 {{ $nbPiges }}/{{ $nb }} pigés</span>
            @endif
        </div>
        <div style="position:absolute;bottom:0;left:0;right:0;padding:6px 10px;background:linear-gradient(transparent,rgba(0,0,0,.7));font-family:monospace;font-size:11px;font-weight:700;color:#fff;">
            {{ $axe->reference }}
        </div>
    </div>

    <div style="padding:12px 14px;display:grid;gap:8px;">
        <div>
            <div style="font-size:13px;font-weight:700;color:var(--text);">{{ $nomAxe ?: $axe->reference }}</div>
            <div style="font-size:11px;color:var(--text3);">
                {{ $commune ?? '' }}@if($format) · {{ $format->name }} par pied @endif
            </div>
        </div>

        {{-- Les pieds, dans l'ordre du terrain --}}
        <div style="display:grid;gap:4px;">
            @foreach($pieds as $pied)
                @php
                    $photosPied = $pigesVerif[$pied->id] ?? collect();
                    $derniere = $photosPied->first();
                    $pose = isset($poses[$pied->id]);
                @endphp
                <div style="display:flex;align-items:center;gap:8px;padding:5px 8px;border-radius:9px;background:var(--surface2);">
                    <span style="font-size:10px;font-weight:800;color:var(--text3);width:44px;flex-shrink:0;">Pied {{ $pied->pied_numero }}</span>
                    <span style="font-family:monospace;font-size:10.5px;color:var(--text2);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $pied->reference }}</span>
                    @if($derniere)
                        <button type="button" onclick="openLightbox({{ $pigeIndexMap[$derniere->id] ?? 0 }})"
                                title="Photo validée le {{ $derniere->verified_at?->format('d/m/Y') }} — cliquer pour agrandir"
                                style="width:34px;height:34px;border-radius:6px;overflow:hidden;border:1.5px solid rgba(34,197,94,.4);padding:0;background:none;cursor:pointer;flex-shrink:0;">
                            <img src="{{ Storage::url($derniere->photo_thumb ?? $derniere->photo_path) }}" style="width:100%;height:100%;object-fit:cover;" loading="lazy" alt="Pied {{ $pied->pied_numero }}">
                        </button>
                    @elseif($pose)
                        <span style="font-size:9.5px;font-weight:700;color:#8b5cf6;flex-shrink:0;">Posé · photo en validation</span>
                    @else
                        <span style="font-size:9.5px;font-weight:700;color:var(--text3);flex-shrink:0;">À poser</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
