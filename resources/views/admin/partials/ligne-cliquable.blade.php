{{--
    Ligne de liste cliquable — SOURCE UNIQUE (2026-10-29).

    Demande : ouvrir le détail depuis n'importe où dans la ligne, sans
    devoir viser le bouton 👁️.

    Usage, en deux temps :
      1. sur la ligne du tableau, l'adresse du détail
         <tr data-ligne-url="{{ route('admin.invoices.show', $invoice) }}">
      2. une fois dans l'écran de liste
         @include('admin.partials.ligne-cliquable')

    Pourquoi un écouteur posé sur le document et non sur chaque ligne :
    les listes rechargent leur <tbody> en AJAX à chaque filtre
    (invoices/index.blade.php : tableBody.innerHTML = data.html). Des
    écouteurs attachés aux lignes seraient perdus au premier filtre.
    La délégation, elle, couvre aussi les lignes arrivées après.

    Ce qui NE déclenche jamais la navigation :
      · un clic sur une zone déjà interactive (lien client, bouton
        d'action, formulaire, case à cocher, bouton « voir les
        relances »…) — elle garde son rôle ;
      · un clic qui termine une sélection de texte — on copie un montant
        sans être emmené ailleurs ;
      · tout élément marqué data-ligne-ignore.

    Ctrl/⌘ + clic et clic milieu ouvrent dans un nouvel onglet, comme sur
    un lien ordinaire.
--}}
@once
<style>
    [data-ligne-url] { cursor: pointer; }

    /* Le survol du tableau est volontairement très discret (app.css,
       section TABLEAU) et invisible en thème clair. Une ligne cliquable
       doit se voir : teinte de surface, lisible dans les deux thèmes. */
    tr[data-ligne-url]:hover td { background: var(--surface2); }

    /* Listes en cartes (piges, messages) : la carte porte déjà son fond, et
       parfois sa propre animation de survol (.pige-card se soulève). On se
       contente d'accentuer la bordure — ni transform ni ombre, pour ne rien
       écraser : les deux effets se cumulent proprement. */
    [data-ligne-url]:not(tr) { transition: border-color .12s; }
    [data-ligne-url]:not(tr):hover { border-color: var(--accent); }

    /* La colonne de sélection multiple reste neutre : on y vise une case
       à cocher, jamais le détail. */
    [data-ligne-url] [data-ligne-ignore] { cursor: default; }

    /* Le lien principal de la ligne (référence, nom) : un vrai lien, pour
       le clavier et le clic droit, mais sans soulignement imposé. */
    [data-ligne-url] a[data-ligne-principal] { text-decoration: none; }
    [data-ligne-url] a[data-ligne-principal]:hover { text-decoration: underline; }

    /* Confort : pas d'animation pour qui en demande moins (app.css,
       section ACCESSIBILITÉ). */
    @media (prefers-reduced-motion: reduce) {
        [data-ligne-url]:not(tr) { transition: none; }
    }
</style>
<script>
(function () {
    'use strict';

    // Tout ce qui réagit déjà au clic par lui-même.
    var ZONES_INTERACTIVES = 'a, button, input, select, textarea, label, form,'
        + ' summary, details, [role="button"], [onclick], [data-ligne-ignore]';

    function adresseDeLaLigne(evenement) {
        // Ligne de tableau ou carte : tout élément qui porte l'adresse.
        var ligne = evenement.target.closest('[data-ligne-url]');
        if (!ligne) return null;
        if (evenement.target.closest(ZONES_INTERACTIVES)) return null;
        return ligne.getAttribute('data-ligne-url') || null;
    }

    function duTexteEstSelectionne() {
        var selection = window.getSelection && window.getSelection();
        return !!(selection && selection.toString().trim().length);
    }

    document.addEventListener('click', function (evenement) {
        if (evenement.button !== 0 || evenement.defaultPrevented) return;

        var adresse = adresseDeLaLigne(evenement);
        if (!adresse || duTexteEstSelectionne()) return;

        if (evenement.ctrlKey || evenement.metaKey || evenement.shiftKey) {
            window.open(adresse, '_blank', 'noopener');
        } else {
            window.location.assign(adresse);
        }
    });

    // Clic milieu : nouvel onglet, comme sur un lien.
    document.addEventListener('auxclick', function (evenement) {
        if (evenement.button !== 1) return;

        var adresse = adresseDeLaLigne(evenement);
        if (!adresse) return;

        evenement.preventDefault();
        window.open(adresse, '_blank', 'noopener');
    });
})();
</script>
@endonce
