<x-admin-layout>
<x-slot name="title">Carte & Heatmap</x-slot>

<x-slot name="topbarActions">
    <button onclick="toggleView('map')"
            id="btn-map"
            class="btn btn-primary btn-sm">
        🗺️ Carte
    </button>
    <button onclick="toggleView('heatmap')"
            id="btn-heatmap"
            class="btn btn-ghost btn-sm">
        🔥 Heatmap
    </button>
</x-slot>

{{-- STATS --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:16px;">
    <div class="stat-card">
        <div class="stat-label">Total Panneaux</div>
        <div class="stat-value" id="stat-total">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Libres</div>
        <div class="stat-value" style="color:var(--green);" id="stat-libres">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Occupés</div>
        <div class="stat-value" style="color:var(--accent);" id="stat-occupes">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Maintenance</div>
        <div class="stat-value" style="color:var(--red);" id="stat-maintenance">—</div>
    </div>
</div>

{{-- CARTE --}}
<div class="card">
    <div class="card-header">
        <div class="card-title" id="map-title">🗺️ Carte des panneaux</div>
        <div style="display:flex; gap:8px;">
            <select id="filterCommune" class="filter-select" onchange="filterMap()">
                <option value="">Toutes communes</option>
                @foreach($communes as $commune)
                <option value="{{ $commune->id }}">{{ $commune->name }}</option>
                @endforeach
            </select>
            <select id="filterStatus" class="filter-select" onchange="filterMap()">
                <option value="">Tous statuts</option>
                <option value="libre">Libre</option>
                <option value="occupe">Occupé</option>
                <option value="option">Option</option>
                <option value="confirme">Confirmé</option>
                <option value="maintenance">Maintenance</option>
            </select>
        </div>
    </div>

    {{-- LÉGENDE --}}
    <div id="legende-map"
         style="display:flex; gap:16px; padding:12px 17px;
                border-bottom:1px solid var(--border); flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:12px; height:12px; border-radius:50%; background:#22c55e;"></div>
            Libre
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:12px; height:12px; border-radius:50%; background:#e8a020;"></div>
            Option
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:12px; height:12px; border-radius:50%; background:#3b82f6;"></div>
            Confirmé
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:12px; height:12px; border-radius:50%; background:#a855f7;"></div>
            Occupé
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:12px; height:12px; border-radius:50%; background:#ef4444;"></div>
            Maintenance
        </div>
    </div>

    {{-- LÉGENDE HEATMAP --}}
    <div id="legende-heatmap"
         style="display:none; gap:16px; padding:12px 17px;
                border-bottom:1px solid var(--border); flex-wrap:wrap; align-items:center;">
        <span style="font-size:12px; color:var(--text3);">Densité :</span>
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:20px; height:12px; border-radius:3px; background:#22c55e;"></div>
            Faible
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:20px; height:12px; border-radius:3px; background:#eab308;"></div>
            Moyenne
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:20px; height:12px; border-radius:3px; background:#f97316;"></div>
            Haute
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:20px; height:12px; border-radius:3px; background:#ef4444;"></div>
            Très haute
        </div>
    </div>

    {{-- MAP --}}
    <div id="map" style="height:550px; width:100%;"></div>
</div>

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

<script>
    // ── Init carte ──────────────────────────────────────────
    const map = L.map('map').setView([5.3600, -4.0083], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    let markers   = [];
    let heatLayer = null;
    let allPanels = [];
    let currentView = 'map';

    // ── Couleurs statuts ────────────────────────────────────
    function getColor(status) {
        const colors = {
            'libre':       '#22c55e',
            'option':      '#e8a020',
            'confirme':    '#3b82f6',
            'occupe':      '#a855f7',
            'maintenance': '#ef4444'
        };
        return colors[status] || '#64748b';
    }

    // ── Toggle vue ──────────────────────────────────────────
    function toggleView(view) {
        currentView = view;

        if (view === 'map') {
            document.getElementById('btn-map').className = 'btn btn-primary btn-sm';
            document.getElementById('btn-heatmap').className = 'btn btn-ghost btn-sm';
            document.getElementById('map-title').textContent = '🗺️ Carte des panneaux';
            document.getElementById('legende-map').style.display = 'flex';
            document.getElementById('legende-heatmap').style.display = 'none';
            showMarkers();
            hideHeatmap();
        } else {
            document.getElementById('btn-map').className = 'btn btn-ghost btn-sm';
            document.getElementById('btn-heatmap').className = 'btn btn-primary btn-sm';
            document.getElementById('map-title').textContent = '🔥 Heatmap densité panneaux';
            document.getElementById('legende-map').style.display = 'none';
            document.getElementById('legende-heatmap').style.display = 'flex';
            hideMarkers();
            showHeatmap();
        }
    }

    // ── Markers ─────────────────────────────────────────────
    function showMarkers() {
        markers.forEach(m => m.addTo(map));
    }

    function hideMarkers() {
        markers.forEach(m => map.removeLayer(m));
    }

    // ── Heatmap ─────────────────────────────────────────────
    function showHeatmap() {
        if (heatLayer) {
            heatLayer.addTo(map);
            return;
        }

        const points = allPanels
            .filter(p => p.latitude && p.longitude)
            .map(p => {
                // Intensité selon statut
                const intensity = p.status === 'occupe'   ? 1.0
                    : p.status === 'confirme' ? 0.9
                    : p.status === 'option'   ? 0.7
                    : p.status === 'libre'    ? 0.3
                    : 0.5;
                return [p.latitude, p.longitude, intensity];
            });

        heatLayer = L.heatLayer(points, {
            radius:    30,
            blur:      20,
            maxZoom:   17,
            gradient:  {
                0.3: '#22c55e',
                0.5: '#eab308',
                0.7: '#f97316',
                1.0: '#ef4444'
            }
        }).addTo(map);
    }

    function hideHeatmap() {
        if (heatLayer) map.removeLayer(heatLayer);
    }

    // ── Filtre et chargement ────────────────────────────────
    function filterMap() {
        const communeId = document.getElementById('filterCommune').value;
        const status    = document.getElementById('filterStatus').value;

        let url = '/admin/map/data?';
        if (communeId) url += `commune_id=${communeId}&`;
        if (status)    url += `status=${status}`;

        fetch(url)
            .then(r => r.json())
            .then(panels => {
                allPanels = panels;

                // Supprimer anciens marqueurs
                markers.forEach(m => map.removeLayer(m));
                markers = [];

                // Supprimer heatmap
                if (heatLayer) {
                    map.removeLayer(heatLayer);
                    heatLayer = null;
                }

                // Stats
                const total       = panels.length;
                const libres      = panels.filter(p => p.status === 'libre').length;
                const occupes     = panels.filter(p => ['occupe','option','confirme'].includes(p.status)).length;
                const maintenance = panels.filter(p => p.status === 'maintenance').length;

                document.getElementById('stat-total').textContent       = total;
                document.getElementById('stat-libres').textContent      = libres;
                document.getElementById('stat-occupes').textContent     = occupes;
                document.getElementById('stat-maintenance').textContent = maintenance;

                // Créer marqueurs
                panels.forEach(panel => {
                    if (!panel.latitude || !panel.longitude) return;

                    const color  = getColor(panel.status);
                    const marker = L.circleMarker(
                        [panel.latitude, panel.longitude],
                        {
                            color:       color,
                            fillColor:   color,
                            fillOpacity: 0.85,
                            radius:      9,
                            weight:      2
                        }
                    );

                    marker.bindPopup(`
                        <div style="min-width:200px; font-family:sans-serif;">
                            <div style="font-weight:800; color:${color}; font-size:14px; margin-bottom:6px;">
                                ${panel.reference}
                            </div>
                            <div style="font-size:12px; margin-bottom:4px;">
                                📍 ${panel.name}
                            </div>
                            <div style="font-size:11px; color:#64748b; margin-bottom:8px;">
                                ${panel.commune}
                            </div>
                            <div style="font-size:12px; font-weight:600; color:#e8a020;">
                                💰 ${panel.monthly_rate.toLocaleString()} FCFA/mois
                            </div>
                            <div style="margin-top:8px;">
                                <a href="/admin/panels/${panel.id}"
                                   style="font-size:11px; color:#3b82f6;">
                                    Voir la fiche →
                                </a>
                            </div>
                        </div>
                    `);

                    markers.push(marker);
                });

                // Afficher selon vue courante
                if (currentView === 'map') {
                    showMarkers();
                } else {
                    showHeatmap();
                }
            });
    }

    // Charger au démarrage
    filterMap();
</script>
@endpush

</x-admin-layout>

