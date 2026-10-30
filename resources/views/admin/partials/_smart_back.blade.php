{{-- ════ Bouton retour intelligent — partial mutualisé ════
     2026-06-18 (feedback patronne) : navigation croisée entre pages.
     2026-10-29 : cascade complète. Avant, le bouton n'apparaissait que si
     l'adresse portait ?back=<clé> ; on pouvait donc arriver sur un écran
     (les factures depuis « CA HT équipe », par exemple) sans aucun moyen
     de revenir. Il se déduit maintenant de la page précédente.

     L'ORDRE, du plus sûr au plus souple :

       1. ?back=<clé> connue        → « ‹ Performance commerciale »
          Explicite, posé par le lien d'origine. Liste blanche STRICTE :
          aucune redirection hors du site n'est possible.

       2. la page précédente        → « ‹ Retour »
          Seulement si elle est interne au site, différente de la page
          courante, et si l'on n'est pas sur une page du menu latéral —
          on y arrive normalement par le menu, un retour n'aurait pas de
          sens. Laravel garde cette adresse en session et n'y compte pas
          les requêtes AJAX : les filtres de liste ne la polluent donc pas.

       3. le repli fourni par la vue → « ‹ Devis »
          Pour les écrans qui avaient un retour écrit en dur : il reste la
          destination par défaut quand on arrive sans provenance connue.

       4. rien du tout.

     Utilisation :
       @include('admin.partials._smart_back')
       @include('admin.partials._smart_back', ['defaut' => [
           'url' => route('admin.quotes.index'), 'label' => 'Devis',
       ]])

     Le layout l'appelle de lui-même quand la vue ne déclare pas de
     topbarLeft : aucun écran n'est laissé sans issue.

     Pour poser une provenance explicite sur un lien :
       route('admin.invoices.index', ['back' => 'performance.commercial'])
--}}
@php
    /* Carte des provenances et liste des pages du menu : SOURCE UNIQUE
       dans config/navigation.php. Elle est lue aussi par les vues
       teams/create|edit et par PoseTeamController::redirectBack — avant
       le 2026-10-29, chacun en gardait sa propre copie. */
    $backMap = config('navigation.retours', []);
    $routesDuMenu = config('navigation.pages_du_menu', []);

    $lien = null;
    $libelle = null;
    $infobulle = null;

    // ── 1. Provenance explicite ───────────────────────────────────
    $backCfg = $backMap[(string) request()->query('back', '')] ?? null;
    if ($backCfg) {
        // route() peut échouer si la route n'existe pas pour le rôle
        // courant : on tombe silencieusement sur l'étape suivante.
        try {
            $lien = route($backCfg['route'], $backCfg['params'] ?? []);
            $libelle = $backCfg['label'];
            $infobulle = 'Retour à ' . $backCfg['label'];
        } catch (\Throwable) {
            $lien = null;
        }
    }

    // ── 2. Page précédente ────────────────────────────────────────
    if (!$lien) {
        $precedente = (string) url()->previous();
        $racine = rtrim(url('/'), '/');

        // Faute de page précédente, Laravel renvoie l'accueil du site —
        // y compris quand on arrive par un favori, un lien collé ou un
        // rechargement. Sans ce garde-fou, chaque écran aurait affiché un
        // « Retour » vers la racine, qui ne veut rien dire ici.
        if (rtrim($precedente, '/') === $racine) {
            $precedente = '';
        }

        // Le séparateur est obligatoire : sans lui, « https://panora.ci »
        // laisserait passer « https://panora.ci.piege.test/… », qui
        // commence bien par la racine mais n'est pas le même site.
        $estInterne = $precedente !== '' && str_starts_with($precedente, $racine . '/');
        // Comparaison sans les paramètres : passer d'un filtre à l'autre
        // sur le même écran ne doit pas proposer de « revenir » à soi-même.
        $estUneAutrePage = $estInterne && strtok($precedente, '?') !== url()->current();
        $surUnePageDuMenu = in_array(request()->route()?->getName(), $routesDuMenu, true);

        if ($estInterne && $estUneAutrePage && !$surUnePageDuMenu) {
            $lien = $precedente;
            $libelle = 'Retour';
            $infobulle = 'Revenir à la page précédente';
        }
    }

    // ── 3. Repli déclaré par la vue ───────────────────────────────
    if (!$lien && !empty($defaut['url'])) {
        $lien = $defaut['url'];
        $libelle = $defaut['label'] ?? 'Retour';
        $infobulle = 'Retour à ' . $libelle;
    }
@endphp

@if($lien)
    <a href="{{ $lien }}"
       class="btn btn-ghost btn-sm"
       style="display:inline-flex;align-items:center;gap:6px"
       title="{{ $infobulle }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        {{ $libelle }}
    </a>
@endif
