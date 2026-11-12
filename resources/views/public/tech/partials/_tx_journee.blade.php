{{-- Refonte 2026-11-12 — « Ma journée » (maquette validée) :
       · recherche sur les poses affichées (data-search, refonte.js) ;
       · « Maintenant » : la pose (ou l'axe) la plus urgente ;
       · les poses par commune, avec une carte par axe ;
       · « Déjà faites » repliée (partial existant).
     Paramètres : $itemsByCommune, $doneByCommune, $nextTask. --}}
@php
    // L'entrée qui contient la pose « Maintenant » : affichée en tête, pas
    // répétée dans sa commune.
    $nextId = $nextTask?->id;
    $entreeMaintenant = null;
    foreach ($itemsByCommune as $entrees) {
        foreach ($entrees as $e) {
            if ($nextId && $e['tasks']->contains('id', $nextId)) { $entreeMaintenant = $e; break 2; }
        }
    }
@endphp

<label class="tx-chercher">
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
    <input type="search" id="tx-recherche" placeholder="Chercher un panneau, une rue, une campagne" autocomplete="off">
</label>
<p class="tx-vide-recherche" id="tx-vide-recherche" hidden>Aucune pose ne correspond à ta recherche.</p>

@if($entreeMaintenant)
    {{-- Section à part entière : la recherche la masque comme une commune. --}}
    <section class="tx-commune" data-tx-maintenant>
        <div class="tx-section-titre"><h3>Maintenant</h3><span>la plus urgente</span></div>
        <div class="tx-liste">
            @if($entreeMaintenant['type'] === 'axe')
                @include('public.tech.partials._tx_axe', ['entree' => $entreeMaintenant, 'maintenant' => true])
            @elseif($entreeMaintenant['task']->panel?->estAxe())
                @include('public.tech.partials._tx_axe_entier', ['task' => $entreeMaintenant['task']])
            @else
                @include('public.tech.partials._tx_maintenant', ['task' => $entreeMaintenant['task']])
            @endif
        </div>
    </section>
@endif

@foreach($itemsByCommune as $commune => $entrees)
    @php
        $reste = array_values(array_filter($entrees, fn ($e) => $e !== $entreeMaintenant));
        $actives = collect($entrees)->sum(fn ($e) => $e['tasks']->count());
        $faites = (int) ($doneByCommune[$commune] ?? 0);
    @endphp
    @continue(empty($reste))
    <section class="tx-commune" data-commune="{{ $commune }}">
        <div class="tx-section-titre"><h3>{{ $commune }}</h3><span data-tx-commune-compte data-faites="{{ $faites }}" data-total="{{ $faites + $actives }}">{{ $faites }} / {{ $faites + $actives }} faites</span></div>
        <div class="tx-liste">
            @foreach($reste as $e)
                @if($e['type'] === 'axe')
                    @include('public.tech.partials._tx_axe', ['entree' => $e])
                @elseif($e['task']->panel?->estAxe())
                    @include('public.tech.partials._tx_axe_entier', ['task' => $e['task']])
                @else
                    @include('public.tech.partials._tx_pose_ligne', ['task' => $e['task']])
                @endif
            @endforeach
        </div>
    </section>
@endforeach
