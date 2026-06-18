<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mes poses — {{ $tech->name }} · Panora</title>

    {{-- Favicon Panora (aligné sur le layout admin pour cohérence onglet) --}}
    <link rel="icon" href="{{ asset('images/faviconl.png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('images/favicond.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="shortcut icon" href="{{ asset('images/faviconl.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- PWA : manifeste + theme color + apple-touch-icon. Le Service Worker
         est enregistré côté JS plus bas (lignes ~2824 dans le <script>). --}}
    @include('public.tech.partials._pwa_install')

    {{-- Select2 v4 — source AJAX paginée, indispensable pour scaler la
         recherche au-delà de 200+ poses (le SSR ne rend que les 200 plus
         urgentes — la recherche sert de point d'entrée pour le reste). --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">

    @include('public.tech.partials._styles')
</head>
<body>

@include('public.tech.partials._topbar', [
    'tech'            => $tech,
    'token'           => $token,
    'pigesRejected'   => $pigesRejected ?? 0,
    'pigesTotal'      => $pigesTotal ?? 0,
    'totalActive'     => $totalActive ?? 0,
    'activeToday'     => $activeToday ?? 0,
    'pigesSentToday'  => $pigesSentToday ?? 0,
    'doneToday'       => $doneToday ?? 0,
    'zonesTodayCount' => $zonesTodayCount ?? 0,
    'totalAssigned'   => $totalAssigned ?? 0,
    'totalDone'       => $totalDone ?? 0,
    'progressPct'     => $progressPct ?? 0,
    'zonesTodayList'  => $zonesTodayList ?? [],
])

{{-- Bandeau live : nouvelle pose assignée pendant que tu es sur la page --}}
@include('public.tech.partials._banner_new_task')

{{-- ═══ BARRE DE CONTRÔLES STICKY ═══
     - Select2 recherche AJAX paginée (source : tech.space.search) →
       trouve n'importe quelle pose même hors SSR. Le tech sélectionne,
       on scroll vers la carte (ou on la matérialise si elle n'est pas
       dans la liste rendue).
     - Bouton "🧭 Distance" : géolocalise le tech et trie les cards par
       distance haversine croissante (calcul JS local sur lat/lng déjà
       en data-attr).
     - Bouton "🖨 Feuille de route" : lien vers /poses/route-sheet (vue
       imprimable A4 avec toutes les poses).
--}}
@if($totalActive > 0)
    @include('public.tech.partials._controls_bar', ['token' => $token])
@endif

{{-- ═══ SOMMAIRE ZONES STICKY (TOC) ═══
     Une rangée scrollable horizontalement de chips zones, chacun
     avec mini-progress + compteur. Tap → scroll smooth vers la
     section commune. Indispensable au-delà de 4-5 zones (sans ça
     le tech perd l'orientation dans une longue liste).
--}}
@if(!empty($allZones) && count($allZones) > 1)
<div class="zones-toc">
    <div class="zones-toc-inner">
        @foreach($allZones as $z)
            @php
                $zid = 'zone-' . md5($z['name']);
                $hasOverdue = false; // calculé via dataset côté JS si besoin
            @endphp
            <a href="#{{ $zid }}" class="zone-toc-chip" data-zone="{{ $z['name'] }}" title="{{ $z['done'] }}/{{ $z['total'] }} faites · {{ $z['pct'] }}%">
                <span>📍 {{ $z['name'] }}</span>
                <span class="ztc-prog"><span class="ztc-prog-fill" style="width:{{ $z['pct'] }}%"></span></span>
                <span class="ztc-num">{{ $z['active'] }}</span>
            </a>
        @endforeach
    </div>
</div>
@endif

<div class="container">

    @if($totalActive === 0)
        <div class="empty">
            <div class="icon">🎉</div>
            <h2>Bravo, rien à poser !</h2>
            <p>Tu es à jour. Tu recevras un message WhatsApp dès qu'il y aura un nouveau panneau.</p>
        </div>
    @else
        {{-- ═══ BANDEAU CAP SSR ═══
             Si on a plus de poses qu'on ne peut raisonnablement rendre
             en SSR (cap 200 par défaut, configurable), on prévient le
             tech : "X poses au total — voici les 200 les plus urgentes,
             pour les autres utilise la recherche". --}}
        @if(($totalActive ?? 0) > ($totalRendered ?? 0))
            <div class="ssr-cap-banner">
                <span style="font-size:16px;line-height:1.2">⚡</span>
                <div>
                    Tu as <strong>{{ $totalActive }} panneaux</strong> à poser.
                    On te montre d'abord les <strong>{{ $totalRendered }} plus pressés</strong>.
                    <br>Pour les autres : utilise la <strong>recherche en haut</strong> 🔍
                    ou la <strong>🖨 liste papier</strong>.
                </div>
            </div>
        @endif

        {{-- Banner mode tournée — visible quand TSP optimisé activé --}}
        <div class="tour-summary" id="ts-tour-summary">
            <span>🚀</span>
            <span>Ton chemin : <strong id="ts-tour-count">0</strong> arrêts · <strong id="ts-tour-total">0 km</strong> en tout</span>
            <button type="button" id="ts-tour-quit">Annuler</button>
        </div>

        {{-- ═══ HERO « PROCHAINE POSE » ═══ --}}
        @if(!empty($nextTask))
            @include('public.tech.partials._focus_card', ['task' => $nextTask])
        @endif

        {{-- ═══ CHIPS FILTRES ═══ --}}
        @include('public.tech.partials._filters_chips')

        @php $today = \Carbon\Carbon::today(); @endphp
        @include('public.tech.partials._pose_list', [
            'groupedByCommune' => $groupedByCommune,
            'doneByCommune'    => $doneByCommune,
            'today'            => $today,
        ])

    @endif

    <div class="footer">
        Panora · CIBLE CI<br>
        <span style="opacity:.6">Lien personnel — ne pas partager</span>
    </div>
</div>

<div id="toast-container"></div>

{{-- Overlay succès plein écran (feedback fort terrain) --}}
<div id="ts-success" aria-hidden="true">
    <div class="ts-check"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24" fill="none"/><path fill="none" d="M14 27l8 8 16-16"/></svg></div>
    <div class="ts-msg" id="ts-success-msg">Envoyé&nbsp;!</div>
</div>

@include('public.tech.partials._modal_report')

{{-- Phase 3 SM1 — publication TECH_CONFIG (csrf + token + routes + bootstrap)
     consommé par les modules JS chargés juste après. À garder AVANT le
     <script type="module"> qui suit. --}}
@include('public.tech.partials._js_config', ['token' => $token])

{{-- ═══ jQuery + Select2 — déps de features/search.js (lot 3)
     Chargés en fin de body pour ne pas bloquer le rendu initial. La lib
     Select2 est cachée par le Service Worker dès la 1ère visite. Doivent
     être chargés AVANT tech-app.js — le `defer` les exécute dans l'ordre
     du DOM, garantissant que window.jQuery existe au DOMContentLoaded. --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js" defer></script>

{{-- ═══ FIN SM1.5 — entry unique : tech-app.js orchestrateur ESM ═══
     100 % des features du tech-space sont migrées en modules ES (lots 1-6).
     0 ligne de JS inline. Cf. docs/REFONTE_ESPACE_TECHNICIEN.md. --}}
<script type="module" src="{{ asset('js/tech/tech-app.js') }}?v={{ config('app.version', '1') }}"></script>

{{-- ═══ Bandeau hors-ligne — affiché par offline.js quand on perd le réseau ═══ --}}
<div class="offline-banner" id="ts-offline-banner">
    📵 Pas de réseau — tu peux quand même prendre des photos, on les enverra dès que ça revient.
</div>

</body>
</html>
