{{-- Refonte 2026-11-12 — section « Maintenant » : la pose (ou l'axe) la
     plus urgente. UN seul gabarit, rendu par la page (_tx_journee) ET par
     la réponse d'une photo (TechSpaceController::poseSuivante), qui
     la remplace sans rechargement. Section à part entière : la recherche
     la masque comme une commune.
     Paramètre : $entree (TechSpaceController::entreesParAxe + completerAxes). --}}
<section class="tx-commune" data-tx-maintenant>
    <div class="tx-section-titre"><h3>Maintenant</h3><span>la plus urgente</span></div>
    <div class="tx-liste">
        @if($entree['type'] === 'axe')
            @include('public.tech.partials._tx_axe', ['entree' => $entree, 'maintenant' => true])
        @elseif($entree['task']->panel?->estAxe())
            @include('public.tech.partials._tx_axe_entier', ['task' => $entree['task'], 'maintenant' => true])
        @else
            @include('public.tech.partials._tx_maintenant', ['task' => $entree['task']])
        @endif
    </div>
</section>
