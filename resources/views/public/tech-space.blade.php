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
    {{-- Refonte 2026-11-12 (maquette validée) : styles de « Ma journée ». --}}
    <link rel="stylesheet" href="{{ asset('css/tech/tx-refonte.css') }}?v={{ config('app.version', '1') }}">
</head>
<body class="tx">

{{-- ── 1. En-tête (refonte 2026-11-12) : menu, alertes, journée ────── --}}
@include('public.tech.partials._tx_tete', [
    'tech'           => $tech,
    'jourFaites'     => $jourFaites ?? 0,
    'jourTotal'      => $jourTotal ?? 0,
    'zonesTodayList' => $zonesTodayList ?? [],
])

{{-- ── 2. Bandeau live "nouvelle pose" (piloté par heartbeat.js) ── --}}
@include('public.tech.partials._banner_new_task')

<div class="container tx-corps">

    {{-- ── 3. Bandeau rouge "photos à refaire" si pige refusée ──── --}}
    @include('public.tech.partials._banner_t9_rejected')

    {{-- Photos en attente d'envoi (sans réseau) : visibles et relançables
         (upload.js : refreshSyncBadge / flushUploadQueue). Le badge vivait
         dans une barre retirée — on ne voyait plus les photos en attente. --}}
    <button type="button" id="ts-sync-badge" class="tx-alerte tx-alerte-ambre" style="display:none">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0-4 4m4-4 4 4M4 16v4h16v-4"/></svg>
        <span><strong><span id="ts-sync-count">0</span> photo(s) en attente d'envoi</strong>Pas de réseau tout à l'heure. Touche pour réessayer.</span>
    </button>

    @if(($totalActive ?? 0) === 0)
        {{-- Empty state global : aucune pose active --}}
        <div class="empty">
            <div class="icon">🎉</div>
            <h2>Bravo, rien à poser !</h2>
            <p>Tu es à jour. Tu recevras un message WhatsApp dès qu'il y aura un nouveau panneau.</p>
        </div>
    @else
        {{-- ── Ma journée (refonte 2026-11-12) : recherche, « Maintenant »,
             poses par commune, une carte par axe. Remplace _pose_list. --}}
        @include('public.tech.partials._tx_journee', [
            'itemsByCommune' => $itemsByCommune ?? collect(),
            'doneByCommune'  => $doneByCommune,
            'nextTask'       => $nextTask,
        ])
    @endif

    {{-- ── « Déjà faites » repliée — visible même sans pose à faire. ── --}}
    @include('public.tech.partials._done_section')

    <div class="footer">
        Panora · CIBLE CI<br>
        <span style="opacity:.6">Lien personnel — ne pas partager</span>
    </div>
</div>

{{-- ── Barre du bas (refonte 2026-11-12) ─────────────────────────── --}}
@include('public.tech.partials._tx_nav', ['token' => $token, 'pigesRejected' => $pigesRejected ?? 0])

{{-- ── Feuilles du bas (refonte, étape 2) : menu, « Photo envoyée » ── --}}
@include('public.tech.partials._tx_feuilles', ['token' => $token])

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
