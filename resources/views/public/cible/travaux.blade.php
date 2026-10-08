@extends('public.cible._coque', ['titre' => 'Nos travaux', 'actuelle' => 'travaux'])

@push('css')
.tete{padding:clamp(130px,17vh,200px) var(--pad) clamp(44px,6vw,76px);position:relative;overflow:hidden}
.tete .sur{--c:var(--jaune)}
.tete__t{margin-top:20px}
.tete__t em{font-style:normal;color:var(--jaune)}

/* Grille de travaux : première carte en pleine largeur, le reste en
   deux colonnes. La hiérarchie dit quoi regarder d'abord. */
.grille{display:grid;grid-template-columns:1fr 1fr;gap:clamp(18px,2.6vw,38px)}
@media(max-width:860px){.grille{grid-template-columns:1fr}}
.oeuvre{display:block;position:relative}
.oeuvre--large{grid-column:1 / -1}
.oeuvre__ph{aspect-ratio:4/3;border-radius:20px;overflow:hidden}
.oeuvre--large .oeuvre__ph{aspect-ratio:21/9}
@media(max-width:860px){.oeuvre--large .oeuvre__ph{aspect-ratio:4/3}}
.oeuvre__tete{display:flex;align-items:baseline;gap:14px;margin-top:20px}
.oeuvre__client{
  font-family:var(--titre);font-weight:800;font-size:11px;
  letter-spacing:.17em;text-transform:uppercase;color:var(--rouge);
}
.oeuvre__nom{
  margin-top:10px;font-family:var(--titre);font-weight:900;
  font-size:clamp(23px,2.7vw,38px);line-height:1.08;letter-spacing:-.028em;
  text-transform:uppercase;max-width:22ch;
}
.oeuvre__cat{
  font-family:var(--titre);font-weight:700;font-size:10.5px;letter-spacing:.11em;text-transform:uppercase;
  color:var(--texte-3);margin-left:auto;text-align:right;max-width:52%;line-height:1.5;
}
.oeuvre__txt{margin-top:11px;font-size:15px;color:var(--texte-2);line-height:1.6;max-width:62ch}
.oeuvre__serv{
  margin-top:14px;font-family:var(--titre);font-weight:700;font-size:12.5px;
  color:var(--texte-3);line-height:1.6;
}

/* Filtres : agissent réellement, par attribut data. Pas de rechargement. */
.filtres{display:flex;flex-wrap:wrap;gap:9px;margin-top:clamp(28px,4vw,44px)}
.filtres button{
  font-family:var(--titre);font-weight:700;font-size:13px;
  padding:10px 18px;border-radius:999px;cursor:pointer;
  background:transparent;color:var(--texte-2);
  border:0;box-shadow:inset 0 0 0 1.5px var(--ligne);
  transition:background .3s,color .3s,box-shadow .3s;
}
.filtres button:hover{color:var(--noir);background:rgba(17,17,17,.05)}
.filtres button[aria-pressed="true"]{background:var(--noir);color:#fff;box-shadow:none}
.oeuvre[hidden]{display:none!important}

/* Clients : les logos sont fournis en PNG sur fond clair — on les pose
   donc sur une pastille claire plutôt que de les forcer en blanc, ce qui
   détruirait les logos bicolores. */
.clients{background:var(--fond-2);border-block:1px solid var(--ligne)}
.clients__grille{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-top:clamp(32px,4vw,52px)}
@media(max-width:1000px){.clients__grille{grid-template-columns:repeat(4,1fr)}}
@media(max-width:620px){.clients__grille{grid-template-columns:repeat(3,1fr)}}
.client{
  aspect-ratio:3/2;border-radius:13px;background:var(--blanc);
  display:grid;place-items:center;padding:16px;
  filter:grayscale(1);opacity:.72;
  transition:filter .45s,opacity .45s,transform .45s var(--ease);
}
.client:hover{filter:none;opacity:1;transform:translateY(-4px)}
.client img{max-width:100%;max-height:100%;object-fit:contain}
@endpush

@section('contenu')

{{-- ═══════════════════════ TÊTE ═══════════════════════ --}}
<section class="tete">
    <div class="plume" style="--c:var(--jaune);--op:.07;top:-8%;right:-6%;width:clamp(220px,28vw,400px)" data-par="-16" data-rot="-12"></div>
    <div class="large">
        <p class="sur" data-rev>Nos travaux</p>
        <h1 class="t-geant tete__t" data-lignes>Six campagnes. <em>Et la preuve de chacune.</em></h1>
        <p class="intro" style="margin-top:24px" data-rev=".12">
            Des marques institutionnelles, bancaires et de grande consommation. Pour
            chacune, le même engagement : une recommandation justifiée, puis la photo
            horodatée de ce qui a été posé.
        </p>

        <div class="filtres" id="filtres" data-rev=".18">
            <button type="button" data-f="tous" aria-pressed="true">Tout</button>
            @php
                $tousFiltres = collect($realisations)->pluck('filtres')->flatten()->unique()->filter()->values();
                $libelles = [
                    'affichage'        => 'Affichage',
                    'brand-experience' => 'Brand experience',
                    'street-marketing' => 'Street marketing',
                    'digital'          => 'Digital',
                    'audiovisuel'      => 'Audiovisuel',
                    'institutionnel'   => 'Institutionnel',
                ];
            @endphp
            @foreach($tousFiltres as $f)
                <button type="button" data-f="{{ $f }}" aria-pressed="false">
                    {{ $libelles[$f] ?? ucfirst(str_replace('-', ' ', $f)) }}
                </button>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════ LES 6 TRAVAUX ═══════════════════════ --}}
<section class="bloc bloc--serre" style="padding-top:0">
    <div class="large">
        <div class="grille" id="grille">
            @foreach($realisations as $slug => $p)
                <article class="oeuvre @if($loop->first) oeuvre--large @endif"
                         id="{{ $slug }}"
                         style="--c:{{ $p['couleur'] ?? 'var(--rouge)' }}"
                         data-filtres="{{ implode(' ', $p['filtres'] ?? []) }}"
                         data-rev="{{ min($loop->index * 0.05, 0.25) }}">
                    <div class="oeuvre__ph ph" style="--c:{{ $p['couleur'] ?? 'var(--rouge)' }}">
                        <img src="{{ \App\Support\Contenu::urlImage($p['image'] ?? 'images/cible/campagne-1.jpg') }}"
                             alt="Campagne {{ $p['nom'] ?? $slug }}" loading="lazy">
                    </div>
                    {{-- Client en capitales puis titre court, à la manière de la
                         galerie McCann. Le titre descriptif d'origine et le
                         paragraphe suivent : sur la page dédiée aux travaux, le
                         visiteur a choisi de lire. --}}
                    <div class="oeuvre__tete">
                        <span class="oeuvre__client">{{ $p['nom'] ?? $slug }}</span>
                        <span class="oeuvre__cat">{{ $p['cat'] ?? '' }}</span>
                    </div>
                    <h2 class="oeuvre__nom">{{ $p['titre_court'] ?? ($p['titre'] ?? '') }}</h2>
                    <p class="oeuvre__txt">{{ $p['texte'] ?? '' }}</p>
                    @if(!empty($p['services']))
                        <p class="oeuvre__serv">{{ $p['services'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>

        <p id="vide" class="intro" style="margin-top:34px;display:none">
            Aucune campagne sur ce filtre pour l'instant.
        </p>
    </div>
</section>

{{-- ═══════════════════════ CLIENTS ═══════════════════════ --}}
<section class="bloc clients">
    <div class="large">
        <div class="entete">
            <p class="sur" style="--c:var(--rouge)">Ils nous font confiance</p>
            <h2 class="t-grand" data-lignes>Des marques qui ne laissent rien au hasard.</h2>
        </div>
        <div class="clients__grille" data-cascade>
            @foreach([
                'danone' => 'Danone', 'moov' => 'Moov', 'sipra' => 'Sipra',
                'bgfibank' => 'BGFIBank', 'banque-atlantique' => 'Banque Atlantique', 'rimco' => 'Rimco',
                'autre-1' => 'Client', 'autre-2' => 'Client', 'autre-3' => 'Client',
                'autre-4' => 'Client', 'autre-5' => 'Client', 'autre-6' => 'Client',
            ] as $f => $nom)
                <div class="client">
                    <img src="{{ asset('refonte/client/' . $f . '.png') }}" alt="{{ $nom }}" loading="lazy">
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════ APPEL ═══════════════════════ --}}
<section class="bloc" style="text-align:center;position:relative;overflow:hidden">
    <div class="plume" style="--c:var(--rouge);--op:.08;bottom:-8%;left:3%;width:clamp(170px,21vw,300px)" data-par="12" data-rot="-14"></div>
    <div style="max-width:900px;margin-inline:auto;position:relative;z-index:2">
        <p class="sur" style="--c:var(--jaune)">À vous</p>
        <h2 class="t-geant" style="margin-top:18px" data-lignes>La prochaine, c'est la vôtre.</h2>
        <div style="margin-top:34px" data-rev=".1">
            <a class="bt" href="{{ route('cible.contact') }}" data-viseur>
                Parler de mon projet<i class="fl"></i>
            </a>
        </div>
    </div>
</section>

@endsection

@push('js')
<script>
/* Filtrage des travaux. Sans JS, toutes les campagnes restent visibles :
   les boutons ne masquent rien tant qu'ils n'ont pas été câblés ici. */
(function () {
  var barre  = document.getElementById('filtres');
  var oeuvres = Array.prototype.slice.call(document.querySelectorAll('.oeuvre'));
  var vide   = document.getElementById('vide');
  if (!barre || !oeuvres.length) { return; }

  barre.addEventListener('click', function (e) {
    var bouton = e.target.closest('button[data-f]');
    if (!bouton) { return; }

    barre.querySelectorAll('button').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b === bouton));
    });

    var f = bouton.dataset.f;
    var visibles = 0;

    oeuvres.forEach(function (o) {
      var ok = f === 'tous' || (' ' + o.dataset.filtres + ' ').indexOf(' ' + f + ' ') !== -1;
      o.hidden = !ok;
      if (ok) { visibles++; }
      // La première carte ne garde sa pleine largeur que si elle est
      // encore la première affichée, sinon la grille se troue.
      o.classList.remove('oeuvre--large');
    });

    var premier = oeuvres.find(function (o) { return !o.hidden; });
    if (premier && f === 'tous') { premier.classList.add('oeuvre--large'); }

    vide.style.display = visibles ? 'none' : 'block';

    if (typeof ScrollTrigger !== 'undefined') { ScrollTrigger.refresh(); }
  });
})();
</script>
@endpush
