@extends('public.cible._layout', [
    'seo_title'       => 'Nos services — CIBLE · Affichage, mobile, audiovisuel, digital, street',
    'seo_description' => 'Cinq territoires de visibilité : affichage grand format, publicité mobile, production audiovisuelle, communication digitale, street marketing.',
])

@push('page-css')
    .hero-serv{padding:clamp(60px,8vw,110px) var(--pad);background:linear-gradient(180deg,#fff,#F9F9F5)}
    .hero-serv .sur{color:var(--bleu)}
    .hero-serv h1{margin-top:14px;max-width:20ch}
    .hero-serv p{margin-top:24px;max-width:60ch;font-size:19px;color:#444}

    .terr{padding:clamp(60px,8vw,110px) var(--pad);border-top:8px solid var(--c);position:relative;overflow:hidden}
    .terr.bg-color{background:var(--c);color:#fff}
    .terr.bg-color h2,.terr.bg-color h3{color:#fff}
    .terr.bg-color p{color:rgba(255,255,255,.92)}
    .terr.bg-color .sur{color:rgba(255,255,255,.85)}
    .terr.bg-color .tags li{border-color:rgba(255,255,255,.7);color:#fff}
    .terr.bg-color .preuve{border-color:#fff;color:#fff}
    .terr-grid{display:grid;grid-template-columns:1fr 1fr;gap:clamp(30px,5vw,80px);align-items:center}
    @media(max-width:900px){.terr-grid{grid-template-columns:1fr}}
    .terr .num-big{font-family:var(--titre);font-weight:900;font-size:clamp(52px,7.6vw,104px);line-height:.9;letter-spacing:-.04em;color:var(--c);margin-bottom:8px}
    .terr.bg-color .num-big{color:#fff}
    .terr h2{font-family:var(--titre);font-weight:900;font-size:clamp(28px,3.8vw,50px);line-height:1.05;letter-spacing:-.025em;margin-top:12px}
    .terr p{margin-top:16px;max-width:52ch;line-height:1.65}
    .terr .tags{list-style:none;display:flex;flex-wrap:wrap;gap:8px;margin-top:24px}
    .terr .tags li{border:1.5px solid var(--c);color:var(--c);padding:6px 13px;border-radius:999px;font-family:var(--titre);font-weight:600;font-size:13px}
    .terr .preuve{margin-top:24px;padding:16px 20px;border-left:4px solid var(--c);color:#444;font-size:15px;background:rgba(0,0,0,.03);border-radius:0 8px 8px 0}
    .terr .visu{aspect-ratio:4/3;border-radius:22px;overflow:hidden}

    .cta-serv{background:var(--noir);color:#fff;padding:clamp(60px,8vw,120px) var(--pad);text-align:center}
    .cta-serv .t1{color:#fff;max-width:22ch;margin:0 auto}
    .cta-serv p{margin-top:20px;max-width:52ch;margin-left:auto;margin-right:auto;color:rgba(255,255,255,.75)}
@endpush

@section('content')

<section class="hero-serv">
    <span class="sur">Cinq territoires de visibilité</span>
    <h1 class="t1">Où votre marque apparaît.</h1>
    <p>Les cinq couleurs de notre symbole représentent des panneaux publicitaires. Elles représentent aussi les cinq espaces que traverse une journée abidjanaise — et dans lesquels nous vous rendons présent.</p>
</section>

{{-- 01 · LA RUE ═══ rouge ═══ --}}
<section class="terr" id="rue" style="--c:var(--rouge)">
    <div class="terr-grid">
        <div class="rev">
            <span class="sur" style="color:var(--rouge)">01 · La rue</span>
            <div class="num-big num" data-cible="364">0</div>
            <span class="sur">Panneaux en exploitation</span>
            <h2>Affichage grand format : le seul média qu'on ne peut pas fermer.</h2>
            <p>L'affichage grand format reste le seul média que personne ne peut sauter, bloquer ou faire défiler. Nous exploitons notre propre patrimoine : nous maîtrisons les emplacements, les délais et la preuve de pose.</p>
            <ul class="tags"><li>Classiques</li><li>Lumipub</li><li>Trivision</li><li>Panoramiques</li><li>Écrans digitaux</li><li>Affichage en magasin</li></ul>
            <p class="preuve">Chaque campagne se termine par une pige photo horodatée depuis le terrain. La visibilité se constate, elle ne se promet pas.</p>
        </div>
        <div class="visu"><div class="slot">Panneau grand format<br>avec campagne en place<small>images/cible/terr-rue.jpg</small></div></div>
    </div>
</section>

{{-- 02 · LE MOUVEMENT ═══ jaune ═══ --}}
<section class="terr bg-color" id="mouvement" style="--c:var(--jaune);color:#111">
    <div class="terr-grid">
        <div class="rev">
            <span class="sur" style="color:rgba(0,0,0,.75)">02 · Le mouvement</span>
            <div class="num-big num" data-cible="31" style="color:#111">0</div>
            <span class="sur" style="color:rgba(0,0,0,.75)">Communes atteintes</span>
            <h2 style="color:#111">Publicité mobile : la ville devient votre support.</h2>
            <p style="color:rgba(0,0,0,.85)">Camions publicitaires, tricycles, motos, taxis, chevalets. Le message va chercher l'audience là où elle est immobile et captive : embouteillages, marchés, sorties d'école, abords des zones commerciales.</p>
            <ul class="tags" style="--c:#111"><li style="border-color:#111;color:#111">Camions publicitaires</li><li style="border-color:#111;color:#111">Branding véhicules</li><li style="border-color:#111;color:#111">Taxis &amp; motos</li><li style="border-color:#111;color:#111">Chevalets</li><li style="border-color:#111;color:#111">Régie mobile événementielle</li></ul>
            <p class="preuve" style="border-color:#111;color:#111;background:rgba(0,0,0,.05)">Itinéraires et créneaux définis avec vous, tracés et rapportés après diffusion.</p>
        </div>
        <div class="visu"><div class="slot">Camion publicitaire CIBLE<br>en circulation<small>images/cible/terr-mobile.jpg</small></div></div>
    </div>
</section>

{{-- 03 · L'ÉCRAN ═══ vert ═══ --}}
<section class="terr bg-color" id="ecran" style="--c:var(--vert)">
    <div class="terr-grid">
        <div class="rev">
            <span class="sur">03 · L'écran</span>
            <div class="num-big" style="color:#fff">Studio</div>
            <span class="sur">Production interne</span>
            <h2>Production audiovisuelle : l'image qui porte le message.</h2>
            <p>Films institutionnels, spots TV et radio, motion design, contenus de marque. Le studio produit ce que le réseau diffuse : une même équipe, de la conception jusqu'à l'affichage.</p>
            <ul class="tags"><li>Films institutionnels</li><li>Spots TV &amp; audio</li><li>Motion design</li><li>Identité visuelle</li><li>Contenu de marque</li></ul>
            <p class="preuve">Film institutionnel réalisé pour le Groupe Cofina — de l'écriture au montage final.</p>
        </div>
        <div class="visu"><div class="slot slot--sombre">Extrait de film institutionnel<br>ou plateau de tournage<small>images/cible/terr-ecran.jpg</small></div></div>
    </div>
</section>

{{-- 04 · LE DIGITAL ═══ bleu ═══ --}}
<section class="terr bg-color" id="digital" style="--c:var(--bleu)">
    <div class="terr-grid">
        <div class="rev">
            <span class="sur">04 · Le digital</span>
            <div class="num-big" style="color:#fff">24/7</div>
            <span class="sur">Présence continue</span>
            <h2>Communication digitale : le prolongement naturel du panneau.</h2>
            <p>Une campagne d'affichage sans relais digital perd la moitié de son effet. Social media ads, SEO/SEA, activations interactives, drive-to-store : nous transformons l'exposition en interaction, puis en visite.</p>
            <ul class="tags"><li>Social media ads</li><li>SEO / SEA</li><li>Campagnes virales</li><li>Activations interactives</li><li>Drive-to-store</li></ul>
            <p class="preuve">Conception graphique et gestion des réseaux sociaux pour SGS / SICTA.</p>
        </div>
        <div class="visu"><div class="slot slot--sombre">Campagne social media<br>maquette mobile<small>images/cible/terr-digital.jpg</small></div></div>
    </div>
</section>

{{-- 05 · LE TERRAIN ═══ violet ═══ --}}
<section class="terr bg-color" id="terrain" style="--c:var(--violet)">
    <div class="terr-grid">
        <div class="rev">
            <span class="sur">05 · Le terrain</span>
            <div class="num-big" style="color:#fff">Face à face</div>
            <span class="sur">Le dernier mètre</span>
            <h2>Street marketing : là où la marque devient une rencontre.</h2>
            <p>Street marketing, pop-up stores, roadshows, stands expérientiels, architecture événementielle. Le moment où l'audience ne regarde plus la marque : elle lui parle.</p>
            <ul class="tags"><li>Street marketing</li><li>Pop-up store</li><li>Roadshow</li><li>Stand expérientiel</li><li>Architecture événementielle</li></ul>
            <p class="preuve">Brand experience déployée pour Orange · Stand expérientiel pour IFG.</p>
        </div>
        <div class="visu"><div class="slot slot--sombre">Activation street marketing<br>ou stand expérientiel<small>images/cible/terr-terrain.jpg</small></div></div>
    </div>
</section>

<section class="cta-serv">
    <h2 class="t1">Une campagne 360° combinée&nbsp;?</h2>
    <p>Nous orchestrons ces cinq territoires dans une stratégie unique, mesurée du premier affichage jusqu'à la visite en point de vente.</p>
    <div style="margin-top:32px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a class="bouton b-rouge" href="{{ route('cible.contact') }}">Nous consulter</a>
        <a class="bouton b-ligne" href="{{ route('cible.references') }}" style="color:#fff;border-color:#fff">Voir des cas concrets</a>
    </div>
</section>

@endsection
