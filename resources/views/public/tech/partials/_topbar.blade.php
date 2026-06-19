{{-- _topbar.blade.php — Phase 2 SM1 (rendu pixel-identique).
     Header sticky + chip Mes photos + hero + 4 KPI cards cliquables
     + barre de progression à paliers + récap zones du jour.
     Variables consommées : $tech, $token, $pigesRejected, $pigesTotal,
       $totalActive, $activeToday, $pigesSentToday, $zonesTodayCount,
       $totalAssigned, $totalDone, $progressPct, $zonesTodayList,
       $doneToday (utilisée dans le sub-label Aujourd'hui). --}}
@php
    $initial = mb_strtoupper(mb_substr($tech->name, 0, 1));
    $zonesLabel = $zonesTodayCount > 0
        ? $zonesTodayCount . ' zone' . ($zonesTodayCount > 1 ? 's' : '')
        : 'Aucune zone';
    $heroSub = $totalActive > 0
        ? "$totalActive panneau" . ($totalActive > 1 ? 'x' : '') . " à poser · $zonesLabel"
        : 'Rien à poser — bravo, tu es à jour !';
@endphp
<div class="header">

    <div class="header-top">
        <img src="{{ asset('images/panora.png') }}" alt="Panora by CIBLE" class="brand-logo">
        <span class="live-indicator" data-live-indicator>live</span>
        <div style="flex:1"></div>
        {{-- Bloc droit : label "Espace Technicien" + chip "Mes piges" côte à côte. --}}
        <span class="header-kicker" style="flex:0 0 auto;text-align:right;line-height:1.15">Espace<br>Technicien</span>
        <a href="{{ route('tech.space.piges', $token) }}"
           class="header-chip {{ ($pigesRejected ?? 0) > 0 ? 'has-warn' : '' }}"
           aria-label="Mes photos">
            <span aria-hidden="true">📸</span><span class="chip-text">Mes photos</span>
            @if(($pigesTotal ?? 0) > 0)
                <span class="chip-badge" data-piges-chip-badge>
                    {{ ($pigesRejected ?? 0) > 0 ? $pigesRejected : $pigesTotal }}
                </span>
            @endif
        </a>
        {{-- SM2a T1 §3.1 — Bouton aide rond jaune (consommé par features/help.js
             Phase 5 : tap → modale T8 "Besoin d'aide ?"). Pour l'instant pas
             d'action JS branchée — le bouton apparaît mais ne fait rien.
             Cohérence accessibilité : aria-label + role button. --}}
        <button type="button"
                class="sm2-help-btn"
                data-action="open-help"
                aria-label="Ouvrir l'aide"
                title="Besoin d'aide ?">?</button>
    </div>

    <div class="hero">
        <div class="hero-avatar">{{ $initial }}</div>
        <div class="hero-text">
            <h1>Bonjour {{ $tech->name }}</h1>
            <div class="hero-subline">{{ $heroSub }}</div>
        </div>
    </div>

    {{-- SM2a Lot 1.1 — Barre de progression simple (T1 §3.2).
         Hotfix 2026-06-19 : la grille des 4 KPIs (À FAIRE / AUJOURD'HUI /
         PHOTOS / ZONES) a été retirée. La spec demande UNIQUEMENT la barre
         verte + compteur "X/Y" + message motivant. Les compteurs détaillés
         restent disponibles dans les rapports / le polling met à jour la
         barre de progression et le compteur via data-progress-* (cf.
         features/heartbeat.js). --}}
    @include('public.tech.partials._progress_bar')

    {{-- Récap zones de la journée (visible si au moins une zone) --}}
    @if(!empty($zonesTodayList))
    <div class="today-recap">
        <span style="font-weight:700;color:var(--text2)">📍 Tes zones du jour :</span>
        @foreach(array_slice($zonesTodayList, 0, 4) as $zone)
            <span class="zone-pill">{{ $zone }}</span>
        @endforeach
        @if(count($zonesTodayList) > 4)
            <span style="color:var(--text3);font-size:11px">+{{ count($zonesTodayList) - 4 }}</span>
        @endif
    </div>
    @endif
</div>
