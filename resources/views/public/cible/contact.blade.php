@extends('public.cible._coque', ['titre' => 'Contact', 'actuelle' => 'contact'])

@push('css')
/* ═══════════════ CONTACT — refonte du 2026-10-09 ═══════════════
   Retour client : « trop vide, des espaces blancs ; anime, dynamise ».
   L'ancienne page posait un titre seul, un formulaire centré dans 820 px et
   trois cartes en rangée : de grandes marges blanches de part et d'autre.

   Nouvelle composition, sur le modèle de l'accueil :
     · TÊTE en deux colonnes — le titre et les lignes directes cliquables
       d'un côté, un mini « mur » de campagnes en bichromie rouge de l'autre ;
     · RUBAN défilant (même composant que l'accueil) ;
     · FORMULAIRE + colonne noire collante (ce que vous obtenez + photo) :
       la colonne colle au défilement, ce qui règle le problème de hauteur
       variable du formulaire par étapes ;
     · BANDE NOIRE « Nous rendre visite » + les quatre chiffres officiels.
   Aucun contenu nouveau : tout vient de l'ancienne page, de l'accueil ou du
   pied de page. */

/* ═══════════════ TÊTE ═══════════════ */
.tete{padding:clamp(112px,15vh,170px) var(--pad) clamp(40px,5vw,72px);position:relative;overflow:hidden}
.tete .sur{--c:var(--rouge)}
.tete__grille{
  display:grid;grid-template-columns:1.12fr .88fr;
  gap:clamp(28px,4vw,64px);align-items:stretch;position:relative;z-index:1;
}
@media(max-width:1040px){.tete__grille{grid-template-columns:1fr}}
.tete__txt{display:flex;flex-direction:column;justify-content:center;min-width:0}
.tete__filet{width:52px;height:4px;background:var(--rouge);border-radius:4px;margin-top:14px}
.tete__t{margin-top:18px;font-size:clamp(31px,3.9vw,60px)}
.tete__t .insec{white-space:nowrap}
.tete__t .l{display:block}
.tete__t em{font-style:normal;color:var(--rouge)}
.tete .intro{margin-top:20px;max-width:52ch}

/* Lignes directes : trois cartes cliquables, la carte entière est le lien. */
/* Deux téléphones côte à côte, l'email sur toute la largeur : à trois de
   front, l'adresse email se coupait en deux dans une colonne de 690 px. */
.lignes{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:clamp(26px,3.4vw,40px)}
.ligne:last-child{grid-column:1/-1}
@media(max-width:440px){.lignes{grid-template-columns:1fr}}
.ligne{
  position:relative;display:flex;flex-direction:column;gap:6px;
  padding:18px 18px 18px;border-radius:16px;overflow:hidden;
  background:var(--fond-2);box-shadow:inset 0 0 0 1px var(--ligne);
  transition:background .35s var(--ease),color .35s,transform .45s var(--ease),box-shadow .35s;
  min-width:0;
}
.ligne:hover,.ligne:focus-visible{background:var(--rouge);color:var(--blanc);transform:translateY(-4px);box-shadow:none}
.ligne__l{
  font-family:var(--titre);font-weight:700;font-size:11px;letter-spacing:.14em;
  text-transform:uppercase;color:var(--texte-3);transition:color .35s;
  padding-right:40px;
}
.ligne__v{
  font-family:var(--titre);font-weight:800;font-size:clamp(15px,1.25vw,17.5px);
  letter-spacing:-.01em;line-height:1.2;overflow-wrap:anywhere;
}
.ligne:hover .ligne__l,.ligne:focus-visible .ligne__l{color:rgba(255,255,255,.8)}
.ligne__fl{
  position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:50%;
  display:grid;place-items:center;box-shadow:inset 0 0 0 1.5px var(--ligne);
  transition:transform .45s var(--ease),box-shadow .35s,background .35s;
}
.ligne__fl i{
  width:13px;height:13px;background:var(--noir);transition:background .35s;
  -webkit-mask:url('{{ asset('images/fleche.svg') }}') no-repeat center/contain;
  mask:url('{{ asset('images/fleche.svg') }}') no-repeat center/contain;
}
.ligne:hover .ligne__fl,.ligne:focus-visible .ligne__fl{background:var(--blanc);box-shadow:none;transform:rotate(-45deg)}
.ligne:hover .ligne__fl i,.ligne:focus-visible .ligne__fl i{background:var(--rouge)}

/* Repères chiffrés, comme sous le manifeste de l'accueil. */
.tete__reperes{
  display:flex;gap:clamp(16px,2.4vw,36px);flex-wrap:wrap;
  margin-top:clamp(24px,3vw,34px);padding-top:16px;border-top:1px solid var(--ligne);
  font-family:var(--titre);font-weight:700;font-size:11px;
  letter-spacing:.11em;text-transform:uppercase;color:var(--texte-3);
}
.tete__reperes b{display:block;font-family:var(--chiffres);font-size:clamp(19px,1.9vw,24px);
  letter-spacing:-.02em;color:var(--noir);font-weight:900;margin-bottom:2px}

/* Mini-mur : deux colonnes de campagnes qui défilent en sens contraires,
   en bichromie rouge (une couleur + noir, cf. coque §9). Décoratif. */
.vitrine{
  position:relative;border-radius:clamp(18px,2vw,28px);overflow:hidden;
  background:var(--noir);min-height:clamp(420px,40vw,600px);
  box-shadow:0 40px 90px -50px rgba(17,17,17,.7);
}
@media(max-width:1040px){.vitrine{min-height:0;height:clamp(260px,52vw,420px)}}
.vitrine__mur{
  position:absolute;inset:-10% 0;display:grid;grid-template-columns:repeat(2,1fr);
  gap:12px;padding-inline:12px;pointer-events:none;
}
.vitrine__col{display:flex;flex-direction:column;animation:vitMonte 40s linear infinite}
.vitrine__col:nth-child(2){animation-name:vitDescend;animation-duration:52s}
.vitrine__col > div{display:flex;flex-direction:column;gap:12px;padding-bottom:12px}
.vitrine .tuile{aspect-ratio:4/5;border-radius:14px;flex:0 0 auto}
@keyframes vitMonte{to{transform:translateY(-50%)}}
@keyframes vitDescend{from{transform:translateY(-50%)}to{transform:translateY(0)}}
@media (prefers-reduced-motion:reduce){.vitrine__col{animation:none}}
/* Sous 1040 px la vitrine devient un bandeau : trois colonnes, et le
   mouvement continue — il est en CSS pur, sur deux couches compositées. */
@media(max-width:1040px){.vitrine__mur{grid-template-columns:repeat(3,1fr)}}
.vitrine__col--3{display:none;animation-duration:46s}
@media(max-width:1040px){.vitrine__col--3{display:flex}}
/* Filet rouge en pied de vitrine, comme le panneau du motion de l'accueil. */
.vitrine::after{content:"";position:absolute;inset:auto 0 0 0;height:4px;background:var(--rouge);z-index:3}
/* Carte blanche posée sur la vitrine : la promesse de délai. */
.vitrine__carte{
  position:absolute;z-index:2;left:clamp(14px,1.6vw,22px);right:clamp(14px,1.6vw,22px);bottom:clamp(18px,2vw,26px);
  display:flex;align-items:center;gap:14px;
  padding:16px 18px;border-radius:14px;background:var(--blanc);color:var(--noir);
  box-shadow:0 20px 50px -20px rgba(0,0,0,.6);
}
.vitrine__pouls{position:relative;width:12px;height:12px;flex:0 0 auto;border-radius:50%;background:var(--rouge)}
.vitrine__pouls::after{
  content:"";position:absolute;inset:0;border-radius:50%;background:var(--rouge);
  animation:pouls 2s var(--ease) infinite;
}
@keyframes pouls{from{transform:scale(1);opacity:.55}to{transform:scale(3.2);opacity:0}}
@media (prefers-reduced-motion:reduce){.vitrine__pouls::after{animation:none;opacity:0}}
.vitrine__carte b{display:block;font-family:var(--titre);font-weight:800;font-size:clamp(14.5px,1.2vw,16.5px);letter-spacing:-.01em;line-height:1.25}
.vitrine__carte span{display:block;font-size:13px;color:var(--texte-3);margin-top:2px}

/* ═══════════════ FORMULAIRE + COLONNE ═══════════════ */
.demande{padding:clamp(48px,6vw,96px) var(--pad) clamp(56px,7vw,110px);position:relative}
.demande__grille{
  display:grid;grid-template-columns:minmax(0,1.38fr) minmax(0,.92fr);
  gap:clamp(22px,3vw,48px);align-items:start;
}
@media(max-width:1040px){.demande__grille{grid-template-columns:1fr}}
.demande__tete{display:flex;align-items:baseline;justify-content:space-between;gap:12px 24px;flex-wrap:wrap;margin-bottom:18px}
.demande__tete .sur{--c:var(--rouge)}

/* ── Formulaire : un seul écran, découpé en 4 temps ──
   La V1 présentait un mini-brief de 7 blocs d'affilée, abandonné en cours
   de route par les visiteurs. On garde les mêmes questions mais une seule
   à la fois, avec une progression visible. */
.form{
  padding:clamp(24px,3.4vw,46px);border-radius:24px;
  background:var(--fond-2);box-shadow:inset 0 0 0 1px var(--ligne);
  position:relative;
}
/* Bord rouge qui s'allume quand on remplit : le formulaire « s'active ». */
.form::before{
  content:"";position:absolute;left:0;top:24px;bottom:24px;width:4px;border-radius:0 4px 4px 0;
  background:var(--rouge);transform:scaleY(.18);transform-origin:50% 0;
  transition:transform .7s var(--ease);
}
.form:focus-within::before{transform:scaleY(1)}
.jauge-e{display:flex;gap:7px}
.jauge-e span{height:3px;flex:1;border-radius:3px;background:var(--ligne);transition:background .45s}
.jauge-e span.fait{background:var(--rouge)}
/* Noms des étapes sous la jauge, alignés sur ses quatre segments. */
.etapes-l{list-style:none;display:flex;gap:7px;margin:10px 0 28px}
.etapes-l li{
  flex:1;min-width:0;font-family:var(--titre);font-weight:700;font-size:11px;
  letter-spacing:.1em;text-transform:uppercase;color:var(--texte-3);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:color .35s;
}
.etapes-l li.fait{color:var(--noir)}
.etapes-l li.encours{color:var(--rouge)}
@media(max-width:520px){.etapes-l li{font-size:9.5px;letter-spacing:.06em}}
.etape-n{font-family:var(--titre);font-weight:800;font-size:11.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--texte-3)}
.etape-t{font-family:var(--titre);font-weight:900;font-size:clamp(21px,2.3vw,29px);letter-spacing:-.02em;margin-top:9px;line-height:1.15}
.etape-s{margin-top:9px;font-size:15px;color:var(--texte-2);line-height:1.55}
.pas[hidden]{display:none}
/* Chaque temps entre en glissant : l'animation rejoue à chaque fois que
   l'attribut hidden est retiré (display none → block). Coupée en
   mouvement réduit par la règle générale de la coque. */
.pas:not([hidden]){animation:pasEntre .55s var(--ease)}
@keyframes pasEntre{from{opacity:0;transform:translateX(18px)}to{opacity:1;transform:none}}

.form label{display:block;font-family:var(--titre);font-weight:700;font-size:12px;letter-spacing:.05em;text-transform:uppercase;color:var(--texte-3);margin-bottom:7px;transition:color .25s}
.form input[type=text],.form input[type=email],.form input[type=tel],.form textarea,.form select{
  width:100%;padding:14px 16px;border-radius:12px;
  background:var(--blanc);color:var(--noir);border:0;
  box-shadow:inset 0 0 0 1.5px var(--ligne);
  font-family:var(--corps);font-size:16px;
  transition:box-shadow .25s,transform .35s var(--ease);
}
.form input:hover,.form textarea:hover,.form select:hover{box-shadow:inset 0 0 0 1.5px rgba(17,17,17,.3)}
.form input:focus,.form textarea:focus,.form select:focus{outline:none;box-shadow:inset 0 0 0 2px var(--rouge),0 8px 22px -14px rgba(226,6,19,.55)}
/* Micro-interaction : l'étiquette du champ actif passe en rouge. */
.champ > div:focus-within > label,.champ:focus-within > label{color:var(--rouge)}
.form input::placeholder,.form textarea::placeholder{color:var(--texte-3)}
.form textarea{min-height:120px;resize:vertical}
.champ{margin-bottom:17px}
.champ--duo{display:grid;grid-template-columns:1fr 1fr;gap:17px}
@media(max-width:620px){.champ--duo{grid-template-columns:1fr}}

/* Choix multiples en pastilles : plus rapide à l'œil et au doigt qu'une
   colonne de cases à cocher. */
.choix{display:flex;flex-wrap:wrap;gap:9px}
.choix input{position:absolute;opacity:0;width:0;height:0}
.form .choix label{
  margin:0;padding:11px 17px;border-radius:999px;cursor:pointer;
  font-family:var(--titre);font-weight:700;font-size:13.5px;
  text-transform:none;letter-spacing:0;color:var(--texte-2);
  background:var(--blanc);
  box-shadow:inset 0 0 0 1.5px var(--ligne);
  transition:background .25s,color .25s,box-shadow .25s,transform .3s var(--ease);
  display:inline-flex;align-items:center;gap:0;
}
.form .choix label::before{
  content:"✓";display:inline-block;width:0;overflow:hidden;opacity:0;
  font-weight:900;transition:width .3s var(--ease),opacity .25s,margin .3s var(--ease);
}
.form .choix label:hover{color:var(--noir);box-shadow:inset 0 0 0 1.5px rgba(17,17,17,.35);transform:translateY(-2px)}
.form .choix input:checked + label{background:var(--rouge);color:#fff;box-shadow:none}
.form .choix input:checked + label::before{width:1em;opacity:1;margin-right:5px}
.form .choix input:focus-visible + label{outline:3px solid var(--jaune);outline-offset:3px}

.form__pied{display:flex;gap:11px;align-items:center;margin-top:26px;flex-wrap:wrap}
.form__pied .compte{margin-left:auto;font-family:var(--titre);font-weight:700;font-size:12.5px;color:var(--texte-3)}
/* États du bouton : pression franche au clic. */
.form__pied .bt:active{transform:translateY(0) scale(.97)}

/* Confirmation de maquette : le formulaire n'envoie rien ici. */
.recu{display:none;text-align:center;padding:16px 0}
.recu.vu{display:block;animation:pasEntre .6s var(--ease)}
.recu__rond{
  width:66px;height:66px;border-radius:50%;margin:0 auto 20px;
  display:grid;place-items:center;background:rgba(58,168,53,.16);
  box-shadow:inset 0 0 0 1.5px var(--vert);
  font-size:28px;color:var(--vert);
}
.recu.vu .recu__rond{animation:recuPop .7s var(--ease) .1s both}
@keyframes recuPop{from{transform:scale(.4);opacity:0}to{transform:scale(1);opacity:1}}
.recu h3{font-family:var(--titre);font-weight:900;font-size:25px;letter-spacing:-.02em}
.recu p{margin-top:12px;color:var(--texte-2);font-size:15px;line-height:1.6}

/* ── Colonne noire, collante en bureau ──
   Le formulaire change de hauteur à chaque étape : rien ne peut s'aligner
   à côté de lui. La colonne colle donc au défilement plutôt que de
   chercher à suivre sa hauteur. Sur fond noir, texte en blanc. */
.cote{
  position:sticky;top:96px;
  background:var(--noir);color:var(--blanc);border-radius:24px;overflow:hidden;
}
@media(max-width:1040px){.cote{position:relative;top:auto}}
.cote__in{padding:clamp(24px,3vw,38px)}
.cote .sur{color:rgba(255,255,255,.6)}
.cote__filet{width:44px;height:4px;background:var(--rouge);border-radius:4px;margin-top:12px}
.promesse{list-style:none;counter-reset:p;margin-top:18px;display:grid}
.promesse li{
  counter-increment:p;display:grid;grid-template-columns:34px 1fr;gap:12px;align-items:baseline;
  padding:12px 0;border-bottom:1px solid rgba(255,255,255,.12);
  font-size:15px;line-height:1.45;color:rgba(255,255,255,.86);
  transition:padding-left .45s var(--ease),color .3s;
}
.promesse li:last-child{border-bottom:0}
.promesse li::before{
  content:counter(p,decimal-leading-zero);
  font-family:var(--chiffres);font-weight:900;font-size:13px;letter-spacing:.06em;color:var(--blanc);
  padding-top:2px;border-top:2px solid var(--rouge);width:24px;
}
.promesse li:hover{padding-left:6px;color:var(--blanc)}
.cote__ph{aspect-ratio:16/9;border-radius:0;--c:var(--rouge)}
@media(max-width:1040px) and (min-width:700px){
  .cote{display:grid;grid-template-columns:1.1fr .9fr}
  .cote__ph{aspect-ratio:auto;min-height:100%}
}

/* ═══════════════ VISITE — bande noire ═══════════════ */
.visite{background:var(--noir);color:var(--blanc);padding:clamp(56px,7vw,110px) var(--pad);position:relative;overflow:hidden}
.visite__grille{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.05fr);gap:clamp(28px,4vw,72px);align-items:center;position:relative;z-index:1}
@media(max-width:900px){.visite__grille{grid-template-columns:1fr}}
.visite .sur{color:rgba(255,255,255,.6)}
.visite .t-grand{margin-top:16px;color:var(--blanc)}
.visite .t-grand .l{display:block}
.visite__filet{width:60px;height:4px;background:var(--rouge);border-radius:4px;margin-top:22px}
.visite__adr{margin-top:20px;font-size:clamp(16px,1.3vw,18px);line-height:1.6;color:rgba(255,255,255,.78)}
.visite__horaire{
  display:inline-flex;align-items:center;gap:10px;margin-top:18px;
  padding:9px 15px;border-radius:999px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.2);
  font-family:var(--titre);font-weight:700;font-size:13px;color:var(--blanc);
}
.visite__horaire i{width:8px;height:8px;border-radius:50%;background:var(--rouge);flex:0 0 auto}
.visite__actions{display:flex;flex-wrap:wrap;gap:11px;margin-top:28px}
.visite .bt--creux{color:var(--blanc);box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.35)}
.visite .bt--creux:hover{background:rgba(255,255,255,.08);box-shadow:inset 0 0 0 1.5px var(--blanc)}
.visite__ph{aspect-ratio:4/3;border-radius:clamp(16px,1.8vw,24px);--c:var(--rouge)}

/* Les quatre chiffres officiels, sur la bande noire : traits de couleur,
   chiffres en blanc (pas de chiffre en couleur sur fond non blanc). */
.visite__chiffres{
  display:grid;grid-template-columns:repeat(4,1fr);gap:clamp(16px,2.4vw,34px);
  margin-top:clamp(44px,6vw,84px);position:relative;z-index:1;
}
@media(max-width:880px){.visite__chiffres{grid-template-columns:repeat(2,1fr)}}
.chiffre{padding-top:20px;border-top:2px solid var(--c)}
.chiffre__v{
  font-family:var(--chiffres);font-weight:900;color:var(--blanc);
  font-size:clamp(40px,5.6vw,84px);line-height:.88;letter-spacing:-.05em;
  display:flex;align-items:baseline;gap:2px;
}
.chiffre__v i{font-style:normal;font-size:.52em;color:rgba(255,255,255,.7)}
.chiffre__l{font-family:var(--titre);font-weight:700;font-size:13px;margin-top:12px;color:rgba(255,255,255,.66);line-height:1.35}
@endpush

@section('contenu')

{{-- ═══════════════════════ TÊTE ═══════════════════════ --}}
<section class="tete">
    <div class="plume" style="--c:var(--rouge);--op:.06;top:-12%;left:34%;width:clamp(200px,24vw,340px)" data-par="-16" data-rot="12"></div>
    <div class="large tete__grille">
        <div class="tete__txt">
            <p class="sur" data-rev>Contact · <b>Réponse sous 24 heures ouvrées</b></p>
            <div class="tete__filet" data-rev=".05"></div>
            {{-- Lignes posées à la main pour garder le <em> rouge (cf. coque §11). --}}
            <h1 class="t-geant tete__t" data-cascade>
                <span class="l">Où <span class="insec">voulez-vous</span> être vu&nbsp;?</span>
                <span class="l"><em>On s'occupe du reste.</em></span>
            </h1>
            <p class="intro" data-rev=".1">
                Un échange de quinze minutes suffit à savoir si on peut vous être utile.
                Réponse sous 24 heures ouvrées, sans engagement.
            </p>

            <div class="lignes" data-cascade>
                <a class="ligne" href="tel:+2250700780628" data-viseur>
                    <span class="ligne__l">Mobile · WhatsApp</span>
                    <span class="ligne__v num">+225 07 00 78 06 28</span>
                    <span class="ligne__fl" aria-hidden="true"><i></i></span>
                </a>
                <a class="ligne" href="tel:+2252722208008" data-viseur>
                    <span class="ligne__l">Standard</span>
                    <span class="ligne__v num">+225 27 22 20 80 08</span>
                    <span class="ligne__fl" aria-hidden="true"><i></i></span>
                </a>
                <a class="ligne" href="mailto:commercial@cible-ci.com" data-viseur>
                    <span class="ligne__l">Email</span>
                    <span class="ligne__v">commercial@cible-ci.com</span>
                    <span class="ligne__fl" aria-hidden="true"><i></i></span>
                </a>
            </div>

            <div class="tete__reperes" data-rev=".18">
                <span><b>+{{ \App\Support\Contenu::get('chiffres.panneaux', 400) }}</b> panneaux</span>
                <span><b>{{ \App\Support\Contenu::get('chiffres.communes', 31) }}</b> communes</span>
                <span><b>{{ \App\Support\Contenu::get('chiffres.annees', 30) }}</b> ans</span>
                <span><b>{{ str_pad((string) \App\Support\Contenu::get('chiffres.distinctions', 3), 2, '0', STR_PAD_LEFT) }}</b> distinctions</span>
            </div>
        </div>

        {{-- Vitrine : mini-mur de campagnes, purement décoratif (aria-hidden).
             Chaque colonne contient ses tuiles en DOUBLE, condition pour que
             translateY(-50%) boucle sans saut. --}}
        <div class="vitrine" data-rev=".12">
            <div class="vitrine__mur" aria-hidden="true">
                @php
                    $visuels = collect($realisations)->values();
                    $colonnes = [
                        $visuels->all(),
                        $visuels->reverse()->values()->all(),
                        $visuels->slice(3)->concat($visuels->slice(0, 3))->values()->all(),
                    ];
                @endphp
                @foreach($colonnes as $n => $colonne)
                    <div class="vitrine__col{{ $n === 2 ? ' vitrine__col--3' : '' }}">
                        @for($passe = 0; $passe < 2; $passe++)
                            <div>
                                @foreach($colonne as $p)
                                    <div class="tuile ph" style="--c:var(--rouge)">
                                        <img src="{{ \App\Support\Contenu::urlImage($p['image'] ?? 'images/cible/campagne-1.jpg') }}"
                                             alt="" width="1200" height="1800" @if($passe) loading="lazy" @endif decoding="async">
                                    </div>
                                @endforeach
                            </div>
                        @endfor
                    </div>
                @endforeach
            </div>
            <div class="vitrine__carte">
                <span class="vitrine__pouls" aria-hidden="true"></span>
                <div>
                    <b>Une réponse sous 24 heures ouvrées</b>
                    <span>Du lundi au vendredi, 8h – 17h30</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════ RUBAN ═══════════════════════ --}}
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

{{-- ═══════════════════════ FORMULAIRE ═══════════════════════ --}}
<section class="demande" aria-labelledby="demande-titre">
    <div class="large demande__grille">

        <div>
            <div class="demande__tete" data-rev>
                <p class="sur" id="demande-titre">Recevoir ma <b>recommandation</b></p>
            </div>

        <form class="form" id="form" novalidate data-rev>
            <div class="jauge-e" id="jauge-e" aria-hidden="true">
                <span class="fait"></span><span></span><span></span><span></span>
            </div>
            {{-- Noms courts des quatre temps, tirés de leurs titres. --}}
            <ol class="etapes-l" id="etapes-l" aria-hidden="true">
                <li class="encours">Vous</li><li>Objectif</li><li>Dispositifs</li><li>Projet</li>
            </ol>

            <div id="pas-tous">
                {{-- Temps 1 — qui --}}
                <div class="pas" data-pas="1">
                    <p class="etape-n">Étape 1 sur 4</p>
                    <h2 class="etape-t">Qui êtes-vous&nbsp;?</h2>
                    <p class="etape-s">De quoi vous répondre, et savoir à qui l'on parle.</p>
                    <div style="margin-top:26px">
                        <div class="champ champ--duo">
                            <div><label for="nom">Nom et prénom</label><input id="nom" type="text" placeholder="Aya Koné" autocomplete="name" required></div>
                            <div><label for="fonction">Fonction</label><input id="fonction" type="text" placeholder="Directrice marketing" autocomplete="organization-title" required></div>
                        </div>
                        <div class="champ"><label for="societe">Société ou institution</label><input id="societe" type="text" placeholder="Nom de votre structure" autocomplete="organization" required></div>
                        <div class="champ champ--duo">
                            <div><label for="email">Email professionnel</label><input id="email" type="email" placeholder="vous@societe.com" autocomplete="email" required></div>
                            <div><label for="tel">Téléphone</label><input id="tel" type="tel" placeholder="+225 ..." autocomplete="tel" required></div>
                        </div>
                    </div>
                </div>

                {{-- Temps 2 — objectif. Les options viennent de la source unique
                     CibleController::formOptions(), comme en V1. --}}
                <div class="pas" data-pas="2" hidden>
                    <p class="etape-n">Étape 2 sur 4</p>
                    <h2 class="etape-t" id="t-objectifs">Que cherchez-vous à obtenir&nbsp;?</h2>
                    <p class="etape-s">Plusieurs réponses possibles. C'est ce qui détermine la recommandation.</p>
                    <div class="choix" style="margin-top:26px" role="group" aria-labelledby="t-objectifs">
                        @foreach(($options['objectif'] ?? []) as $cle => $libelle)
                            <input type="checkbox" id="o-{{ $cle }}" name="objectifs[]" value="{{ $cle }}">
                            <label for="o-{{ $cle }}">{{ $libelle }}</label>
                        @endforeach
                    </div>
                </div>

                {{-- Temps 3 — dispositifs et zones --}}
                <div class="pas" data-pas="3" hidden>
                    <p class="etape-n">Étape 3 sur 4</p>
                    <h2 class="etape-t" id="t-dispositifs">Quels dispositifs vous intéressent&nbsp;?</h2>
                    <p class="etape-s">Si vous ne savez pas, laissez vide : c'est notre métier de vous orienter.</p>
                    <div class="choix" style="margin-top:26px" role="group" aria-labelledby="t-dispositifs">
                        @foreach(($options['services'] ?? []) as $cle => $libelle)
                            <input type="checkbox" id="d-{{ $cle }}" name="dispositifs[]" value="{{ $cle }}">
                            <label for="d-{{ $cle }}">{{ $libelle }}</label>
                        @endforeach
                    </div>
                    @if(!empty($options['zone']))
                        <div style="margin-top:28px">
                            <label id="t-zones">Zones visées</label>
                            <div class="choix" role="group" aria-labelledby="t-zones">
                                @foreach($options['zone'] as $cle => $libelle)
                                    <input type="checkbox" id="z-{{ $cle }}" name="zones[]" value="{{ $cle }}">
                                    <label for="z-{{ $cle }}">{{ $libelle }}</label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Temps 4 — contexte --}}
                <div class="pas" data-pas="4" hidden>
                    <p class="etape-n">Étape 4 sur 4</p>
                    <h2 class="etape-t">Votre projet en quelques lignes</h2>
                    <p class="etape-s">Échéance, budget indicatif, contraintes : tout ce qui nous évitera un aller-retour.</p>
                    <div style="margin-top:26px">
                        @if(!empty($options['budget']))
                            <div class="champ">
                                <label for="budget">Budget indicatif</label>
                                <select id="budget">
                                    <option value="">Je préfère en parler</option>
                                    @foreach($options['budget'] as $cle => $libelle)
                                        <option value="{{ $cle }}">{{ $libelle }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="champ">
                            <label for="message">Votre projet</label>
                            <textarea id="message" placeholder="Lancement d'un produit en novembre, cible Abidjan Sud…"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form__pied" id="form-pied">
                <button class="bt bt--creux" type="button" id="prec" hidden>Retour</button>
                <button class="bt" type="button" id="suiv">Continuer<i class="fl"></i></button>
                <button class="bt" type="submit" id="envoi" hidden>Recevoir ma recommandation<i class="fl"></i></button>
                <span class="compte" id="compte" aria-live="polite">Étape 1 / 4</span>
            </div>

            {{-- État de confirmation. Dans la maquette, rien n'est envoyé :
                 le formulaire réel, fonctionnel et protégé contre les robots,
                 existe déjà sur le site en production. --}}
            <div class="recu" id="recu">
                <div class="recu__rond">✓</div>
                <h3>C'est noté</h3>
                <p>
                    Sur le site réel, votre demande partirait à l'instant vers
                    commercial@cible-ci.com, avec une réponse sous 24 heures ouvrées.<br>
                    <strong style="color:var(--jaune)">Ceci est une maquette : rien n'a été envoyé.</strong>
                </p>
            </div>
        </form>
        </div>

        {{-- ── Colonne noire : ce que vous obtenez ── --}}
        <aside class="cote" data-rev=".1">
            <div class="cote__in">
                <p class="sur">Ce que vous obtenez</p>
                <div class="cote__filet"></div>
                <ul class="promesse" data-cascade>
                    <li>Une recommandation adaptée à votre objectif, pas un catalogue</li>
                    <li>Les formats et emplacements les plus pertinents, justifiés</li>
                    <li>Une proposition calée sur votre budget</li>
                    <li>Un interlocuteur unique jusqu'au lancement</li>
                    <li>Une réponse sous 24 heures ouvrées</li>
                </ul>
            </div>
            <div class="cote__ph ph ph--scroll">
                <img src="{{ asset('refonte/photo/lumipub.webp') }}"
                     alt="Panneau d'affichage sur un terre-plein"
                     width="1600" height="1067" loading="lazy" decoding="async">
            </div>
        </aside>
    </div>
</section>

{{-- ═══════════════════════ NOUS RENDRE VISITE ═══════════════════════ --}}
<section class="visite" aria-labelledby="visite-titre">
    <div class="plume" style="--c:var(--rouge);--op:.09;bottom:-14%;right:-4%;width:clamp(180px,22vw,320px)" data-par="14" data-rot="-10"></div>
    <div class="large">
        <div class="visite__grille">
            <div>
                <p class="sur" data-rev>Nous rendre visite</p>
                <h2 class="t-grand" id="visite-titre" data-cascade>
                    <span class="l">Rue des Ambassadeurs,</span>
                    <span class="l">Riviera M'Badon.</span>
                </h2>
                <div class="visite__filet" data-rev=".1"></div>
                <p class="visite__adr" data-rev=".14">
                    10 BP 1029 Abidjan 10<br>Côte d'Ivoire
                </p>
                <p class="visite__horaire" data-rev=".18"><i aria-hidden="true"></i>Du lundi au vendredi, 8h – 17h30</p>
                <div class="visite__actions" data-rev=".22">
                    <a class="bt" href="tel:+2252722208008" data-viseur><span class="num">+225 27 22 20 80 08</span><i class="fl"></i></a>
                    <a class="bt bt--creux" href="mailto:commercial@cible-ci.com" data-viseur>commercial@cible-ci.com</a>
                </div>
            </div>
            <div class="visite__ph ph ph--scroll" data-rev=".12">
                <img src="{{ asset('refonte/photo/rue.webp') }}"
                     alt="Panneaux d'affichage le long d'un grand axe"
                     width="1800" height="1460" loading="lazy" decoding="async">
            </div>
        </div>

        <div class="visite__chiffres" data-cascade>
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

@endsection

@push('js')
<script>
/* Formulaire par étapes.
   Sans JS, les quatre temps restent tous visibles et lisibles : c'est
   l'attribut `hidden` posé par ce script qui les replie, jamais le CSS.
   Un formulaire inaccessible parce qu'un script n'a pas chargé serait pire
   qu'un formulaire long. */
(function () {
  var form  = document.getElementById('form');
  var pas   = Array.prototype.slice.call(form.querySelectorAll('.pas'));
  var prec  = document.getElementById('prec');
  var suiv  = document.getElementById('suiv');
  var envoi = document.getElementById('envoi');
  var jauge = document.getElementById('jauge-e');
  var compte = document.getElementById('compte');
  var recu  = document.getElementById('recu');
  var pied  = document.getElementById('form-pied');
  var noms  = document.getElementById('etapes-l');
  var i = 0;

  function peindre() {
    pas.forEach(function (p, n) { p.hidden = n !== i; });
    prec.hidden  = i === 0;
    suiv.hidden  = i === pas.length - 1;
    envoi.hidden = i !== pas.length - 1;
    compte.textContent = 'Étape ' + (i + 1) + ' / ' + pas.length;
    jauge.querySelectorAll('span').forEach(function (s, n) {
      s.classList.toggle('fait', n <= i);
    });
    // Noms des étapes : passées en noir, courante en rouge.
    if (noms) {
      noms.querySelectorAll('li').forEach(function (l, n) {
        l.classList.toggle('fait', n < i);
        l.classList.toggle('encours', n === i);
      });
    }
    var premier = pas[i].querySelector('input, textarea, select');
    if (premier && i > 0) { premier.focus({ preventScroll: true }); }
    if (typeof ScrollTrigger !== 'undefined') { ScrollTrigger.refresh(); }
  }

  /* Validation du seul temps affiché : on ne reproche pas à quelqu'un
     un champ qu'il n'a pas encore vu. */
  function valide() {
    var manquants = pas[i].querySelectorAll('[required]');
    for (var n = 0; n < manquants.length; n++) {
      if (!manquants[n].checkValidity()) {
        manquants[n].focus();
        manquants[n].style.boxShadow = 'inset 0 0 0 1.5px var(--rouge)';
        return false;
      }
    }
    return true;
  }

  suiv.addEventListener('click', function () {
    if (!valide()) { return; }
    if (i < pas.length - 1) { i++; peindre(); }
  });
  prec.addEventListener('click', function () {
    if (i > 0) { i--; peindre(); }
  });

  // Entrée fait avancer plutôt que soumettre, sauf au dernier temps.
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && i < pas.length - 1) {
      e.preventDefault();
      suiv.click();
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!valide()) { return; }
    document.getElementById('pas-tous').hidden = true;
    pied.hidden = true;
    jauge.hidden = true;
    if (noms) { noms.hidden = true; }
    recu.classList.add('vu');
    if (typeof ScrollTrigger !== 'undefined') { ScrollTrigger.refresh(); }
  });

  peindre();
})();
</script>
@endpush
