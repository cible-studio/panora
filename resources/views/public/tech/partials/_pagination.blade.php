{{-- Pagination de l'espace technicien.

     Contexte (feedback user 2026-09-22) : la vue tech-piges utilisait
     $piges->links() sans argument → Laravel servait son thème Tailwind
     par défaut. Or l'espace tech est une page standalone avec son
     propre CSS (tech-app.css + <style> inline), SANS Tailwind chargé.
     Résultat : les chevrons SVG du thème s'affichaient à leur taille
     naturelle (énormes), les classes utilitaires ne faisaient rien, et
     le compteur restait en anglais (« Showing 1 to 30 of 59 results »).

     Cette vue produit une structure PLATE (uniquement <a> et <span>
     enfants directs) qui correspond au CSS .pagination déjà présent
     dans tech-piges.blade.php. Aucun SVG, aucune dépendance framework.

     Mobile-first : le tech est sur Android Go la plupart du temps, on
     limite le nombre de pages affichées pour éviter le débordement. --}}

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigation des pages">

        {{-- Précédent --}}
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" style="opacity:.4">‹</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Page précédente">‹</a>
        @endif

        {{-- Numéros de page — fenêtre glissante autour de la page courante.
             On n'utilise pas $elements (qui peut contenir des '...' non
             cliquables mal rendus) : on calcule une fenêtre de 5 pages max
             pour rester lisible sur petit écran. --}}
        @php
            $current = $paginator->currentPage();
            $last    = $paginator->lastPage();
            $from    = max(1, $current - 2);
            $to      = min($last, $from + 4);
            $from    = max(1, $to - 4); // recentre si on est en fin de liste
        @endphp

        @if ($from > 1)
            <a href="{{ $paginator->url(1) }}">1</a>
            @if ($from > 2)
                <span aria-hidden="true" style="border:none;background:none">…</span>
            @endif
        @endif

        @for ($page = $from; $page <= $to; $page++)
            @if ($page == $current)
                <span aria-current="page">{{ $page }}</span>
            @else
                <a href="{{ $paginator->url($page) }}">{{ $page }}</a>
            @endif
        @endfor

        @if ($to < $last)
            @if ($to < $last - 1)
                <span aria-hidden="true" style="border:none;background:none">…</span>
            @endif
            <a href="{{ $paginator->url($last) }}">{{ $last }}</a>
        @endif

        {{-- Suivant --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Page suivante">›</a>
        @else
            <span aria-disabled="true" style="opacity:.4">›</span>
        @endif
    </nav>

    {{-- Compteur en français (le thème par défaut est en anglais). --}}
    <div style="width:100%;text-align:center;margin-top:8px;font-size:11.5px;color:var(--text3)">
        {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} sur {{ $paginator->total() }} photos
    </div>
@endif
