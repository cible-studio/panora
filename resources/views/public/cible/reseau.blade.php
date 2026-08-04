@extends('public.cible._layout', [
    'seo_title'       => 'Notre réseau — CIBLE · 364 panneaux dans 31 communes',
    'seo_description' => 'Le réseau CIBLE : 364 panneaux publicitaires · 180 à Abidjan (14 communes) · 184 à l\'intérieur (17 villes) · Détail par commune.',
])

@push('page-css')
    .reseau-hero{background:var(--bleu);color:#fff;padding:clamp(60px,8vw,110px) var(--pad)}
    .reseau-hero .sur{color:rgba(255,255,255,.85)}
    .reseau-hero h1{margin-top:14px;color:#fff;max-width:22ch}
    .reseau-hero p{margin-top:22px;max-width:56ch;color:rgba(255,255,255,.9);font-size:18px}
    .stats{display:flex;gap:44px;flex-wrap:wrap;margin-top:44px}
    .stats .v{font-family:var(--titre);font-weight:900;font-size:clamp(46px,6vw,80px);line-height:.86;letter-spacing:-.04em}
    .stats .l{font-family:var(--titre);font-weight:600;font-size:13px;opacity:.9;margin-top:10px;text-transform:uppercase;letter-spacing:.08em}

    .carte-section{padding:clamp(56px,8vw,100px) var(--pad)}
    .carte-slot{aspect-ratio:16/10;border-radius:26px;overflow:hidden;background:var(--gris);position:relative;box-shadow:0 20px 60px -30px rgba(0,0,0,.3)}
    .carte-slot .note{position:absolute;bottom:16px;left:16px;right:16px;background:rgba(255,255,255,.94);padding:12px 16px;border-radius:10px;font-size:13px;color:#666;font-family:var(--titre);font-weight:600;z-index:1000;pointer-events:none}
    /* Carte Leaflet 2026-08-04 — remplace le placeholder */
    #reseau-map{width:100%;height:100%}
    #reseau-map-loading{
        position:absolute;inset:0;display:flex;align-items:center;
        justify-content:center;flex-direction:column;gap:10px;
        background:var(--gris);color:#666;font-family:var(--titre);
        font-weight:700;font-size:14px;z-index:500;
    }
    #reseau-map-loading .spinner{
        width:36px;height:36px;border:3px solid rgba(0,0,0,.1);
        border-top-color:var(--bleu);border-radius:50%;
        animation:reseau-spin .9s linear infinite;
    }
    @keyframes reseau-spin{to{transform:rotate(360deg)}}
    #reseau-map-loading.hidden{display:none}
    /* Style des pins CIBLE — pastilles jaune/dorées avec chiffre panneaux */
    .cible-pin{
        background:var(--jaune);color:var(--noir);
        padding:6px 12px;border-radius:20px;
        font-family:var(--titre);font-weight:800;font-size:13px;
        white-space:nowrap;box-shadow:0 3px 10px rgba(0,0,0,.25);
        border:2px solid #fff;
        transform:translate(-50%,-50%);
    }
    .cible-pin b{color:var(--rouge)}
    .leaflet-popup-content-wrapper{
        border-radius:12px;font-family:var(--corps);
    }
    .leaflet-popup-content{
        margin:14px 16px;font-size:13px;line-height:1.5;
    }
    .leaflet-popup-content strong{
        display:block;font-family:var(--titre);font-weight:800;
        font-size:15px;color:var(--bleu);margin-bottom:4px;
    }

    .communes{padding:clamp(56px,8vw,100px) var(--pad);background:var(--gris)}
    .communes .entete{max-width:640px;margin:0 auto 44px;text-align:center}
    .communes .sur{color:var(--vert)}
    .communes-grid{display:grid;grid-template-columns:1fr 1fr;gap:clamp(20px,3vw,40px)}
    @media(max-width:800px){.communes-grid{grid-template-columns:1fr}}
    .zone{background:#fff;border-radius:16px;padding:32px;border-top:5px solid var(--c)}
    .zone h3{font-family:var(--titre);font-weight:900;font-size:26px;color:var(--c);margin-bottom:6px}
    .zone .zone-sub{font-family:var(--titre);font-weight:600;font-size:14px;color:#666;margin-bottom:20px}
    .zone-list{list-style:none;display:grid;grid-template-columns:repeat(2,1fr);gap:8px 20px;font-size:14.5px;color:#333}
    .zone-list li{padding:6px 0;border-bottom:1px dashed #E4E4E4;font-family:var(--titre);font-weight:600}

    .qualite{padding:clamp(56px,8vw,100px) var(--pad)}
    .qualite .entete{max-width:640px;margin:0 auto 48px;text-align:center}
    .qualite .sur{color:var(--rouge)}
    .q-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(18px,3vw,32px)}
    @media(max-width:900px){.q-grid{grid-template-columns:1fr}}
    .qcard{padding:28px;background:#fff;border:1px solid var(--gris);border-radius:16px}
    .qcard h4{font-family:var(--titre);font-weight:800;font-size:18px;margin-bottom:10px;color:var(--c)}
    .qcard p{font-size:14.5px;color:#555;line-height:1.6}
@endpush

@section('content')

<section class="reseau-hero">
    <div>
        <span class="sur">La preuve terrain</span>
        <h1 class="t1">Un réseau d'affichage en propre, à Abidjan et dans tout le pays.</h1>
        <p>Une agence loue l'espace d'un tiers. Nous exploitons le nôtre : 364 panneaux répartis dans 31 communes, de Bouaké à San-Pédro. C'est ce qui nous permet de vous garantir un emplacement, une date de pose et une preuve photo — pas une estimation.</p>
        <div class="stats">
            <div><div class="v num" data-cible="364">0</div><div class="l">Panneaux au total<br>31 communes</div></div>
            <div><div class="v num" data-cible="180">0</div><div class="l">Panneaux · Abidjan<br>14 communes</div></div>
            <div><div class="v num" data-cible="184">0</div><div class="l">Panneaux · Intérieur<br>17 villes</div></div>
        </div>
    </div>
</section>

<section class="carte-section">
    <div class="carte-slot rev">
        <div id="reseau-map" aria-label="Carte interactive du réseau CIBLE"></div>
        <div id="reseau-map-loading" role="status">
            <div class="spinner" aria-hidden="true"></div>
            <div>Chargement de la carte…</div>
        </div>
        <div class="note">💡 Cliquez sur un pin pour voir le nombre de panneaux par commune. Détail complet auprès de votre commercial.</div>
    </div>
</section>

<section class="communes">
    <div class="entete rev">
        <span class="sur">Détail par zone</span>
        <h2 class="t1">Là où votre marque peut apparaître.</h2>
    </div>
    <div class="communes-grid">
        <div class="zone rev" style="--c:var(--rouge)">
            <h3>Zone Abidjan</h3>
            <div class="zone-sub">180 panneaux · 14 communes du Grand Abidjan</div>
            <ul class="zone-list">
                <li>Plateau</li><li>Cocody</li>
                <li>Yopougon</li><li>Abobo</li>
                <li>Marcory</li><li>Treichville</li>
                <li>Koumassi</li><li>Port-Bouët</li>
                <li>Attécoubé</li><li>Adjamé</li>
                <li>Riviera</li><li>Angré</li>
                <li>Bingerville</li><li>Songon</li>
            </ul>
        </div>
        <div class="zone rev" style="--c:var(--vert)">
            <h3>Zone Intérieur</h3>
            <div class="zone-sub">184 panneaux · 17 villes stratégiques du pays</div>
            <ul class="zone-list">
                <li>Bouaké</li><li>San-Pédro</li>
                <li>Yamoussoukro</li><li>Korhogo</li>
                <li>Man</li><li>Daloa</li>
                <li>Gagnoa</li><li>Divo</li>
                <li>Bondoukou</li><li>Odienné</li>
                <li>Séguéla</li><li>Ferkessédougou</li>
                <li>Dabou</li><li>Anyama</li>
                <li>Grand-Bassam</li><li>Aboisso</li>
                <li>Soubré</li>
            </ul>
        </div>
    </div>
</section>

<section class="qualite">
    <div class="entete rev">
        <span class="sur">Ce qui distingue notre réseau</span>
        <h2 class="t1">Un patrimoine géré, pas revendu.</h2>
    </div>
    <div class="q-grid">
        <div class="qcard rev" style="--c:var(--rouge)">
            <h4>Emplacements en propre</h4>
            <p>Nous n'agrégeons pas des panneaux tiers : nous exploitons notre patrimoine. Vous savez exactement où votre affiche apparaîtra, dans quel angle, à quel moment.</p>
        </div>
        <div class="qcard rev" style="--c:var(--vert)">
            <h4>Maintenance permanente</h4>
            <p>Équipes de pose sur toutes les zones. Une affiche déchirée ou taguée est remplacée dans les 48h. La qualité de votre visibilité ne dépend pas d'un sous-traitant.</p>
        </div>
        <div class="qcard rev" style="--c:var(--bleu)">
            <h4>Preuve photo horodatée</h4>
            <p>Chaque pose est documentée sur le terrain : photo, date, heure, GPS. Vous recevez le dossier complet à la fin de la campagne.</p>
        </div>
    </div>
</section>

<section style="padding:clamp(60px,8vw,100px) var(--pad);text-align:center">
    <h2 class="t2">Envie de repérer les emplacements pour votre marque&nbsp;?</h2>
    <p style="margin-top:16px;color:#666;max-width:56ch;margin-left:auto;margin-right:auto">Envoyez-nous vos critères (zone, format, période) — nous vous préparons une sélection dans la journée.</p>
    <div style="margin-top:28px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a class="bouton b-rouge" href="{{ route('cible.contact') }}">Demander une sélection</a>
    </div>
</section>

@endsection

@push('head')
    {{-- Leaflet 1.9.4 (même version que l'admin — cf. resources/views/admin/panels/map.blade.php).
         CSS chargé dans le head, JS déféré en bas de page via @push('page-js'). --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@push('page-js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""
        defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapEl = document.getElementById('reseau-map');
    if (!mapEl || typeof L === 'undefined') return;

    // Init map — centre Abidjan, bounds Côte d'Ivoire, tiles CARTO light
    // (cohérent avec la carte admin cf. admin/panels/map.blade.php).
    const map = L.map(mapEl, {
        center: [5.36, -4.01],
        zoom: 11,
        minZoom: 6,
        maxZoom: 15,
        maxBounds: L.latLngBounds(L.latLng(4.3, -8.6), L.latLng(10.7, -2.5)),
        maxBoundsViscosity: 0.85,
        scrollWheelZoom: false, // désactivé côté vitrine — évite le zoom accidentel en scroll
        zoomControl: true,
    });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> · © <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 19,
    }).addTo(map);

    // Réactive la molette souris uniquement quand la carte a le focus
    // (clic dessus). Évite les scrolls "captés" involontairement.
    mapEl.addEventListener('click', () => map.scrollWheelZoom.enable());
    mapEl.addEventListener('mouseleave', () => map.scrollWheelZoom.disable());

    // Chargement des pins agrégés par commune
    fetch("{{ route('cible.api.reseau-map') }}", { headers: { 'Accept': 'application/json' } })
        .then(r => r.ok ? r.json() : Promise.reject(r.status))
        .then(data => {
            const pins = Array.isArray(data.pins) ? data.pins : [];
            if (pins.length === 0) {
                document.getElementById('reseau-map-loading').innerHTML =
                    '<div>Aucune donnée à afficher pour le moment.</div>';
                return;
            }

            const bounds = L.latLngBounds();
            pins.forEach(pin => {
                if (pin.lat == null || pin.lng == null) return;
                const html = '<div class="cible-pin"><b>' + pin.total + '</b> · ' + pin.commune + '</div>';
                const icon = L.divIcon({
                    html: html,
                    className: 'cible-pin-wrapper',
                    iconSize: null, // laisse le contenu déterminer la taille
                });
                const marker = L.marker([pin.lat, pin.lng], { icon: icon }).addTo(map);
                const zoneLabel = (pin.city && pin.city !== pin.commune) ? pin.city : (pin.region || '');
                marker.bindPopup(
                    '<strong>' + pin.commune + '</strong>' +
                    (zoneLabel ? '<em style="color:#666;font-style:normal">' + zoneLabel + '</em><br>' : '') +
                    pin.total + ' panneau' + (pin.total > 1 ? 'x' : '') + ' CIBLE'
                );
                bounds.extend([pin.lat, pin.lng]);
            });

            // Cadre auto sur l'ensemble des pins (pas trop serré → padding).
            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 });
            }

            document.getElementById('reseau-map-loading').classList.add('hidden');
        })
        .catch(err => {
            console.warn('[cible/reseau] map load failed', err);
            const loader = document.getElementById('reseau-map-loading');
            if (loader) {
                loader.innerHTML = '<div>Chargement de la carte impossible.<br><small>Notre équipe reste disponible : commercial@cible-ci.com</small></div>';
            }
        });
});
</script>
@endpush
