<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Carte — {{ $tech->name }} · Panora</title>

    {{-- Favicon Panora (aligné sur le layout admin pour cohérence onglet) --}}
    <link rel="icon" href="{{ asset('images/faviconl.png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('images/favicond.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="shortcut icon" href="{{ asset('images/faviconl.png') }}">

    <link rel="manifest" href="{{ asset('tech.webmanifest') }}">
    <meta name="theme-color" content="#e8a020">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Leaflet — moteur de carte open-source, ~40 ko gzip + CSS. Préchargé
         dans la PWA par le Service Worker dès la 1ère visite. --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">

    <style>
        :root {
            --accent: #e8a020;
            --accent-dark: #c2570d;
            --bg: #f8f9fb;
            --surface: #ffffff;
            --border: #e5e7eb;
            --text: #111827;
            --text2: #4b5563;
            --text3: #9ca3af;
            --planned: #e8a020;
            --en-route: #8b5cf6;
            --in-progress: #3b82f6;
            --done: #22c55e;
            --cancelled: #ef4444;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { margin: 0; padding: 0; height: 100%; font-family: 'Inter', sans-serif; color: var(--text); }
        body { display: flex; flex-direction: column; background: var(--bg); }

        .header {
            background: linear-gradient(180deg, #fff 0%, #fffaf0 100%);
            padding: 12px 16px; padding-top: max(12px, env(safe-area-inset-top));
            border-bottom: 1px solid var(--border);
            display: flex; gap: 10px; align-items: center;
            box-shadow: 0 4px 14px -8px rgba(232,160,32,.2);
            z-index: 1000;
        }
        .header .brand-logo {
            flex: 0 0 auto;
            height: 30px; width: auto; display: block; object-fit: contain;
        }
        .header h1 {
            margin: 0; font-size: 14.5px; font-weight: 800;
            flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .header .subtitle {
            font-size: 11px; color: var(--text3); font-weight: 600;
        }
        .header .back {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 7px 11px; border-radius: 10px;
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text2); font-size: 12px; font-weight: 700;
            text-decoration: none;
        }
        .header .back:active { transform: scale(.97); }

        #map { flex: 1; min-height: 200px; }

        .map-controls {
            position: absolute; right: 12px; top: 70px; z-index: 999;
            display: flex; flex-direction: column; gap: 6px;
        }
        .map-ctrl-btn {
            background: var(--surface); color: var(--text2);
            border: 1px solid var(--border); border-radius: 12px;
            width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center;
            font-size: 19px; cursor: pointer; font-family: inherit;
            box-shadow: 0 6px 20px -8px rgba(0,0,0,.2);
            transition: transform .08s;
        }
        .map-ctrl-btn:active { transform: scale(.94); }
        .map-ctrl-btn.is-active { background: var(--accent); color: #fff; border-color: var(--accent); }

        /* Bottom panel : compteur + bouton optimiser tournée */
        .bottom-panel {
            background: var(--surface); border-top: 1px solid var(--border);
            padding: 12px 16px; padding-bottom: calc(12px + env(safe-area-inset-bottom));
            display: flex; gap: 10px; align-items: center;
            box-shadow: 0 -6px 20px -8px rgba(0,0,0,.1);
        }
        .bottom-panel .stat {
            flex: 1; min-width: 0;
            font-size: 11.5px; color: var(--text2);
        }
        .bottom-panel .stat strong { color: var(--text); font-weight: 800; font-size: 13px; }
        .bottom-panel .stat .nogps {
            color: var(--cancelled); font-weight: 700; font-size: 10.5px; margin-top: 2px;
        }
        .bottom-panel .opt-btn {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff; border: none; border-radius: 12px;
            padding: 10px 14px;
            font-size: 13px; font-weight: 800; cursor: pointer;
            font-family: inherit;
            box-shadow: 0 6px 18px -4px rgba(59,130,246,.5);
            transition: transform .08s;
        }
        .bottom-panel .opt-btn:active { transform: scale(.96); }
        .bottom-panel .opt-btn.is-active {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            box-shadow: 0 6px 18px -4px rgba(34,197,94,.5);
        }

        /* Popup styling */
        .leaflet-popup-content-wrapper {
            border-radius: 14px; padding: 0;
            box-shadow: 0 20px 50px -10px rgba(0,0,0,.25);
        }
        .leaflet-popup-content { margin: 0; min-width: 220px; }
        .marker-popup { padding: 12px 14px; }
        .marker-popup .pop-ref {
            font-family: ui-monospace, monospace; font-weight: 800;
            color: var(--accent-dark); font-size: 14px;
            display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
            margin-bottom: 3px;
        }
        .marker-popup .pop-name {
            font-size: 12.5px; font-weight: 600; color: var(--text);
            margin-bottom: 2px;
        }
        .marker-popup .pop-meta {
            font-size: 11px; color: var(--text3); margin-bottom: 8px;
            line-height: 1.45;
        }
        .marker-popup .pop-actions {
            display: flex; gap: 6px;
        }
        .marker-popup .pop-actions a, .marker-popup .pop-actions button {
            flex: 1; padding: 9px 10px; border-radius: 10px;
            font-size: 12px; font-weight: 700; cursor: pointer;
            border: 1px solid transparent; font-family: inherit;
            text-decoration: none; text-align: center;
            display: flex; align-items: center; justify-content: center; gap: 4px;
        }
        .marker-popup .pop-actions .go {
            background: #3b82f6; color: #fff;
        }
        .marker-popup .pop-actions .view {
            background: var(--surface); color: var(--text2); border-color: var(--border);
        }
        .pop-pill {
            display: inline-block; padding: 1px 6px; border-radius: 6px;
            font-size: 9.5px; font-weight: 700;
        }
        .pop-pill.late { background: rgba(239,68,68,.12); color: #b91c1c; }

        /* Custom marker (couleur statut) */
        .ts-marker {
            background: var(--marker-color, var(--accent));
            border: 2.5px solid #fff;
            border-radius: 50%;
            width: 26px; height: 26px;
            box-shadow: 0 4px 10px rgba(0,0,0,.25);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 800; font-size: 11px;
            font-family: ui-monospace, monospace;
        }
        .ts-marker.late {
            box-shadow: 0 0 0 3px rgba(239,68,68,.45), 0 4px 10px rgba(0,0,0,.25);
        }
        .ts-marker.tour-step {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-color: #fff;
            box-shadow: 0 0 0 3px rgba(34,197,94,.4), 0 6px 14px rgba(0,0,0,.3);
        }

        /* Itinéraire (polyline) */
        .leaflet-tour-line {
            stroke: #22c55e; stroke-width: 4; stroke-opacity: .85;
            stroke-dasharray: 8 6;
            animation: dashFlow 1.5s linear infinite;
        }
        @keyframes dashFlow { from { stroke-dashoffset: 28; } to { stroke-dashoffset: 0; } }

        .empty-overlay {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            background: rgba(255,255,255,.92); z-index: 999;
            padding: 24px; text-align: center;
        }
        .empty-overlay .icon { font-size: 52px; margin-bottom: 12px; opacity: .5; }
        .empty-overlay h2 { font-size: 18px; margin: 0 0 6px; color: var(--text); }
        .empty-overlay p { font-size: 13px; color: var(--text2); margin: 0; max-width: 320px; line-height: 1.5; }
    </style>
</head>
<body>

<div class="header">
    <a href="{{ route('tech.space', $token) }}" class="back">←</a>
    <img src="{{ asset('images/panora.png') }}" alt="Panora by CIBLE" class="brand-logo">
    <div style="flex:1;min-width:0">
        <h1>🗺 Mes panneaux sur la carte</h1>
        <div class="subtitle">
            {{ $points->count() }} panneau{{ $points->count() > 1 ? 'x' : '' }} avec position
            @if($withoutGps > 0)
                · <span style="color:var(--cancelled)">{{ $withoutGps }} sans position</span>
            @endif
        </div>
    </div>
</div>

<div id="map" style="position:relative">
    @if($points->isEmpty())
        <div class="empty-overlay">
            <div class="icon">🗺</div>
            <h2>Aucun panneau sur la carte</h2>
            <p>
                Tes panneaux n'ont pas encore d'adresse GPS enregistrée.
                Touche « Retour » et utilise la liste pour les retrouver par leur numéro ou leur rue.
            </p>
        </div>
    @endif
</div>

<div class="map-controls">
    <button type="button" class="map-ctrl-btn" id="ts-locate-btn" title="Voir où je suis">📍</button>
    <button type="button" class="map-ctrl-btn" id="ts-fit-btn" title="Voir tous les panneaux">🔭</button>
</div>

@if($points->isNotEmpty())
<div class="bottom-panel">
    <div class="stat">
        <strong>{{ $points->count() }}</strong> panneau{{ $points->count() > 1 ? 'x' : '' }} sur la carte
        @if($withoutGps > 0)
            <div class="nogps">⚠ {{ $withoutGps }} sans position (cherche dans la liste)</div>
        @endif
    </div>
    <button type="button" class="opt-btn" id="ts-optimize-btn">🚀 Mon chemin</button>
</div>
@endif

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js" defer></script>

<script>
window.addEventListener('DOMContentLoaded', function () {
    if (typeof L === 'undefined') return;

    const TOKEN = @json($token);
    const POINTS = @json($points);
    const OPTIMIZE_URL = "{{ route('tech.space.optimize', $token) }}";
    const SPACE_URL = "{{ route('tech.space', $token) }}";

    if (!POINTS.length) return;

    // ─── 1. Centre par défaut : barycentre des points ──────────────
    let centerLat = 0, centerLng = 0;
    POINTS.forEach(p => { centerLat += p.lat; centerLng += p.lng; });
    centerLat /= POINTS.length;
    centerLng /= POINTS.length;

    const map = L.map('map', {
        center: [centerLat, centerLng],
        zoom: 12,
        zoomControl: true,
        attributionControl: false,
    });

    // ─── 2. Tuiles OSM (libre, sans clé API) ────────────────────────
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap',
        crossOrigin: true,
    }).addTo(map);
    L.control.attribution({ position: 'bottomleft', prefix: false })
        .addAttribution('OSM')
        .addTo(map);

    // ─── 3. Markers avec icônes statut + cluster ────────────────────
    const cluster = L.markerClusterGroup({
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        maxClusterRadius: 50,
        iconCreateFunction: (c) => {
            const count = c.getChildCount();
            const cls = count >= 50 ? 'big' : (count >= 10 ? 'medium' : 'small');
            const size = cls === 'big' ? 50 : (cls === 'medium' ? 42 : 36);
            const color = cls === 'big' ? '#dc2626' : (cls === 'medium' ? '#f97316' : '#e8a020');
            return L.divIcon({
                html: `<div style="background:${color};color:#fff;width:${size}px;height:${size}px;border-radius:50%;border:3px solid #fff;box-shadow:0 6px 18px -2px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;font-family:ui-monospace,monospace">${count}</div>`,
                className: 'ts-cluster-icon',
                iconSize: [size, size],
            });
        },
    });

    const markerByTaskId = {};

    function makeMarker(p, opts = {}) {
        const color = p.status_color || '#e8a020';
        const lateClass = p.is_late ? 'late' : '';
        const tourClass = opts.tourStep ? 'tour-step' : '';
        const label = opts.tourStep ? String(opts.tourStep) : '';
        const html = `<div class="ts-marker ${lateClass} ${tourClass}" style="--marker-color:${color}">${label}</div>`;
        const icon = L.divIcon({ html, className: 'ts-marker-wrapper', iconSize: [26, 26] });
        const m = L.marker([p.lat, p.lng], { icon });
        m.bindPopup(() => renderPopup(p), { closeButton: true, maxWidth: 280 });
        return m;
    }

    function renderPopup(p) {
        const goUrl = `https://www.google.com/maps/dir/?api=1&destination=${p.lat},${p.lng}`;
        const lateHtml = p.is_late ? '<span class="pop-pill late">⏰ Retard</span>' : '';
        const focusUrl = `${SPACE_URL}#pose-${p.id}`;
        return `
            <div class="marker-popup">
                <div class="pop-ref">${p.ref || ''} ${lateHtml}</div>
                <div class="pop-name">${p.name || ''}</div>
                <div class="pop-meta">
                    📍 ${p.commune || '—'}${p.adresse ? ' · ' + p.adresse : ''}
                    ${p.campaign ? '<br>📢 ' + (p.campaign.length > 32 ? p.campaign.slice(0, 32) + '…' : p.campaign) : ''}
                    ${p.sched ? '<br>🕒 ' + p.sched : ''}
                </div>
                <div class="pop-actions">
                    <a class="go" href="${goUrl}" target="_blank" rel="noopener">🧭 Y aller</a>
                    <a class="view" href="${focusUrl}">📋 Voir</a>
                </div>
            </div>`;
    }

    POINTS.forEach(p => {
        const m = makeMarker(p);
        cluster.addLayer(m);
        markerByTaskId[p.id] = m;
    });
    map.addLayer(cluster);

    // Fit bounds initial
    if (POINTS.length > 1) {
        map.fitBounds(cluster.getBounds().pad(0.15));
    }

    // ─── 4. Bouton "Me localiser" ──────────────────────────────────
    let userMarker = null;
    let userCircle = null;
    document.getElementById('ts-locate-btn')?.addEventListener('click', () => {
        if (!navigator.geolocation) return;
        const btn = document.getElementById('ts-locate-btn');
        btn.classList.add('is-active');
        btn.textContent = '🛰';
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude, lng = pos.coords.longitude;
                if (userMarker) { map.removeLayer(userMarker); }
                if (userCircle) { map.removeLayer(userCircle); }
                userMarker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        html: '<div style="background:#3b82f6;width:18px;height:18px;border:3px solid #fff;border-radius:50%;box-shadow:0 0 0 4px rgba(59,130,246,.3),0 4px 10px rgba(0,0,0,.3)"></div>',
                        className: '',
                        iconSize: [18, 18],
                    }),
                }).addTo(map);
                userCircle = L.circle([lat, lng], {
                    radius: pos.coords.accuracy || 50,
                    color: '#3b82f6', fillColor: '#3b82f6', fillOpacity: .1, weight: 1,
                }).addTo(map);
                map.setView([lat, lng], Math.max(14, map.getZoom()));
                btn.textContent = '📍';
                btn.classList.remove('is-active');
            },
            () => {
                btn.textContent = '📍';
                btn.classList.remove('is-active');
                alert('On ne te trouve pas. Autorise le GPS sur ton téléphone.');
            },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    });

    // ─── 5. Bouton "Tout voir" ────────────────────────────────────
    document.getElementById('ts-fit-btn')?.addEventListener('click', () => {
        if (cluster.getBounds().isValid()) {
            map.fitBounds(cluster.getBounds().pad(0.15));
        }
    });

    // ─── 6. Bouton "Optimiser la tournée" — TSP nearest-neighbor ──
    let tourLine = null;
    let tourActive = false;
    const optBtn = document.getElementById('ts-optimize-btn');
    optBtn?.addEventListener('click', async () => {
        if (tourActive) {
            // Toggle off → restaure les markers classiques
            tourActive = false;
            optBtn.classList.remove('is-active');
            optBtn.textContent = '🚀 Mon chemin';
            if (tourLine) { map.removeLayer(tourLine); tourLine = null; }
            // Remet les markers normaux
            Object.values(markerByTaskId).forEach(m => map.removeLayer(m));
            cluster.clearLayers();
            POINTS.forEach(p => {
                const m = makeMarker(p);
                cluster.addLayer(m);
                markerByTaskId[p.id] = m;
            });
            map.addLayer(cluster);
            return;
        }
        if (!navigator.geolocation) {
            alert('GPS bloqué — autorise-le pour calculer ton chemin.');
            return;
        }
        optBtn.disabled = true;
        optBtn.textContent = '🛰 On te cherche…';
        navigator.geolocation.getCurrentPosition(
            async (pos) => {
                optBtn.textContent = '🔄 On calcule…';
                try {
                    const u = new URL(OPTIMIZE_URL, location.origin);
                    u.searchParams.set('lat', pos.coords.latitude.toFixed(6));
                    u.searchParams.set('lng', pos.coords.longitude.toFixed(6));
                    const r = await fetch(u.toString(), { headers: { 'Accept': 'application/json' } });
                    const d = await r.json();
                    if (!r.ok || !d.ok) throw new Error('optimize failed');
                    displayTour(d.order, [pos.coords.latitude, pos.coords.longitude], d.total_meters);
                    tourActive = true;
                    optBtn.classList.add('is-active');
                    optBtn.textContent = `✓ ${(d.total_meters/1000).toFixed(1).replace('.0', '')} km — touche pour annuler`;
                } catch (e) {
                    optBtn.textContent = '❌ Réessaie';
                    setTimeout(() => optBtn.textContent = '🚀 Mon chemin', 2000);
                } finally {
                    optBtn.disabled = false;
                }
            },
            () => {
                optBtn.disabled = false;
                optBtn.textContent = '🚀 Mon chemin';
                alert('On ne te trouve pas — autorise le GPS.');
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    function displayTour(order, startLatLng, totalMeters) {
        // Vide le cluster pour repartir propre, puis affiche markers
        // numérotés selon l'ordre TSP + une polyline qui relie tout.
        cluster.clearLayers();
        const latLngs = [L.latLng(startLatLng[0], startLatLng[1])];
        order.forEach((step, idx) => {
            const p = POINTS.find(pt => pt.id === step.id);
            if (!p) return;
            const m = makeMarker(p, { tourStep: idx + 1 });
            cluster.addLayer(m);
            latLngs.push(L.latLng(p.lat, p.lng));
        });
        map.addLayer(cluster);
        if (tourLine) map.removeLayer(tourLine);
        tourLine = L.polyline(latLngs, {
            color: '#22c55e', weight: 4, opacity: .85,
            dashArray: '8 6', className: 'leaflet-tour-line',
        }).addTo(map);
        map.fitBounds(tourLine.getBounds().pad(0.1));
    }

    // ─── 7. Service Worker register (PWA shared) ──────────────────
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('{{ asset('tech-sw.js') }}', { scope: '/' }).catch(() => {});
    }
});
</script>

</body>
</html>
