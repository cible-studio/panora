@extends('public.cible._coque', ['titre' => 'Le réseau', 'actuelle' => 'reseau'])

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@push('css')
/* ═══════════════════════════════════════════════════════════════════
   PAGE RÉSEAU — refonte du 2026-10-09.
   Retour client : « trop vide, des espaces blancs ; anime, dynamise ».
   Même grammaire que l'accueil : alternance blanc / aplat noir, chiffres
   en grand, un ruban qui défile, et surtout une carte qui se PILOTE —
   la liste des communes, à côté, déplace la carte au clic.
   Rien n'est ajouté au contenu : textes de la page, du Contenu admin
   (section « reseau ») et données de /api/reseau-map.
═══════════════════════════════════════════════════════════════════ */

/* ═══════════════ TÊTE ═══════════════ */
.rs-tete{padding:clamp(116px,15vh,168px) var(--pad) clamp(38px,5vw,68px);position:relative;overflow:hidden}
.rs-tete .sur{--c:var(--rouge)}
.rs-tete__grille{
  display:grid;grid-template-columns:minmax(0,1.08fr) minmax(0,.92fr);
  gap:clamp(28px,4vw,72px);align-items:center;position:relative;z-index:1;
}
@media(max-width:960px){.rs-tete__grille{grid-template-columns:1fr;gap:34px}}
.rs-filet{width:52px;height:4px;background:var(--rouge);border-radius:4px;margin-top:14px}
.rs-tete__t{margin-top:18px}
.rs-tete__t em{font-style:normal;color:var(--rouge)}
.rs-tete .intro{margin-top:22px}
.rs-actions{display:flex;flex-wrap:wrap;gap:11px;margin-top:28px}

/* Les quatre chiffres officiels, sur un aplat noir : l'équivalent du
   panneau du motion de l'accueil. Texte blanc, la couleur n'est portée
   que par les filets. */
.rs-chiffres{
  background:var(--noir);color:var(--blanc);
  border-radius:clamp(18px,2vw,28px);padding:clamp(24px,3vw,42px);
  display:grid;grid-template-columns:1fr 1fr;
  gap:clamp(22px,2.6vw,36px) clamp(16px,2vw,30px);
  box-shadow:0 40px 100px -50px rgba(17,17,17,.7);
  position:relative;overflow:hidden;
}
.rs-chiffres::after{content:"";position:absolute;inset:auto 0 0 0;height:4px;background:var(--rouge)}
.rs-ch{padding-top:16px;border-top:2px solid var(--c);min-width:0}
.rs-ch__v{
  font-family:var(--chiffres);font-weight:900;
  font-size:clamp(36px,4.6vw,68px);line-height:.9;letter-spacing:-.045em;
  display:flex;align-items:baseline;gap:2px;
}
.rs-ch__v i{font-style:normal;font-size:.5em}
.rs-ch__l{
  font-family:var(--titre);font-weight:700;font-size:12.5px;line-height:1.35;
  margin-top:10px;color:rgba(255,255,255,.68);
}

/* ═══════════════ RUBAN DES COMMUNES ═══════════════ */
.rs-ruban .ruban__piste{animation-duration:58s}
.rs-ruban .ruban__piste span:not(.ruban__pt){color:var(--noir)}

/* ═══════════════ CARTE + PANNEAU DE PILOTAGE ═══════════════
   La carte à gauche, la liste des communes sur aplat noir à droite. Un
   clic sur une commune envoie la carte dessus ; un clic sur une zone la
   cadre. La carte n'est plus seule au milieu d'un grand blanc. */
.rs-carte{
  display:grid;grid-template-columns:minmax(0,1fr) clamp(340px,31vw,470px);
  background:var(--noir);
}
@media(max-width:960px){.rs-carte{grid-template-columns:1fr}}
.rs-carte__map{position:relative;min-height:min(88vh,820px);background:var(--fond-3);overflow:hidden}
@media(max-width:960px){.rs-carte__map{min-height:0;height:clamp(380px,64vh,560px)}}
#carte{position:absolute;inset:0;z-index:1;background:var(--fond-2)}
.carte-chargement{
  position:absolute;inset:0;z-index:600;display:grid;place-items:center;
  background:var(--fond-3);color:var(--texte-3);
  font-family:var(--titre);font-weight:700;font-size:13.5px;text-align:center;
}
.carte-chargement[hidden]{display:none}
.tourne{width:34px;height:34px;border:2.5px solid rgba(17,17,17,.12);border-top-color:var(--rouge);border-radius:50%;animation:tourner .9s linear infinite;margin-inline:auto}
@keyframes tourner{to{transform:rotate(360deg)}}

/* Épingles : un point rouge cerclé de blanc et une étiquette. Les
   étiquettes des communes d'Abidjan se chevauchent à l'échelle du pays :
   elles sont masquées tant qu'on est loin (.rs-loin), et une seule
   étiquette « Grand Abidjan » les remplace. */
.rs-pin-icone{background:none;border:0}
.rs-pin{position:absolute;left:0;top:0;cursor:pointer}
.rs-pin i{
  position:absolute;left:-7px;top:-7px;width:14px;height:14px;border-radius:50%;
  background:var(--rouge);box-shadow:0 0 0 3px var(--blanc),0 3px 10px rgba(17,17,17,.35);
  transition:transform .35s var(--ease);
  animation:rsPose .7s var(--ease) both;animation-delay:var(--d,0s);
}
.rs-pin i::after{
  content:"";position:absolute;inset:-4px;border-radius:50%;
  border:2px solid var(--rouge);opacity:0;
}
.rs-pin b{
  position:absolute;left:13px;top:-13px;white-space:nowrap;
  font-family:var(--titre);font-weight:800;font-size:12px;line-height:1;
  padding:7px 10px;border-radius:999px;
  background:var(--blanc);color:var(--noir);
  box-shadow:0 3px 12px rgba(17,17,17,.2);
  transition:background .25s,color .25s;
  animation:rsPose .7s var(--ease) both;animation-delay:calc(var(--d,0s) + .12s);
}
.rs-pin--survol i,.rs-pin--actif i{transform:scale(1.4)}
.rs-pin--survol b,.rs-pin--actif b{background:var(--rouge);color:var(--blanc)}
.rs-pin--actif i::after{animation:rsOnde 1.6s ease-out infinite}
.rs-loin .rs-pin--abj b{display:none}
.rs-pin--groupe{display:none}
.rs-loin .rs-pin--groupe{display:block}
.rs-pin--groupe i{display:none}
.rs-pin--groupe b{left:0;top:0;transform:translate(-50%,-140%);background:var(--noir);color:var(--blanc)}
.rs-pin--groupe:hover b{background:var(--rouge)}
@keyframes rsPose{from{opacity:0;transform:scale(.2)}}
@keyframes rsOnde{0%{opacity:.9;transform:scale(1)}100%{opacity:0;transform:scale(2.8)}}

.leaflet-popup-content-wrapper{border-radius:12px}
.leaflet-popup-content{margin:13px 15px;font-family:var(--corps);font-size:13px}
.leaflet-popup-content strong{display:block;font-family:var(--titre);font-weight:800;font-size:15px;color:var(--noir);margin-bottom:3px}
.leaflet-container a.leaflet-popup-close-button{color:var(--noir)}

/* Le panneau noir */
.rs-panneau{
  color:var(--blanc);padding:clamp(28px,3vw,44px) clamp(22px,2.6vw,40px);
  display:flex;flex-direction:column;gap:22px;
}
.rs-panneau .sur{color:rgba(255,255,255,.55)}
.rs-panneau .sur b{color:var(--blanc)}
.rs-panneau__t{
  font-family:var(--titre);font-weight:900;
  font-size:clamp(24px,2.3vw,34px);line-height:1.05;letter-spacing:-.025em;
  margin-top:12px;
}
.rs-panneau__note{font-size:14.5px;line-height:1.55;color:rgba(255,255,255,.66)}
.rs-tout{
  display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;
  border:0;cursor:pointer;text-align:left;
  padding:15px 18px;border-radius:14px;
  background:var(--rouge);color:var(--blanc);
  font-family:var(--titre);font-weight:800;font-size:15px;
  transition:background .3s,transform .4s var(--ease);
}
.rs-tout:hover{background:#B00510;transform:translateY(-2px)}
.rs-tout .fl,.rs-zone__t .fl{
  width:16px;height:16px;flex:0 0 auto;background:currentColor;
  -webkit-mask:url('{{ asset('images/fleche.svg') }}') no-repeat center/contain;
  mask:url('{{ asset('images/fleche.svg') }}') no-repeat center/contain;
  transition:transform .4s var(--ease);
}
.rs-tout:hover .fl{transform:translateX(4px)}

.rs-zone{padding-top:20px;border-top:1px solid rgba(255,255,255,.16)}
.rs-zone__t{
  display:flex;align-items:center;gap:12px;width:100%;
  border:0;background:none;color:var(--blanc);cursor:pointer;text-align:left;padding:0;
  font-family:var(--titre);font-weight:900;font-size:clamp(19px,1.7vw,23px);letter-spacing:-.02em;
}
.rs-zone__t::before{content:"";width:10px;height:10px;border-radius:50%;background:var(--c);flex:0 0 auto}
.rs-zone__t .fl{margin-left:auto;opacity:.5}
.rs-zone__t:hover .fl,.rs-zone__t[aria-pressed="true"] .fl{opacity:1;transform:rotate(-45deg)}
.rs-zone__sous{font-size:13.5px;line-height:1.5;color:rgba(255,255,255,.6);margin-top:6px}
.rs-puces{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px;list-style:none}
.rs-puce{
  border:0;cursor:pointer;
  font-family:var(--titre);font-weight:700;font-size:12.5px;line-height:1;
  padding:9px 12px;border-radius:999px;
  background:rgba(255,255,255,.07);color:rgba(255,255,255,.88);
  box-shadow:inset 0 0 0 1px rgba(255,255,255,.16);
  transition:background .25s,color .25s,box-shadow .25s,transform .35s var(--ease);
}
.rs-puce:hover{background:var(--blanc);color:var(--noir);transform:translateY(-2px)}
.rs-puce[aria-pressed="true"]{background:var(--rouge);color:var(--blanc);box-shadow:inset 0 0 0 1px var(--rouge)}
.rs-zone__note{font-size:12.5px;color:rgba(255,255,255,.5);margin-top:10px;font-style:italic}

/* ═══════════════ PLUS QU'UNE CARTE ═══════════════ */
.rs-app{padding:clamp(60px,8vw,118px) var(--pad)}
.rs-app__grille{
  display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);
  gap:clamp(28px,4.5vw,80px);align-items:center;
}
@media(max-width:900px){.rs-app__grille{grid-template-columns:1fr}}
.rs-app .sur{--c:var(--rouge)}
.rs-app .t-grand{margin-top:16px}
.rs-app .intro{margin-top:20px}
.rs-app__ph{aspect-ratio:4/3;border-radius:clamp(16px,1.8vw,24px)}
@media(max-width:900px){.rs-app__ph{aspect-ratio:16/10}}
/* Les six critères, tirés mot pour mot du texte ci-dessus. */
.rs-crit{
  display:grid;grid-template-columns:repeat(3,minmax(0,1fr));
  margin-top:clamp(34px,4.5vw,60px);border-top:1px solid var(--ligne);
  list-style:none;
}
@media(max-width:900px){.rs-crit{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:480px){.rs-crit{grid-template-columns:1fr}}
.rs-crit li{
  position:relative;display:flex;align-items:baseline;gap:16px;
  padding:clamp(20px,2.4vw,30px) clamp(10px,1.4vw,20px) clamp(20px,2.4vw,30px) 0;
  border-bottom:1px solid var(--ligne);
  font-family:var(--titre);font-weight:800;font-size:clamp(16px,1.45vw,20px);
  letter-spacing:-.015em;line-height:1.2;
  transition:color .3s,padding-left .5s var(--ease);
}
.rs-crit li::before{
  content:"";position:absolute;left:0;bottom:-1px;height:2px;width:100%;
  background:var(--rouge);transform:scaleX(0);transform-origin:0 50%;
  transition:transform .55s var(--ease);
}
.rs-crit li:hover{padding-left:12px}
.rs-crit li:hover::before{transform:scaleX(1)}
.rs-crit span{font-size:12px;font-weight:900;letter-spacing:.1em;color:var(--rouge);flex:0 0 auto}

/* ═══════════════ FORMATS — aplat noir ═══════════════ */
.rs-fmt{background:var(--noir);color:var(--blanc);position:relative;overflow:hidden;padding:clamp(60px,8vw,118px) var(--pad)}
.rs-fmt .sur{color:rgba(255,255,255,.55)}
.rs-fmt .sur b{color:var(--blanc)}
.rs-fmt .t-grand{margin-top:16px}
.rs-fmt__haut{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:20px 40px}
.rs-fmt__grille{
  display:grid;grid-template-columns:repeat(3,minmax(0,1fr));
  gap:clamp(12px,1.6vw,20px);margin-top:clamp(32px,4.5vw,56px);position:relative;z-index:1;
}
@media(max-width:900px){.rs-fmt__grille{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.rs-fmt__grille{grid-template-columns:1fr}}
.rs-fmt__c{
  position:relative;overflow:hidden;
  padding:clamp(24px,2.4vw,32px) clamp(20px,2.2vw,28px) clamp(26px,2.6vw,34px);
  border-radius:18px;background:rgba(255,255,255,.045);
  box-shadow:inset 0 0 0 1px rgba(255,255,255,.12);
  transition:transform .5s var(--ease),background .4s;
}
.rs-fmt__c::before{
  content:"";position:absolute;inset:0 0 auto 0;height:4px;background:var(--c);
  transform:scaleX(.18);transform-origin:0 50%;transition:transform .6s var(--ease);
}
.rs-fmt__c:hover{transform:translateY(-6px);background:rgba(255,255,255,.08)}
.rs-fmt__c:hover::before{transform:scaleX(1)}
.rs-fmt__n{font-family:var(--chiffres);font-weight:900;font-size:13px;letter-spacing:.12em;color:rgba(255,255,255,.45)}
.rs-fmt__c h3{font-family:var(--titre);font-weight:900;font-size:clamp(19px,1.7vw,23px);letter-spacing:-.02em;margin-top:14px;color:var(--blanc)}
.rs-fmt__c p{margin-top:10px;font-size:14.5px;line-height:1.6;color:rgba(255,255,255,.68)}

/* ═══════════════ APPEL FINAL ═══════════════ */
.rs-fin{padding:clamp(60px,8vw,118px) var(--pad);position:relative;overflow:hidden}
.rs-fin__grille{
  display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);
  gap:clamp(30px,4.5vw,80px);align-items:center;position:relative;z-index:2;
}
@media(max-width:900px){.rs-fin__grille{grid-template-columns:1fr}}
.rs-fin .sur{--c:var(--jaune)}
.rs-fin .t-grand{margin-top:18px}
.rs-fin .intro{margin-top:22px}
/* Mosaïque des travaux : une grande vignette, deux petites. */
.rs-mosaique{
  display:grid;grid-template-columns:1.25fr 1fr;grid-template-rows:1fr 1fr;
  gap:clamp(10px,1.2vw,16px);aspect-ratio:5/4;
}
.rs-mosaique a{display:block;border-radius:clamp(14px,1.5vw,20px);min-height:0}
.rs-mosaique a:first-child{grid-row:1 / 3}
.rs-mosaique .ph{width:100%;height:100%}
.rs-mosaique .ph__legende{inset:auto 10px 10px 10px;padding:9px 12px;font-size:12px}
@media(max-width:480px){.rs-mosaique{aspect-ratio:1/1}}
@endpush

@section('contenu')
@php
    $abidjan   = \App\Support\Contenu::lignes('reseau.comm_abidjan_liste');
    $interieur = \App\Support\Contenu::lignes('reseau.comm_int_liste');
    $nbCommunes = \App\Support\Contenu::get('chiffres.communes', 31);
@endphp

{{-- ═══════════════════════ TÊTE ═══════════════════════ --}}
<section class="rs-tete">
    <div class="plume" style="--c:var(--rouge);--op:.06;top:-12%;right:-6%;width:clamp(210px,27vw,380px)" data-par="-18" data-rot="10"></div>
    <div class="large rs-tete__grille">
        <div>
            <p class="sur" data-rev>Le réseau · <b>Côte d'Ivoire</b> · depuis 1994</p>
            <div class="rs-filet" data-rev=".05"></div>
            <h1 class="t-geant rs-tete__t" data-lignes>On ne loue pas la rue. <em>On l'habite.</em></h1>
            <p class="intro" data-rev=".12">
                Trente ans à choisir les emplacements un par un, en fonction des flux réels :
                axes de sortie, marchés, carrefours, zones de concentration. C'est ce choix-là
                qu'on vous vend, pas une surface.
            </p>
            <div class="rs-actions" data-rev=".18">
                <a class="bt" href="#reseau-carte" data-viseur>
                    {{ \App\Support\Contenu::get('reseau.hero_cta', 'Trouver mes emplacements') }}<i class="fl"></i>
                </a>
                <a class="bt bt--creux" href="{{ route('cible.contact') }}" data-viseur>
                    Demander un plan d'emplacements
                </a>
            </div>
        </div>

        <div class="rs-chiffres" data-cascade>
            <div class="rs-ch" style="--c:var(--rouge)">
                <div class="rs-ch__v num"><i>+</i><span data-compte="{{ \App\Support\Contenu::get('chiffres.panneaux', 400) }}">0</span></div>
                <div class="rs-ch__l">Panneaux en exploitation</div>
            </div>
            <div class="rs-ch" style="--c:var(--jaune)">
                <div class="rs-ch__v num"><span data-compte="{{ $nbCommunes }}">0</span></div>
                <div class="rs-ch__l">Communes et villes</div>
            </div>
            <div class="rs-ch" style="--c:var(--jaune)">
                <div class="rs-ch__v num"><span data-compte="30">0</span></div>
                <div class="rs-ch__l">Ans de terrain · depuis 1994</div>
            </div>
            <div class="rs-ch" style="--c:var(--rouge)">
                <div class="rs-ch__v num"><span data-compte="{{ \App\Support\Contenu::get('chiffres.distinctions', 3) }}" data-pad="2">00</span></div>
                <div class="rs-ch__l">Distinctions d'État</div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════ RUBAN DES COMMUNES ═══════════════════════
     Les communes de la liste admin, en défilement. Décoratif : la même
     liste, interactive, est dans le panneau de la carte juste dessous. --}}
<div class="ruban rs-ruban" aria-hidden="true">
    <div class="ruban__piste">
        @for($passe = 0; $passe < 2; $passe++)
            <div>
                @foreach(array_merge($abidjan, $interieur) as $c)
                    <span>{{ $c }}</span><span class="ruban__pt"></span>
                @endforeach
            </div>
        @endfor
    </div>
</div>

{{-- ═══════════════════════ CARTE + PANNEAU ═══════════════════════ --}}
<section class="rs-carte" id="reseau-carte" aria-label="Carte du parc d'affichage">
    <div class="rs-carte__map">
        <div id="carte" role="application" aria-label="Carte interactive des communes couvertes"></div>
        <div class="carte-chargement" id="carte-chargement">
            <div>
                <div class="tourne"></div>
                <div style="margin-top:12px">Chargement de la carte…</div>
            </div>
        </div>
    </div>

    <div class="rs-panneau" id="panneau-communes"
         data-abidjan="{{ \App\Support\Contenu::get('reseau.comm_abidjan_titre', 'Abidjan') }}">
        <div>
            <p class="sur">{{ \App\Support\Contenu::get('reseau.comm_surtitre', 'La couverture') }}</p>
            <h2 class="rs-panneau__t" data-rev>Abidjan d'abord. Puis tout le pays.</h2>
        </div>
        <p class="rs-panneau__note">
            Chaque épingle marque une commune où CIBLE exploite des faces.
            Déplacez la carte pour parcourir le territoire.
        </p>

        <button type="button" class="rs-tout" data-zone="tout" aria-pressed="true" data-viseur>
            <span>{{ $nbCommunes }} communes couvertes</span><i class="fl"></i>
        </button>

        <div class="rs-zone" style="--c:var(--rouge)">
            <button type="button" class="rs-zone__t" data-zone="abidjan" aria-pressed="false" data-viseur>
                {{ \App\Support\Contenu::get('reseau.comm_abidjan_titre', 'Abidjan') }}<i class="fl"></i>
            </button>
            <p class="rs-zone__sous">{{ \App\Support\Contenu::get('reseau.comm_abidjan_sous', '13 communes') }}</p>
            <ul class="rs-puces" data-cascade>
                @foreach($abidjan as $c)
                    <li><button type="button" class="rs-puce" data-commune="{{ $c }}" aria-pressed="false">{{ $c }}</button></li>
                @endforeach
            </ul>
            @if($note = \App\Support\Contenu::get('reseau.comm_abidjan_note'))
                <p class="rs-zone__note">{{ $note }}</p>
            @endif
        </div>

        <div class="rs-zone" style="--c:var(--jaune)">
            <button type="button" class="rs-zone__t" data-zone="interieur" aria-pressed="false" data-viseur>
                {{ \App\Support\Contenu::get('reseau.comm_int_titre', 'Intérieur du pays') }}<i class="fl"></i>
            </button>
            <p class="rs-zone__sous">{{ \App\Support\Contenu::get('reseau.comm_int_sous', '18 villes') }}</p>
            <ul class="rs-puces" data-cascade>
                @foreach($interieur as $c)
                    <li><button type="button" class="rs-puce" data-commune="{{ $c }}" aria-pressed="false">{{ $c }}</button></li>
                @endforeach
            </ul>
            @if($note = \App\Support\Contenu::get('reseau.comm_int_note'))
                <p class="rs-zone__note">{{ $note }}</p>
            @endif
        </div>
    </div>
</section>

{{-- ═══════════════════════ PLUS QU'UNE CARTE ═══════════════════════ --}}
<section class="rs-app">
    <div class="large">
        <div class="rs-app__grille">
            <div>
                <p class="sur" data-rev>{{ \App\Support\Contenu::get('reseau.app_surtitre', "Plus qu'une carte") }}</p>
                <h2 class="t-grand" data-lignes>{{ \App\Support\Contenu::get('reseau.app_titre', 'Des emplacements sélectionnés selon vos audiences et vos objectifs.') }}</h2>
                <p class="intro" data-rev=".1">{{ \App\Support\Contenu::get('reseau.app_texte', '') }}</p>
            </div>
            <div class="rs-app__ph ph ph--scroll" style="--c:var(--rouge)" data-rev=".12">
                <img src="{{ \App\Support\Contenu::urlDatee('refonte/test/abidjan.webp') }}"
                     alt="Scène de rue à Abidjan" width="1800" height="1200" loading="lazy">
                <div class="ph__legende">Visuel de test · Unsplash, Yanick Folly</div>
            </div>
        </div>

        {{-- Les six critères énoncés dans le texte ci-dessus, posés en
             liste pour qu'on les lise d'un coup d'œil. --}}
        <ul class="rs-crit" data-cascade>
            @foreach(['La pertinence des zones', 'La visibilité du support', 'Le sens de circulation', 'Le format', "La durée d'exposition", 'Les habitudes de votre cible'] as $i => $critere)
                <li><span>0{{ $i + 1 }}</span>{{ $critere }}</li>
            @endforeach
        </ul>
    </div>
</section>

{{-- ═══════════════════════ FORMATS ═══════════════════════ --}}
<section class="rs-fmt">
    <div class="plume" style="--c:var(--rouge);--op:.1;bottom:-18%;right:-4%;width:clamp(200px,24vw,340px)" data-par="-16" data-rot="-8"></div>
    <div class="large">
        <div class="rs-fmt__haut">
            <div>
                <p class="sur" data-rev>Les <b>formats</b></p>
                <h2 class="t-grand" data-lignes>Le bon support, pas le plus grand.</h2>
            </div>
        </div>
        <div class="rs-fmt__grille" data-cascade>
            @foreach([
                ['var(--rouge)',  'Panneaux classiques',  "Le socle du réseau. Présence continue sur les axes à fort trafic, en 4×3 et grands formats."],
                ['var(--jaune)',  'Lumipub',              "Caissons éclairés : votre message reste lisible après la tombée de la nuit, quand le trafic est encore dense."],
                ['var(--rouge)',  'Trivision',            "Trois visuels en rotation sur une même face. Trois messages, ou trois annonceurs, un seul emplacement."],
                ['var(--jaune)',  'Panoramiques',         "Les très grands formats, sur les axes d'entrée et de sortie d'Abidjan. Pour les prises de parole fortes."],
                ['var(--bleu)',   'Écrans digitaux',      "Diffusion animée et programmable. Idéal pour une campagne à durée courte ou à message variable."],
                ['var(--rouge)',  'Affichage en magasin', "Au dernier mètre, là où la décision d'achat se prend réellement."],
            ] as $i => [$c, $titre, $txt])
                <div class="rs-fmt__c" style="--c:{{ $c }}">
                    <span class="rs-fmt__n">0{{ $i + 1 }}</span>
                    <h3>{{ $titre }}</h3>
                    <p>{{ $txt }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════ APPEL ═══════════════════════ --}}
<section class="rs-fin">
    <div class="fleche-d" style="--c:var(--jaune);--op:.1;top:10%;left:3%;width:clamp(80px,10vw,140px)" data-par="-22" data-rot="-16"></div>
    <div class="large rs-fin__grille">
        <div>
            <p class="sur" data-rev>Parlons-en</p>
            <h2 class="t-grand" data-lignes>Dites-nous quelles zones vous voulez couvrir.</h2>
            <p class="intro" data-rev=".1">
                On vous revient avec un plan d'emplacements justifié commune par commune,
                sous 24 heures ouvrées.
            </p>
            <div class="rs-actions" data-rev=".16">
                <a class="bt" href="{{ route('cible.contact') }}" data-viseur>
                    Demander un plan d'emplacements<i class="fl"></i>
                </a>
                <a class="bt bt--creux" href="tel:+2250700780628" data-viseur>
                    +225 07 00 78 06 28
                </a>
            </div>
        </div>

        @php $vignettes = collect($realisations ?? [])->take(3); @endphp
        @if($vignettes->count() === 3)
            <div class="rs-mosaique" data-cascade>
                @foreach($vignettes as $slug => $p)
                    <a href="{{ route('cible.travaux') }}#{{ $slug }}" data-viseur aria-label="{{ $p['nom'] ?? $slug }} — voir la campagne">
                        <div class="ph ph--scroll" style="--c:var(--rouge);border-radius:inherit">
                            <img src="{{ \App\Support\Contenu::urlImage($p['image'] ?? 'images/cible/campagne-1.jpg') }}"
                                 alt="{{ $p['cat'] ?? 'Campagne' }} — {{ $p['nom'] ?? '' }}" loading="lazy" decoding="async">
                            <div class="ph__legende">{{ $p['nom'] ?? $slug }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>

@endsection

@push('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
<script>
/* Carte du parc. Même endpoint que la V1 (/api/reseau-map) : la maquette
   ne duplique pas la source de données.
   Le champ `total` du JSON n'est volontairement PAS affiché — décision
   client d'août 2026 de ne plus publier de répartition chiffrée par zone.

   2026-10-09 :
   · la carte n'est créée qu'à l'approche de l'écran (IntersectionObserver)
     et Leaflet est chargé en `defer` : elle ne retarde plus le rendu ;
   · fond de carte : les tuiles CARTO renvoient désormais « API KEY
     REQUIRED » à la place de la carte ; remplacées par le fond gris clair
     d'Esri (sans clé), neutre, qui laisse le rouge des épingles porter ;
   · le panneau noir pilote la carte : commune → centrage + bulle,
     zone → cadrage de la zone, « N communes » → vue d'ensemble. */
document.addEventListener('DOMContentLoaded', function () {
  var zone = document.getElementById('carte');
  var attente = document.getElementById('carte-chargement');
  var panneau = document.getElementById('panneau-communes');
  if (!zone || typeof window.L === 'undefined') { return; }

  var doux = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var demarree = false;

  function norme(s) {
    return String(s || '').toLowerCase().normalize('NFD')
      .replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]/g, '');
  }
  function echappe(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function demarrer() {
    if (demarree) { return; }
    demarree = true;

    var carte = L.map(zone, {
      zoomControl: true,
      zoomSnap: 0.25,           // cadrage plus serré du territoire
      scrollWheelZoom: false,   // sinon la molette capture le scroll de page
      attributionControl: true,
    }).setView([7.5, -5.5], 7);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
      attribution: 'Fond &copy; Esri — Esri, HERE, Garmin, &copy; OpenStreetMap',
      maxZoom: 16,
    }).addTo(carte);

    // La molette ne pilote le zoom qu'après un clic : on respecte le scroll
    // de lecture, tout en gardant la carte manipulable.
    carte.on('click', function () { carte.scrollWheelZoom.enable(); });
    carte.on('mouseout', function () { carte.scrollWheelZoom.disable(); });

    // Étiquettes d'Abidjan masquées tant qu'on est à l'échelle du pays.
    function echelle() { zone.classList.toggle('rs-loin', carte.getZoom() < 10); }
    carte.on('zoomend', echelle);
    echelle();

    var marqueurs = {};   // nom normalisé → { m, p }
    var tous = [], abj = [], ailleurs = [];
    var actif = null;

    function aller(latlngs, zoomMax) {
      if (!latlngs.length) { return; }
      var opts = { paddingTopLeft: [50, 50], paddingBottomRight: [120, 50], maxZoom: zoomMax || 12 };
      if (doux) { carte.flyToBounds(latlngs, Object.assign({ duration: 1.1 }, opts)); }
      else { carte.fitBounds(latlngs, opts); }
    }
    function marquer(cle, classe, oui) {
      var e = marqueurs[cle] && marqueurs[cle].m.getElement();
      if (e && e.firstChild) { e.firstChild.classList.toggle(classe, oui); }
    }
    function presser(el) {
      if (!panneau) { return; }
      panneau.querySelectorAll('[aria-pressed]').forEach(function (b) {
        b.setAttribute('aria-pressed', b === el ? 'true' : 'false');
      });
    }

    function choisirCommune(cle, bouton) {
      var x = marqueurs[cle];
      if (!x) { return; }
      if (actif) { marquer(actif, 'rs-pin--actif', false); marqueurs[actif].m.setZIndexOffset(0); }
      actif = cle;
      marquer(cle, 'rs-pin--actif', true);
      x.m.setZIndexOffset(1000);
      var cible = L.latLng(x.p.lat, x.p.lng);
      // Bulle ouverte à l'arrivée. L'écouteur est posé AVANT le déplacement :
      // sans animation, setView émet moveend immédiatement.
      if (carte.getZoom() === 12 && carte.getCenter().distanceTo(cible) < 50) {
        x.m.openPopup();
      } else {
        carte.once('moveend', function () { x.m.openPopup(); });
        if (doux) { carte.flyTo(cible, 12, { duration: 1.1 }); } else { carte.setView(cible, 12); }
      }
      if (panneau) {
        presser(bouton || panneau.querySelector('.rs-puce[data-commune="' + CSS.escape(x.p.commune) + '"]'));
      }
    }

    fetch('{{ route('cible.api.reseau-map') }}', { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var pins = (d && d.pins) || [];
        if (!pins.length) { attente.hidden = true; return; }

        var n = 0;
        pins.forEach(function (p) {
          if (typeof p.lat !== 'number' || typeof p.lng !== 'number') { return; }
          var cle = norme(p.commune);
          var estAbj = norme(p.region) === 'abidjan';
          var ll = [p.lat, p.lng];
          tous.push(ll); (estAbj ? abj : ailleurs).push(ll);

          var m = L.marker(ll, {
            icon: L.divIcon({
              className: 'rs-pin-icone', iconSize: [0, 0],
              html: '<span class="rs-pin' + (estAbj ? ' rs-pin--abj' : '') + '" style="--d:' + (n++ * 0.035).toFixed(3) + 's">'
                  + '<i></i><b>' + echappe(p.commune) + '</b></span>',
            }),
            title: p.commune,
          })
            .bindPopup('<strong>' + echappe(p.commune) + '</strong>' + (p.region ? echappe(p.region) : ''))
            .addTo(carte);

          m.on('click', function () { choisirCommune(cle); });
          m.on('mouseover', function () { marquer(cle, 'rs-pin--survol', true); });
          m.on('mouseout', function () { marquer(cle, 'rs-pin--survol', false); });
          marqueurs[cle] = { m: m, p: p };
        });

        // Une seule étiquette « Grand Abidjan » à l'échelle du pays : un clic
        // cadre l'agglomération.
        if (abj.length && panneau) {
          var c = L.latLngBounds(abj).getCenter();
          L.marker(c, {
            icon: L.divIcon({
              className: 'rs-pin-icone', iconSize: [0, 0],
              html: '<span class="rs-pin rs-pin--groupe"><i></i><b>' + echappe(panneau.dataset.abidjan) + '</b></span>',
            }),
            zIndexOffset: 2000, keyboard: false,
          }).on('click', function () {
            aller(abj, 12);
            presser(panneau.querySelector('[data-zone="abidjan"]'));
          }).addTo(carte);
        }

        if (tous.length) { carte.fitBounds(tous, { paddingTopLeft: [40, 40], paddingBottomRight: [120, 40] }); }
        attente.hidden = true;

        // Le conteneur change de hauteur après coup (polices chargées,
        // panneau qui s'allonge, rotation d'écran) : Leaflet doit le savoir,
        // sinon la carte reste décalée. Tant que le visiteur n'a pas touché
        // la carte, on recadre aussi sur l'ensemble du réseau.
        var touchee = false;
        zone.addEventListener('pointerdown', function () { touchee = true; });
        if ('ResizeObserver' in window) {
          var attenteTaille;
          new ResizeObserver(function () {
            clearTimeout(attenteTaille);
            attenteTaille = setTimeout(function () {
              carte.invalidateSize();
              if (!touchee && !actif) { carte.fitBounds(tous, { paddingTopLeft: [40, 40], paddingBottomRight: [120, 40] }); }
            }, 120);
          }).observe(zone);
        }

        // Pilotage depuis le panneau
        if (!panneau) { return; }
        panneau.querySelectorAll('.rs-puce').forEach(function (b) {
          var cle = norme(b.dataset.commune);
          if (!marqueurs[cle]) { b.disabled = true; return; }
          b.addEventListener('click', function () { choisirCommune(cle, b); });
          b.addEventListener('mouseenter', function () { marquer(cle, 'rs-pin--survol', true); });
          b.addEventListener('mouseleave', function () { marquer(cle, 'rs-pin--survol', false); });
        });
        panneau.querySelectorAll('[data-zone]').forEach(function (b) {
          b.addEventListener('click', function () {
            touchee = true;
            carte.closePopup();
            if (actif) { marquer(actif, 'rs-pin--actif', false); actif = null; }
            var z = b.dataset.zone;
            aller(z === 'abidjan' ? abj : z === 'interieur' ? ailleurs : tous, z === 'abidjan' ? 12 : 9);
            presser(b);
          });
        });
      })
      .catch(function () {
        attente.innerHTML = '<div>Carte momentanément indisponible.<br>'
          + 'Le réseau couvre {{ $nbCommunes }} communes et villes.</div>';
      });
  }

  // Création différée : la carte ne coûte rien tant qu'elle est loin.
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (e) {
      if (e[0].isIntersecting) { io.disconnect(); demarrer(); }
    }, { rootMargin: '400px 0px' });
    io.observe(zone);
  } else {
    demarrer();
  }
});
</script>
@endpush
