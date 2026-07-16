<x-admin-layout title="Carte des poses">

<x-slot:topbarLeft>
    <a href="{{ route('admin.pose-tasks.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour
    </a>
</x-slot:topbarLeft>

<x-slot:topbarActions>
    <a href="{{ route('admin.pose-tasks.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        Vue Tableau
    </a>
</x-slot:topbarActions>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"/>

<style>
    .map-page-wrap { display:flex; gap:16px; height:calc(100vh - 130px); min-height:560px; }
    .map-filters-panel {
        flex-shrink:0; width:280px;
        background:var(--surface); border:1px solid var(--border); border-radius:14px;
        padding:18px; overflow:auto;
        display:flex; flex-direction:column; gap:16px;
    }
    .map-container-wrap {
        flex:1; min-width:0;
        background:var(--surface); border:1px solid var(--border); border-radius:14px;
        overflow:hidden; position:relative;
    }
    #poses-map { width:100%; height:100%; }
    .map-status-card {
        padding:9px 12px; border-radius:10px; cursor:pointer;
        background:var(--surface2); border:1px solid var(--border);
        display:flex; align-items:center; justify-content:space-between;
        transition:all .15s; user-select:none;
    }
    .map-status-card:hover { transform:translateX(2px); }
    .map-status-card.active { box-shadow:inset 3px 0 0 currentColor; }
    .map-status-dot {
        width:10px; height:10px; border-radius:50%; flex-shrink:0;
        box-shadow:0 0 0 3px rgba(0,0,0,.04);
    }
    .map-filter-label {
        font-size:10px; font-weight:700; text-transform:uppercase;
        letter-spacing:.5px; color:var(--text3); margin-bottom:6px;
    }
    .map-loading-overlay {
        position:absolute; inset:0; background:rgba(255,255,255,.85);
        display:none; align-items:center; justify-content:center; z-index:1001;
        font-size:14px; color:var(--text2); font-weight:600;
    }
    .map-empty {
        position:absolute; inset:0; display:none; flex-direction:column;
        align-items:center; justify-content:center; gap:8px;
        text-align:center; color:var(--text3); background:rgba(255,255,255,.92);
        z-index:1000;
    }
    /* Popup contenu */
    .pose-popup {
        font-family:inherit; min-width:220px; padding:8px 4px;
    }
    .pose-popup-ref {
        font-family:ui-monospace,Menlo,Consolas,monospace;
        font-size:13px; font-weight:700; color:var(--accent); margin-bottom:3px;
    }
    .pose-popup-name { font-size:13px; color:var(--text); font-weight:500; margin-bottom:8px; }
    .pose-popup-row { display:flex; justify-content:space-between; gap:10px; font-size:12px; margin:3px 0; }
    .pose-popup-row .lbl { color:var(--text3); }
    .pose-popup-row .val { color:var(--text); font-weight:600; }
    .pose-popup-cta {
        display:block; margin-top:8px; padding:6px 12px;
        background:var(--accent); color:#000 !important; text-align:center;
        border-radius:8px; text-decoration:none; font-size:12px; font-weight:700;
    }
    .pose-popup-cta:hover { opacity:.9; }

    /* ═══════════════════════════════════════════════════════════════
       Responsive mobile (2026-07-16 — audit responsive admin)
       Sous 900px la carte et le panneau filtres s'empilent verticalement.
       Le panneau reste accessible (max-height 45vh, scroll interne) et
       la carte prend le reste. Sous 560px, panneau plus tassé.
       ═══════════════════════════════════════════════════════════════ */
    @media (max-width: 900px) {
        .map-page-wrap {
            flex-direction: column;
            height: auto;
            min-height: 0;
            gap: 10px;
        }
        .map-filters-panel {
            width: 100%;
            max-height: 45vh;
            padding: 14px;
            gap: 12px;
        }
        .map-container-wrap {
            height: 60vh;
            min-height: 380px;
        }
    }
    @media (max-width: 560px) {
        .map-filters-panel {
            max-height: 40vh;
            padding: 12px;
        }
        .map-container-wrap {
            height: 55vh;
            min-height: 320px;
        }
    }
</style>

<div class="map-page-wrap">

    {{-- ── PANEL FILTRES (gauche) ───────────────────────────── --}}
    <div class="map-filters-panel">
        <div>
            <div style="display:flex;align-items:center;gap:9px;margin-bottom:14px">
                <div style="width:34px;height:34px;border-radius:10px;background:rgba(232,160,32,.12);display:flex;align-items:center;justify-content:center">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--text)">Carte des poses</div>
                    <div style="font-size:11px;color:var(--text3)"><span id="map-count">0</span> sur la carte</div>
                </div>
            </div>
        </div>

        {{-- Filtre statut (cliquable) --}}
        <div>
            <div class="map-filter-label">Filtrer par statut</div>
            <div style="display:flex;flex-direction:column;gap:6px">
                <div class="map-status-card" data-status="" style="color:var(--accent)">
                    <span style="display:flex;align-items:center;gap:8px">
                        <span class="map-status-dot" style="background:var(--accent)"></span>
                        <span style="font-size:12px;color:var(--text);font-weight:600">Toutes</span>
                    </span>
                    <span id="count-all" style="font-size:11px;color:var(--text3);font-weight:700">0</span>
                </div>
                <div class="map-status-card" data-status="planifiee" style="color:#e8a020">
                    <span style="display:flex;align-items:center;gap:8px">
                        <span class="map-status-dot" style="background:#e8a020"></span>
                        <span style="font-size:12px;color:var(--text);font-weight:600">📅 Planifiée</span>
                    </span>
                    <span id="count-planifiee" style="font-size:11px;color:var(--text3);font-weight:700">0</span>
                </div>
                <div class="map-status-card" data-status="en_cours" style="color:#3b82f6">
                    <span style="display:flex;align-items:center;gap:8px">
                        <span class="map-status-dot" style="background:#3b82f6"></span>
                        <span style="font-size:12px;color:var(--text);font-weight:600">🔧 En cours</span>
                    </span>
                    <span id="count-en_cours" style="font-size:11px;color:var(--text3);font-weight:700">0</span>
                </div>
                <div class="map-status-card" data-status="realisee" style="color:#22c55e">
                    <span style="display:flex;align-items:center;gap:8px">
                        <span class="map-status-dot" style="background:#22c55e"></span>
                        <span style="font-size:12px;color:var(--text);font-weight:600">✅ Réalisée</span>
                    </span>
                    <span id="count-realisee" style="font-size:11px;color:var(--text3);font-weight:700">0</span>
                </div>
            </div>
        </div>

        {{-- Filtre technicien --}}
        <div>
            <div class="map-filter-label">Technicien</div>
            <select id="map-filter-tech" class="filter-select" style="width:100%">
                <option value="">Tous</option>
                @foreach($techniciens as $t)
                <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Filtre campagne --}}
        <div>
            <div class="map-filter-label">Campagne</div>
            <select id="map-filter-campaign" class="filter-select" style="width:100%">
                <option value="">Toutes</option>
                @foreach($campaigns as $c)
                <option value="{{ $c->id }}">{{ Str::limit($c->name, 28) }}</option>
                @endforeach
            </select>
        </div>

        {{-- Légende retards --}}
        <div style="padding:10px 12px;background:rgba(220,38,38,.06);border:1px solid rgba(220,38,38,.2);border-radius:10px">
            <div style="display:flex;align-items:center;gap:7px;font-size:11px;color:#dc2626;font-weight:700">
                <span class="map-status-dot" style="background:#dc2626"></span>
                Marker rouge foncé = pose en retard
            </div>
        </div>

        <button type="button" id="map-reset-view" class="btn btn-ghost btn-sm" style="margin-top:auto">
            ↻ Recentrer
        </button>
    </div>

    {{-- ── CARTE (droite) ───────────────────────────────────── --}}
    <div class="map-container-wrap">
        <div id="poses-map"></div>
        <div class="map-loading-overlay" id="map-loading">⟳ Chargement…</div>
        <div class="map-empty" id="map-empty">
            <div style="font-size:42px;opacity:.4">🗺</div>
            <div style="font-size:14px;font-weight:600;color:var(--text2)">Aucune pose avec coordonnées GPS</div>
            <div style="font-size:11px;max-width:280px;line-height:1.5">
                Renseignez la latitude/longitude des panneaux dans leur fiche
                pour les voir apparaître ici.
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const MAP_DATA_URL = @json(route('admin.pose-tasks.map.data'));

    // ── Carte centrée sur Abidjan par défaut ────────────────
    const map = L.map('poses-map', {
        center: [5.34, -4.03],
        zoom: 11,
        scrollWheelZoom: true,
    });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    const markerCluster = L.markerClusterGroup({
        chunkedLoading: true,
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
    });
    map.addLayer(markerCluster);

    let allMarkers = [];
    let initialBounds = null;

    function statusLabel(s) {
        return ({
            'planifiee': '📅 Planifiée',
            'en_cours' : '🔧 En cours',
            'realisee' : '✅ Réalisée',
            'annulee'  : '🚫 Annulée',
        })[s] || s;
    }

    function buildPopup(m) {
        const parts = [
            `<div class="pose-popup">`,
            `<div class="pose-popup-ref">${m.reference}</div>`,
            `<div class="pose-popup-name">${m.name || '—'}</div>`,
            m.commune ? `<div class="pose-popup-row"><span class="lbl">Commune</span><span class="val">${m.commune}</span></div>` : '',
            m.campaign ? `<div class="pose-popup-row"><span class="lbl">Campagne</span><span class="val">${m.campaign}</span></div>` : '',
            m.tech ? `<div class="pose-popup-row"><span class="lbl">Technicien</span><span class="val">${m.tech}</span></div>` : `<div class="pose-popup-row"><span class="lbl">Technicien</span><span class="val" style="color:#ef4444">— Non assigné —</span></div>`,
            `<div class="pose-popup-row"><span class="lbl">Statut</span><span class="val" style="color:${m.color}">${statusLabel(m.status)}${m.is_late ? ' · ⚠ Retard' : ''}</span></div>`,
            m.scheduled ? `<div class="pose-popup-row"><span class="lbl">Prévu le</span><span class="val">${m.scheduled}</span></div>` : '',
            m.done_at ? `<div class="pose-popup-row"><span class="lbl">Réalisé</span><span class="val" style="color:#22c55e">${m.done_at}</span></div>` : '',
            `<div class="pose-popup-row"><span class="lbl">GPS</span><span class="val">${gpsSourceLabel(m.gps_source)}${m.dispersion ? ' · <span style="color:#ef4444">⚠ divergent</span>' : ''}</span></div>`,
            `<a href="${m.show_url}" class="pose-popup-cta">Ouvrir la pose</a>`,
            `</div>`,
        ];
        return parts.join('');
    }

    function gpsSourceLabel(src) {
        return ({
            'manual':           '📍 Manuel',
            'pige_confirmed':   '✅ Confirmé (piges)',
            'pige_provisional': '⏳ Provisoire (1 pige)',
        })[src] || '— origine inconnue';
    }

    function buildMarkerIcon(color, isLate, dispersion) {
        const size = (isLate || dispersion) ? 30 : 26;
        let ring = '';
        if (isLate) {
            ring = `<circle cx="15" cy="15" r="13" fill="none" stroke="#dc2626" stroke-width="2" stroke-dasharray="4 3" opacity=".7"/>`;
        } else if (dispersion) {
            // Anneau pointillé rouge : positions piges divergentes (à vérifier terrain)
            ring = `<circle cx="15" cy="15" r="13" fill="none" stroke="#ef4444" stroke-width="2" stroke-dasharray="2 2" opacity=".85"/>`;
        }
        return L.divIcon({
            html: `<svg width="${size}" height="${size}" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg">
                       <circle cx="15" cy="15" r="11" fill="${color}" stroke="#fff" stroke-width="2.5"/>
                       ${ring}
                   </svg>`,
            className: 'pose-divicon',
            iconSize: [size, size],
            iconAnchor: [size/2, size/2],
        });
    }

    async function loadMarkers() {
        const loadingEl = document.getElementById('map-loading');
        const emptyEl   = document.getElementById('map-empty');
        loadingEl.style.display = 'flex';
        emptyEl.style.display   = 'none';

        const params = new URLSearchParams();
        const status = document.querySelector('.map-status-card.active')?.dataset.status || '';
        const tech   = document.getElementById('map-filter-tech').value;
        const camp   = document.getElementById('map-filter-campaign').value;
        if (status) params.set('status', status);
        if (tech)   params.set('technicien_id', tech);
        if (camp)   params.set('campaign_id', camp);

        try {
            const res = await fetch(MAP_DATA_URL + '?' + params.toString(), {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            allMarkers = data.markers || [];

            // Update counts
            document.getElementById('map-count').textContent = allMarkers.length;
            document.getElementById('count-all').textContent = allMarkers.length;
            ['planifiee','en_cours','realisee'].forEach(s => {
                const c = allMarkers.filter(m => m.status === s).length;
                const el = document.getElementById('count-' + s);
                if (el) el.textContent = c;
            });

            // Vider et rebuild
            markerCluster.clearLayers();
            const latLngs = [];
            allMarkers.forEach(m => {
                const marker = L.marker([m.lat, m.lng], {
                    icon: buildMarkerIcon(m.color, m.is_late, m.dispersion),
                });
                marker.bindPopup(buildPopup(m), { maxWidth: 300 });
                markerCluster.addLayer(marker);
                latLngs.push([m.lat, m.lng]);
            });

            // Recentrer une seule fois au 1er load
            if (latLngs.length && !initialBounds) {
                const b = L.latLngBounds(latLngs);
                map.fitBounds(b.pad(0.1));
                initialBounds = b;
            }

            if (allMarkers.length === 0) {
                emptyEl.style.display = 'flex';
            }
        } catch (e) {
            console.error('map.load.failed', e);
        } finally {
            loadingEl.style.display = 'none';
        }
    }

    // ── Filtres ──
    document.querySelectorAll('.map-status-card').forEach(el => {
        el.addEventListener('click', () => {
            document.querySelectorAll('.map-status-card').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            loadMarkers();
        });
    });
    document.querySelector('.map-status-card[data-status=""]').classList.add('active');

    document.getElementById('map-filter-tech').addEventListener('change', loadMarkers);
    document.getElementById('map-filter-campaign').addEventListener('change', loadMarkers);

    document.getElementById('map-reset-view').addEventListener('click', () => {
        if (initialBounds) map.fitBounds(initialBounds.pad(0.1));
    });

    // Chargement initial
    loadMarkers();
})();
</script>

</x-admin-layout>
