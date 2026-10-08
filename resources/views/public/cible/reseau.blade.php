@extends('public.cible._coque', ['titre' => 'Le réseau', 'actuelle' => 'reseau'])

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@push('css')
.tete{padding:clamp(130px,17vh,200px) var(--pad) clamp(44px,6vw,76px);position:relative;overflow:hidden}
.tete .sur{--c:var(--rouge)}
.tete__t{margin-top:20px}
.tete__t em{font-style:normal;color:var(--rouge)}
.tete__grille{display:grid;grid-template-columns:1.1fr .9fr;gap:clamp(28px,4vw,64px);align-items:end}
@media(max-width:900px){.tete__grille{grid-template-columns:1fr;gap:30px}}
.mini{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.mini > div{padding-top:16px;border-top:2px solid var(--c)}
.mini .v{font-family:var(--titre);font-weight:900;font-size:clamp(32px,4.4vw,56px);line-height:.9;letter-spacing:-.04em}
.mini .l{font-family:var(--titre);font-weight:700;font-size:12.5px;color:var(--texte-2);margin-top:9px}

/* La carte devient le héros de la page : plein écran, le reste autour. */
.carte-zone{position:relative;height:min(84vh,780px);background:var(--fond-3);overflow:hidden}
#carte{position:absolute;inset:0;z-index:1}
.carte-zone__voile{
  position:absolute;z-index:400;inset:auto 0 0 0;height:160px;pointer-events:none;
  background:none;
}
.carte-zone__note{
  position:absolute;z-index:500;left:var(--pad);bottom:28px;max-width:330px;
  padding:17px 19px;border-radius:14px;
  background:rgba(255,255,255,.93);backdrop-filter:blur(10px);
  box-shadow:inset 0 0 0 1px var(--ligne);
  font-size:13.5px;color:var(--texte-2);line-height:1.5;
}
.carte-zone__note b{display:block;font-family:var(--titre);font-weight:800;font-size:14.5px;color:var(--noir);margin-bottom:5px}
.carte-chargement{
  position:absolute;inset:0;z-index:600;display:grid;place-items:center;gap:12px;
  background:var(--fond-3);color:var(--texte-3);
  font-family:var(--titre);font-weight:700;font-size:13.5px;
}
.carte-chargement[hidden]{display:none}
.tourne{width:34px;height:34px;border:2.5px solid rgba(17,17,17,.12);border-top-color:var(--vert);border-radius:50%;animation:tourner .9s linear infinite;margin-inline:auto}
@keyframes tourner{to{transform:rotate(360deg)}}
/* Épingles aux couleurs de la charte */
.epingle{
  background:var(--jaune);color:var(--noir);
  padding:6px 12px;border-radius:999px;border:2px solid #fff;
  font-family:var(--titre);font-weight:800;font-size:12.5px;white-space:nowrap;
  box-shadow:0 4px 14px rgba(0,0,0,.4);transform:translate(-50%,-50%);
}
.leaflet-popup-content-wrapper{border-radius:12px}
.leaflet-popup-content{margin:13px 15px;font-family:'Nunito',sans-serif;font-size:13px}
.leaflet-popup-content strong{display:block;font-family:'Poppins',sans-serif;font-weight:800;font-size:15px;color:#111;margin-bottom:3px}

/* Zones couvertes */
.zones{display:grid;grid-template-columns:1fr 1fr;gap:clamp(20px,3vw,44px);margin-top:clamp(36px,5vw,60px)}
@media(max-width:860px){.zones{grid-template-columns:1fr}}
.zone{padding:clamp(26px,3vw,38px);border-radius:20px;background:var(--fond-2);border-top:4px solid var(--c)}
.zone h3{font-family:var(--titre);font-weight:900;font-size:clamp(22px,2.6vw,32px);letter-spacing:-.02em;color:var(--noir)}
.zone .sous{font-family:var(--titre);font-weight:700;font-size:13px;color:var(--texte-3);margin-top:7px}
.zone ul{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:7px 18px;margin-top:22px}
@media(max-width:520px){.zone ul{grid-template-columns:1fr}}
.zone li{
  font-family:var(--titre);font-weight:700;font-size:14px;
  padding:7px 0;border-bottom:1px solid var(--ligne);color:var(--texte-2);
}

/* Formats */
.fmt{background:var(--fond-2);border-block:1px solid var(--ligne)}
.fmt__grille{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(14px,2vw,24px);margin-top:clamp(34px,4.5vw,56px)}
@media(max-width:900px){.fmt__grille{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.fmt__grille{grid-template-columns:1fr}}
.fmt__c{padding:28px 26px;border-radius:17px;background:var(--fond-3);border-left:4px solid var(--c);transition:transform .45s var(--ease)}
.fmt__c:hover{transform:translateY(-5px)}
.fmt__c h3{font-family:var(--titre);font-weight:800;font-size:18px;color:var(--noir)}
.fmt__c p{margin-top:10px;font-size:14.5px;color:var(--texte-2);line-height:1.6}
@endpush

@section('contenu')

{{-- ═══════════════════════ TÊTE ═══════════════════════ --}}
<section class="tete">
    <div class="plume" style="--c:var(--vert);--op:.07;top:-10%;right:-5%;width:clamp(210px,27vw,380px)" data-par="-18" data-rot="10"></div>
    <div class="large tete__grille">
        <div>
            <p class="sur" data-rev>Le réseau</p>
            <h1 class="t-geant tete__t" data-lignes>On ne loue pas la rue. <em>On l'habite.</em></h1>
            <p class="intro" style="margin-top:24px" data-rev=".12">
                Trente ans à choisir les emplacements un par un, en fonction des flux réels :
                axes de sortie, marchés, carrefours, zones de concentration. C'est ce choix-là
                qu'on vous vend, pas une surface.
            </p>
        </div>
        <div class="mini" data-cascade>
            <div style="--c:var(--rouge)">
                <div class="v num">+<span data-compte="{{ \App\Support\Contenu::get('chiffres.panneaux', 400) }}">0</span></div>
                <div class="l">Panneaux en exploitation</div>
            </div>
            <div style="--c:var(--jaune)">
                <div class="v num"><span data-compte="{{ \App\Support\Contenu::get('chiffres.communes', 31) }}">0</span></div>
                <div class="l">Communes et villes</div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════ CARTE ═══════════════════════ --}}
<section class="carte-zone" aria-label="Carte du parc d'affichage">
    <div id="carte" role="application" aria-label="Carte interactive des communes couvertes"></div>
    <div class="carte-chargement" id="carte-chargement">
        <div style="text-align:center">
            <div class="tourne"></div>
            <div style="margin-top:12px">Chargement de la carte…</div>
        </div>
    </div>
    <div class="carte-zone__note">
        <b>31 communes couvertes</b>
        Chaque épingle marque une commune où CIBLE exploite des faces.
        Déplacez la carte pour parcourir le territoire.
    </div>
    <div class="carte-zone__voile"></div>
</section>

{{-- ═══════════════════════ ZONES ═══════════════════════ --}}
<section class="bloc">
    <div class="large">
        <div class="entete">
            <p class="sur" style="--c:var(--rouge)">La couverture</p>
            <h2 class="t-grand" data-lignes>Abidjan d'abord. Puis tout le pays.</h2>
        </div>

        <div class="zones">
            <div class="zone" style="--c:var(--rouge)" data-rev>
                <h3>{{ \App\Support\Contenu::get('reseau.comm_abidjan_titre', 'Abidjan') }}</h3>
                <div class="sous">{{ \App\Support\Contenu::get('reseau.comm_abidjan_sous', '13 communes') }}</div>
                <ul>
                    @foreach(\App\Support\Contenu::lignes('reseau.comm_abidjan_liste') as $c)
                        <li>{{ $c }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="zone" style="--c:var(--jaune)" data-rev=".08">
                <h3>{{ \App\Support\Contenu::get('reseau.comm_int_titre', 'Intérieur du pays') }}</h3>
                <div class="sous">{{ \App\Support\Contenu::get('reseau.comm_int_sous', '18 villes') }}</div>
                <ul>
                    @foreach(\App\Support\Contenu::lignes('reseau.comm_int_liste') as $c)
                        <li>{{ $c }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════ FORMATS ═══════════════════════ --}}
<section class="bloc fmt">
    <div class="large">
        <div class="entete">
            <p class="sur" style="--c:var(--jaune)">Les formats</p>
            <h2 class="t-grand" data-lignes>Le bon support, pas le plus grand.</h2>
        </div>
        <div class="fmt__grille" data-cascade>
            @foreach([
                ['var(--rouge)',  'Panneaux classiques',  "Le socle du réseau. Présence continue sur les axes à fort trafic, en 4×3 et grands formats."],
                ['var(--jaune)',  'Lumipub',              "Caissons éclairés : votre message reste lisible après la tombée de la nuit, quand le trafic est encore dense."],
                ['var(--rouge)',  'Trivision',            "Trois visuels en rotation sur une même face. Trois messages, ou trois annonceurs, un seul emplacement."],
                ['var(--jaune)',  'Panoramiques',         "Les très grands formats, sur les axes d'entrée et de sortie d'Abidjan. Pour les prises de parole fortes."],
                ['var(--bleu)',   'Écrans digitaux',      "Diffusion animée et programmable. Idéal pour une campagne à durée courte ou à message variable."],
                ['var(--rouge)',  'Affichage en magasin', "Au dernier mètre, là où la décision d'achat se prend réellement."],
            ] as [$c, $titre, $txt])
                <div class="fmt__c" style="--c:{{ $c }}">
                    <h3>{{ $titre }}</h3>
                    <p>{{ $txt }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════ APPEL ═══════════════════════ --}}
<section class="bloc" style="text-align:center;position:relative;overflow:hidden">
    <div class="fleche-d" style="--c:var(--jaune);--op:.09;top:22%;left:8%;width:clamp(80px,10vw,140px)" data-par="-22" data-rot="-16"></div>
    <div style="max-width:900px;margin-inline:auto;position:relative;z-index:2">
        <h2 class="t-grand" data-lignes>Dites-nous quelles zones vous voulez couvrir.</h2>
        <p class="intro" style="margin:22px auto 0">
            On vous revient avec un plan d'emplacements justifié commune par commune,
            sous 24 heures ouvrées.
        </p>
        <div style="margin-top:34px" data-rev=".1">
            <a class="bt" href="{{ route('cible.contact') }}" data-viseur>
                Demander un plan d'emplacements<i class="fl"></i>
            </a>
        </div>
    </div>
</section>

@endsection

@push('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
/* Carte du parc. Même endpoint que la V1 (/api/reseau-map) : la maquette
   ne duplique pas la source de données.
   Le champ `total` du JSON n'est volontairement PAS affiché — décision
   client d'août 2026 de ne plus publier de répartition chiffrée par zone. */
(function () {
  var zone = document.getElementById('carte');
  var attente = document.getElementById('carte-chargement');
  if (!zone || typeof window.L === 'undefined') { return; }

  var carte = L.map(zone, {
    zoomControl: true,
    scrollWheelZoom: false,   // sinon la molette capture le scroll de page
    attributionControl: true,
  }).setView([6.9, -5.3], 7);

  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap, &copy; CARTO',
    maxZoom: 18,
  }).addTo(carte);

  // La molette ne pilote le zoom qu'après un clic : on respecte le scroll
  // de lecture, tout en gardant la carte manipulable.
  carte.on('click', function () { carte.scrollWheelZoom.enable(); });
  carte.on('mouseout', function () { carte.scrollWheelZoom.disable(); });

  fetch('{{ route('cible.api.reseau-map') }}', { headers: { Accept: 'application/json' } })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      var pins = (d && d.pins) || [];
      if (!pins.length) { attente.hidden = true; return; }

      var limites = [];
      pins.forEach(function (p) {
        if (typeof p.lat !== 'number' || typeof p.lng !== 'number') { return; }
        limites.push([p.lat, p.lng]);
        L.marker([p.lat, p.lng], {
          icon: L.divIcon({ className: '', html: '<div class="epingle">' + p.commune + '</div>', iconSize: null }),
        })
          .bindPopup('<strong>' + p.commune + '</strong>' + (p.region ? p.region : ''))
          .addTo(carte);
      });

      if (limites.length) { carte.fitBounds(limites, { padding: [60, 60] }); }
      attente.hidden = true;
    })
    .catch(function () {
      attente.innerHTML = '<div style="text-align:center">Carte momentanément indisponible.<br>'
        + 'Le réseau couvre 31 communes et villes.</div>';
    });
})();
</script>
@endpush
