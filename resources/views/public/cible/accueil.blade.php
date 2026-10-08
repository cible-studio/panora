@extends('public.cible._coque', ['titre' => 'Accueil', 'actuelle' => 'accueil'])

@push('css')
/* ═══════════════ HERO ═══════════════
   Composition en deux temps. Au chargement : le manifeste à gauche sur
   noir, le motion du perroquet à droite dans un panneau blanc arrondi —
   la lecture d'un panneau allumé la nuit, qui est littéralement le métier.
   Au scroll : le panneau s'ouvre en plein écran et le texte s'efface, donc
   jamais de texte par-dessus le motion. C'est délibéré : la vidéo n'a pas
   de zone neutre garantie, superposer du texte serait un pari. */
/* Le titre occupait une colonne d'environ 700 px à 148 px de corps : un ou
   deux mots par ligne, sur huit lignes. Il passe donc en PLEINE LARGEUR,
   au-dessus d'une rangée à deux colonnes. Deux lignes de trente caractères
   tiennent alors sans se briser.

   Et plus de masques par ligne ici : une ligne qui déborde devenait plus
   haute que son masque en overflow:hidden, et le texte apparaissait coupé.
   Les lignes se révèlent par simple montée en opacité, ce qui tolère le
   retour à la ligne. */
.hero{min-height:100svh;display:grid;align-content:center;padding:clamp(118px,15vh,164px) var(--pad) clamp(40px,6vh,80px);position:relative;overflow:hidden}
.hero > .large{position:relative;z-index:2}

.hero .sur{--c:var(--rouge)}
/* Pas de max-width ici : la ligne la plus longue compte 32 caractères, et
   un cap à 22ch la brisait en deux. Le conteneur .large (1480 px) suffit à
   la borner, et text-wrap:balance répartit proprement les lignes le jour
   où l'écran est trop étroit. */
.hero__titre{margin-top:18px}
.hero__titre .l{display:block}
.hero__titre em{font-style:normal;color:var(--rouge)}

.hero__bas{
  display:grid;grid-template-columns:1fr minmax(290px,.72fr);
  gap:clamp(30px,5vw,70px);align-items:end;margin-top:clamp(34px,5vw,58px);
}
@media(max-width:980px){
  .hero__bas{grid-template-columns:1fr;gap:34px;align-items:start}
  .hero__titre{max-width:none}
}
.hero__sous{font-family:var(--titre);font-weight:600;font-size:clamp(16px,1.5vw,20px);line-height:1.45;color:var(--texte-2);max-width:42ch}
.hero__actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}

/* Panneau blanc : le motion est sur fond blanc opaque (H.264 ne porte pas
   de transparence), on en fait donc un objet graphique assumé. */
.hero__panneau{
  position:relative;border-radius:26px;overflow:hidden;
  background:var(--blanc);aspect-ratio:16/11;
  box-shadow:0 30px 70px -40px rgba(17,17,17,.4);
  will-change:transform;
}
.hero__panneau video{width:100%;height:100%;object-fit:cover}
/* Liseré aux 5 couleurs : la charte entière en un seul trait. */
.hero__panneau::after{
  content:"";position:absolute;inset:auto 0 0 0;height:5px;z-index:2;
  /* Deux aplats francs rouge/jaune, et non les 5 couleurs en dégradé :
     la charte proscrit les dégradés et veut le rouge prépondérant,
     « principalement accompagné avec le jaune ». */
  background:var(--rouge);
}
.hero__etiq{
  position:absolute;z-index:3;top:16px;left:16px;
  display:flex;align-items:center;gap:8px;
  padding:8px 13px;border-radius:999px;background:var(--noir);color:var(--blanc);
  font-family:var(--titre);font-weight:800;font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;
}
.hero__etiq b{width:6px;height:6px;border-radius:50%;background:var(--rouge);animation:bat 2s ease-in-out infinite}

/* ═══════════════ PREUVE ═══════════════ */
.preuve{padding:clamp(56px,7vw,92px) var(--pad)}
.preuve__grille{display:grid;grid-template-columns:repeat(4,1fr);gap:clamp(16px,2.4vw,34px)}
@media(max-width:880px){.preuve__grille{grid-template-columns:repeat(2,1fr)}}
.chiffre{padding-top:22px;border-top:2px solid var(--c)}
.chiffre__v{
  font-family:var(--titre);font-weight:900;
  font-size:clamp(42px,6.4vw,92px);line-height:.88;letter-spacing:-.05em;
  display:flex;align-items:baseline;gap:2px;
}
.chiffre__v i{font-style:normal;color:var(--rouge);font-size:.52em}
.chiffre__l{font-family:var(--titre);font-weight:700;font-size:13px;margin-top:12px;color:var(--texte-2);line-height:1.35}

/* ═══════════════ EXPERTISES ═══════════════ */
.exp{background:var(--fond-2);border-block:1px solid var(--ligne)}
.exp__liste{margin-top:clamp(38px,5vw,64px);border-top:1px solid var(--ligne)}
.exp__item{
  display:grid;grid-template-columns:76px 1fr minmax(0,.78fr) 54px;
  gap:clamp(14px,2.6vw,40px);align-items:center;
  padding:clamp(22px,3vw,34px) 0;border-bottom:1px solid var(--ligne);
  position:relative;transition:padding-left .5s var(--ease);
}
.exp__item::before{
  content:"";position:absolute;inset:0;z-index:0;
  background:var(--fond-2);
  opacity:0;transition:opacity .5s var(--ease);
}
.exp__item:hover{padding-left:clamp(12px,2vw,28px)}
.exp__item:hover::before{opacity:1}
.exp__item > *{position:relative;z-index:1}
.exp__n{font-family:var(--titre);font-weight:900;font-size:13px;color:var(--rouge);letter-spacing:.1em}
.exp__nom{font-family:var(--titre);font-weight:900;font-size:clamp(20px,2.7vw,38px);letter-spacing:-.028em;line-height:1.04}
.exp__txt{color:var(--texte-2);font-size:15px;line-height:1.55}
.exp__fl{
  width:46px;height:46px;border-radius:50%;justify-self:end;
  display:grid;place-items:center;box-shadow:inset 0 0 0 1.5px var(--ligne);
  transition:background .4s,box-shadow .4s,transform .4s var(--ease);
}
.exp__item:hover .exp__fl{background:var(--c);box-shadow:inset 0 0 0 1.5px var(--c);transform:rotate(-45deg)}
.exp__item:hover .exp__fl i{background:#fff}
.exp__fl i{
  width:16px;height:16px;background:var(--noir);
  -webkit-mask:url('{{ asset('images/fleche.svg') }}') no-repeat center/contain;
  mask:url('{{ asset('images/fleche.svg') }}') no-repeat center/contain;
}
@media(max-width:880px){
  .exp__item{grid-template-columns:48px 1fr;gap:12px 16px}
  .exp__txt{grid-column:2;margin-top:6px}
  .exp__fl{display:none}
}

/* ═══════════════ MANIFESTE ═══════════════
   « Vous visez juste » prend le rang qu'occupe « Truth Well Told » chez
   la référence citée : seul, grand, juste avant les travaux. Il était
   relégué en pied de page. */
/* ⚠ Cette bande était un aplat noir presque vide : un texte centré de deux
   mots au milieu de 470 px de hauteur. C'est le « grand espace vide »
   signalé par le client.

   Elle devient une composition à deux colonnes : le perroquet écarlate à
   fond perdu d'un côté, la signature de l'autre. Le perroquet EST la
   marque — « Opération Plume Rouge » — et il remplit l'espace avec le
   sujet le plus juste possible.

   Photo et aplat noir côte à côte, sans voile ni fusion par-dessus
   l'image : la charte proscrit les effets, et une couleur ne se combine
   qu'avec du blanc ou du noir. Aucun mot de titre en couleur ici non plus
   — le fond n'est pas blanc. */
.manif{background:var(--noir);color:var(--blanc);position:relative;overflow:hidden}
.manif__grille{
  display:grid;grid-template-columns:.86fr 1.14fr;align-items:stretch;
  min-height:min(74vh,620px);
}
@media(max-width:900px){.manif__grille{grid-template-columns:1fr;min-height:0}}
.manif__ph{position:relative;overflow:hidden;background:#000}
.manif__ph img{width:100%;height:100%;object-fit:cover;display:block}
@media(max-width:900px){.manif__ph{aspect-ratio:16/10}}
.manif__txt{
  display:flex;flex-direction:column;justify-content:center;
  padding:clamp(54px,7vw,96px) clamp(26px,5vw,80px);
  position:relative;z-index:2;
}
.manif__l{
  font-family:var(--titre);font-weight:900;
  font-size:clamp(34px,5.6vw,84px);line-height:.95;letter-spacing:-.04em;
}
.manif__l .l{display:block}
.manif__filet{
  width:60px;height:4px;background:var(--rouge);
  margin-top:clamp(22px,3vw,32px);border-radius:4px;
}
.manif__s{
  margin-top:18px;font-family:var(--titre);font-weight:700;
  font-size:12px;letter-spacing:.2em;text-transform:uppercase;
  color:rgba(255,255,255,.6);
}
.manif__credit{
  position:absolute;z-index:3;bottom:10px;left:12px;
  font-size:10px;letter-spacing:.06em;color:rgba(255,255,255,.4);
}

/* ═══════════════ TRAVAUX ═══════════════
   ⚠ C'était un défilement horizontal épinglé. Il supposait que la piste
   soit plus large que l'écran : dès que ce n'était pas le cas — écran très
   large, ou navigateur dézoomé — l'épinglage ne s'armait pas, la piste
   restait alignée à gauche et laissait un grand vide à droite. C'est le
   second « espace vide » des captures client.

   Une grille règle le problème par construction : elle remplit toujours la
   largeur disponible, quelle qu'elle soit. Elle colle aussi à la référence
   du client, dont la page d'accueil est une grille de travaux.

   Vignettes réduites au client et au titre, sans paragraphe : la galerie
   doit donner envie d'ouvrir, pas tout raconter. */
.tr{position:relative;padding-block:0 clamp(60px,8vw,110px)}
.tr__grille{
  display:grid;grid-template-columns:repeat(3,1fr);
  gap:clamp(18px,2.4vw,34px) clamp(16px,2vw,28px);
  padding:0 var(--pad);
}
@media(max-width:1000px){.tr__grille{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.tr__grille{grid-template-columns:1fr}}
.carte{display:flex;flex-direction:column}
.carte__ph{aspect-ratio:4/5;border-radius:18px;overflow:hidden;--c:var(--rouge)}
@media(max-width:560px){.carte__ph{aspect-ratio:3/2}}

/* Pied de galerie : une rangée pleine largeur, et non un bloc flottant
   coincé au bout d'une piste. */
.tr__pied{
  display:flex;align-items:center;gap:clamp(16px,3vw,34px);flex-wrap:wrap;
  margin:clamp(34px,4.5vw,56px) var(--pad) 0;
  padding-top:clamp(24px,3vw,34px);border-top:1px solid var(--ligne);
}
.tr__pied p{max-width:36ch;margin:0}
.carte__nom{
  margin-top:18px;font-family:var(--titre);font-weight:800;
  font-size:11px;letter-spacing:.17em;text-transform:uppercase;color:var(--rouge);
}
.carte__titre{
  margin-top:9px;font-family:var(--titre);font-weight:900;
  font-size:clamp(17px,1.7vw,23px);line-height:1.12;letter-spacing:-.022em;
  text-transform:uppercase;
}



/* ═══════════════ APPEL FINAL ═══════════════ */
.fin{position:relative;overflow:hidden;text-align:center}
.fin__in{max-width:980px;margin-inline:auto;position:relative;z-index:2}
.fin .t-geant em{font-style:normal;color:var(--jaune)}
.fin .intro{margin:26px auto 0}
.fin__actions{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-top:38px}
.fin__ph{
  margin-top:clamp(44px,6vw,80px);border-radius:22px;overflow:hidden;
  aspect-ratio:21/9;position:relative;z-index:2;
}
@media(max-width:680px){.fin__ph{aspect-ratio:4/3}}
@endpush

@section('contenu')

{{-- ═══════════════════════ HERO ═══════════════════════ --}}
<section class="hero" id="hero">
    <div class="plume" style="--c:var(--rouge);--op:.07;top:-6%;right:-7%;width:clamp(230px,30vw,440px)" data-par="-16" data-rot="14"></div>
    <div class="plume" style="--c:var(--jaune);--op:.055;bottom:-12%;left:-6%;width:clamp(180px,22vw,330px)" data-par="18" data-rot="-10"></div>

    <div class="large">
        <p class="sur" data-rev>Régie &amp; studio · <b>Côte d'Ivoire</b> · depuis 1994</p>

        {{-- Deux lignes, posées à la main pour garder le <em> rouge que le
             découpeur automatique effacerait. Révélation par [data-cascade],
             qui monte chaque ligne en opacité sans masque : une ligne qui
             déborde n'est donc jamais tronquée. --}}
        <h1 class="t-geant hero__titre" data-cascade>
            <span class="l">Nous ne vendons pas d'espace.</span>
            <span class="l"><em>Nous vendons de l'attention.</em></span>
        </h1>

        <div class="hero__bas">
            <div>
                <p class="hero__sous" data-rev=".3">
                    +400 panneaux dans 31 communes, trente ans de terrain, et la preuve
                    photo de chaque pose. Voilà ce qu'on met derrière votre message.
                </p>

                <div class="hero__actions" data-rev=".38">
                    <a class="bt" href="{{ route('cible.contact') }}" data-viseur>
                        Parler de mon projet<i class="fl"></i>
                    </a>
                    <a class="bt bt--creux" href="{{ route('cible.travaux') }}" data-viseur>
                        Voir nos travaux
                    </a>
                </div>
            </div>

        <div class="hero__panneau" id="panneau" data-rev=".2">
            <span class="hero__etiq"><b></b> En exploitation</span>
            {{-- Motion fourni par le client (fond blanc, 1920×1080, 8,5 s, sans
                 piste audio). muted + playsinline : sans ces deux attributs,
                 Safari iOS refuse la lecture automatique. --}}
            <video src="{{ asset('refonte/motion/perroquet-blanc.mp4') }}"
                   autoplay muted loop playsinline preload="metadata"
                   poster="{{ asset('refonte/photo/perroquet.webp') }}"
                   aria-label="Animation du logo CIBLE"></video>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════ MANIFESTE ═══════════════════════
     La signature de marque prend ici le rang qu'occupe « Truth Well Told »
     chez McCann : seule, grande, juste avant les travaux. Elle n'explique
     rien — c'est la galerie qui argumente. --}}
<section class="manif">
    <div class="manif__grille">
        <div class="manif__ph">
            <img src="{{ asset('refonte/test/perroquet.webp') }}"
                 alt="Perroquet écarlate — le symbole de la marque CIBLE" loading="lazy">
            <span class="manif__credit">Visuel de test · Unsplash, David Clode</span>
        </div>

        <div class="manif__txt">
            <h2 class="manif__l" data-cascade>
                <span class="l">Vous visez</span>
                <span class="l">juste.</span>
            </h2>
            <div class="manif__filet" data-rev=".2"></div>
            <p class="manif__s" data-rev=".28">Six campagnes · et la preuve de chacune</p>
        </div>
    </div>
</section>

{{-- ═══════════════════════ TRAVAUX ═══════════════════════ --}}
<section class="tr" id="travaux" aria-labelledby="tr-titre">
    {{-- Titre réservé aux lecteurs d'écran : à l'œil, le manifeste
         ci-dessus tient ce rôle, et McCann n'intercale aucun intertitre
         entre son manifeste et ses travaux. --}}
    <h2 id="tr-titre" class="hors-ecran">Nos travaux</h2>

    <div class="tr__grille" data-cascade>
        @foreach($realisations as $slug => $p)
            <a class="carte" href="{{ route('cible.travaux') }}#{{ $slug }}" data-viseur>
                <div class="carte__ph ph">
                    <img src="{{ \App\Support\Contenu::urlImage($p['image'] ?? 'images/cible/campagne-1.jpg') }}"
                         alt="{{ $p['cat'] ?? 'Campagne' }} — {{ $p['nom'] ?? '' }}" loading="lazy">
                </div>
                <span class="carte__nom">{{ $p['nom'] ?? $slug }}</span>
                <span class="carte__titre">{{ $p['titre_court'] ?? '' }}</span>
            </a>
        @endforeach
    </div>

    <div class="tr__pied">
        <p class="t-petit">Chaque campagne, de la recommandation à la preuve de pose.</p>
        <a class="bt bt--clair" href="{{ route('cible.travaux') }}" data-viseur style="margin-left:auto">
            Tout voir<i class="fl"></i>
        </a>
    </div>
</section>

{{-- ═══════════════════════ PREUVE ═══════════════════════
     Après les travaux, et non avant : on montre d'abord, on prouve ensuite.
     C'est le seul endroit où la maquette s'écarte de McCann, et c'est
     volontaire — CIBLE possède un réseau physique, un annonceur qui arrive
     doit savoir qu'il existe. Une galerie seule le cacherait. --}}
<div class="ruban" aria-hidden="true">
    <div class="ruban__piste">
        @for($passe = 0; $passe < 2; $passe++)
            <div>
                <span><em style="--c:var(--rouge)">+400</em> panneaux</span><span class="ruban__pt"></span>
                <span>Abidjan &amp; intérieur du pays</span><span class="ruban__pt"></span>
                <span><em style="--c:var(--jaune)">31</em> communes</span><span class="ruban__pt"></span>
                <span>Pige photo horodatée</span><span class="ruban__pt"></span>
                <span><em style="--c:var(--vert)">30</em> ans de terrain</span><span class="ruban__pt"></span>
                <span>De la rue au digital</span><span class="ruban__pt"></span>
            </div>
        @endfor
    </div>
</div>

<section class="preuve">
    <div class="large">
        <div class="preuve__grille" data-cascade>
            <div class="chiffre" style="--c:var(--rouge)">
                <div class="chiffre__v num"><i>+</i><span data-compte="{{ \App\Support\Contenu::get('chiffres.panneaux', 400) }}">0</span></div>
                <div class="chiffre__l">Panneaux en exploitation</div>
            </div>
            <div class="chiffre" style="--c:var(--jaune)">
                <div class="chiffre__v num"><span data-compte="{{ \App\Support\Contenu::get('chiffres.communes', 31) }}">0</span></div>
                <div class="chiffre__l">Communes couvertes</div>
            </div>
            <div class="chiffre" style="--c:var(--rouge)">
                <div class="chiffre__v num">1994</div>
                <div class="chiffre__l">Depuis</div>
            </div>
            <div class="chiffre" style="--c:var(--jaune)">
                <div class="chiffre__v num"><span data-compte="{{ \App\Support\Contenu::get('chiffres.distinctions', 3) }}">0</span></div>
                <div class="chiffre__l">Distinctions d'État</div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════ EXPERTISES ═══════════════════════
     Descriptions ramenées à une ligne chacune : la page Expertises leur
     consacre un écran entier, les répéter ici n'ajoutait que des mots. --}}
<section class="bloc exp">
    <div class="large">
        <div class="entete">
            <p class="sur" style="--c:var(--rouge)">Ce qu'on fait</p>
            <h2 class="t-grand" data-lignes>Quatre métiers, une seule obsession.</h2>
        </div>

        <div class="exp__liste">
            @foreach([
                ['Régie publicitaire',   'var(--rouge)',  'Le plus grand maillage du pays.'],
                ['Communication mobile', 'var(--jaune)',  'Votre message va à la rencontre de son audience.'],
                ['Brand experience',     'var(--rouge)',  'Faire voir votre marque, et surtout la faire vivre.'],
                ['Media Intelligence',   'var(--jaune)',  'Des campagnes prouvées par la photo horodatée.'],
            ] as $i => [$nom, $couleur, $texte])
                <a class="exp__item" style="--c:{{ $couleur }}" href="{{ route('cible.expertises') }}#p{{ $i + 1 }}" data-viseur data-rev="{{ $i * 0.06 }}">
                    <span class="exp__n">0{{ $i + 1 }}</span>
                    <span class="exp__nom">{{ $nom }}</span>
                    <span class="exp__txt">{{ $texte }}</span>
                    <span class="exp__fl"><i></i></span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════ APPEL FINAL ═══════════════════════ --}}
<section class="bloc fin">
    <div class="fleche-d" style="--c:var(--jaune);--op:.09;top:14%;left:7%;width:clamp(90px,11vw,160px)" data-par="-26" data-rot="-18"></div>
    <div class="plume" style="--c:var(--jaune);--op:.07;bottom:4%;right:4%;width:clamp(170px,21vw,300px)" data-par="14" data-rot="12"></div>

    <div class="fin__in">
        <p class="sur" style="--c:var(--jaune)">Parlons-en</p>
        <h2 class="t-geant" style="margin-top:18px" data-lignes>Dites-nous où vous voulez être vu. <em>On s'occupe du reste.</em></h2>
        <p class="intro">
            Un échange de quinze minutes suffit à savoir si on peut vous être utile.
            Réponse sous 24 heures ouvrées, sans engagement.
        </p>
        <div class="fin__actions" data-rev=".1">
            <a class="bt" href="{{ route('cible.contact') }}" data-viseur>
                Recevoir une recommandation média<i class="fl"></i>
            </a>
            <a class="bt bt--creux" href="tel:+2250700780628" data-viseur>
                +225 07 00 78 06 28
            </a>
        </div>
    </div>

    <div class="large">
        <div class="fin__ph ph" data-rev=".16">
            <img src="{{ asset('refonte/test/foule-festive.webp') }}"
                 alt="Foule rassemblée en plein air" loading="lazy">
            <div class="ph__legende">Visuel de test · Unsplash, Andrey K</div>
        </div>
    </div>
</section>

@endsection

@push('js')
<script>
/* Héro — parallaxe douce du panneau.

   La version précédente épinglait le héro et agrandissait le panneau à
   1,42 pour l'ouvrir en plein écran. Trois défauts d'affichage en
   découlaient, et c'est le gros des bugs signalés sur la page d'accueil :

     1. `.hero` porte overflow:hidden (il contient les plumes de décor) :
        le panneau agrandi était donc rogné aux bords de la section au lieu
        de remplir l'écran ;
     2. deux sections épinglées sur la même page — le héro puis la galerie
        de travaux — provoquaient un saut au passage de l'une à l'autre,
        accentué par le scroll inertiel de Lenis ;
     3. l'épinglage ajoute la hauteur d'un écran au document, ce qui
        décalait le manifeste placé juste après.

   Remplacé par un simple décalage vertical au scrub : aucun épinglage,
   aucun rognage, et le seul épinglage de la page reste celui de la
   galerie. */
(function () {
  if (typeof window.gsap === 'undefined') { return; }
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
  if (window.innerWidth < 980) { return; }

  var panneau = document.getElementById('panneau');
  if (!panneau) { return; }

  gsap.to(panneau, {
    yPercent: -9, ease: 'none',
    scrollTrigger: {
      trigger: '#hero', start: 'top top', end: 'bottom top',
      scrub: .6, invalidateOnRefresh: true,
    },
  });
})();
</script>
@endpush
