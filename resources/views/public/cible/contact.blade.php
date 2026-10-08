@extends('public.cible._coque', ['titre' => 'Contact', 'actuelle' => 'contact'])

@push('css')
.tete{padding:clamp(130px,17vh,200px) var(--pad) clamp(34px,5vw,60px);position:relative;overflow:hidden}
.tete .sur{--c:var(--rouge)}
.tete__t{margin-top:20px}
.tete__t em{font-style:normal;color:var(--rouge)}

/* Colonne unique : voir l'en-tête de ce fichier. Le formulaire change de
   hauteur à chaque étape, rien ne peut s'aligner à côté de lui. */
.duo-c{max-width:820px;margin-inline:auto}

/* Informations de contact : une rangée de trois sous le formulaire. */
.infos{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(14px,2vw,22px);margin-top:clamp(40px,5vw,70px)}
@media(max-width:860px){.infos{grid-template-columns:1fr}}

/* ── Formulaire : un seul écran, découpé en 4 temps ──
   La V1 présentait un mini-brief de 7 blocs d'affilée, abandonné en cours
   de route par les visiteurs. On garde les mêmes questions mais une seule
   à la fois, avec une progression visible. */
.form{
  padding:clamp(26px,3.4vw,44px);border-radius:24px;
  background:var(--fond-2);box-shadow:inset 0 0 0 1px var(--ligne);
}
.jauge-e{display:flex;gap:7px;margin-bottom:28px}
.jauge-e span{height:3px;flex:1;border-radius:3px;background:var(--ligne);transition:background .45s}
.jauge-e span.fait{background:var(--rouge)}
.etape-n{font-family:var(--titre);font-weight:800;font-size:11.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--texte-3)}
.etape-t{font-family:var(--titre);font-weight:900;font-size:clamp(21px,2.3vw,29px);letter-spacing:-.02em;margin-top:9px}
.etape-s{margin-top:9px;font-size:15px;color:var(--texte-2);line-height:1.55}
.pas[hidden]{display:none}

label{display:block;font-family:var(--titre);font-weight:700;font-size:12px;letter-spacing:.05em;text-transform:uppercase;color:var(--texte-3);margin-bottom:7px}
input[type=text],input[type=email],input[type=tel],textarea,select{
  width:100%;padding:14px 16px;border-radius:12px;
  background:var(--blanc);color:var(--noir);border:0;
  box-shadow:inset 0 0 0 1.5px var(--ligne);
  font-family:var(--corps);font-size:16px;
  transition:box-shadow .25s;
}
input:focus,textarea:focus,select:focus{outline:none;box-shadow:inset 0 0 0 1.5px var(--rouge)}
input::placeholder,textarea::placeholder{color:var(--texte-3)}
textarea{min-height:120px;resize:vertical}
.champ{margin-bottom:17px}
.champ--duo{display:grid;grid-template-columns:1fr 1fr;gap:17px}
@media(max-width:620px){.champ--duo{grid-template-columns:1fr}}

/* Choix multiples en pastilles : plus rapide à l'œil et au doigt qu'une
   colonne de cases à cocher. */
.choix{display:flex;flex-wrap:wrap;gap:9px}
.choix input{position:absolute;opacity:0;width:0;height:0}
.choix label{
  margin:0;padding:11px 17px;border-radius:999px;cursor:pointer;
  font-family:var(--titre);font-weight:700;font-size:13.5px;
  text-transform:none;letter-spacing:0;color:var(--texte-2);
  box-shadow:inset 0 0 0 1.5px var(--ligne);
  transition:background .25s,color .25s,box-shadow .25s;
}
.choix label:hover{color:var(--noir);background:rgba(17,17,17,.05)}
.choix input:checked + label{background:var(--rouge);color:#fff;box-shadow:none}
.choix input:focus-visible + label{outline:3px solid var(--jaune);outline-offset:3px}

.form__pied{display:flex;gap:11px;align-items:center;margin-top:26px;flex-wrap:wrap}
.form__pied .compte{margin-left:auto;font-size:12.5px;color:var(--texte-3)}

/* Confirmation de maquette : le formulaire n'envoie rien ici. */
.recu{display:none;text-align:center;padding:16px 0}
.recu.vu{display:block}
.recu__rond{
  width:66px;height:66px;border-radius:50%;margin:0 auto 20px;
  display:grid;place-items:center;background:rgba(58,168,53,.16);
  box-shadow:inset 0 0 0 1.5px var(--vert);
  font-size:28px;color:var(--vert);
}
.recu h3{font-family:var(--titre);font-weight:900;font-size:25px;letter-spacing:-.02em}
.recu p{margin-top:12px;color:var(--texte-2);font-size:15px;line-height:1.6}

/* ── Colonne de droite ── */


.bloc-c{padding:24px;border-radius:18px;background:var(--fond-2);box-shadow:inset 0 0 0 1px var(--ligne);border-left:4px solid var(--c)}
.bloc-c h3{font-family:var(--titre);font-weight:800;font-size:12px;letter-spacing:.13em;text-transform:uppercase;color:var(--rouge)}
.bloc-c ul{list-style:none;display:grid;gap:10px;margin-top:15px}
.bloc-c li{font-size:15px;color:var(--texte-2);line-height:1.45}
.bloc-c a{font-family:var(--titre);font-weight:700;color:var(--noir)}
.bloc-c a:hover{color:var(--rouge)}
.promesse{list-style:none;display:grid;gap:11px;margin-top:15px}
.promesse li{display:flex;gap:11px;font-size:14.5px;color:var(--texte-2);line-height:1.45}
.promesse li::before{content:"";width:6px;height:6px;border-radius:50%;background:var(--c);margin-top:7px;flex:0 0 auto}
@endpush

@section('contenu')

{{-- ═══════════════════════ TÊTE ═══════════════════════ --}}
<section class="tete">
    <div class="plume" style="--c:var(--rouge);--op:.07;top:-8%;right:-6%;width:clamp(200px,26vw,360px)" data-par="-16" data-rot="12"></div>
    <div class="large">
        <p class="sur" data-rev>Contact</p>
        <h1 class="t-geant tete__t" data-lignes>Où voulez-vous être vu&nbsp;? <em>On s'occupe du reste.</em></h1>
    </div>
</section>

{{-- ═══════════════════════ FORMULAIRE ═══════════════════════ --}}
<section class="bloc" style="padding-top:clamp(18px,2vw,30px)">
    <div class="large">

        <div class="duo-c">

        <form class="form" id="form" novalidate data-rev>
            <div class="jauge-e" id="jauge-e" aria-hidden="true">
                <span class="fait"></span><span></span><span></span><span></span>
            </div>

            <div id="pas-tous">
                {{-- Temps 1 — qui --}}
                <div class="pas" data-pas="1">
                    <p class="etape-n">Étape 1 sur 4</p>
                    <h2 class="etape-t">Qui êtes-vous&nbsp;?</h2>
                    <p class="etape-s">De quoi vous répondre, et savoir à qui l'on parle.</p>
                    <div style="margin-top:26px">
                        <div class="champ champ--duo">
                            <div><label for="nom">Nom et prénom</label><input id="nom" type="text" placeholder="Aya Koné" required></div>
                            <div><label for="fonction">Fonction</label><input id="fonction" type="text" placeholder="Directrice marketing" required></div>
                        </div>
                        <div class="champ"><label for="societe">Société ou institution</label><input id="societe" type="text" placeholder="Nom de votre structure" required></div>
                        <div class="champ champ--duo">
                            <div><label for="email">Email professionnel</label><input id="email" type="email" placeholder="vous@societe.com" required></div>
                            <div><label for="tel">Téléphone</label><input id="tel" type="tel" placeholder="+225 ..." required></div>
                        </div>
                    </div>
                </div>

                {{-- Temps 2 — objectif. Les options viennent de la source unique
                     CibleController::formOptions(), comme en V1. --}}
                <div class="pas" data-pas="2" hidden>
                    <p class="etape-n">Étape 2 sur 4</p>
                    <h2 class="etape-t">Que cherchez-vous à obtenir&nbsp;?</h2>
                    <p class="etape-s">Plusieurs réponses possibles. C'est ce qui détermine la recommandation.</p>
                    <div class="choix" style="margin-top:26px">
                        @foreach(($options['objectif'] ?? []) as $cle => $libelle)
                            <input type="checkbox" id="o-{{ $cle }}" name="objectifs[]" value="{{ $cle }}">
                            <label for="o-{{ $cle }}">{{ $libelle }}</label>
                        @endforeach
                    </div>
                </div>

                {{-- Temps 3 — dispositifs et zones --}}
                <div class="pas" data-pas="3" hidden>
                    <p class="etape-n">Étape 3 sur 4</p>
                    <h2 class="etape-t">Quels dispositifs vous intéressent&nbsp;?</h2>
                    <p class="etape-s">Si vous ne savez pas, laissez vide : c'est notre métier de vous orienter.</p>
                    <div class="choix" style="margin-top:26px">
                        @foreach(($options['services'] ?? []) as $cle => $libelle)
                            <input type="checkbox" id="d-{{ $cle }}" name="dispositifs[]" value="{{ $cle }}">
                            <label for="d-{{ $cle }}">{{ $libelle }}</label>
                        @endforeach
                    </div>
                    @if(!empty($options['zone']))
                        <div style="margin-top:28px">
                            <label>Zones visées</label>
                            <div class="choix">
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
                <span class="compte" id="compte">Étape 1 / 4</span>
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

        {{-- ── Colonne de droite ── --}}
        </div>{{-- /.duo-c --}}

        <div class="infos">
            <div class="bloc-c" style="--c:var(--rouge)" data-rev=".08">
                <h3>Nous joindre directement</h3>
                <ul>
                    <li><a href="tel:+2250700780628" class="num" data-viseur>+225 07 00 78 06 28</a><br><span style="font-size:13px;color:var(--texte-3)">Mobile · WhatsApp</span></li>
                    <li><a href="tel:+2252722208008" class="num" data-viseur>+225 27 22 20 80 08</a><br><span style="font-size:13px;color:var(--texte-3)">Standard</span></li>
                    <li><a href="mailto:commercial@cible-ci.com" data-viseur>commercial@cible-ci.com</a></li>
                </ul>
            </div>

            <div class="bloc-c" style="--c:var(--jaune)" data-rev=".14">
                <h3>Ce que vous obtenez</h3>
                <ul class="promesse">
                    <li>Une recommandation adaptée à votre objectif, pas un catalogue</li>
                    <li>Les formats et emplacements les plus pertinents, justifiés</li>
                    <li>Une proposition calée sur votre budget</li>
                    <li>Un interlocuteur unique jusqu'au lancement</li>
                    <li>Une réponse sous 24 heures ouvrées</li>
                </ul>
            </div>

            <div class="bloc-c" style="--c:var(--rouge)" data-rev=".2">
                <h3>Nous rendre visite</h3>
                <ul>
                    <li>Rue des Ambassadeurs<br>Riviera M'Badon<br>10 BP 1029 Abidjan 10<br>Côte d'Ivoire</li>
                    <li style="color:var(--texte-3);font-size:13.5px">Du lundi au vendredi, 8h – 17h30</li>
                </ul>
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
    var premier = pas[i].querySelector('input, textarea, select');
    if (premier && i > 0) { premier.focus({ preventScroll: true }); }
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
    recu.classList.add('vu');
    if (typeof ScrollTrigger !== 'undefined') { ScrollTrigger.refresh(); }
  });

  peindre();
})();
</script>
@endpush
