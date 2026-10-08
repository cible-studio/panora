@extends('public.cible._coque', ['titre' => "Ce qu'on fait", 'actuelle' => 'expertises'])

@push('css')
/* ═══════════════ TÊTE ═══════════════ */
.tete{padding:clamp(130px,17vh,200px) var(--pad) clamp(40px,6vw,76px);position:relative;overflow:hidden}
.tete .sur{--c:var(--rouge)}
.tete__t{margin-top:20px}
.tete__t em{font-style:normal;color:var(--rouge)}

/* Sommaire des quatre pôles, en rangée sous le titre.
   ⚠ Il était en colonne FIXE à gauche de l'écran (position:fixed, top:50%).
   Sur la capture client, il passait par-dessus les titres de section : un
   élément fixe est hors du flux, donc rien ne lui réserve de place, et
   « Régie / Mobile / Experience / Intelligence » se confondait avec
   « Quatre temps, et une preuve à la fin ». Remis dans le flux, en rangée,
   le chevauchement ne peut plus se produire. */
.sommaire{display:flex;flex-wrap:wrap;gap:8px;margin-top:clamp(28px,4vw,42px)}
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

/* ═══════════════ UN PÔLE ═══════════════
   Structure voulue par le client : accroche → introduction → ce que nous
   faisons → comment nous travaillons → notre différence → appel.
   Un filet supérieur et une étiquette en aplat portent la couleur. Pas de
   halo radial : la charte proscrit les dégradés. */
.pole{
  padding:clamp(58px,7.5vw,100px) var(--pad);
  border-top:1px solid var(--ligne);
  position:relative;overflow:hidden;scroll-margin-top:80px;
}
.pole:nth-of-type(even){background:var(--fond-2)}
.pole__filet{position:absolute;inset:0 0 auto 0;height:5px;background:var(--c)}

.pole__haut{display:grid;grid-template-columns:1.12fr .88fr;gap:clamp(28px,4vw,62px);align-items:center;position:relative;z-index:2}
@media(max-width:940px){.pole__haut{grid-template-columns:1fr;gap:30px}}
.pole__etiq{
  display:inline-flex;align-items:center;gap:9px;
  padding:8px 15px;border-radius:999px;background:var(--c);color:#fff;
  font-family:var(--titre);font-weight:800;font-size:11px;
  letter-spacing:.14em;text-transform:uppercase;
}
.pole__etiq b{font-family:var(--chiffres);opacity:.72}
.pole__t{margin-top:18px}
.pole__intro{margin-top:18px;color:var(--texte-2);max-width:54ch;font-size:16.5px;line-height:1.68}
.pole__ph{aspect-ratio:4/3;border-radius:20px;overflow:hidden}
@media(max-width:940px){.pole__ph{aspect-ratio:16/10}}

/* ── Les deux colonnes de contenu ── */
.pole__bas{display:grid;grid-template-columns:1.22fr .78fr;gap:clamp(28px,4vw,56px);align-items:start;margin-top:clamp(34px,4.5vw,58px);position:relative;z-index:2}
@media(max-width:940px){.pole__bas{grid-template-columns:1fr;gap:32px}}
.bloc-t{
  font-family:var(--titre);font-weight:800;font-size:11.5px;
  letter-spacing:.15em;text-transform:uppercase;color:var(--texte-3);
  padding-bottom:12px;border-bottom:1px solid var(--ligne);
}
.faisons{list-style:none;padding:0;margin:0}
.faisons li{display:grid;grid-template-columns:auto 1fr;gap:3px 14px;padding:15px 0;border-bottom:1px solid var(--ligne)}
.faisons .pt{width:7px;height:7px;border-radius:50%;background:var(--c);margin-top:8px;grid-row:span 2}
.faisons strong{font-family:var(--titre);font-weight:800;font-size:16px;line-height:1.3}
.faisons span{font-size:14.5px;color:var(--texte-2);line-height:1.55}

/* La méthode est une vraie séquence : on la numérote et on la relie. */
.methode{list-style:none;padding:0;margin:14px 0 0;counter-reset:m}
.methode li{counter-increment:m;position:relative;padding:0 0 20px 44px}
.methode li::before{
  content:counter(m);position:absolute;left:0;top:-2px;
  width:28px;height:28px;border-radius:50%;background:var(--c);color:#fff;
  font-family:var(--chiffres);font-weight:800;font-size:12px;
  display:grid;place-items:center;
}
.methode li:not(:last-child)::after{content:"";position:absolute;left:13.5px;top:31px;bottom:4px;width:1.5px;background:var(--ligne)}
.methode strong{display:block;font-family:var(--titre);font-weight:800;font-size:15.5px}
.methode span{display:block;font-size:14px;color:var(--texte-2);margin-top:3px;line-height:1.5}

/* ── Notre différence + appel ── */
.pole__pied{
  margin-top:clamp(28px,3.5vw,44px);position:relative;z-index:2;
  display:grid;grid-template-columns:1fr auto;gap:clamp(20px,3vw,40px);align-items:center;
  padding-top:clamp(22px,3vw,32px);border-top:2px solid var(--c);
}
@media(max-width:760px){.pole__pied{grid-template-columns:1fr;align-items:start}}
.pole__diff{font-family:var(--titre);font-weight:700;font-size:clamp(16px,1.7vw,21px);line-height:1.45;max-width:58ch}
@endpush

@section('contenu')

{{-- ═══════════════════════ TÊTE ═══════════════════════ --}}
<section class="tete">
    <div class="plume" style="--c:var(--rouge);--op:.07;top:-12%;right:-5%;width:clamp(190px,24vw,340px)" data-par="-18" data-rot="12"></div>
    <div class="large">
        <p class="sur" data-rev>Nos expertises</p>
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
</section>

{{-- ═══════════════════════ LES 4 PÔLES ═══════════════════════
     Contenu dans V2Controller::POLES. Les pôles 01 et 02 viennent du
     client ; 03 et 04 sont écrits dans la même structure et restent à
     valider. --}}
@foreach($poles as $pole)
    <section class="pole" id="{{ $pole['id'] }}" data-pole
             style="--c:{{ $pole['couleur'] }};--c-txt:{{ $pole['couleur_texte'] ?? $pole['couleur'] }}">
        <div class="pole__filet"></div>

        <div class="large">
            <div class="pole__haut">
                <div>
                    <span class="pole__etiq" data-rev><b>{{ $pole['num'] }}</b> {!! $pole['nom'] !!}</span>
                    <h2 class="t-grand pole__t" data-rev=".06">{{ $pole['accroche'] }}</h2>
                    <p class="pole__intro" data-rev=".12">{{ $pole['intro'] }}</p>
                </div>
                <div class="pole__ph ph ph--scroll" style="--c:{{ $pole['couleur'] }}" data-rev=".1">
                    <img src="{{ asset('refonte/' . $pole['visuel'] . '.webp') }}"
                         alt="{{ strip_tags($pole['nom']) }}" loading="lazy">
                </div>
            </div>

            <div class="pole__bas">
                <div>
                    <p class="bloc-t">Ce que nous faisons</p>
                    <ul class="faisons" data-cascade>
                        @foreach($pole['faisons'] as [$quoi, $precision])
                            <li>
                                <span class="pt"></span>
                                <strong>{{ $quoi }}</strong>
                                <span>{{ $precision }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <p class="bloc-t">Comment nous travaillons</p>
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

            <div class="pole__pied">
                <p class="pole__diff" data-rev>{{ $pole['difference'] }}</p>
                <a class="bt" href="{{ route('cible.contact') }}" data-viseur data-rev=".06">
                    {{ $pole['cta'] }}<i class="fl"></i>
                </a>
            </div>
        </div>
    </section>
@endforeach

{{-- ═══════════════════════ APPEL FINAL ═══════════════════════ --}}
<section class="bloc" style="text-align:center;position:relative;overflow:hidden;border-top:1px solid var(--ligne)">
    <div class="fleche-d" style="--c:var(--rouge);--op:.1;top:20%;right:9%;width:clamp(80px,10vw,140px)" data-par="-24" data-rot="20"></div>
    <div style="max-width:880px;margin-inline:auto;position:relative;z-index:2">
        <h2 class="t-grand" data-lignes>Lequel de ces quatre leviers vous servirait le mieux&nbsp;?</h2>
        <p class="intro" style="margin:22px auto 0">
            Décrivez-nous votre objectif en quelques lignes. On vous répond avec une
            recommandation, pas avec un catalogue.
        </p>
        <div style="margin-top:32px" data-rev=".1">
            <a class="bt" href="{{ route('cible.contact') }}" data-viseur>
                Recevoir une recommandation média<i class="fl"></i>
            </a>
        </div>
    </div>
</section>

@endsection

@push('js')
<script>
/* Sommaire : marque le pôle traversé. Dans le flux, et non en position
   fixe — la version fixe passait par-dessus les titres de section. */
(function () {
  if (typeof window.gsap === 'undefined') { return; }
  var liens = document.querySelectorAll('#sommaire a');
  var poles = document.querySelectorAll('[data-pole]');
  if (!liens.length || liens.length !== poles.length) { return; }

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
})();
</script>
@endpush
