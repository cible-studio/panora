{{-- Refonte de l'espace technicien (2026-11-12, maquette validée) —
     en-tête de « Ma journée ».
       · l'initiale ouvre le menu (aide, chef, feuille papier, préférences) —
         data-action="open-tx-menu" (refonte.js, feuille #tx-menu) ;
       · la cloche ouvre les alertes — data-action="open-b3" (sm2c.js) ;
       · la barre compte VRAIMENT la journée (jourFaites / jourTotal).
     Remplace _topbar + _progress_bar sur cette page. --}}
@php
    $prenom = \App\Support\TechCarte::prenom($tech);
    $initiale = mb_strtoupper(mb_substr($prenom, 0, 1));
    $jourFaites = (int) ($jourFaites ?? 0);
    $jourTotal  = (int) ($jourTotal ?? 0);
    $pct = $jourTotal > 0 ? (int) round($jourFaites / $jourTotal * 100) : 0;
    // Même message que les réponses AJAX (TechCarte::journee).
    $message = \App\Support\TechCarte::messageDuJour($jourFaites, $jourTotal, $prenom);
    $zones = collect($zonesTodayList ?? [])->take(3);
    $dateDuJour = ucfirst(\Carbon\Carbon::today()->locale('fr')->isoFormat('dddd D MMMM'));
@endphp
<header class="tx-tete">
    <div class="tx-tete-l">
        <button type="button" class="tx-avatar" data-action="open-tx-menu" aria-label="Menu" aria-haspopup="dialog">{{ $initiale }}</button>
        <div class="tx-salut">
            <strong>Bonjour {{ $prenom }}</strong>
            <span>{{ $dateDuJour }}@if($zones->isNotEmpty()) · {{ $zones->implode(', ') }}@endif</span>
        </div>
        <span class="live-indicator" data-live-indicator hidden>live</span>
        {{-- Aide (T8) : l'ancien « ? » jaune vivait dans _topbar. --}}
        <button type="button" class="tx-cloche" data-action="open-help" aria-label="Besoin d'aide ?" title="Besoin d'aide ?">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M9.6 9.4a2.5 2.5 0 1 1 3.4 2.3c-.6.3-1 .8-1 1.5v.4"/><circle cx="12" cy="17" r="1.1" fill="currentColor"/></svg>
        </button>
        <button type="button" class="tx-cloche" data-action="open-b3" aria-label="Alertes">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4l2-2zM10 21h4"/></svg>
            <i data-notifs-dot hidden></i>
        </button>
    </div>
    {{-- data-jour-* : lus et tenus à jour par refonte.js (photo envoyée sans
         rechargement) et par l'écran T4 « Bravo » (upload.js). --}}
    <div class="tx-jour" data-jour-faites="{{ $jourFaites }}" data-jour-total="{{ $jourTotal }}" data-prenom="{{ $prenom }}">
        <div class="tx-jour-t"><span data-tx-jour-message>{{ $message }}</span><b data-tx-jour-compte>{{ $jourFaites }} / {{ $jourTotal }} aujourd'hui</b></div>
        <div class="tx-barre"><i style="width: {{ $pct }}%"></i></div>
    </div>
</header>
