@extends('public.cible._coque', ['titre' => "Ce qu'on fait", 'actuelle' => 'expertises'])

@push('css')
/* Un pôle = un écran. La V1 empilait 1 542 mots sur une seule page ;
   ici chaque métier occupe sa pleine hauteur, avec sa couleur de charte,
   et on ne lit jamais deux pôles en même temps. */
.tete{padding:clamp(130px,17vh,200px) var(--pad) clamp(50px,7vw,90px);position:relative;overflow:hidden}
.tete .sur{--c:var(--bleu)}
.tete__t{margin-top:20px}
.tete__t em{font-style:normal;color:var(--jaune)}

/* Index latéral : repère de position, visible seulement quand il y a de
   la place pour lui. */
.index{
  position:fixed;z-index:120;left:var(--pad);top:50%;transform:translateY(-50%);
  display:grid;gap:14px;opacity:0;transition:opacity .5s;
}
.index.vu{opacity:1}
.index a{display:flex;align-items:center;gap:11px;font-family:var(--titre);font-weight:800;font-size:11px;letter-spacing:.12em;color:var(--texte-3);transition:color .35s}
.index a i{width:20px;height:2px;background:currentColor;border-radius:2px;transition:width .4s var(--ease),background .35s}
.index a.actif{color:#fff}
.index a.actif i{width:42px;background:var(--c)}
@media(max-width:1340px){.index{display:none}}

.pole{
  min-height:100svh;display:grid;align-content:center;
  padding:clamp(80px,11vh,130px) var(--pad);
  position:relative;overflow:hidden;
  border-top:1px solid var(--ligne);
}
/* Halo de la couleur du pôle : situe le métier sans ajouter de couleur
   hors charte, puisque c'est la couleur du pôle elle-même. */
.pole::before{
  content:"";position:absolute;inset:0;z-index:0;pointer-events:none;
  background:radial-gradient(70% 60% at 85% 20%,color-mix(in srgb,var(--c) 17%,transparent),transparent 70%);
}
.pole__in{display:grid;grid-template-columns:1.04fr .96fr;gap:clamp(30px,5vw,80px);align-items:center;position:relative;z-index:2}
@media(max-width:980px){.pole__in{grid-template-columns:1fr;gap:34px}}
.pole:nth-child(even) .pole__in{direction:rtl}
.pole:nth-child(even) .pole__in > *{direction:ltr}
@media(max-width:980px){.pole:nth-child(even) .pole__in{direction:ltr}}

.pole__etiq{
  display:inline-flex;align-items:center;gap:9px;
  padding:8px 15px;border-radius:999px;background:var(--c);color:#fff;
  font-family:var(--titre);font-weight:800;font-size:10.5px;letter-spacing:.15em;text-transform:uppercase;
}
.pole__t{margin-top:20px}
.pole__t em{font-style:normal;color:var(--c)}
.pole__txt{margin-top:22px;color:var(--texte-2);max-width:54ch;font-size:16.5px;line-height:1.68}
.pole__acc{
  margin-top:20px;padding-left:18px;border-left:3px solid var(--c);
  color:var(--texte-2);font-size:15.5px;line-height:1.6;max-width:52ch;
}
.pole__ph{aspect-ratio:4/5;border-radius:22px;overflow:hidden}
@media(max-width:980px){.pole__ph{aspect-ratio:16/11}}

.disp{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:26px}
@media(max-width:560px){.disp{grid-template-columns:1fr}}
.disp li{
  padding:13px 15px;border-radius:11px;background:rgba(255,255,255,.045);
  box-shadow:inset 0 0 0 1px var(--ligne);
  font-family:var(--titre);font-weight:700;font-size:13.5px;
  display:flex;align-items:center;gap:10px;
  transition:background .3s,box-shadow .3s;
}
.disp li:hover{background:rgba(255,255,255,.09);box-shadow:inset 0 0 0 1px var(--c)}
.disp li::before{content:"";width:5px;height:5px;border-radius:50%;background:var(--c);flex:0 0 auto}

/* Méthode : 4 temps, la séquence est réelle donc la numérotation l'est aussi */
.meth{background:var(--fond-2);border-block:1px solid var(--ligne)}
.meth__grille{display:grid;grid-template-columns:repeat(4,1fr);gap:clamp(14px,2vw,24px);margin-top:clamp(38px,5vw,60px)}
@media(max-width:900px){.meth__grille{grid-template-columns:repeat(2,1fr)}}
@media(max-width:520px){.meth__grille{grid-template-columns:1fr}}
.etape{padding:26px 22px;border-radius:16px;background:var(--fond-3);border-top:3px solid var(--c)}
.etape__n{font-family:var(--titre);font-weight:900;font-size:40px;line-height:1;color:var(--c);opacity:.35}
.etape h3{font-family:var(--titre);font-weight:800;font-size:17px;margin-top:12px}
.etape p{margin-top:9px;font-size:14.5px;color:var(--texte-2);line-height:1.55}
@endpush

@section('contenu')

<nav class="index" id="index" aria-label="Les pôles">
    @foreach([['p1','Régie','var(--rouge)'],['p2','Mobile','var(--jaune)'],['p3','Experience','var(--violet)'],['p4','Intelligence','var(--bleu)']] as [$id, $nom, $c])
        <a href="#{{ $id }}" data-viseur style="--c:{{ $c }}"><i></i>{{ $nom }}</a>
    @endforeach
</nav>

{{-- ═══════════════════════ TÊTE ═══════════════════════ --}}
<section class="tete">
    <div class="plume" style="--c:var(--bleu);--op:.07;top:-10%;right:-5%;width:clamp(220px,28vw,400px)" data-par="-18" data-rot="12"></div>
    <div class="large">
        <p class="sur" data-rev>Nos expertises</p>
        <h1 class="t-geant tete__t" data-lignes>Quatre métiers. <em>Un seul résultat attendu.</em></h1>
        <p class="intro" style="margin-top:24px" data-rev=".12">
            On ne vous vendra pas « du 360 ». On vous dira lequel de ces quatre leviers
            sert votre objectif, et pourquoi les autres peuvent attendre.
        </p>
    </div>
</section>

{{-- ═══════════════════════ LES 4 PÔLES ═══════════════════════ --}}
@foreach([
    ['id' => 'p1', 'c' => 'var(--rouge)',  'ph' => 'lumipub'],
    ['id' => 'p2', 'c' => 'var(--jaune)',  'ph' => 'mobile'],
    ['id' => 'p3', 'c' => 'var(--violet)', 'ph' => 'campagne-5'],
    ['id' => 'p4', 'c' => 'var(--bleu)',   'ph' => 'affichage'],
] as $n => $pole)
    @php
        $k = 'p' . ($n + 1);
        $accroche = \App\Support\Contenu::get("services.{$k}_accroche");
    @endphp
    <section class="pole" id="{{ $pole['id'] }}" style="--c:{{ $pole['c'] }}" data-pole="{{ $pole['id'] }}">
        <div class="large pole__in">
            <div>
                <span class="pole__etiq" data-rev>{{ \App\Support\Contenu::get("services.{$k}_tag") }}</span>
                {{-- riche() : le texte entre **astérisques** ressort dans la couleur
                     du pôle. Aucun HTML saisi en admin n'est interprété. --}}
                <h2 class="t-grand pole__t" data-rev=".06">{!! \App\Support\Contenu::riche("services.{$k}_titre") !!}</h2>
                <p class="pole__txt" data-rev=".12">{!! \App\Support\Contenu::riche("services.{$k}_texte") !!}</p>
                @if($accroche)
                    <p class="pole__acc" data-rev=".18">{{ $accroche }}</p>
                @endif

                @if($k === 'p1')
                    <ul class="disp" data-cascade>
                        @foreach(\App\Support\Contenu::lignes('services.p1_dispositifs') as $d)
                            <li>{{ $d }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="pole__ph ph ph--scroll" style="--c:{{ $pole['c'] }}" data-rev=".1">
                <img src="{{ asset('refonte/photo/' . $pole['ph'] . '.webp') }}"
                     alt="{{ \App\Support\Contenu::get("services.{$k}_tag") }}" loading="lazy">
                <div class="ph__legende">Côte d'Ivoire · dispositif en exploitation</div>
            </div>
        </div>
    </section>
@endforeach

{{-- ═══════════════════════ MÉTHODE ═══════════════════════ --}}
<section class="bloc meth">
    <div class="large">
        <div class="entete">
            <p class="sur" style="--c:var(--vert)">Comment on travaille</p>
            <h2 class="t-grand" data-lignes>Quatre temps, et une preuve à la fin.</h2>
        </div>

        <div class="meth__grille" data-cascade>
            @foreach([
                ['var(--rouge)',  'Cadrage',      "On part de votre objectif, pas de notre inventaire. Audience, zones, budget, échéance."],
                ['var(--jaune)',  'Recommandation', "Un plan d'emplacements et de formats justifié zone par zone, chiffré, sans engagement."],
                ['var(--vert)',   'Déploiement',  "Production, pose, coordination terrain. Vous avez un interlocuteur unique."],
                ['var(--bleu)',   'Preuve',       "Pige photo horodatée et géolocalisée de chaque face. Vous voyez ce que vous avez payé."],
            ] as $i => [$c, $titre, $txt])
                <div class="etape" style="--c:{{ $c }}">
                    <div class="etape__n num">0{{ $i + 1 }}</div>
                    <h3>{{ $titre }}</h3>
                    <p>{{ $txt }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════ APPEL ═══════════════════════ --}}
<section class="bloc" style="text-align:center;position:relative;overflow:hidden">
    <div class="fleche-d" style="--c:var(--rouge);--op:.1;top:20%;right:9%;width:clamp(80px,10vw,140px)" data-par="-24" data-rot="20"></div>
    <div style="max-width:880px;margin-inline:auto;position:relative;z-index:2">
        <h2 class="t-grand" data-lignes>Lequel de ces quatre leviers vous servirait le mieux&nbsp;?</h2>
        <p class="intro" style="margin:24px auto 0">
            Décrivez-nous votre objectif en quelques lignes. On vous répond avec une
            recommandation, pas avec un catalogue.
        </p>
        <div style="margin-top:34px" data-rev=".1">
            <a class="bt" href="{{ route('cible.contact') }}" data-viseur>
                Recevoir une recommandation média<i class="fl"></i>
            </a>
        </div>
    </div>
</section>

@endsection

@push('js')
<script>
/* Index latéral : met en évidence le pôle traversé. Pas de bibliothèque,
   un seul ScrollTrigger par section. */
(function () {
  if (typeof window.gsap === 'undefined') { return; }
  var index = document.getElementById('index');
  var liens = index ? index.querySelectorAll('a') : [];
  if (!liens.length) { return; }

  document.querySelectorAll('[data-pole]').forEach(function (sec, i) {
    ScrollTrigger.create({
      trigger: sec, start: 'top 55%', end: 'bottom 45%',
      onToggle: function (self) {
        if (!self.isActive) { return; }
        liens.forEach(function (a) { a.classList.remove('actif'); });
        liens[i].classList.add('actif');
      },
    });
  });

  // L'index n'apparaît qu'une fois le premier pôle atteint : au-dessus,
  // il flotterait à côté du titre sans rien repérer.
  ScrollTrigger.create({
    trigger: '[data-pole]', start: 'top 80%',
    onEnter:     function () { index.classList.add('vu'); },
    onLeaveBack: function () { index.classList.remove('vu'); },
  });
})();
</script>
@endpush
