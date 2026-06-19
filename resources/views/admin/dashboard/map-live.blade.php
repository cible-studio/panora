{{-- SM2b Phase 4 — Carte LIVE des techs (A3). Leaflet 1.9 + markercluster.
     Polling 20s sur admin.map.live → repositionne les marqueurs sans
     recharger la carte. Marqueurs custom avec initiales du tech. --}}
<x-admin-layout>
    <x-slot name="title">Carte live des techs</x-slot>

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"/>
        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"/>
        <link rel="stylesheet" href="{{ asset('css/admin/live-dashboard.css') }}?v={{ config('app.version', '1') }}">
        <link rel="stylesheet" href="{{ asset('css/admin/map-live.css') }}?v={{ config('app.version', '1') }}">
    @endpush

    <div class="map-live-page" data-map-live>
        <header class="map-live-head">
            <a href="{{ route('admin.pilotage') }}" class="tech-live-back">← Retour pilotage</a>
            <h1 class="map-live-title">🗺️ Carte live</h1>
            <span class="map-live-badge">
                <span class="live-status-pulse" aria-hidden="true"></span>
                <span data-field="techs-count">0 techs en ligne</span>
            </span>
        </header>

        <div class="map-live-body">
            <div id="map-live-canvas" class="map-live-canvas" data-field="canvas"></div>

            <aside class="map-live-side" data-field="side-panel">
                <header class="map-live-side-head">Techs visibles</header>
                <ul class="map-live-side-list" data-field="side-list">
                    <template data-field="side-row-tpl">
                        <li class="map-live-side-row" tabindex="0">
                            <div class="map-live-side-avatar" data-field="side-initials">??</div>
                            <div class="map-live-side-info">
                                <div class="map-live-side-name" data-field="side-name">—</div>
                                <div class="map-live-side-meta" data-field="side-meta">—</div>
                            </div>
                        </li>
                    </template>
                </ul>
                <div class="map-live-side-empty" data-field="side-empty">
                    Aucun tech localisé. Vérifie qu'au moins un tech a envoyé une photo dans les 24h.
                </div>
            </aside>
        </div>

        <footer class="map-live-legend">
            <span><span class="legend-dot" style="background:#16a34a"></span> Actif (&lt; 5 min)</span>
            <span><span class="legend-dot" style="background:#ea580c"></span> En pose</span>
            <span><span class="legend-dot" style="background:#9ca3af"></span> Inactif (&gt; 5 min)</span>
        </footer>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
        <script>
            window.ADMIN_MAP_LIVE_CONFIG = {
                endpoint: @json(route('admin.map.live')),
                techDetailUrlTpl: @json(route('admin.pilotage.tech', ['user' => '__USER__'])),
                pollMs: 20000,
                defaultCenter: [5.345, -4.024],   // Abidjan
                defaultZoom: 11,
                focusTechId: @json(request('focus')),
            };
        </script>
        <script src="{{ asset('js/admin/map-live.js') }}?v={{ config('app.version', '1') }}"></script>
    @endpush
</x-admin-layout>
