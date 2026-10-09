@extends('public.cible._coque', ['titre' => 'Nos travaux', 'actuelle' => 'travaux'])

@php
    // Données communes à plusieurs sections, calculées une fois.
    $nbCampagnes = count($realisations);
    $nbAffiche = str_pad($nbCampagnes, 2, '0', STR_PAD_LEFT);
    $visuelsTete = collect($realisations)->values();
    $colonnesTete = [
        $visuelsTete->all(),
        $visuelsTete->reverse()->values()->all(),
    ];
    $tousFiltres = collect($realisations)->pluck('filtres')->flatten()->unique()->filter()->values();
    // Libellés lisibles des filtres. Les clés réellement présentes dans
    // config/contenu.php (affichage-regie, digital-contenus…) n'avaient pas
    // de libellé et s'affichaient sans accent (« Design evenementiel »). Le
    // repli ucfirst() reste pour toute clé ajoutée plus tard depuis l'admin.
    $libelles = [
        'affichage'                => 'Affichage',
        'brand-experience'         => 'Brand experience',
        'street-marketing'         => 'Street marketing',
        'digital'                  => 'Digital',
        'audiovisuel'              => 'Audiovisuel',
        'institutionnel'           => 'Institutionnel',
        'production-audiovisuelle' => 'Production audiovisuelle',
        'affichage-regie'          => 'Affichage & régie',
        'digital-contenus'         => 'Digital & contenus',
        'design-evenementiel'      => 'Design événementiel',
    ];
    $clients = [
        'danone' => 'Danone', 'moov' => 'Moov Africa', 'sipra' => 'Sipra',
        'bgfibank' => 'BGFIBank', 'banque-atlantique' => 'Banque Atlantique', 'rimco' => 'Rimco',
        'autre-1' => 'Client', 'autre-2' => 'Client', 'autre-3' => 'Client',
        'autre-4' => 'Client', 'autre-5' => 'Client', 'autre-6' => 'Client',
    ];
    // Deux rangées de logos qui défilent en sens contraire.
    $rangeesClients = array_chunk($clients, 6, true);
@endphp

@push('css')
/* ═══════════════ TÊTE — titre + mur de campagnes ═══════════════
   2026-10-09 — Retour client : « trop vide avec des espaces blancs ».
   La tête n'occupait que la moitié gauche de l'écran, la droite restait
   blanche. Elle devient une composition à deux colonnes, sur le modèle du
   héro de l'accueil : le texte à gauche, un mur de campagnes en bichromie
   rouge à droite qui défile lentement (CSS pur, coupé en mouvement réduit). */
.tete{
  position:relative;overflow:hidden;
  padding:clamp(108px,13vh,150px) var(--pad) clamp(40px,5vw,64px);
}
.tete__grille{
  display:grid;grid-template-columns:1.08fr .92fr;
  gap:clamp(28px,4vw,64px);align-items:center;position:relative;z-index:2;
}
@media(max-width:960px){.tete__grille{grid-template-columns:1fr}}
.tete .sur{--c:var(--rouge)}
.tete__filet{width:52px;height:4px;background:var(--rouge);border-radius:4px;margin-top:14px}
.tete__t{margin-top:20px}
.tete__t .l{display:block}
.tete__t em{font-style:normal;color:var(--jaune)}
.tete .intro{margin-top:22px}
.tete__actions{display:flex;flex-wrap:wrap;gap:11px;margin-top:clamp(24px,3vw,34px)}

/* Le mur : panneau noir, deux colonnes de vignettes qui défilent en sens
   contraire. Chaque colonne contient ses vignettes en DOUBLE, condition
   pour que translateY(-50%) boucle sans saut (même principe que l'accueil). */
.tmur{
  position:relative;height:clamp(380px,40vw,580px);
  border-radius:clamp(16px,1.8vw,26px);overflow:hidden;background:var(--noir);
  box-shadow:0 40px 90px -46px rgba(17,17,17,.7);
}
.tmur__cols{
  position:absolute;inset:-6% 0;display:grid;grid-template-columns:1fr 1fr;
  gap:12px;padding-inline:12px;pointer-events:none;
}
.tmur__col{display:flex;flex-direction:column;animation:tmurMonte 40s linear infinite}
.tmur__col:nth-child(2){animation-name:tmurDescend;animation-duration:52s}
.tmur__col > div{display:flex;flex-direction:column;gap:12px;padding-bottom:12px}
.tmur .tuile{aspect-ratio:4/5;border-radius:12px;flex:0 0 auto}
@keyframes tmurMonte{to{transform:translateY(-50%)}}
@keyframes tmurDescend{from{transform:translateY(-50%)}to{transform:translateY(0)}}
@media (prefers-reduced-motion:reduce){.tmur__col{animation:none}}
@media(max-width:960px){.tmur{height:clamp(300px,62vw,440px)}}
/* Étiquette blanche : le compteur de campagnes. Aplat blanc franc posé à
   côté des photos, pas un voile par-dessus. */
.tmur__carte{
  position:absolute;z-index:3;left:clamp(12px,1.6vw,22px);bottom:clamp(14px,1.8vw,24px);
  background:var(--blanc);border-radius:14px;
  padding:clamp(14px,1.6vw,22px) clamp(16px,2vw,26px);
  display:flex;align-items:center;gap:16px;max-width:calc(100% - 24px);
  box-shadow:0 18px 40px -18px rgba(0,0,0,.6);
}
.tmur__n{
  font-family:var(--titre);font-weight:900;color:var(--rouge);
  font-size:clamp(44px,5vw,72px);line-height:.85;letter-spacing:-.05em;
}
.tmur__l{
  font-family:var(--titre);font-weight:700;font-size:11.5px;letter-spacing:.15em;
  text-transform:uppercase;color:var(--texte-2);line-height:1.5;max-width:20ch;
}
.tmur::after{content:"";position:absolute;inset:auto 0 0 0;height:4px;z-index:2;background:var(--rouge)}

/* ═══════════════ RUBAN NOIR — les six campagnes ═══════════════
   Il ouvre le bloc sombre de la galerie, comme le manifeste ouvre celui de
   l'accueil. Texte blanc sur noir (charte : pas de titre en couleur sur un
   fond non blanc) ; seules les pastilles portent le rouge. */
.ruban--noir{background:var(--noir);color:var(--blanc);border-block:0;border-bottom:1px solid rgba(255,255,255,.12)}
.ruban--noir .ruban__piste{animation-duration:44s}
.ruban--noir .ruban__cat{color:rgba(255,255,255,.55);font-weight:700}

/* ═══════════════ GALERIE — aplat noir ═══════════════
   Avant : une grande carte puis une grille de deux colonnes. Avec six
   campagnes, la sixième restait seule sur sa ligne, une demi-largeur vide
   à côté — le « trou blanc » le plus visible de la page.

   Grille éditoriale sur 12 colonnes : les lignes alternent 7/5 et 5/7, les
   photos ont la même hauteur dans une ligne (16/10 sur 7 colonnes, 8/7 sur
   5). Le script recalcule les positions après chaque filtre (data-pos), et
   un nombre impair fait passer la dernière carte en pleine largeur
   (data-seul) : la grille ne se troue jamais, quel que soit le filtre. */
.gal{background:var(--noir);color:var(--blanc);padding:0 var(--pad) clamp(64px,8vw,120px)}

/* Barre de filtres collante : elle reste sous la navigation pendant qu'on
   parcourt la galerie. Sur mobile, une seule ligne qui défile au doigt,
   pour ne pas manger l'écran. */
.gal__barre{
  position:sticky;top:70px;z-index:20;background:var(--noir);
  display:flex;align-items:center;gap:14px 22px;
  padding:clamp(18px,2.4vw,28px) 0;margin-bottom:clamp(18px,2.4vw,30px);
  border-bottom:1px solid rgba(255,255,255,.12);
}
@media(max-width:600px){.gal__barre{top:58px}}
.filtres{display:flex;flex-wrap:wrap;gap:8px;flex:1 1 auto;min-width:0}
.filtres button{
  font-family:var(--titre);font-weight:700;font-size:13px;white-space:nowrap;flex:0 0 auto;
  padding:10px 18px;border-radius:999px;cursor:pointer;
  background:transparent;color:rgba(255,255,255,.78);
  border:0;box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.22);
  transition:background .3s,color .3s,box-shadow .3s;
}
.filtres button:hover{color:var(--blanc);box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.6)}
.filtres button[aria-pressed="true"]{background:var(--rouge);color:var(--blanc);box-shadow:none}
@media(max-width:860px){
  .filtres{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;
    margin-right:calc(var(--pad) * -1);padding-right:var(--pad)}
  .filtres::-webkit-scrollbar{display:none}
}
.gal__compte{
  flex:0 0 auto;font-family:var(--titre);font-weight:700;font-size:12px;
  letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.55);
}
.gal__compte b{font-weight:900;font-size:clamp(22px,2vw,28px);letter-spacing:-.03em;color:var(--blanc);margin-right:4px}
@media(max-width:600px){.gal__compte{display:none}}

.grille{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:clamp(36px,4.4vw,64px) clamp(18px,2.4vw,36px)}
.oeuvre{grid-column:span 7;position:relative;scroll-margin-top:150px}
.oeuvre[data-pos="1"],.oeuvre[data-pos="2"]{grid-column:span 5}
.oeuvre[data-seul]{grid-column:1 / -1}
/* Hauteur commune (et non un ratio) : les deux photos d’une même ligne
   tombent exactement à la même hauteur, donc les textes démarrent alignés. */
.oeuvre__ph{height:clamp(300px,32vw,520px);border-radius:18px}
@media(max-width:860px){
  .oeuvre,.oeuvre[data-pos="1"],.oeuvre[data-pos="2"]{grid-column:1 / -1}
  .oeuvre__ph{height:auto;aspect-ratio:4/3}
}
/* Survol de toute la carte, pas seulement de la photo : la bichromie
   s'efface et la photo s'avance. */
.oeuvre:hover .ph::after{opacity:0}
.oeuvre:hover .ph img{filter:none;transform:scale(1.045)}
/* Numéro sur une pastille noire franche (aplat, pas un voile). */
.oeuvre__n{
  position:absolute;z-index:2;top:14px;left:14px;
  min-width:58px;padding:8px 12px;border-radius:12px;text-align:center;
  background:var(--noir);color:var(--blanc);
  font-family:var(--titre);font-weight:900;font-size:clamp(22px,2vw,30px);
  line-height:1;letter-spacing:-.04em;
}
.oeuvre__cat-ph{
  position:absolute;z-index:2;top:14px;right:14px;max-width:calc(100% - 110px);
  padding:8px 12px;border-radius:999px;background:var(--blanc);color:var(--noir);
  font-family:var(--titre);font-weight:700;font-size:10.5px;letter-spacing:.1em;
  text-transform:uppercase;line-height:1.3;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
@media(max-width:480px){.oeuvre__cat-ph{display:none}}
.oeuvre__client{
  display:block;margin-top:20px;
  font-family:var(--titre);font-weight:800;font-size:11px;
  letter-spacing:.17em;text-transform:uppercase;color:var(--c,var(--rouge));
}
.oeuvre__nom{
  margin-top:9px;font-family:var(--titre);font-weight:900;color:var(--blanc);
  font-size:clamp(22px,2.4vw,34px);line-height:1.06;letter-spacing:-.028em;
  text-transform:uppercase;max-width:24ch;
}
/* Filet qui s'allonge au survol : un signe d'interaction discret. */
.oeuvre__nom::after{
  content:"";display:block;width:34px;height:3px;margin-top:14px;
  background:var(--rouge);border-radius:3px;transition:width .6s var(--ease);
}
.oeuvre:hover .oeuvre__nom::after{width:96px}
.oeuvre__txt{margin-top:14px;font-size:15px;color:rgba(255,255,255,.7);line-height:1.6;max-width:62ch}
.oeuvre__serv{display:flex;flex-wrap:wrap;gap:6px;margin-top:16px;list-style:none}
.oeuvre__serv li{
  font-family:var(--titre);font-weight:700;font-size:11.5px;
  padding:6px 11px;border-radius:999px;color:rgba(255,255,255,.82);
  box-shadow:inset 0 0 0 1px rgba(255,255,255,.2);
}
.gal #vide{color:rgba(255,255,255,.7)}

/* ═══════════════ PREUVE — l'engagement et les chiffres ═══════════════
   Chiffres officiels uniquement (les mêmes que l'accueil, même source). */
.preuve{padding:clamp(56px,7vw,100px) var(--pad);position:relative;overflow:hidden}
.preuve__grille{
  display:grid;grid-template-columns:.95fr 1.05fr;gap:clamp(30px,5vw,80px);align-items:end;
  position:relative;z-index:2;
}
@media(max-width:900px){.preuve__grille{grid-template-columns:1fr}}
.preuve .t-grand{margin-top:18px;font-size:clamp(26px,3.3vw,50px)}
.preuve .t-grand em{font-style:normal;color:var(--rouge)}
.preuve .intro{margin-top:20px}
.chiffres{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:clamp(22px,3vw,40px) clamp(16px,2.4vw,34px)}
.chiffre{padding-top:20px;border-top:2px solid var(--c)}
.chiffre__v{
  font-family:var(--titre);font-weight:900;
  font-size:clamp(40px,5.6vw,84px);line-height:.88;letter-spacing:-.05em;
  display:flex;align-items:baseline;gap:2px;
}
.chiffre__v i{font-style:normal;color:var(--rouge);font-size:.52em}
.chiffre__l{font-family:var(--titre);font-weight:700;font-size:13px;margin-top:12px;color:var(--texte-2);line-height:1.35}

/* ═══════════════ CLIENTS ═══════════════
   ⚠ Leçons précédentes conservées : taille FIXE + object-fit:contain sur
   les logos (sinon un logo large étire sa carte), cartes claires (certains
   PNG ont un fond noir cuit), et bandeau défilant plutôt que grille (aucun
   débordement possible). L'écart est porté en padding, pas en gap, pour
   que translateX(-50%) boucle sans saut.

   2026-10-09 — Section resserrée (elle avait le padding d'un « bloc » plein
   pour une seule rangée de logos) et passée à deux rangées en sens
   contraire : plus dense, plus vivante. */
.clients{background:var(--fond-2);border-block:1px solid var(--ligne);overflow:hidden;padding:clamp(52px,6.5vw,92px) 0}
.clients__tete{padding:0 var(--pad);max-width:calc(1480px + var(--pad) * 2);margin-inline:auto}
.clients__tete .t-grand{margin-top:16px}
.clients__rangs{margin-top:clamp(28px,3.6vw,46px);display:grid;gap:14px}
.clients__ruban{overflow:hidden}
.clients__piste{display:flex;width:max-content;animation:clients 46s linear infinite}
.clients__ruban--inv .clients__piste{animation-direction:reverse;animation-duration:52s}
.clients__piste:hover{animation-play-state:paused}
.clients__piste > div{display:flex;gap:14px;padding-right:14px;flex:0 0 auto}
@keyframes clients{to{transform:translateX(-50%)}}
@media (prefers-reduced-motion:reduce){.clients__piste{animation:none}}
.client{
  width:clamp(140px,15vw,196px);height:clamp(90px,9vw,120px);flex:0 0 auto;
  border-radius:13px;background:var(--blanc);
  display:grid;place-items:center;padding:14px;
  box-shadow:inset 0 0 0 1px var(--ligne);
  filter:grayscale(1);opacity:.78;
  transition:filter .45s,opacity .45s,transform .45s var(--ease);
}
.client:hover{filter:none;opacity:1;transform:translateY(-4px)}
.client img{width:100%;height:100%;object-fit:contain;display:block}

/* ═══════════════ APPEL — aplat rouge ═══════════════
   Le rouge, couleur prépondérante de la charte, porte la dernière section.
   Texte blanc (pas de titre en couleur sur un fond non blanc). */
.appel{background:var(--rouge);color:var(--blanc);position:relative;overflow:hidden;padding:clamp(64px,8vw,120px) var(--pad)}
.appel__in{
  display:grid;grid-template-columns:1.3fr .7fr;gap:clamp(28px,4vw,60px);align-items:end;
  position:relative;z-index:2;
}
@media(max-width:900px){.appel__in{grid-template-columns:1fr}}
.appel .sur{color:rgba(255,255,255,.75)}
.appel .t-geant{margin-top:18px}
.appel__droite{display:flex;flex-direction:column;align-items:flex-start;gap:12px}
.appel__droite p{font-size:15.5px;line-height:1.6;color:rgba(255,255,255,.88);max-width:36ch;margin-bottom:8px}
.appel .bt{background:var(--blanc);color:var(--noir)}
.appel .bt:hover{background:var(--noir);color:var(--blanc)}
.appel .bt--creux{background:transparent;color:var(--blanc);box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.55)}
.appel .bt--creux:hover{background:rgba(255,255,255,.12);color:var(--blanc);box-shadow:inset 0 0 0 1.5px var(--blanc)}
@endpush

@section('contenu')

{{-- ═══════════════════════ TÊTE ═══════════════════════ --}}
<section class="tete">
    <div class="plume" style="--c:var(--jaune);--op:.07;top:-10%;left:38%;width:clamp(200px,24vw,360px)" data-par="-16" data-rot="-12"></div>
    <div class="large tete__grille">
        <div>
            <p class="sur" data-rev>Nos travaux · <b>Côte d'Ivoire</b></p>
            <div class="tete__filet" data-rev=".04"></div>
            {{-- Lignes posées à la main : le découpeur automatique effacerait
                 le <em> jaune en reconstruisant le titre depuis le texte. --}}
            <h1 class="t-geant tete__t" data-cascade>
                <span class="l">Six campagnes.</span>
                <span class="l"><em>Et la preuve</em></span>
                <span class="l"><em>de chacune.</em></span>
            </h1>
            <p class="intro" data-rev=".12">
                Des marques institutionnelles, bancaires et de grande consommation. Pour
                chacune, le même engagement : une recommandation justifiée, puis la photo
                horodatée de ce qui a été posé.
            </p>
            <div class="tete__actions" data-rev=".18">
                <a class="bt" href="#galerie" data-viseur>Voir les campagnes<i class="fl"></i></a>
                <a class="bt bt--creux" href="{{ route('cible.contact') }}" data-viseur>Parler de mon projet</a>
            </div>
        </div>

        {{-- Mur de campagnes : décoratif (aria-hidden), la galerie juste en
             dessous porte les mêmes visuels avec leur texte. Première moitié
             de chaque colonne chargée tout de suite (en tête de page), la
             copie qui sert à la boucle en différé. --}}
        <div class="tmur" data-rev=".1">
            <div class="tmur__cols" aria-hidden="true">
                @foreach($colonnesTete as $colonne)
                    <div class="tmur__col">
                        @for($passe = 0; $passe < 2; $passe++)
                            <div>
                                @foreach($colonne as $p)
                                    <div class="tuile ph" style="--c:var(--rouge)">
                                        <img src="{{ \App\Support\Contenu::urlImage($p['image'] ?? 'images/cible/campagne-1.jpg') }}"
                                             alt="" width="400" height="500" @if($passe) loading="lazy" @endif>
                                    </div>
                                @endforeach
                            </div>
                        @endfor
                    </div>
                @endforeach
            </div>
            <div class="tmur__carte">
                <span class="tmur__n num" data-compte="{{ $nbCampagnes }}" data-pad="2">{{ $nbAffiche }}</span>
                <span class="tmur__l">Campagnes · et la preuve de chacune</span>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════ RUBAN — les campagnes ═══════════════════════ --}}
<div class="ruban ruban--noir" aria-hidden="true">
    <div class="ruban__piste">
        @for($passe = 0; $passe < 2; $passe++)
            <div>
                @foreach($realisations as $p)
                    <span>{{ $p['nom'] ?? '' }} <span class="ruban__cat">· {{ $p['cat'] ?? '' }}</span></span><span class="ruban__pt"></span>
                @endforeach
            </div>
        @endfor
    </div>
</div>

{{-- ═══════════════════════ GALERIE — LES 6 TRAVAUX ═══════════════════════ --}}
<section class="gal" id="galerie" aria-labelledby="gal-titre">
    <h2 id="gal-titre" class="hors-ecran">Les campagnes</h2>
    <div class="large">
        <div class="gal__barre">
            <div class="filtres" id="filtres" role="group" aria-label="Filtrer les campagnes">
                <button type="button" data-f="tous" aria-pressed="true">Tout</button>
                @foreach($tousFiltres as $f)
                    <button type="button" data-f="{{ $f }}" aria-pressed="false">
                        {{ $libelles[$f] ?? ucfirst(str_replace('-', ' ', $f)) }}
                    </button>
                @endforeach
            </div>
            <p class="gal__compte" aria-live="polite">
                <b class="num" id="nb">{{ $nbAffiche }}</b>/ {{ $nbAffiche }} campagnes
            </p>
        </div>

        <div class="grille" id="grille">
            @foreach($realisations as $slug => $p)
                @php
                    $seul = $loop->last && $nbCampagnes % 2 === 1;
                    $services = array_values(array_filter(array_map('trim', explode('·', $p['services'] ?? ''))));
                @endphp
                <article class="oeuvre"
                         id="{{ $slug }}"
                         style="--c:{{ $p['couleur'] ?? 'var(--rouge)' }}"
                         data-pos="{{ $loop->index % 4 }}" @if($seul) data-seul @endif
                         data-filtres="{{ implode(' ', $p['filtres'] ?? []) }}"
                         data-rev="{{ ($loop->index % 2) * 0.08 }}">
                    <div class="oeuvre__ph ph ph--scroll">
                        <img src="{{ \App\Support\Contenu::urlImage($p['image'] ?? 'images/cible/campagne-1.jpg') }}"
                             alt="Campagne {{ $p['nom'] ?? $slug }}" width="1200" height="750" loading="lazy">
                        <span class="oeuvre__n num" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        @if(!empty($p['cat']))
                            <span class="oeuvre__cat-ph">{{ $p['cat'] }}</span>
                        @endif
                    </div>
                    <span class="oeuvre__client">{{ $p['nom'] ?? $slug }}</span>
                    <h3 class="oeuvre__nom">{{ $p['titre_court'] ?? ($p['titre'] ?? '') }}</h3>
                    <p class="oeuvre__txt">{{ $p['texte'] ?? '' }}</p>
                    @if($services)
                        <ul class="oeuvre__serv" aria-label="Prestations">
                            @foreach($services as $s)<li>{{ $s }}</li>@endforeach
                        </ul>
                    @endif
                </article>
            @endforeach
        </div>

        <p id="vide" class="intro" style="margin-top:34px;display:none">
            Aucune campagne sur ce filtre pour l'instant.
        </p>
    </div>
</section>

{{-- ═══════════════════════ PREUVE ═══════════════════════
     Textes et chiffres repris de l'accueil, sans ajout. --}}
<section class="preuve">
    <div class="fleche-d" style="--c:var(--jaune);--op:.1;top:12%;right:4%;width:clamp(90px,10vw,150px)" data-par="-24" data-rot="-14"></div>
    <div class="large preuve__grille">
        <div>
            <p class="sur" style="--c:var(--rouge)">Régie &amp; studio · <b>depuis 1994</b></p>
            <h2 class="t-grand" data-lignes>Chaque campagne, de la recommandation <em>à la preuve de pose.</em></h2>
            <p class="intro" data-rev=".1">
                Affichage, digital, terrain et pilotage par la donnée. Quatre leviers,
                +400 panneaux dans 31 communes, et la preuve photo de chaque pose.
            </p>
        </div>
        <div class="chiffres" data-cascade>
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

{{-- ═══════════════════════ CLIENTS ═══════════════════════ --}}
<section class="clients">
    <div class="clients__tete">
        <p class="sur" style="--c:var(--rouge)">Ils nous font confiance</p>
        <h2 class="t-grand" data-lignes>Des marques qui ne laissent rien au hasard.</h2>
    </div>

    <div class="clients__rangs" aria-label="Quelques-uns de nos clients">
        @foreach($rangeesClients as $r => $rangee)
            <div class="clients__ruban @if($r % 2) clients__ruban--inv @endif">
                <div class="clients__piste">
                    {{-- Deux moitiés identiques : translateX(-50%) ne boucle sans
                         saut qu'à cette condition. Chaque moitié répète la
                         rangée deux fois : six logos seuls ne couvrent pas un
                         écran large. --}}
                    @for($passe = 0; $passe < 2; $passe++)
                        <div @if($passe) aria-hidden="true" @endif>
                            @for($rep = 0; $rep < 2; $rep++)
                                @foreach($rangee as $f => $nom)
                                    <div class="client">
                                        <img src="{{ asset('refonte/client/' . $f . '.png') }}"
                                             alt="{{ ($passe || $rep) ? '' : $nom }}" width="196" height="120" loading="lazy">
                                    </div>
                                @endforeach
                            @endfor
                        </div>
                    @endfor
                </div>
            </div>
        @endforeach
    </div>
</section>

{{-- ═══════════════════════ APPEL — aplat rouge ═══════════════════════ --}}
<section class="appel">
    <div class="plume" style="--c:var(--blanc);--op:.1;bottom:-14%;right:3%;width:clamp(180px,22vw,320px)" data-par="14" data-rot="-12"></div>
    <div class="large appel__in">
        <div>
            <p class="sur">À vous</p>
            <h2 class="t-geant" data-lignes>La prochaine, c'est la vôtre.</h2>
        </div>
        <div class="appel__droite" data-rev=".1">
            <p>
                Un échange de quinze minutes suffit à savoir si on peut vous être utile.
                Réponse sous 24 heures ouvrées, sans engagement.
            </p>
            <a class="bt" href="{{ route('cible.contact') }}" data-viseur>
                Parler de mon projet<i class="fl"></i>
            </a>
            <a class="bt bt--creux" href="tel:+2250700780628" data-viseur>
                +225 07 00 78 06 28
            </a>
        </div>
    </div>
</section>

@endsection

@push('js')
<script>
/* Filtrage des travaux. Sans JS, toutes les campagnes restent visibles et
   la grille est déjà placée côté serveur (data-pos / data-seul). */
(function () {
  var barre   = document.getElementById('filtres');
  var oeuvres = Array.prototype.slice.call(document.querySelectorAll('.oeuvre'));
  var vide    = document.getElementById('vide');
  var nb      = document.getElementById('nb');
  if (!barre || !oeuvres.length) { return; }
  var doux = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Positions dans la grille 7/5 – 5/7, recalculées sur les seules cartes
  // visibles ; la dernière d'un nombre impair passe en pleine largeur.
  function placer(visibles) {
    visibles.forEach(function (o, i) {
      o.dataset.pos = String(i % 4);
      o.removeAttribute('data-seul');
    });
    if (visibles.length % 2 === 1) {
      visibles[visibles.length - 1].setAttribute('data-seul', '');
    }
  }

  barre.addEventListener('click', function (e) {
    var bouton = e.target.closest('button[data-f]');
    if (!bouton) { return; }

    barre.querySelectorAll('button').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b === bouton));
    });

    var f = bouton.dataset.f;
    var visibles = oeuvres.filter(function (o) {
      var ok = f === 'tous' || (' ' + o.dataset.filtres + ' ').indexOf(' ' + f + ' ') !== -1;
      o.hidden = !ok;
      return ok;
    });

    placer(visibles);
    if (nb) { nb.textContent = (visibles.length < 10 ? '0' : '') + visibles.length; }
    vide.style.display = visibles.length ? 'none' : 'block';

    // Courte entrée en cascade des cartes retenues.
    if (doux && typeof gsap !== 'undefined' && visibles.length) {
      gsap.fromTo(visibles, { opacity: 0, y: 24 },
        { opacity: 1, y: 0, duration: .7, ease: 'expo.out', stagger: .06, overwrite: true });
    }
    if (typeof ScrollTrigger !== 'undefined') { ScrollTrigger.refresh(); }
  });
})();
</script>
@endpush
