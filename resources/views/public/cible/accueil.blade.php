@extends('public.cible._coque', ['titre' => 'Accueil', 'actuelle' => 'accueil'])

@push('css')
/* ═══════════════ HÉRO — ÉCRAN SCINDÉ ═══════════════
   Direction retenue avec le client le 2026-10-08, après trois versions
   empilées jugées « trop vides ».

   Le défaut n'était pas le manque d'éléments mais la composition : une
   ligne d'en-tête, un cadre, une rangée de texte — trois bandes
   horizontales sur fond blanc, sans point d'entrée pour le regard.

   Ici, deux colonnes pleine hauteur, bord à bord, sans marge extérieure :
     · à gauche, le blanc porte le manifeste. Le fond étant blanc, le mot
       en rouge y est autorisé — ce qu'un héro entièrement noir aurait
       interdit (« n'appliquez pas le texte du titre en couleur à un
       arrière-plan non blanc ») ;
     · à droite, le noir porte le motion dans son panneau blanc : une face
       éclairée dans la nuit, qui est le métier de CIBLE.

   Plus un pixel inoccupé à l'ouverture, et le contraste tombe exactement
   sur les neutres de la charte.

   ⚠ Contrepartie assumée : une moitié d'écran donne forcément un motion
   plus petit qu'une pleine largeur. Le partage 40/60 privilégie le motion,
   et le titre descend en conséquence à 38 px pour tenir dans la colonne de
   gauche sans se briser mot par mot. */
.hero{
  min-height:100svh;
  display:grid;grid-template-columns:36fr 64fr;
  position:relative;overflow:hidden;
}
@media(max-width:980px){.hero{grid-template-columns:1fr;min-height:0}}

/* ── Colonne gauche : le manifeste, sur blanc ── */
.hero__dit{
  background:var(--blanc);
  display:flex;flex-direction:column;justify-content:center;
  padding:clamp(96px,12vh,128px) clamp(26px,3.4vw,62px) clamp(34px,5vh,56px) var(--pad);
  position:relative;z-index:2;
}
@media(max-width:980px){
  .hero__dit{padding:clamp(96px,13vh,124px) var(--pad) clamp(34px,5vw,48px)}
}
.hero .sur{--c:var(--rouge)}
.hero__filet{width:52px;height:4px;background:var(--rouge);border-radius:4px;margin-top:16px}

/* Titre nettement plus bas que la pleine largeur : la colonne fait environ
   580 px utiles, et 29 caractères à 46 px en demanderaient 734. Mesuré. */
.hero__titre{
  margin-top:20px;
  font-size:clamp(26px,3.1vw,50px);line-height:1.04;letter-spacing:-.03em;
}
@media(max-width:980px){.hero__titre{font-size:clamp(30px,6.4vw,46px)}}
.hero__titre .l{display:block}
.hero__titre em{font-style:normal;color:var(--rouge)}

.hero__sous{margin-top:18px;font-size:16px;line-height:1.6;color:var(--texte-2);max-width:46ch}
.hero__actions{display:flex;flex-wrap:wrap;gap:11px;margin-top:26px}

/* Repères en pied de colonne : ils donnent une base à la composition et
   évitent que le bas de la colonne blanche reste vide. */
.hero__reperes{
  display:flex;gap:clamp(16px,2vw,30px);flex-wrap:wrap;
  margin-top:clamp(28px,4vh,46px);padding-top:18px;
  border-top:1px solid var(--ligne);
  font-family:var(--titre);font-weight:700;font-size:11.5px;
  letter-spacing:.11em;text-transform:uppercase;color:var(--texte-3);
}
.hero__reperes b{display:block;font-family:var(--chiffres);font-size:22px;
  letter-spacing:-.02em;color:var(--noir);font-weight:900;margin-bottom:3px}

/* ── Colonne droite : le motion, sur noir ── */
.hero__scene{
  background:var(--noir);
  display:grid;place-items:center;
  padding:clamp(20px,2.2vw,40px);
  position:relative;overflow:hidden;
}
@media(max-width:980px){.hero__scene{padding:clamp(24px,5vw,40px)}}

.hero__panneau{
  position:relative;width:100%;aspect-ratio:16/9;
  border-radius:clamp(10px,1.2vw,16px);overflow:hidden;
  background:var(--blanc);
  box-shadow:0 40px 90px -40px rgba(0,0,0,.9);
}
@media(max-width:560px){.hero__panneau{aspect-ratio:4/3}}
/* La vidéo est en 16/9 comme son panneau : `cover` ne rogne donc rien, et
   garantit qu'aucune bordure blanche ne subsiste sur les côtés. */
.hero__panneau video{width:100%;height:100%;object-fit:cover;background:var(--blanc);display:block}
/* Filet rouge en pied de face : l'accent de marque, en aplat. */
.hero__panneau::after{
  content:"";position:absolute;inset:auto 0 0 0;height:4px;z-index:2;background:var(--rouge);
}
.hero__etiq{
  position:absolute;z-index:3;top:14px;left:14px;
  display:flex;align-items:center;gap:8px;
  padding:7px 13px;border-radius:999px;background:var(--noir);color:var(--blanc);
  font-family:var(--titre);font-weight:800;font-size:10px;
  letter-spacing:.14em;text-transform:uppercase;
}
.hero__etiq b{width:6px;height:6px;border-radius:50%;background:var(--rouge);animation:bat 2s ease-in-out infinite}

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
/* Section sur aplat noir, comme la bande du manifeste qui la précède : le
   manifeste et les travaux forment un seul bloc sombre, et la bichromie
   des vignettes y ressort bien plus qu'elle ne le ferait sur du blanc.
   C'est le style que le client a redemandé. */
.tr{
  position:relative;background:var(--noir);color:var(--blanc);
  padding-block:clamp(44px,6vw,76px) clamp(60px,8vw,110px);
}
.tr .carte__titre{color:var(--blanc)}
.tr .t-petit{color:rgba(255,255,255,.76)}
.tr .bt--clair{background:var(--blanc);color:var(--noir)}
.tr .bt--clair:hover{background:var(--jaune)}
.tr .tr__pied{border-top-color:rgba(255,255,255,.16)}
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

{{-- ═══════════════════════ HÉRO — ÉCRAN SCINDÉ ═══════════════════════ --}}
<section class="hero" id="hero">

    {{-- Colonne gauche : ce qu'on dit --}}
    <div class="hero__dit">
        <p class="sur" data-rev>Régie &amp; studio · <b>Côte d'Ivoire</b> · depuis 1994</p>
        <div class="hero__filet" data-rev=".06"></div>

        {{-- Lignes posées à la main pour garder le <em> rouge, que le
             découpeur automatique effacerait en reconstruisant le HTML
             depuis le texte seul. --}}
        <h1 class="hero__titre" data-cascade>
            <span class="l">Nous ne vendons</span>
            <span class="l">pas d'espace.</span>
            <span class="l"><em>Nous vendons</em></span>
            <span class="l"><em>de l'attention.</em></span>
        </h1>

        <p class="hero__sous" data-rev=".24">
            +400 panneaux dans 31 communes, trente ans de terrain, et la preuve
            photo de chaque pose. Voilà ce qu'on met derrière votre message.
        </p>

        <div class="hero__actions" data-rev=".32">
            <a class="bt" href="{{ route('cible.contact') }}" data-viseur>
                Parler de mon projet<i class="fl"></i>
            </a>
            <a class="bt bt--creux" href="{{ route('cible.travaux') }}" data-viseur>
                Voir nos travaux
            </a>
        </div>

        <div class="hero__reperes" data-rev=".4">
            <span><b>+400</b> panneaux</span>
            <span><b>31</b> communes</span>
            <span><b>30</b> ans</span>
        </div>
    </div>

    {{-- Colonne droite : ce qu'on montre. Le motion dans son panneau blanc,
         posé sur le noir — une face éclairée dans la nuit.
         Motion du client : fond blanc, 1920×1080, 7 s, sans piste audio.
         muted + playsinline sont obligatoires, sans eux Safari iOS refuse
         la lecture automatique. --}}
    <div class="hero__scene">
        <div class="hero__panneau" id="panneau" data-rev=".12">
            <span class="hero__etiq"><b></b> En exploitation</span>
            <video src="{{ \App\Support\Contenu::urlDatee('refonte/motion/perroquet-blanc.mp4') }}"
                   autoplay muted loop playsinline preload="metadata"
                   poster="{{ \App\Support\Contenu::urlDatee('refonte/photo/perroquet.webp') }}"
                   aria-label="Animation du logo CIBLE"></video>
        </div>
    </div>
</section>


{{-- ═══════════════════════ MANIFESTE ═══════════════════════
     La signature de marque prend ici le rang qu'occupe « Truth Well Told »
     chez McCann : seule, grande, juste avant les travaux. Elle n'explique
     rien — c'est la galerie qui argumente. --}}
<section class="manif">
    <div class="manif__grille">
        <div class="manif__ph ph ph--nue">
            <img src="{{ \App\Support\Contenu::urlDatee('refonte/test/perroquet.webp') }}"
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
                <div class="carte__ph ph ph--scroll" style="--c:{{ $p['couleur'] ?? 'var(--rouge)' }}">
                    <img src="{{ \App\Support\Contenu::urlImage($p['image'] ?? 'images/cible/campagne-1.jpg') }}"
                         alt="{{ $p['cat'] ?? 'Campagne' }} — {{ $p['nom'] ?? '' }}" loading="lazy">
                </div>
                <span class="carte__nom" style="color:{{ $p['couleur'] ?? 'var(--rouge)' }}">{{ $p['nom'] ?? $slug }}</span>
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
                <div class="chiffre__v num"><span data-compte="{{ \App\Support\Contenu::get('chiffres.distinctions', 3) }}" data-pad="2">00</span></div>
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
        <div class="fin__ph ph ph--scroll" style="--c:var(--rouge)" data-rev=".16">
            <img src="{{ \App\Support\Contenu::urlDatee('refonte/test/foule-festive.webp') }}"
                 alt="Foule rassemblée en plein air" loading="lazy">
            <div class="ph__legende">Visuel de test · Unsplash, Andrey K</div>
        </div>
    </div>
</section>

@endsection

@push('js')
<script>
/* La parallaxe du panneau est retirée : le motion est désormais en
   tête du héro, et le décaler vers le haut ouvrirait un vide sous le
   cadre. Les plumes de décor gardent la leur. */
</script>
@endpush
