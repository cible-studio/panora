@extends('public.cible._coque', ['titre' => "Ce qu'on fait", 'actuelle' => 'expertises'])

@push('css')
/* ═══════════════════════════════════════════════════════════════════
   EXPERTISES — refonte du 2026-10-09.
   Retour client : « trop vide, des espaces blancs, anime, dynamise ».
   La page tient désormais le rythme de l'accueil : alternance blanc /
   aplat noir, photos en bichromie qui reprennent leurs couleurs, numéros
   géants, compteurs, un défilé de photos terrain. Aucun contenu nouveau :
   tout vient de V2Controller::POLES, des chiffres officiels et des photos
   CIBLE déjà présentes dans public/refonte/photo.
═══════════════════════════════════════════════════════════════════ */

/* ═══════════════ TÊTE ═══════════════
   Deux colonnes : le propos à gauche, une mosaïque des quatre pôles à
   droite. La mosaïque occupe l'espace blanc qui flanquait le titre, et
   sert aussi de sommaire visuel (chaque tuile mène à son pôle). */
.tete{padding:clamp(118px,15vh,176px) var(--pad) clamp(52px,6.5vw,96px);position:relative;overflow:hidden}
.tete .sur{--c:var(--rouge)}
.tete__grille{display:grid;grid-template-columns:1.08fr .92fr;gap:clamp(32px,5vw,84px);align-items:center;position:relative;z-index:2}
@media(max-width:980px){.tete__grille{grid-template-columns:1fr}}
.tete__t{margin-top:20px}
.tete__t em{font-style:normal;color:var(--rouge)}
.tete__filet{width:52px;height:4px;background:var(--rouge);border-radius:4px;margin-top:16px}

/* Sommaire des quatre pôles, en rangée sous le titre.
   ⚠ Il était en colonne FIXE à gauche de l'écran (position:fixed, top:50%).
   Sur la capture client, il passait par-dessus les titres de section : un
   élément fixe est hors du flux, donc rien ne lui réserve de place. Remis
   dans le flux, en rangée, le chevauchement ne peut plus se produire. */
.sommaire{display:flex;flex-wrap:wrap;gap:8px;margin-top:clamp(26px,3.6vw,38px)}
.sommaire a{
  display:inline-flex;align-items:baseline;gap:10px;
  padding:11px 18px;border-radius:999px;
  box-shadow:inset 0 0 0 1.5px var(--ligne);
  font-family:var(--titre);font-weight:700;font-size:13.5px;color:var(--texte-2);
  transition:background .3s,color .3s,box-shadow .3s;
}
.sommaire a b{font-family:var(--chiffres);font-size:11px;letter-spacing:.08em;color:var(--rouge)}
.sommaire a:hover,.sommaire a.actif{background:var(--noir);color:#fff;box-shadow:none}
.sommaire a:hover b,.sommaire a.actif b{color:var(--jaune)}

/* Mosaïque : deux colonnes décalées, toutes en bichromie ROUGE (le rouge
   reste prépondérant ; la couleur propre du pôle n'apparaît qu'en pastille).
   La photo reprend ses couleurs au survol. */
.mosa{display:grid;grid-template-columns:1fr 1fr;gap:clamp(10px,1.2vw,18px)}
.mosa__col{display:flex;flex-direction:column;gap:clamp(10px,1.2vw,18px)}
.mosa__col:nth-child(2){margin-top:clamp(34px,5vw,84px)}
.mosa__tuile{display:block;aspect-ratio:4/5;border-radius:clamp(14px,1.4vw,20px)}
@media(max-width:980px){.mosa__tuile{aspect-ratio:1/1}.mosa__col:nth-child(2){margin-top:28px}}
.mosa__lab{
  position:absolute;z-index:2;left:10px;right:10px;bottom:10px;
  display:flex;align-items:center;gap:9px;
  padding:9px 12px;border-radius:9px;background:var(--blanc);color:var(--noir);
  font-family:var(--titre);font-weight:700;font-size:12px;line-height:1.25;
  box-shadow:0 2px 10px rgba(17,17,17,.12);
  transition:transform .5s var(--ease);
}
.mosa__lab i{width:8px;height:8px;border-radius:50%;background:var(--c-pole);flex:0 0 auto}
.mosa__lab b{font-family:var(--chiffres);font-weight:800;color:var(--texte-3);letter-spacing:.06em}
.mosa__tuile:hover .mosa__lab{transform:translateY(-4px)}
@media(max-width:420px){.mosa__lab{font-size:10.5px;padding:7px 9px;gap:6px;left:7px;right:7px;bottom:7px}}

/* ═══════════════ UN PÔLE ═══════════════
   Structure voulue par le client : accroche → introduction → ce que nous
   faisons → comment nous travaillons → notre différence → appel.
   Un filet supérieur, un numéro géant et une étiquette portent la couleur.
   Pas de halo radial : la charte proscrit les dégradés. */
.pole{
  padding:clamp(64px,8vw,118px) var(--pad);
  border-top:1px solid var(--ligne);
  position:relative;overflow:hidden;scroll-margin-top:80px;
  --tuile:var(--fond-2);
}
.pole--gris{background:var(--fond-2);--tuile:var(--blanc)}
.pole__filet{position:absolute;inset:0 0 auto 0;height:5px;background:var(--c)}
/* Ancres #p1…#p4 : la liste des métiers de l'accueil pointe vers elles. */
.pole__ancre{position:absolute;top:0;left:0;scroll-margin-top:80px}

.pole__haut{display:grid;grid-template-columns:.94fr 1.06fr;gap:clamp(28px,4.4vw,72px);align-items:center;position:relative;z-index:2}
@media(min-width:941px){.pole--inverse .pole__texte{order:2}}
@media(max-width:940px){.pole__haut{grid-template-columns:1fr;gap:30px}}
.pole__marque{display:flex;align-items:flex-end;gap:clamp(12px,1.6vw,20px);flex-wrap:wrap}
.pole__num{
  font-family:var(--chiffres);font-weight:900;color:var(--c);
  font-size:clamp(76px,11vw,168px);line-height:.78;letter-spacing:-.06em;
}
.pole__etiq{
  display:inline-flex;align-items:center;gap:9px;margin-bottom:4px;
  padding:8px 15px;border-radius:999px;background:var(--c);color:var(--sur-c,#fff);
  font-family:var(--titre);font-weight:800;font-size:11px;
  letter-spacing:.14em;text-transform:uppercase;
}
.pole__t{margin-top:clamp(20px,2.4vw,30px)}
.pole__intro{margin-top:18px;color:var(--texte-2);max-width:54ch;font-size:16.5px;line-height:1.68}
.pole__ph{aspect-ratio:5/4;border-radius:22px}
.pole__ph img{transform:scale(1.14)}
.pole__ph:hover img{transform:scale(1.17)}
@media(max-width:940px){.pole__ph{aspect-ratio:16/10}}

.bloc-t{
  font-family:var(--titre);font-weight:800;font-size:11.5px;
  letter-spacing:.15em;text-transform:uppercase;color:var(--texte-3);
  display:flex;align-items:center;gap:14px;
}
.bloc-t::after{content:"";flex:1;height:1px;background:var(--ligne)}

/* ── Ce que nous faisons : tuiles ── */
.pole__faisons{margin-top:clamp(40px,5vw,68px);position:relative;z-index:2}
.faisons{list-style:none;padding:0;margin:18px 0 0;display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.faisons li{
  position:relative;overflow:hidden;
  background:var(--tuile);border-radius:16px;
  padding:20px 22px 22px;
  transition:transform .5s var(--ease),box-shadow .5s var(--ease);
}
.faisons li::before{
  content:"";position:absolute;inset:0 0 auto 0;height:3px;background:var(--c);
  transform:scaleX(0);transform-origin:0 50%;transition:transform .6s var(--ease);
}
.faisons li:hover{transform:translateY(-4px);box-shadow:0 18px 40px -22px rgba(17,17,17,.35)}
.faisons li:hover::before{transform:scaleX(1)}
.faisons__n{font-family:var(--chiffres);font-weight:800;font-size:12px;letter-spacing:.1em;color:var(--c-txt)}
.faisons strong{display:block;margin-top:10px;font-family:var(--titre);font-weight:800;font-size:16.5px;line-height:1.3}
.faisons span:last-child{display:block;margin-top:5px;font-size:14.5px;color:var(--texte-2);line-height:1.55}
/* Un nombre d'éléments qui ne remplit pas la dernière rangée : le dernier
   s'étire, pour ne jamais laisser un trou en bout de grille. */
@media(min-width:941px){.faisons li:last-child:nth-child(3n+2){grid-column:span 2}}
@media(max-width:940px){
  .faisons{grid-template-columns:1fr 1fr}
  .faisons li:last-child:nth-child(odd){grid-column:span 2}
}
@media(max-width:600px){.faisons li{padding:16px 16px 18px}.faisons strong{font-size:15.5px}}
@media(max-width:440px){
  .faisons{grid-template-columns:1fr}
  .faisons li:last-child:nth-child(odd){grid-column:auto}
}

/* ── Comment nous travaillons : frise horizontale ──
   La méthode est une vraie séquence : on la numérote et on la relie. Le
   trait de couleur se trace au défilement (script en bas de page). */
.pole__methode{margin-top:clamp(36px,4.4vw,58px);position:relative;z-index:2}
.methode-piste{position:relative;margin-top:24px}
.methode-piste::before,.methode__trait{
  content:"";position:absolute;top:13px;left:14px;right:calc(25% - 32px);height:2px;
}
.methode-piste::before{background:var(--ligne)}
.methode__trait{background:var(--c);transform-origin:0 50%}
.methode{list-style:none;padding:0;margin:0;counter-reset:m;display:grid;grid-template-columns:repeat(4,1fr);gap:24px;position:relative}
.methode li{counter-increment:m;position:relative;padding-top:46px}
.methode li::before{
  content:counter(m,decimal-leading-zero);position:absolute;left:0;top:0;
  width:28px;height:28px;border-radius:50%;background:var(--c);color:var(--sur-c,#fff);
  font-family:var(--chiffres);font-weight:800;font-size:11px;
  display:grid;place-items:center;
  box-shadow:0 0 0 6px var(--fond-pole,var(--blanc));
}
.pole--gris .methode li::before{--fond-pole:var(--fond-2)}
.methode strong{display:block;font-family:var(--titre);font-weight:800;font-size:clamp(17px,1.6vw,20px);letter-spacing:-.01em}
.methode span{display:block;font-size:14.5px;color:var(--texte-2);margin-top:4px;line-height:1.5;max-width:28ch}
@media(max-width:760px){
  .methode-piste::before,.methode__trait{display:none}
  .methode{grid-template-columns:1fr;gap:0}
  .methode li{padding:0 0 20px 46px}
  .methode li:not(:last-child)::after{content:"";position:absolute;left:13.5px;top:31px;bottom:4px;width:1.5px;background:var(--ligne)}
}

/* ── Notre différence + appel : carte en aplat noir ──
   Le texte y est blanc (jamais de couleur de marque sur fond non blanc) ;
   la couleur du pôle ne passe que par le filet et la plume de décor. */
.pole__pied{
  margin-top:clamp(38px,4.6vw,60px);position:relative;z-index:2;overflow:hidden;
  display:grid;grid-template-columns:1fr auto;gap:clamp(20px,3vw,44px);align-items:center;
  background:var(--noir);color:var(--blanc);border-radius:22px;
  padding:clamp(26px,3.4vw,48px) clamp(26px,3.8vw,56px);
}
.pole__pied::before{content:"";position:absolute;inset:0 auto 0 0;width:6px;background:var(--c)}
.pole__pied > :not(.plume){position:relative;z-index:1}
.pole__pied .sur{color:rgba(255,255,255,.55)}
.pole__diff{margin-top:12px;font-family:var(--titre);font-weight:700;font-size:clamp(17px,1.9vw,24px);line-height:1.4;max-width:52ch;letter-spacing:-.01em}
@media(max-width:760px){.pole__pied{grid-template-columns:1fr;align-items:start}.pole__pied .bt{justify-self:start}}

/* ═══════════════ LA RUE — aplat noir ═══════════════
   Intercalé après l'affichage (pôle 01), dont il est la preuve : les
   chiffres officiels, puis un défilé de vraies photos de pose CIBLE. Même
   alternance blanc / noir que l'accueil. */
.rue{background:var(--noir);color:var(--blanc);padding:clamp(64px,8vw,118px) 0 clamp(56px,7vw,100px);position:relative;overflow:hidden}
.rue__haut{
  padding:0 var(--pad);display:grid;grid-template-columns:.9fr 1.1fr;
  gap:clamp(30px,5vw,80px);align-items:end;position:relative;z-index:2;
}
@media(max-width:980px){.rue__haut{grid-template-columns:1fr}}
.rue .sur{color:rgba(255,255,255,.55)}
.rue .t-grand{margin-top:16px}
.rue__txt{margin-top:18px;color:rgba(255,255,255,.72);font-size:clamp(16px,1.4vw,19px);max-width:44ch}
.rue__chiffres{display:grid;grid-template-columns:repeat(4,1fr);gap:clamp(14px,2vw,28px)}
@media(max-width:620px){.rue__chiffres{grid-template-columns:1fr 1fr;gap:26px 18px}}
.chiffre{padding-top:18px;border-top:2px solid var(--c)}
.chiffre__v{
  font-family:var(--chiffres);font-weight:900;
  font-size:clamp(40px,4.8vw,74px);line-height:.9;letter-spacing:-.05em;
}
.chiffre__l{font-family:var(--titre);font-weight:700;font-size:13px;margin-top:10px;color:rgba(255,255,255,.66);line-height:1.35}

.defile{margin-top:clamp(44px,5.5vw,76px);overflow:hidden}
.defile__piste{display:flex;width:max-content;animation:defile 64s linear infinite}
.defile:hover .defile__piste{animation-play-state:paused}
.defile__piste > div{display:flex;gap:clamp(10px,1.2vw,16px);padding-right:clamp(10px,1.2vw,16px)}
.defile .ph{width:clamp(250px,30vw,440px);aspect-ratio:3/2;border-radius:16px;flex:0 0 auto;background:#222}
@media (prefers-reduced-motion:reduce){
  .defile{overflow-x:auto}
  .defile__piste{animation:none}
}
.rue__pied{
  padding:0 var(--pad);margin-top:clamp(30px,4vw,48px);
  display:flex;align-items:center;flex-wrap:wrap;gap:14px 28px;position:relative;z-index:2;
}
.rue__pied p{font-family:var(--titre);font-weight:700;font-size:clamp(15px,1.4vw,18px);color:rgba(255,255,255,.78);margin:0}
.rue .bt--clair{background:var(--blanc);color:var(--noir);margin-left:auto}
.rue .bt--clair:hover{background:var(--jaune)}
@media(max-width:620px){.rue .bt--clair{margin-left:0}}

/* ═══════════════ APPEL FINAL ═══════════════
   Les quatre pôles reviennent sous forme de quatre portes d'entrée, avec
   l'appel propre à chacun : le visiteur repart avec une action, pas avec
   un catalogue. */
.appel{position:relative;overflow:hidden;padding-block:clamp(72px,9vw,140px)}
.appel__tete{max-width:900px;margin-inline:auto;text-align:center;position:relative;z-index:2}
.appel__tete .t-geant{margin-top:18px}
.appel__tete .intro{margin:22px auto 0}
.portes{
  display:grid;grid-template-columns:repeat(4,1fr);gap:12px;
  margin-top:clamp(40px,5vw,64px);position:relative;z-index:2;
}
@media(max-width:1000px){.portes{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.portes{grid-template-columns:1fr}}
.porte{
  position:relative;overflow:hidden;display:flex;flex-direction:column;
  min-height:clamp(170px,15vw,220px);padding:22px 22px 20px;border-radius:18px;
  background:var(--fond-2);
  transition:background .45s var(--ease),color .45s,transform .5s var(--ease);
}
.porte::before{content:"";position:absolute;inset:0 0 auto 0;height:4px;background:var(--c)}
.porte__n{font-family:var(--chiffres);font-weight:900;font-size:clamp(34px,3.4vw,48px);line-height:.9;letter-spacing:-.04em;color:var(--c-txt)}
.porte__nom{margin-top:12px;font-family:var(--titre);font-weight:800;font-size:15px;line-height:1.3}
.porte__cta{
  margin-top:auto;padding-top:18px;display:flex;align-items:center;justify-content:space-between;gap:10px;
  font-family:var(--titre);font-weight:700;font-size:13.5px;color:var(--texte-2);
}
.porte__cta i{
  width:34px;height:34px;flex:0 0 auto;border-radius:50%;display:grid;place-items:center;
  box-shadow:inset 0 0 0 1.5px var(--ligne);transition:transform .45s var(--ease),background .45s,box-shadow .45s;
}
.porte__cta i::after{
  content:"";width:14px;height:14px;background:currentColor;
  -webkit-mask:url('{{ asset('images/fleche.svg') }}') no-repeat center/contain;
  mask:url('{{ asset('images/fleche.svg') }}') no-repeat center/contain;
}
.porte:hover{background:var(--noir);color:var(--blanc);transform:translateY(-4px)}
.porte:hover .porte__n{color:var(--blanc)}
.porte:hover .porte__cta{color:rgba(255,255,255,.8)}
.porte:hover .porte__cta i{background:var(--rouge);box-shadow:none;transform:rotate(-45deg);color:#fff}
.appel__actions{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-top:clamp(34px,4vw,48px);position:relative;z-index:2}
@endpush

@section('contenu')

@php
    // Texte sur aplat de couleur : blanc, sauf sur le jaune où il passe en
    // noir (le blanc sur jaune est illisible).
    $surCouleur = fn ($c) => $c === 'var(--jaune)' ? 'var(--noir)' : '#fff';

    // Photos de pose CIBLE (vraies photos terrain, pas des visuels de test).
    $photosRue = [
        ['refonte/photo/affichage.webp',  1600, 1067, "Panneau grand format en bord de voie"],
        ['refonte/photo/lumipub.webp',    1600, 1067, "Panneau d'affichage sur un terre-plein"],
        ['refonte/photo/mobile.webp',     1600, 1067, "Camion podium de communication mobile"],
        ['refonte/photo/campagne-2.webp', 1600, 2133, "Bâche publicitaire grand format"],
        ['refonte/photo/rue.webp',        1800, 1460, "Panneaux le long d'un boulevard"],
    ];
@endphp

{{-- ═══════════════════════ TÊTE ═══════════════════════ --}}
<section class="tete">
    <div class="plume" style="--c:var(--rouge);--op:.07;top:-12%;left:-6%;width:clamp(190px,24vw,340px)" data-par="-18" data-rot="12"></div>
    <div class="large tete__grille">
        <div>
            <p class="sur" data-rev>Nos expertises</p>
            <div class="tete__filet" data-rev=".04"></div>
            <h1 class="t-geant tete__t" data-lignes>Quatre métiers. <em>Un seul résultat attendu.</em></h1>
            <p class="intro" style="margin-top:22px" data-rev=".12">
                On ne vous vendra pas « du 360 ». On vous dira lequel de ces quatre leviers
                sert votre objectif, et pourquoi les autres peuvent attendre.
            </p>

            <nav class="sommaire" id="sommaire" aria-label="Les quatre pôles" data-rev=".2">
                @foreach($poles as $pole)
                    <a href="#{{ $pole['id'] }}" data-viseur><b>{{ $pole['num'] }}</b> {!! $pole['nom'] !!}</a>
                @endforeach
            </nav>
        </div>

        {{-- Mosaïque : doublon visuel du sommaire, donc masquée aux lecteurs
             d'écran (aria-hidden + tabindex=-1). --}}
        <div class="mosa" aria-hidden="true">
            @foreach(collect($poles)->chunk(2) as $col => $paire)
                <div class="mosa__col" data-mosa="{{ $col }}">
                    @foreach($paire as $i => $pole)
                        <a class="mosa__tuile ph" href="#{{ $pole['id'] }}" tabindex="-1"
                           style="--c:var(--rouge);--c-pole:{{ $pole['couleur'] }}" data-rev="{{ 0.1 + $i * 0.08 }}">
                            <img src="{{ asset('refonte/' . $pole['visuel'] . '.webp') }}" alt=""
                                 width="1200" height="1600"
                                 @if($i === 0) fetchpriority="high" @endif>
                            <span class="mosa__lab"><i></i><b>{{ $pole['num'] }}</b> {!! $pole['nom'] !!}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Ruban : les savoir-faire des quatre pôles, tirés de leurs listes. --}}
<div class="ruban" aria-hidden="true">
    <div class="ruban__piste">
        @for($passe = 0; $passe < 2; $passe++)
            <div>
                @foreach($poles as $pole)
                    @foreach(array_slice($pole['faisons'], 0, 2) as [$quoi])
                        <span>{{ $quoi }}</span><span class="ruban__pt" style="background:{{ $pole['couleur'] }}"></span>
                    @endforeach
                @endforeach
            </div>
        @endfor
    </div>
</div>

{{-- ═══════════════════════ LES 4 PÔLES ═══════════════════════
     Contenu dans V2Controller::POLES. Les pôles 01 et 02 viennent du
     client ; 03 et 04 sont écrits dans la même structure et restent à
     valider. --}}
@foreach($poles as $pole)
    <section class="pole{{ $loop->even ? ' pole--gris pole--inverse' : '' }}" id="{{ $pole['id'] }}" data-pole
             style="--c:{{ $pole['couleur'] }};--c-txt:{{ $pole['couleur_texte'] ?? $pole['couleur'] }};--sur-c:{{ $surCouleur($pole['couleur']) }}">
        <span class="pole__ancre" id="p{{ $loop->iteration }}"></span>
        <div class="pole__filet"></div>

        <div class="large">
            <div class="pole__haut">
                <div class="pole__texte">
                    <div class="pole__marque">
                        <span class="pole__num" aria-hidden="true" data-rev>{{ $pole['num'] }}</span>
                        <span class="pole__etiq" data-rev=".06">{!! $pole['nom'] !!}</span>
                    </div>
                    <h2 class="t-grand pole__t" data-lignes>{{ $pole['accroche'] }}</h2>
                    <p class="pole__intro" data-rev=".12">{{ $pole['intro'] }}</p>
                </div>
                <div class="pole__ph ph ph--scroll" style="--c:{{ $pole['couleur'] }}" data-rev=".1">
                    <img src="{{ asset('refonte/' . $pole['visuel'] . '.webp') }}"
                         alt="{{ strip_tags($pole['nom']) }}" width="1200" height="1600" loading="lazy">
                </div>
            </div>

            <div class="pole__faisons">
                <p class="bloc-t">Ce que nous faisons</p>
                <ul class="faisons" data-cascade>
                    @foreach($pole['faisons'] as $k => [$quoi, $precision])
                        <li>
                            <span class="faisons__n">{{ str_pad($k + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <strong>{{ $quoi }}</strong>
                            <span>{{ $precision }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="pole__methode">
                <p class="bloc-t">Comment nous travaillons</p>
                <div class="methode-piste">
                    <div class="methode__trait" aria-hidden="true"></div>
                    <ol class="methode" data-cascade>
                        @foreach($pole['methode'] as [$etape, $detail])
                            <li>
                                <strong>{{ $etape }}</strong>
                                <span>{{ $detail }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            <div class="pole__pied" data-rev>
                <div class="plume" style="--c:{{ $pole['couleur'] }};--op:.22;top:-30%;right:22%;width:clamp(110px,12vw,170px)" data-par="-30" data-rot="14"></div>
                <div>
                    <p class="sur">Notre différence</p>
                    <p class="pole__diff">{{ $pole['difference'] }}</p>
                </div>
                <a class="bt" href="{{ route('cible.contact') }}" data-viseur>
                    {{ $pole['cta'] }}<i class="fl"></i>
                </a>
            </div>
        </div>
    </section>

    @if($loop->first)
        {{-- ═══════════════ LA RUE — preuve de l'affichage ═══════════════ --}}
        <section class="rue" aria-labelledby="rue-titre">
            <div class="plume" style="--c:var(--rouge);--op:.16;top:-8%;right:-4%;width:clamp(170px,20vw,300px)" data-par="-20" data-rot="-10"></div>
            <div class="rue__haut">
                <div>
                    <p class="sur">Régie publicitaire ivoirienne depuis 1994</p>
                    <h2 class="t-grand" id="rue-titre" data-lignes>Nous possédons la rue.</h2>
                    <p class="rue__txt" data-rev=".1">+400 panneaux dans 31 communes, et la preuve photo de chaque pose.</p>
                </div>
                <div class="rue__chiffres" data-cascade>
                    <div class="chiffre" style="--c:var(--rouge)">
                        <div class="chiffre__v num">+<span data-compte="{{ \App\Support\Contenu::get('chiffres.panneaux', 400) }}">0</span></div>
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

            {{-- Défilé de photos de pose. Chaque moitié en DOUBLE, condition
                 pour que translateX(-50%) boucle sans saut ; la copie est
                 masquée aux lecteurs d'écran. Pause au survol. --}}
            <div class="defile">
                <div class="defile__piste">
                    @for($passe = 0; $passe < 2; $passe++)
                        <div @if($passe) aria-hidden="true" @endif>
                            @foreach($photosRue as [$src, $w, $h, $alt])
                                <div class="ph ph--scroll" style="--c:var(--rouge)">
                                    <img src="{{ asset($src) }}" alt="{{ $passe ? '' : $alt }}"
                                         width="{{ $w }}" height="{{ $h }}" loading="lazy">
                                </div>
                            @endforeach
                        </div>
                    @endfor
                </div>
            </div>

            <div class="rue__pied">
                <p>Pige photo horodatée · Abidjan &amp; intérieur du pays</p>
                <a class="bt bt--clair" href="{{ route('cible.reseau') }}" data-viseur>Voir le réseau<i class="fl"></i></a>
            </div>
        </section>
    @endif
@endforeach

{{-- ═══════════════════════ APPEL FINAL ═══════════════════════ --}}
<section class="appel" style="border-top:1px solid var(--ligne)">
    <div class="fleche-d" style="--c:var(--rouge);--op:.1;top:12%;right:7%;width:clamp(80px,10vw,140px)" data-par="-24" data-rot="20"></div>
    <div class="plume" style="--c:var(--jaune);--op:.08;bottom:-6%;left:-3%;width:clamp(150px,18vw,260px)" data-par="16" data-rot="-10"></div>

    <div class="large" style="padding-inline:var(--pad)">
        <div class="appel__tete">
            <p class="sur" style="--c:var(--rouge)">Parlons-en</p>
            <h2 class="t-geant" data-lignes>Lequel de ces quatre leviers vous servirait le mieux&nbsp;?</h2>
            <p class="intro" data-rev=".08">
                Décrivez-nous votre objectif en quelques lignes. On vous répond avec une
                recommandation, pas avec un catalogue.
            </p>
        </div>

        <div class="portes" data-cascade>
            @foreach($poles as $pole)
                <a class="porte" href="{{ route('cible.contact') }}" data-viseur
                   style="--c:{{ $pole['couleur'] }};--c-txt:{{ $pole['couleur_texte'] ?? $pole['couleur'] }}">
                    <span class="porte__n">{{ $pole['num'] }}</span>
                    <span class="porte__nom">{!! $pole['nom'] !!}</span>
                    <span class="porte__cta">{{ $pole['cta'] }}<i></i></span>
                </a>
            @endforeach
        </div>

        <div class="appel__actions" data-rev=".1">
            <a class="bt" href="{{ route('cible.contact') }}" data-viseur>
                Recevoir une recommandation média<i class="fl"></i>
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
(function () {
  if (typeof window.gsap === 'undefined' || typeof window.ScrollTrigger === 'undefined') { return; }
  var doux = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Sommaire : marque le pôle traversé. Dans le flux, et non en position
     fixe — la version fixe passait par-dessus les titres de section. */
  var liens = document.querySelectorAll('#sommaire a');
  var poles = document.querySelectorAll('[data-pole]');
  if (liens.length && liens.length === poles.length) {
    poles.forEach(function (sec, i) {
      ScrollTrigger.create({
        trigger: sec, start: 'top 50%', end: 'bottom 50%',
        onToggle: function (self) {
          if (!self.isActive) { return; }
          liens.forEach(function (a) { a.classList.remove('actif'); });
          liens[i].classList.add('actif');
        },
      });
    });
  }

  if (!doux) {
    // En mouvement réduit, la photo garde un léger zoom fixe (CSS) et la
    // frise de méthode reste tracée d'un bloc.
    gsap.set('.methode__trait', { scaleX: 1 });
    return;
  }

  /* Mosaïque de tête : les deux colonnes glissent à des vitesses
     différentes, comme le mur de l'accueil. */
  document.querySelectorAll('[data-mosa]').forEach(function (col, i) {
    gsap.to(col, {
      yPercent: i ? -9 : 4, ease: 'none',
      scrollTrigger: { trigger: '.tete', start: 'top top', end: 'bottom top', scrub: true },
    });
  });

  /* Photo de chaque pôle : parallaxe à l'intérieur du cadre. Le zoom de
     1.14 (CSS) laisse la marge nécessaire, aucun bord ne se découvre. */
  document.querySelectorAll('.pole__ph img').forEach(function (img) {
    gsap.fromTo(img, { yPercent: -6 }, {
      yPercent: 6, ease: 'none',
      scrollTrigger: { trigger: img.parentNode, start: 'top bottom', end: 'bottom top', scrub: true },
    });
  });

  /* Frise « Comment nous travaillons » : le trait se trace au défilement. */
  document.querySelectorAll('.methode__trait').forEach(function (t) {
    gsap.fromTo(t, { scaleX: 0 }, {
      scaleX: 1, ease: 'none',
      scrollTrigger: { trigger: t.parentNode, start: 'top 85%', end: 'top 45%', scrub: .6 },
    });
  });

  /* Numéros géants : légère dérive horizontale au défilement. */
  document.querySelectorAll('.pole__num').forEach(function (n) {
    gsap.fromTo(n, { xPercent: -8 }, {
      xPercent: 0, ease: 'none',
      scrollTrigger: { trigger: n, start: 'top bottom', end: 'top 40%', scrub: true },
    });
  });
})();
</script>
@endpush
