{{-- Refonte 2026-11-12 — carte d'un AXE (maquette validée) : ses pieds
     sur la route, une photo par pied.

     Chaque pied à faire est une vraie ligne de pose (.pose.pose-line +
     data-*) : le circuit photo existant (aperçu, GPS, compression, envoi
     hors ligne, raison si souci ouvert) s'applique tel quel, pied par pied.
     « Y aller » et « Je suis arrivé » s'appuient sur le premier pied à
     faire ; le serveur entraîne tous les pieds de l'axe
     (PoseTask::idsFreresDAxe). Les pieds déjà faits sont affichés pour
     dessiner toute la route, sans action.

     Paramètres : $entree (TechSpaceController::entreesParAxe + completerAxes). --}}
@php
    $axe   = $entree['axe'];
    $axes  = app(\App\Services\Axes\AxeService::class);
    $pieds = collect();
    foreach ($entree['tasks'] as $t) {
        $pieds->push(['n' => (int) ($t->panel?->pied_numero ?? 0), 'task' => $t, 'actif' => true,
                      'etat' => $t->latestRejectedPige ? 'refusee' : 'afaire']);
    }
    foreach ($entree['faites'] ?? [] as $f) {
        $pieds->push(['n' => (int) ($f['task']->panel?->pied_numero ?? 0), 'task' => $f['task'], 'actif' => false,
                      'etat' => ($f['pige']?->status === 'verifie') ? 'validee' : 'envoyee', 'pige' => $f['pige']]);
    }
    $pieds = $pieds->sortBy('n')->values();
    $total = $pieds->count();
    $faites = $pieds->whereIn('etat', ['envoyee', 'validee'])->count();
    $repTask = $entree['tasks']->first();            // porte « Y aller » / « Je suis arrivé »
    $suivantId = $repTask?->id;                      // le pied à faire en premier (mis en avant, comme refonte.js)
    $repData = \App\Support\TechCarte::donnees($repTask);
    // Même règle que l'arrêt sur la carte (TechSpaceController::statutEntree).
    $statut = ['en_cours' => 'place', 'en_route' => 'route'][\App\Http\Controllers\TechSpaceController::statutEntree($entree['tasks'])] ?? 'planifiee';
    $libelleStatut = ['place' => 'Sur place', 'route' => 'En route', 'planifiee' => 'Planifiée'][$statut];
    $photo = $axe->photos->first();
    $piedsDeLAxe = \App\Models\Panel::where('axe_id', $axe->id)->count();
    $ouvert = $statut !== 'planifiee' || !empty($maintenant);
@endphp
<article class="tx-axe {{ $ouvert ? 'ouvert' : '' }} {{ !empty($maintenant) ? 'tx-maintenant' : '' }}"
         data-axe-carte="{{ $axe->id }}-{{ $repTask->campaign_id }}" data-search="{{ $repData['search'] }}">
    <button type="button" class="tx-axe-tete" data-tx-axe-toggle aria-expanded="{{ $ouvert ? 'true' : 'false' }}">
        <span class="tx-vignette" @if($photo) style="background-image: url('{{ asset('storage/' . $photo->path) }}')" @endif></span>
        <span class="tx-ligne-inf">
            <span class="tx-ligne-l1"><span class="tx-ref">{{ $axe->reference }}</span><span class="tx-badge tx-badge-axe">Axe · {{ $total }} {{ $total > 1 ? 'pieds' : 'pied' }}</span></span>
            <span class="tx-axe-nom">{{ $axes->nomCourt($axe->name) }}</span>
            <span class="tx-ligne-sous">{{ $total }} sur {{ $piedsDeLAxe }} pieds · {{ $repTask->campaign?->name }}</span>
        </span>
        <span class="tx-pill tx-pill-{{ $statut }}" data-tx-axe-statut>{{ $libelleStatut }}</span>
    </button>

    <div class="tx-axe-avance">
        <span><b data-tx-axe-compte>{{ $faites }} / {{ $total }}</b> pieds photographiés</span>
        <span class="tx-segments" aria-hidden="true">
            @foreach($pieds as $p)<i class="{{ $p['etat'] }}" data-tx-segment="{{ $p['task']->id }}"></i>@endforeach
        </span>
    </div>

    @if($entree['tasks']->isNotEmpty())
    {{-- Actions de l'axe : portées par le premier pied à faire (data-task-id). --}}
    <div class="tx-axe-actions" {!! \App\Support\TechCarte::attributs($repTask, $repData) !!}>
        <span class="pose-name" hidden>{{ $axes->nomCourt($axe->name) }}</span>
        <a class="tx-btn" data-go-maps href="{{ $repData['goUrl'] }}" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>Y aller</a>
        <button type="button" class="tx-btn tx-btn-bleu" data-tx-arrive {{ $statut === 'place' ? 'disabled' : '' }}>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>{{ $statut === 'place' ? 'Sur place' : 'Je suis arrivé' }}</button>
    </div>
    @endif

    <div class="tx-axe-route">
        <div class="tx-voie" aria-label="Les pieds dans l'ordre du terrain">
            @foreach($pieds as $p)
                <span class="tx-jalon {{ $p['etat'] }} {{ $p['actif'] && $p['task']->id === $suivantId ? 'suivant' : '' }}" data-tx-jalon="{{ $p['task']->id }}"><span>{{ $p['etat'] === 'validee' ? '✓' : $p['n'] }}</span><small>P{{ str_pad((string) $p['n'], 2, '0', STR_PAD_LEFT) }}</small></span>
            @endforeach
        </div>
        <div class="tx-pieds">
            @foreach($pieds as $p)
                @if($p['actif'])
                    @php $dp = \App\Support\TechCarte::donnees($p['task']); @endphp
                    <div class="pose pose-line tx-pied {{ $p['etat'] }} {{ $p['task']->id === $suivantId ? 'suivant' : '' }} {{ $dp['hasProblem'] ? 'has-problem' : '' }} {{ $dp['rejPige'] ? 'has-reject' : '' }}"
                         {!! \App\Support\TechCarte::attributs($p['task'], $dp) !!} data-tx-pied="{{ $p['n'] }}">
                        {{-- .pose-thumb (cachée) : la photo de l'axe, lue par le tiroir. --}}
                        <span class="pose-thumb" hidden @if($dp['photoUrl']) style="background-image: url('{{ $dp['photoUrl'] }}')" @endif></span>
                        <span class="tx-pied-num">{{ $p['n'] }}</span>
                        <span class="tx-pied-lib">
                            <strong>Pied {{ $p['n'] }}</strong>
                            <span class="pose-ref">{{ $p['task']->panel?->reference }}</span>
                            <span class="pose-name" hidden>{{ $p['task']->panel?->name }}</span>
                            @if($dp['rejPige'])<span class="tx-ko">Refusée · {{ $dp['rejPige']->rejection_reason ?: 'à refaire' }}</span>@endif
                            @if($dp['hasProblem'])<span class="tx-ko">Souci signalé : {{ $dp['problemLabel'] }}</span>@endif
                        </span>
                        <label class="tx-mini {{ $dp['rejPige'] ? 'tx-mini-refaire' : 'tx-mini-photo' }}">
                            <input type="file" accept="image/*" data-photo-input>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>{{ $dp['rejPige'] ? 'Refaire' : 'Photo' }}
                        </label>
                    </div>
                @else
                    <div class="tx-pied tx-pied-fait {{ $p['etat'] }}">
                        <span class="tx-pied-num tx-pied-num-ok">✓</span>
                        <span class="tx-pied-lib">
                            <strong>Pied {{ $p['n'] }}</strong>
                            <span class="{{ $p['etat'] === 'validee' ? 'tx-ok' : 'tx-att' }}">
                                {{ $p['etat'] === 'validee' ? 'Validée' : 'Envoyée, à valider' }}{{ !empty($p['pige']?->taken_at) ? ' · ' . $p['pige']->taken_at->format('H\hi') : '' }}
                            </span>
                        </span>
                        <span></span>
                    </div>
                @endif
            @endforeach
        </div>
        <p class="tx-aide">ⓘ Tant qu'un pied n'a pas sa propre position, sa photo est contrôlée sur celle de l'axe (± 500 m).</p>
    </div>
</article>
