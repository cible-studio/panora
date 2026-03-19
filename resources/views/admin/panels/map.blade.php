<x-admin-layout>
<x-slot name="title">Carte & Heatmap</x-slot>

<div class="card">
    <div class="card-header">
        <div class="card-title">🗺️ Carte des panneaux</div>
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
    <div style="display:flex; gap:16px; padding:12px 17px; border-bottom:1px solid var(--border); flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:12px; height:12px; border-radius:50%; background:#22c55e;"></div> Libre
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:12px; height:12px; border-radius:50%; background:#e8a020;"></div> Option
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:12px; height:12px; border-radius:50%; background:#3b82f6;"></div> Confirmé
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:12px; height:12px; border-radius:50%; background:#a855f7;"></div> Occupé
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <div style="width:12px; height:12px; border-radius:50%; background:#ef4444;"></div> Maintenance
        </div>
    </div>

    {{-- CARTE --}}
    <div id="map" style="height:550px; width:100%;"></div>
</div>

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // Initialiser carte centrée sur Abidjan
    const map = L.map('map').setView([5.3600, -4.0083], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    let markers = [];

    function getColor(status) {
        const colors = {
            'libre': '#22c55e',
            'option': '#e8a020',
            'confirme': '#3b82f6',
            'occupe': '#a855f7',
            'maintenance': '#ef4444'
        };
        return colors[status] || '#64748b';
    }

    function filterMap() {
        const communeId = document.getElementById('filterCommune').value;
        const status    = document.getElementById('filterStatus').value;

        let url = '/admin/map/data?';
        if (communeId) url += `commune_id=${communeId}&`;
        if (status)    url += `status=${status}`;

        fetch(url)
            .then(r => r.json())
            .then(panels => {
                // Supprimer anciens marqueurs
                markers.forEach(m => map.removeLayer(m));
                markers = [];

                // Ajouter nouveaux
                panels.forEach(panel => {
                    const color  = getColor(panel.status);
                    const marker = L.circleMarker(
                        [panel.latitude, panel.longitude],
                        { color: color, fillColor: color, fillOpacity: 0.8, radius: 8 }
                    );

                    marker.bindPopup(`
                        <div style="min-width:180px;">
                            <strong style="color:${color}">${panel.reference}</strong><br>
                            ${panel.name}<br>
                            <small>${panel.commune}</small><br>
                            <strong>${panel.monthly_rate.toLocaleString()} FCFA/mois</strong>
                        </div>
                    `);

                    marker.addTo(map);
                    markers.push(marker);
                });
            });
    }

    // Charger tous les panneaux au démarrage
    filterMap();
</script>
@endpush

</x-admin-layout>

