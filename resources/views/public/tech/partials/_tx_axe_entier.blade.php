{{-- Refonte 2026-11-12 — tâche d'un AXE ENTIER (contrat signé avant le
     découpage : BEM, MOOV, LADILI). Choix validé (4b) : le technicien
     envoie une photo par pied sur la même tâche, et la ferme lui-même avec
     « Terminé ». Une photo ne la clôt plus (TechSpaceController::uploadPhoto,
     PigeObserver::closePoseTask).

     2026-11-13 (retours terrain) : MÊME squelette et mêmes gestes que la
     carte d'un axe (_tx_axe) —
       · toucher l'en-tête déplie / replie (il n'ouvre plus la fiche) ;
       · « N / 9 photos » et ses segments ;
       · « Y aller » et « Je suis arrivé » toujours visibles ;
       · dépliée : « Photo », « Terminé », les photos envoyées, l'aide, et
         « Fiche » (le tiroir, pour signaler un souci — pose-drawer.js).
     Dépliée d'office sur place ou en « Maintenant ». La pastille ne porte
     plus .pose-dot (elle l'écrasait en rond).

     Paramètres : $task ; $maintenant (carte en tête de « Ma journée »). --}}
@php
    $d = \App\Support\TechCarte::donnees($task);
    $panel = $task->panel;
    $axes = app(\App\Services\Axes\AxeService::class);
    $piges = \App\Models\Pige::where('pose_task_id', $task->id)->orderByDesc('taken_at')->get(['id', 'photo_thumb', 'photo_path']);
    $nbPieds = \App\Models\Panel::where('axe_id', $panel->id)->count()
        ?: ($axes->lireStructure($panel->name)['pieds'] ?? 0);
    $nbPhotos = $piges->count();
    $statut = (string) $task->status;
    $classe = ['en_cours' => 'place', 'en_route' => 'route'][$statut] ?? 'planifiee';
    $libelle = ['en_cours' => 'Sur place', 'en_route' => 'En route'][$statut] ?? 'Planifiée';
    $surPlace = $statut === 'en_cours';
    $ouvert = !empty($maintenant) || $surPlace;
@endphp
<article class="pose pose-line tx-axe tx-axe-entier {{ $ouvert ? 'ouvert' : '' }} {{ !empty($maintenant) ? 'tx-maintenant' : '' }} {{ $d['hasProblem'] ? 'has-problem' : '' }}"
     {!! \App\Support\TechCarte::attributs($task, $d) !!}
     data-axe-entier="1" data-pieds-total="{{ $nbPieds }}" data-photos="{{ $nbPhotos }}">
    <button type="button" class="tx-axe-tete" data-tx-axe-toggle aria-expanded="{{ $ouvert ? 'true' : 'false' }}">
        <span class="pose-thumb tx-vignette" @if($d['photoUrl']) style="background-image: url('{{ $d['photoUrl'] }}')" @endif></span>
        <span class="tx-ligne-inf">
            <span class="tx-ligne-l1"><span class="pose-ref tx-ref">{{ $panel->reference }}</span><span class="tx-badge tx-badge-axe">Axe entier · {{ $nbPieds }} pieds</span></span>
            <span class="pose-name tx-axe-nom">{{ $axes->nomCourt($panel->name) }}</span>
            <span class="tx-ligne-sous">{{ $task->campaign?->name }}</span>
        </span>
        <span class="tx-pill tx-pill-{{ $classe }}" data-tx-axe-statut>{{ $libelle }}</span>
    </button>

    <div class="tx-axe-avance">
        <span><b data-tx-photos-compte>{{ $nbPhotos }}</b> / {{ $nbPieds }} photos</span>
        <span class="tx-segments" data-tx-axe-segments aria-hidden="true">
            @for($i = 0; $i < max(1, $nbPieds); $i++)<i class="{{ $i < $nbPhotos ? 'envoyee' : '' }}"></i>@endfor
        </span>
    </div>

    <div class="tx-axe-actions">
        <a class="tx-btn" data-go-maps href="{{ $d['goUrl'] }}" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>Y aller</a>
        <button type="button" class="tx-btn tx-btn-bleu" data-tx-arrive {{ $surPlace ? 'disabled' : '' }}>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>{{ $surPlace ? 'Sur place' : 'Je suis arrivé' }}</button>
    </div>

    <div class="tx-axe-route">
        <div class="tx-axe-actions">
            <label class="tx-btn tx-btn-orange">
                <input type="file" accept="image/*" data-photo-input>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>Photo
            </label>
            <button type="button" class="tx-btn tx-btn-vert" data-tx-terminer>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>Terminé
            </button>
        </div>
        @if($piges->isNotEmpty())
            <div class="tx-photos-envoyees">
                @foreach($piges->take(12) as $pg)
                    <i style="background-image: url('{{ \Illuminate\Support\Facades\Storage::url($pg->photo_thumb ?: $pg->photo_path) }}')"></i>
                @endforeach
            </div>
        @endif
        <p class="tx-aide" data-tx-terminer-aide>ⓘ Contrat signé avant le découpage : une photo par pied, sur cette même pose. Touche « Terminé » quand tous les pieds sont faits.
            <button type="button" class="tx-lien" data-tx-fiche>Fiche · signaler un souci</button></p>
    </div>
</article>
