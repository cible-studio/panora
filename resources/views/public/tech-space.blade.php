{{-- ════════════════════════════════════════════════════════════════
     tech-space.blade.php — Refonte radicale 2026-06-19 (hotfix SM2a).
     L'ancienne version "additive" (anciens KPIs + nouveaux partials
     cohabitants) a été remplacée intégralement. Référence :
     docs/SM2_DOSSIER_SPECIFICATION.md §3.1 (écran T1 Accueil).

     Plan de la page (du haut vers le bas) :
       1. Header T1 (avatar + greeting + bouton "?")
       2. Barre de progression unique (verte) + message motivant
       3. Bandeaux temporaires : nouvelle pose / pige refusée
       4. Card MAINTENANT (focus card) ou empty state
       5. Liste compacte des poses, groupée par commune
       6. Section "Déjà faites" pliée par défaut
       7. Bouton secondaire "Voir ma tournée sur la carte"
       8. Modales (cachées par défaut, pilotées par features/*.js)

     SUPPRIMÉ par rapport à l'ancien :
       - _controls_bar (Carte / Près de moi / Mon chemin / Papier)
       - _filters_chips (chips de filtres)
       - zones-toc sticky horizontal
       - tour-summary banner
       - ssr-cap-banner
       - 4 KPI cards (À FAIRE / AUJOURD'HUI / PHOTOS / ZONES)
   ════════════════════════════════════════════════════════════════ --}}
@php
    // Pivot Carbon partagée (cohérence isLate / isToday dans les partials)
    $today = \Carbon\Carbon::today();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mes poses — {{ $tech->name }} · Panora</title>

    <link rel="icon" href="{{ asset('images/faviconl.png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('images/favicond.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="shortcut icon" href="{{ asset('images/faviconl.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- PWA — manifest + theme color + apple-touch-icon. SW enregistré
         par tech-app.js en fin de body. --}}
    @include('public.tech.partials._pwa_install')

    {{-- Select2 : utilisé par search.js (ouverture barre de recherche
         depuis le drawer pose-detail / actions futures). Le CSS reste
         chargé pour ne pas casser le rendu si search.js l'instancie. --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">

    @include('public.tech.partials._styles')
</head>
<body>

{{-- ── 1. Header T1 (sticky) — avatar + greeting + bouton "?" ────── --}}
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

{{-- ── 2. Bandeau live "nouvelle pose" (piloté par heartbeat.js) ── --}}
@include('public.tech.partials._banner_new_task')

<div class="container">

    {{-- ── 3. Bandeau rouge "photos à refaire" si pige refusée ──── --}}
    @include('public.tech.partials._banner_t9_rejected')

    @if(($totalActive ?? 0) === 0)
        {{-- Empty state global : aucune pose active --}}
        <div class="empty">
            <div class="icon">🎉</div>
            <h2>Bravo, rien à poser !</h2>
            <p>Tu es à jour. Tu recevras un message WhatsApp dès qu'il y aura un nouveau panneau.</p>
        </div>
    @else
        {{-- ── 4. Card MAINTENANT (pose courante mise en avant) ── --}}
        @if(!empty($nextTask))
            @include('public.tech.partials._focus_card', ['task' => $nextTask])
        @endif

        {{-- ── 5. Liste compacte des poses groupée par commune ── --}}
        @include('public.tech.partials._pose_list', [
            'groupedByCommune' => $groupedByCommune,
            'doneByCommune'    => $doneByCommune,
            'today'            => $today,
        ])

        {{-- ── 6. Section "🟢 Déjà faites" pliée par défaut ────── --}}
        @include('public.tech.partials._done_section')

        {{-- ── 7. Bouton "Voir ma tournée sur la carte" ────────── --}}
        @include('public.tech.partials._tour_button')
    @endif

    <div class="footer">
        Panora · CIBLE CI<br>
        <span style="opacity:.6">Lien personnel — ne pas partager</span>
    </div>
</div>

{{-- ── Conteneur des toasts (consommé par core/ui-helpers.js) ────── --}}
<div id="toast-container"></div>

{{-- ── Overlay succès plein écran (feedback fort terrain — utilisé
       par upload.js pour le flash 900ms entre photos) ──────────── --}}
<div id="ts-success" aria-hidden="true">
    <div class="ts-check"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24" fill="none"/><path fill="none" d="M14 27l8 8 16-16"/></svg></div>
    <div class="ts-msg" id="ts-success-msg">Envoyé&nbsp;!</div>
</div>

{{-- ─── 8. Modales (cachées par défaut, ouvertes par features/*.js) ─── --}}
@include('public.tech.partials._modal_report')
@include('public.tech.partials._drawer_pose_detail')
@include('public.tech.partials._modal_y_aller')
@include('public.tech.partials._modal_photo_preview')
@include('public.tech.partials._screen_success')
@include('public.tech.partials._modal_help')
@include('public.tech.partials._drawer_t9_rejected')

{{-- Modales SM2c — déjà déployées, conservées (b1/b2/b3) --}}
@include('public.tech.partials._modal_off_schedule')
@include('public.tech.partials._screen_end_of_day')
@include('public.tech.partials._drawer_notifications')
@include('public.tech.partials._drawer_tech_preferences')

{{-- TECH_CONFIG publié AVANT le chargement des modules JS --}}
@include('public.tech.partials._js_config', ['token' => $token])

{{-- jQuery + Select2 — dépendances de features/search.js --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js" defer></script>

{{-- Entry unique : tech-app.js — orchestrateur ESM SM1.5 --}}
<script type="module" src="{{ asset('js/tech/tech-app.js') }}?v={{ config('app.version', '1') }}"></script>

{{-- Bandeau hors-ligne — affiché par offline.js quand on perd le réseau --}}
<div class="offline-banner" id="ts-offline-banner">
    📵 Pas de réseau — tu peux quand même prendre des photos, on les enverra dès que ça revient.
</div>

</body>
</html>
