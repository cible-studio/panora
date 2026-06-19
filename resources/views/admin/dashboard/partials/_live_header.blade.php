{{-- A1 §spec — Header avec titre + badge "Mise à jour il y a Xs" + pastille live. --}}
<header class="live-header">
    <div class="live-header-left">
        <h1 class="live-header-title">📡 Pilotage terrain</h1>
        <div class="live-header-date">{{ \Carbon\Carbon::today()->translatedFormat('l j F Y') }}</div>
    </div>
    <div class="live-header-right">
        <span class="live-status-pulse" aria-hidden="true"></span>
        <span class="live-status-text" data-field="as-of">Connexion…</span>
    </div>
</header>
