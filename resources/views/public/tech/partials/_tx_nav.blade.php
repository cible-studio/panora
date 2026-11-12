{{-- Refonte 2026-11-12 — barre du bas : tout est à un geste. Avant, la
     carte n'était qu'un bouton en fin de liste et les alertes ne
     s'ouvraient que par le « ? » quand il y avait du non-lu. --}}
<nav class="tx-nav" aria-label="Navigation">
    <a href="{{ route('tech.space', ['token' => $token]) }}" class="actif" aria-current="page">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M4 6h16M4 12h16M4 18h10"/></svg>Ma journée
    </a>
    <a href="{{ route('tech.space.map', ['token' => $token]) }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="m9 4-6 2v14l6-2 6 2 6-2V4l-6 2-6-2zM9 4v14M15 6v14"/></svg>Carte
    </a>
    <a href="{{ route('tech.space.piges', ['token' => $token]) }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>Mes photos
        @if(($pigesRejected ?? 0) > 0)<span class="tx-nav-pastille" title="Photos à refaire">{{ $pigesRejected }}</span>@endif
    </a>
    <button type="button" data-action="open-b3">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4l2-2zM10 21h4"/></svg>Alertes
        <span class="tx-nav-pastille" data-notifs-count hidden></span>
    </button>
</nav>
