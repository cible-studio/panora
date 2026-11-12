{{-- Refonte 2026-11-12 — tâche d'un AXE ENTIER (contrat signé avant le
     découpage : BEM, MOOV, LADILI). Choix validé (4b) : le technicien
     envoie une photo par pied sur la même tâche, et la ferme lui-même avec
     « Terminé ». Une photo ne la clôt plus (TechSpaceController::uploadPhoto,
     PigeObserver::closePoseTask). --}}
@php
    $d = \App\Support\TechCarte::donnees($task);
    $panel = $task->panel;
    $piges = \App\Models\Pige::where('pose_task_id', $task->id)->orderByDesc('taken_at')->get(['id', 'photo_thumb', 'photo_path']);
    $nbPieds = \App\Models\Panel::where('axe_id', $panel->id)->count()
        ?: (app(\App\Services\Axes\AxeService::class)->lireStructure($panel->name)['pieds'] ?? 0);
    $statut = (string) $task->status;
    $libelle = ['en_cours' => 'Sur place', 'en_route' => 'En route'][$statut] ?? 'Planifiée';
@endphp
<div class="pose pose-line tx-axe tx-axe-entier ouvert {{ $d['hasProblem'] ? 'has-problem' : '' }}"
     {!! \App\Support\TechCarte::attributs($task, $d) !!}
     data-axe-entier="1" data-pieds-total="{{ $nbPieds }}" data-photos="{{ $piges->count() }}">
    <div class="tx-axe-tete">
        <span class="pose-thumb tx-vignette" @if($d['photoUrl']) style="background-image: url('{{ $d['photoUrl'] }}')" @endif></span>
        <span class="tx-ligne-inf">
            <span class="tx-ligne-l1"><span class="pose-ref">{{ $panel->reference }}</span><span class="tx-badge tx-badge-axe">Axe entier · {{ $nbPieds }} pieds</span></span>
            <span class="pose-name tx-axe-nom">{{ app(\App\Services\Axes\AxeService::class)->nomCourt($panel->name) }}</span>
            <span class="tx-ligne-sous">{{ $task->campaign?->name }}</span>
        </span>
        <span class="pose-dot tx-pill tx-pill-{{ $statut === 'en_cours' ? 'place' : ($statut === 'en_route' ? 'route' : 'planifiee') }}">{{ $libelle }}</span>
    </div>
    <div class="tx-ancien">
        <strong>Contrat signé avant le découpage</strong>
        Envoie une photo par pied. La tâche reste ouverte : tu la fermes toi-même avec « Terminé » quand tous les pieds sont faits.
    </div>
    <div class="tx-axe-avance"><span><b data-tx-photos-compte>{{ $piges->count() }}</b> photo(s) envoyée(s) · {{ $nbPieds }} pieds</span></div>
    @if($piges->isNotEmpty())
        <div class="tx-photos-envoyees">
            @foreach($piges->take(12) as $pg)
                <i style="background-image: url('{{ \Illuminate\Support\Facades\Storage::url($pg->photo_thumb ?: $pg->photo_path) }}')"></i>
            @endforeach
        </div>
    @endif
    <div class="tx-axe-actions">
        <label class="tx-btn tx-btn-orange">
            <input type="file" accept="image/*" data-photo-input>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>Photo
        </label>
        <button type="button" class="tx-btn tx-btn-vert" data-tx-terminer>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>Terminé
        </button>
    </div>
    <p class="tx-aide" data-tx-terminer-aide>ⓘ « Terminé » demande une confirmation s'il manque des photos.</p>
</div>
