<x-admin-layout>
<x-slot name="title">Carte & Heatmap</x-slot>

<x-slot name="topbarActions">
    <button onclick="toggleView('map')" id="btn-map" class="btn btn-primary btn-sm">🗺️ Carte</button>
    <button onclick="toggleView('heatmap')" id="btn-heatmap" class="btn btn-ghost btn-sm">🔥 Heatmap</button>
</x-slot>

{{-- STATS CLIQUABLES --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:16px;">
    <div class="stat-card" onclick="filterByStatus('')" id="card-all"
         style="cursor:pointer;transition:all .15s;border:2px solid transparent;"
         onmouseover="this.style.borderColor='var(--accent)'"
         onmouseout="if(!this.classList.contains('active-card')) this.style.borderColor='transparent'">
        <div class="stat-label">Total Panneaux</div>
        <div class="stat-value" id="stat-total">—</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Voir tous →</div>
    </div>
    <div class="stat-card" onclick="filterByStatus('libre')" id="card-libre"
         style="cursor:pointer;transition:all .15s;border:2px solid transparent;"
         onmouseover="this.style.borderColor='#3aa835'"
         onmouseout="if(!this.classList.contains('active-card')) this.style.borderColor='transparent'">
        <div class="stat-label">Libres</div>
        <div class="stat-value" style="color:#3aa835;" id="stat-libres">—</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Filtrer →</div>
    </div>
    <div class="stat-card" onclick="filterByStatus('occupe')" id="card-occupe"
         style="cursor:pointer;transition:all .15s;border:2px solid transparent;"
         onmouseover="this.style.borderColor='var(--accent)'"
         onmouseout="if(!this.classList.contains('active-card')) this.style.borderColor='transparent'">
        <div class="stat-label">Occupés</div>
        <div class="stat-value" style="color:var(--accent);" id="stat-occupes">—</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Filtrer →</div>
    </div>
    <div class="stat-card" onclick="filterByStatus('maintenance')" id="card-maintenance"
         style="cursor:pointer;transition:all .15s;border:2px solid transparent;"
         onmouseover="this.style.borderColor='#e20613'"
         onmouseout="if(!this.classList.contains('active-card')) this.style.borderColor='transparent'">
        <div class="stat-label">Maintenance</div>
        <div class="stat-value" style="color:#e20613;" id="stat-maintenance">—</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Filtrer →</div>
    </div>
</div>

{{-- CARTE --}}
<div class="card" style="overflow:hidden;">
    <div class="card-header">
        <div class="card-title" id="map-title">🗺️ Carte des panneaux — Côte d'Ivoire</div>
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
    <div id="legende-map" style="display:flex; gap:16px; padding:10px 17px; border-bottom:1px solid var(--border); flex-wrap:wrap; background:var(--surface2);">
        @foreach(['Libre' => '#3aa835', 'Option' => '#fab80b', 'Confirmé' => '#3f7fc0', 'Occupé' => '#81358a', 'Maintenance' => '#e20613'] as $label => $color)
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:12px; height:12px; border-radius:50%; background:{{ $color }}; box-shadow:0 0 4px {{ $color }}60;"></div>
            {{ $label }}
        </div>
        @endforeach
    </div>

    {{-- LÉGENDE HEATMAP --}}
    <div id="legende-heatmap" style="display:none; gap:16px; padding:10px 17px; border-bottom:1px solid var(--border); flex-wrap:wrap; align-items:center; background:var(--surface2);">
        <span style="font-size:12px; color:var(--text3);">Densité :</span>
        @foreach(['Faible' => '#3aa835', 'Moyenne' => '#fab80b', 'Haute' => '#f97316', 'Très haute' => '#e20613'] as $label => $color)
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:24px; height:10px; border-radius:3px; background:{{ $color }};"></div>
            {{ $label }}
        </div>
        @endforeach
    </div>

    {{-- MAP --}}
    <div id="map" style="height:580px; width:100%;"></div>
</div>

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

<style>
.leaflet-popup-content-wrapper { background:#111318 !important; border:1px solid #2a303f !important; border-radius:12px !important; box-shadow:0 8px 32px rgba(0,0,0,0.6) !important; padding:0 !important; }
.leaflet-popup-content { margin:0 !important; padding:0 !important; }
.leaflet-popup-tip { background:#111318 !important; }
.leaflet-popup-close-button { color:#8a90a2 !important; font-size:18px !important; top:8px !important; right:10px !important; }
.leaflet-popup-close-button:hover { color:#e20613 !important; }
.leaflet-bar a { background:#fff !important; color:#333 !important; border-color:#ccc !important; }
.leaflet-bar a:hover { background:#e20613 !important; color:#fff !important; }
.active-card { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.2) !important; }

/* ── PIN PANNEAU CUSTOM (style Google Maps épuré) ─────────────── */
.panel-pin {
    background: transparent !important;
    border: none !important;
    transition: transform .15s ease, filter .15s ease;
}
.panel-pin-bubble {
    background: #facc15;
    color: #111;
    border: 1.5px solid #111;
    border-radius: 4px;
    padding: 4px 9px;
    font-size: 11.5px;
    font-weight: 700;
    white-space: nowrap;
    text-align: center;
    line-height: 1.15;
    font-family: 'DM Sans', system-ui, sans-serif;
    letter-spacing: .1px;
    filter: drop-shadow(0 2px 3px rgba(0,0,0,.35));
}
/* Petite pointe sous la bulle — bordure noire + remplissage jaune. */
.panel-pin-arrow {
    width: 10px;
    height: 6px;
    margin: 0 auto;
    position: relative;
}
.panel-pin-arrow::before,
.panel-pin-arrow::after {
    content: '';
    position: absolute;
    left: 0; top: 0;
    width: 0; height: 0;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
}
/* Triangle noir (le bord) */
.panel-pin-arrow::before {
    border-top: 7px solid #111;
    top: -1px;
}
/* Triangle jaune par-dessus, légèrement plus petit (le remplissage) */
.panel-pin-arrow::after {
    border-top: 5px solid #facc15;
    left: 1px;
}

/* Hover : la bulle se soulève légèrement, ombre plus prononcée. */
.panel-pin:hover {
    transform: translateY(-3px);
    filter: drop-shadow(0 6px 10px rgba(0,0,0,.45));
    z-index: 1000;
}

/* Indicateur statut discret en pastille en coin haut-droit. */
.panel-pin-dot {
    position: absolute;
    top: -3px; right: -3px;
    width: 9px; height: 9px;
    border-radius: 50%;
    border: 1.5px solid #fff;
    box-shadow: 0 1px 2px rgba(0,0,0,.35);
}
.panel-pin-dot[data-status="libre"]       { background: #16a34a; }
.panel-pin-dot[data-status="occupe"],
.panel-pin-dot[data-status="confirme"],
.panel-pin-dot[data-status="option"]      { background: #ea580c; }
.panel-pin-dot[data-status="maintenance"] { background: #dc2626; }
</style>

<script>
const CI_BOUNDS = L.latLngBounds(L.latLng(4.3, -8.6), L.latLng(10.7, -2.5));
const ABIDJAN   = [5.3600, -4.0083];

const map = L.map('map', {
    center: ABIDJAN, zoom: 12, minZoom: 6, maxZoom: 18,
    maxBounds: CI_BOUNDS, maxBoundsViscosity: 0.85,
});

L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '© OpenStreetMap © CARTO', subdomains: 'abcd', maxZoom: 19
}).addTo(map);

let markers = [], heatLayer = null, allPanels = [], currentView = 'map';

function getColor(status) {
    return { libre:'#3aa835', option:'#fab80b', confirme:'#3f7fc0', occupe:'#81358a', maintenance:'#e20613' }[status] || '#64748b';
}
function getLabel(status) {
    return { libre:'Libre', option:'Option', confirme:'Confirmé', occupe:'Occupé', maintenance:'Maintenance' }[status] || status;
}

// Filtre depuis les cards
function filterByStatus(status) {
    // Mettre à jour le select
    document.getElementById('filterStatus').value = status;

    // Style cards actives
    ['card-all','card-libre','card-occupe','card-maintenance'].forEach(id => {
        const el = document.getElementById(id);
        el.classList.remove('active-card');
        el.style.borderColor = 'transparent';
    });

    const cardMap = { '': 'card-all', 'libre': 'card-libre', 'occupe': 'card-occupe', 'maintenance': 'card-maintenance' };
    const activeCard = document.getElementById(cardMap[status] || 'card-all');
    if (activeCard) {
        activeCard.classList.add('active-card');
        const colors = { 'card-all':'var(--accent)', 'card-libre':'#3aa835', 'card-occupe':'var(--accent)', 'card-maintenance':'#e20613' };
        activeCard.style.borderColor = colors[cardMap[status]] || 'var(--accent)';
    }

    filterMap();
}

function toggleView(view) {
    currentView = view;
    const isMap = view === 'map';
    document.getElementById('btn-map').className     = isMap ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm';
    document.getElementById('btn-heatmap').className = isMap ? 'btn btn-ghost btn-sm' : 'btn btn-primary btn-sm';
    document.getElementById('map-title').textContent = isMap ? "🗺️ Carte des panneaux — Côte d'Ivoire" : '🔥 Heatmap densité panneaux';
    document.getElementById('legende-map').style.display     = isMap ? 'flex' : 'none';
    document.getElementById('legende-heatmap').style.display = isMap ? 'none' : 'flex';
    isMap ? (showMarkers(), hideHeatmap()) : (hideMarkers(), showHeatmap());
}

function showMarkers() { markers.forEach(m => m.addTo(map)); }
function hideMarkers()  { markers.forEach(m => map.removeLayer(m)); }

function showHeatmap() {
    if (heatLayer) { heatLayer.addTo(map); return; }
    const points = allPanels.filter(p => p.latitude && p.longitude)
        .map(p => [p.latitude, p.longitude,
            p.status === 'occupe' ? 1.0 : p.status === 'confirme' ? 0.9 :
            p.status === 'option' ? 0.7 : p.status === 'libre' ? 0.3 : 0.5]);
    heatLayer = L.heatLayer(points, {
        radius: 30, blur: 20, maxZoom: 17,
        gradient: { 0.3:'#3aa835', 0.5:'#fab80b', 0.7:'#f97316', 1.0:'#e20613' }
    }).addTo(map);
}
function hideHeatmap() { if (heatLayer) map.removeLayer(heatLayer); }

function buildPopup(panel) {
    const color = getColor(panel.status);
    const label = getLabel(panel.status);
    return `<div style="width:220px;font-family:'DM Sans',sans-serif;border-radius:12px;overflow:hidden;">
        <div style="background:${color}22;border-bottom:2px solid ${color};padding:12px 14px;">
            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:15px;color:${color};">${panel.reference}</div>
            <div style="font-size:12px;color:#eaedf5;margin-top:3px;font-weight:500;">${panel.name}</div>
        </div>
        <div style="padding:12px 14px;display:flex;flex-direction:column;gap:8px;">
            <div style="display:flex;align-items:center;gap:8px;"><span>📍</span><span style="font-size:12px;color:#8a90a2;">${panel.commune}</span></div>
            <div style="display:flex;align-items:center;gap:8px;"><span>💰</span><span style="font-size:12px;font-weight:700;color:#fab80b;">${Number(panel.monthly_rate).toLocaleString()} FCFA/mois</span></div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;">
                <span style="font-size:11px;padding:3px 10px;border-radius:20px;background:${color}20;color:${color};border:1px solid ${color}50;font-weight:600;">${label}</span>
                <a href="/admin/panels/${panel.id}" style="font-size:11px;color:#3f7fc0;font-weight:600;text-decoration:none;">Voir →</a>
            </div>
        </div>
    </div>`;
}

function filterMap() {
    const communeId = document.getElementById('filterCommune').value;
    const status    = document.getElementById('filterStatus').value;
    let url = '/admin/map/data?';
    if (communeId) url += `commune_id=${communeId}&`;
    if (status)    url += `status=${status}`;

    fetch(url).then(r => r.json()).then(panels => {
        allPanels = panels;
        markers.forEach(m => map.removeLayer(m));
        markers = [];
        if (heatLayer) { map.removeLayer(heatLayer); heatLayer = null; }

        document.getElementById('stat-total').textContent       = panels.length;
        document.getElementById('stat-libres').textContent      = panels.filter(p => p.status === 'libre').length;
        document.getElementById('stat-occupes').textContent     = panels.filter(p => ['occupe','option','confirme'].includes(p.status)).length;
        document.getElementById('stat-maintenance').textContent = panels.filter(p => p.status === 'maintenance').length;

        panels.forEach(panel => {
            if (!panel.latitude || !panel.longitude) return;

            // Label : priorité catégorie (ex "Chevalet"), sinon surface (ex "12m²"),
            // sinon format brut, sinon référence courte. Tronqué à 14 chars max
            // pour éviter les bulles trop larges sur la carte.
            let label = panel.category
                || (panel.surface ? `${Math.round(panel.surface)}m²` : null)
                || panel.format
                || panel.reference;
            if (label && label.length > 14) label = label.slice(0, 12) + '…';

            // Largeur dynamique : ~7.5px par caractère + padding, bornée 40-150px.
            const w = Math.min(150, Math.max(40, label.length * 7.5 + 18));
            const h = 30;

            const icon = L.divIcon({
                className: 'panel-pin',
                html: `
                    <div class="panel-pin-bubble">${label}</div>
                    <div class="panel-pin-arrow"></div>
                    <span class="panel-pin-dot" data-status="${panel.status}"></span>
                `,
                iconSize:   [w, h],
                iconAnchor: [w / 2, h],   // pointe en bas-centre sur le lat/lng
            });
            const marker = L.marker([panel.latitude, panel.longitude], { icon, riseOnHover: true });
            marker.on('add', function () {
                const el = this.getElement();
                if (el) el.setAttribute('data-status', panel.status);
            });
            marker.bindPopup(buildPopup(panel), { maxWidth: 240 });
            markers.push(marker);
        });

        const withCoords = panels.filter(p => p.latitude && p.longitude);
        if (withCoords.length > 0) {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1), { maxZoom: 14 });
        }

        currentView === 'map' ? showMarkers() : showHeatmap();
    });
}

// Activer "Tous" par défaut
filterByStatus('');
</script>
@endpush

</x-admin-layout>
