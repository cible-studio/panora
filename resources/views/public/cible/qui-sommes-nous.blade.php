@extends('public.cible._layout', [
    'seo_title'       => 'Qui sommes-nous — CIBLE · 30 ans de communication extérieure',
    'seo_description' => 'CIBLE, régie publicitaire ivoirienne fondée en 1994. Trois décennies au service des marques, trois distinctions officielles de l\'État.',
])

@push('page-css')
    .hero-qui{padding:clamp(60px,8vw,110px) var(--pad);text-align:center;background:linear-gradient(180deg,#fff 0%,#F9F9F5 100%)}
    .hero-qui .sur{color:var(--violet)}
    .hero-qui h1{margin-top:14px}
    .hero-qui p{margin-top:24px;max-width:64ch;margin-left:auto;margin-right:auto;font-size:19px;color:#444}

    .recit{padding:clamp(56px,8vw,100px) var(--pad)}
    .recit-grid{display:grid;grid-template-columns:1fr 1fr;gap:clamp(30px,5vw,80px);align-items:start}
    @media(max-width:900px){.recit-grid{grid-template-columns:1fr}}
    .recit article{padding:26px 0;border-bottom:1px solid #E4E4E4}
    .recit article:last-child{border-bottom:0}
    .recit article h3{font-family:var(--titre);font-weight:800;font-size:22px;margin-bottom:10px;color:var(--rouge)}
    .recit article p{color:#444;line-height:1.7}

    .distinctions{background:var(--gris);padding:clamp(56px,8vw,100px) var(--pad)}
    .distinctions .entete{max-width:600px;margin-bottom:44px}
    .distinctions .sur{color:var(--vert)}
    .distinctions .t1{margin-top:12px}
    .dist-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(18px,3vw,44px)}
    @media(max-width:820px){.dist-grid{grid-template-columns:1fr}}
    .dist{background:#fff;border-radius:16px;padding:28px 26px;border-top:6px solid var(--c)}
    .dist .an{font-family:var(--titre);font-weight:900;font-size:34px;color:var(--c);line-height:1}
    .dist h4{font-family:var(--titre);font-weight:800;font-size:17px;margin-top:8px;line-height:1.35}
    .dist p{font-size:14px;color:#666;margin-top:10px;line-height:1.6}

    .stats-qui{padding:clamp(56px,8vw,100px) var(--pad);background:var(--noir);color:#fff}
    .stats-qui .entete{max-width:600px;margin-bottom:44px}
    .stats-qui .sur{color:var(--jaune)}
    .stats-qui .t1{color:#fff;margin-top:12px}
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:clamp(20px,3vw,40px)}
    @media(max-width:900px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
    .stat .v{font-family:var(--titre);font-weight:900;font-size:clamp(46px,6vw,80px);line-height:.86;letter-spacing:-.04em;color:var(--jaune)}
    .stat .l{font-family:var(--titre);font-weight:600;font-size:13px;opacity:.85;margin-top:12px;text-transform:uppercase;letter-spacing:.08em}
@endpush

@section('content')

<section class="hero-qui">
    <span class="sur">Depuis 1994</span>
    <h1 class="t1">Trente ans à faire rayonner les marques.</h1>
    <p>Née dans l'affichage publicitaire, CIBLE s'est imposée en trente ans comme un pilier de la publicité extérieure en Côte d'Ivoire. De l'artisanat du panneau grand format à la campagne 360° mesurée en temps réel, notre métier a évolué — notre exigence, jamais.</p>
</section>

<section class="recit">
    <div class="recit-grid">
        <article class="rev">
            <h3>Notre origine — maîtres de la visibilité extérieure</h3>
            <p>Née dans l'affichage publicitaire, CIBLE s'est imposée en trente ans comme un pilier de la publicité extérieure en Côte d'Ivoire : panneaux grand format, signalétique de carrefour, camions de parade, habillage de véhicules. Un patrimoine de 364 emplacements, construit un panneau à la fois, dans 31 communes du pays.</p>
        </article>
        <article class="rev">
            <h3>Notre évolution — la visibilité devient mesurable</h3>
            <p>Les usages ont changé, les attentes des annonceurs aussi. Le digital 360° ne remplace pas notre réseau : il le prolonge et le rend mesurable. Nous n'offrons plus seulement de la visibilité, mais une performance vérifiable, orientée résultats.</p>
        </article>
        <article class="rev">
            <h3>Notre force — le terrain et la donnée</h3>
            <p>Trente ans de connaissance du terrain ivoirien fusionnés avec une approche moderne et une exigence de résultat. Nous sommes la seule régie du pays à posséder à la fois son propre réseau et l'outil qui le pilote — de la commande à la preuve de pose.</p>
        </article>
        <article class="rev">
            <h3>Notre engagement — la preuve, pas la promesse</h3>
            <p>Chaque campagne se termine par un dossier de preuves : photos horodatées depuis le terrain, planning de pose, rapports de diffusion. Vous savez exactement où votre marque a été vue, quand, et par combien de personnes.</p>
        </article>
    </div>
</section>

<section class="stats-qui rev">
    <div class="entete">
        <span class="sur">En chiffres</span>
        <h2 class="t1">Trente ans concentrés en quatre nombres.</h2>
    </div>
    <div class="stats-grid">
        <div class="stat"><div class="v num" data-cible="30">0</div><div class="l">Ans d'expertise</div></div>
        <div class="stat"><div class="v num" data-cible="364">0</div><div class="l">Panneaux en propre</div></div>
        <div class="stat"><div class="v num" data-cible="31">0</div><div class="l">Communes couvertes</div></div>
        <div class="stat"><div class="v num" data-cible="3">0</div><div class="l">Distinctions d'État</div></div>
    </div>
</section>

<section class="distinctions">
    <div class="entete rev">
        <span class="sur">Reconnaissances officielles</span>
        <h2 class="t1">Trois distinctions de l'État ivoirien.</h2>
    </div>
    <div class="dist-grid">
        <div class="dist rev" style="--c:var(--jaune)">
            <div class="an num">2016</div>
            <h4>2ᵉ prix du meilleur publicitaire</h4>
            <p>Distinction professionnelle du secteur de la publicité ivoirienne.</p>
        </div>
        <div class="dist rev" style="--c:var(--vert)">
            <div class="an num">2019</div>
            <h4>Chevalier de l'Ordre du Mérite de la Communication</h4>
            <p>Reconnaissance de la contribution à la structuration du métier en Côte d'Ivoire.</p>
        </div>
        <div class="dist rev" style="--c:var(--violet)">
            <div class="an num">2020</div>
            <h4>Officier de l'Ordre du Mérite National</h4>
            <p>Distinction républicaine pour services rendus au pays.</p>
        </div>
    </div>
</section>

<section style="padding:clamp(60px,8vw,100px) var(--pad);text-align:center">
    <h2 class="t2">Prêt à écrire le prochain chapitre avec nous&nbsp;?</h2>
    <div style="margin-top:28px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a class="bouton b-rouge" href="{{ route('cible.contact') }}">Demander un devis</a>
        <a class="bouton b-ligne" href="{{ route('cible.references') }}">Voir nos réalisations</a>
    </div>
</section>

@endsection
