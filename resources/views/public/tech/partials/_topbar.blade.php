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

    {{-- Grille KPI — 4 cartes cliquables (filtre la liste en dessous).
         Polling 20s met à jour data-kpi-value en douceur. État actif
         marqué par aria-pressed + classe 'is-active'. --}}
    <div class="kpi-grid" role="group" aria-label="Filtres rapides">
        <button type="button" class="kpi-card kpi-todo is-active" data-kpi-filter="all" aria-pressed="true">
            <div class="kpi-label">À faire</div>
            <div class="kpi-value" data-kpi-value="totalActive" data-total-active>{{ $totalActive }}</div>
            <div class="kpi-sub">panneaux à poser</div>
        </button>
        <button type="button" class="kpi-card kpi-today" data-kpi-filter="today" aria-pressed="false">
            <div class="kpi-label">Aujourd'hui</div>
            <div class="kpi-value" data-kpi-value="activeToday">{{ $activeToday ?? 0 }}</div>
            <div class="kpi-sub">à poser aujourd'hui @if(($doneToday ?? 0) > 0)· <strong data-done-today>{{ $doneToday }}</strong> fait{{ $doneToday > 1 ? 's' : '' }} ✓@endif</div>
        </button>
        <a href="{{ route('tech.space.piges', $token) }}" class="kpi-card kpi-piges" data-kpi-link>
            <div class="kpi-label">Photos</div>
            <div class="kpi-value" data-kpi-value="pigesSentToday">{{ $pigesSentToday ?? 0 }}</div>
            <div class="kpi-sub">envoyée{{ ($pigesSentToday ?? 0) > 1 ? 's' : '' }} aujourd'hui</div>
        </a>
        <button type="button" class="kpi-card kpi-zones" data-kpi-action="scroll-zones">
            <div class="kpi-label">Zones</div>
            <div class="kpi-value" data-kpi-value="zonesTodayCount">{{ $zonesTodayCount ?? 0 }}</div>
            <div class="kpi-sub">touche pour aller voir ↓</div>
        </button>
    </div>

    {{-- SM2a Lot 1.1 — La progression a été déplacée dans son propre partial
         _progress_bar.blade.php (barre simple + message motivant T1 §3.2).
         L'ancien `.progress-staged` (paliers 10/25/50/75/100) a été retiré. --}}
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
