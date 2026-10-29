{{--
    Aide derrière une icône ⓘ (2026-10-29) : au clic, une bulle avec un
    titre et un texte, comme sur les grands sites. Se ferme au clic
    ailleurs, avec Échap, au défilement ou au redimensionnement.
    Plusieurs aides par page : une seule bulle ouverte à la fois.

    Usage : <x-aide titre="Interlocuteurs">Texte d'aide…</x-aide>
    La bulle est positionnée par rapport à la fenêtre : elle n'est pas
    coupée par un conteneur en overflow:hidden (colonnes, cartes).
--}}
@props(['titre' => null])
@php $idBulle = 'aide-' . \Illuminate\Support\Str::random(10); @endphp
<span class="px-aide" {{ $attributes }}>
    <button type="button" class="px-aide-btn" data-aide-btn
            aria-expanded="false" aria-controls="{{ $idBulle }}"
            aria-label="{{ $titre ? 'Aide : ' . $titre : 'Aide' }}">ⓘ</button>
    <span id="{{ $idBulle }}" class="px-aide-bulle" role="dialog" aria-label="{{ $titre ?? 'Aide' }}" hidden>
        @if($titre)<strong class="px-aide-titre">{{ $titre }}</strong>@endif
        <span class="px-aide-texte">{{ $slot }}</span>
    </span>
</span>

@once
<style>
    .px-aide { display: inline-flex; vertical-align: middle; }
    .px-aide-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 18px; height: 18px; padding: 0; border: 0; border-radius: 50%;
        background: transparent; color: var(--text3); font-size: 13px; line-height: 1;
        cursor: pointer; text-transform: none; letter-spacing: 0; font-weight: 400;
    }
    .px-aide-btn:hover, .px-aide-btn[aria-expanded="true"] { color: var(--accent); background: var(--surface2); }
    .px-aide-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }
    .px-aide-bulle {
        position: fixed; z-index: 10000; width: max-content; max-width: 280px;
        padding: 10px 12px; border-radius: 10px;
        background: var(--surface); color: var(--text2); border: 1px solid var(--border);
        box-shadow: 0 10px 30px rgba(0,0,0,.18);
        font-size: 12px; line-height: 1.5; font-weight: 400;
        text-transform: none; letter-spacing: 0; text-align: left; white-space: normal;
    }
    .px-aide-bulle[hidden] { display: none; }
    .px-aide-titre { display: block; margin-bottom: 4px; color: var(--text); font-size: 12.5px; font-weight: 700; }
    .px-aide-texte { display: block; }
</style>
<script>
(function () {
    var ouverte = null;

    function fermer() {
        if (!ouverte) return;
        ouverte.bulle.hidden = true;
        ouverte.bouton.setAttribute('aria-expanded', 'false');
        ouverte = null;
    }

    function ouvrir(bouton) {
        var bulle = document.getElementById(bouton.getAttribute('aria-controls'));
        if (!bulle) return;
        bulle.hidden = false;
        var r = bouton.getBoundingClientRect();
        var largeur = bulle.offsetWidth, hauteur = bulle.offsetHeight;
        var gauche = Math.min(Math.max(8, r.left - 8), window.innerWidth - largeur - 8);
        var haut = r.bottom + 6;
        if (haut + hauteur > window.innerHeight - 8) haut = Math.max(8, r.top - hauteur - 6); // pas de place dessous
        bulle.style.left = gauche + 'px';
        bulle.style.top = haut + 'px';
        bouton.setAttribute('aria-expanded', 'true');
        ouverte = { bouton: bouton, bulle: bulle };
    }

    document.addEventListener('click', function (e) {
        var bouton = e.target.closest('[data-aide-btn]');
        if (bouton) {
            // Dans un <summary> : ne pas plier / déplier la section.
            e.preventDefault();
            e.stopPropagation();
            var memeBulle = ouverte && ouverte.bouton === bouton;
            fermer();
            if (!memeBulle) ouvrir(bouton);
            return;
        }
        if (ouverte && !ouverte.bulle.contains(e.target)) fermer();
    }, true);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && ouverte) { var b = ouverte.bouton; fermer(); b.focus(); }
    });
    window.addEventListener('resize', fermer);
    window.addEventListener('scroll', fermer, true);
})();
</script>
@endonce
