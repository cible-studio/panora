<?php

namespace Tests\Unit;

use App\Services\AvailabilityService;
use PHPUnit\Framework\TestCase;

/**
 * Disponibilité d'un panneau dans le SÉLECTEUR DU DEVIS, sur la période
 * de campagne saisie — et non sur l'état du jour.
 *
 * ═══ RÈGLE MÉTIER VALIDÉE PAR ÉCRIT — 2026-11-13 ═══
 *   « Quand le panneau est réellement occupé sur la période visée, on
 *     l'affiche occupé mais pas moyen de le sélectionner. Mais s'il est
 *     occupé et sera libre dans la période saisie, on l'affiche et on
 *     indique la date de libération. »
 *
 * Contexte du bug : le devis affichait « OCCUPE » pour CDY-048 sur une
 * campagne de 2027 alors que son occupation s'arrêtait le 22/11/2026 —
 * searchPanels() renvoyait panels.status, le cache de lecture du jour.
 *
 * Tests purement unitaires (méthode statique, aucun accès BDD) : ils
 * tournent même sur un poste à la base vide.
 */
class DevisDisponibilitePeriodeTest extends TestCase
{
    /** Fenêtre de référence pour tous les tests : mars → juin 2027. */
    private const DEBUT = '2027-03-01';
    private const FIN   = '2027-06-30';

    private function verdict(bool $available, ?string $releaseDate, ?string $raw = 'libre'): array
    {
        return AvailabilityService::verdictSelection(
            $raw, true, $available, $releaseDate, self::FIN
        );
    }

    // ══════════════════════════════════════════════════════════════
    // CAS LIBRES — sélectionnables
    // ══════════════════════════════════════════════════════════════

    public function test_aucun_chevauchement_le_panneau_est_libre(): void
    {
        $v = $this->verdict(true, null);

        $this->assertSame('libre', $v['display_status']);
        $this->assertTrue($v['selectable']);
        $this->assertNull($v['libre_le']);
    }

    /**
     * LE BUG D'ORIGINE. Occupation terminée le 22/11/2026, devis sur
     * 2027 : aucun chevauchement, donc getPanelAvailabilityData renvoie
     * available = true. Le panneau DOIT être libre, quel que soit le
     * statut courant « occupe » en base.
     */
    public function test_occupation_terminee_avant_la_periode_le_panneau_est_libre(): void
    {
        $v = AvailabilityService::verdictSelection(
            'occupe',          // panels.status = occupé AUJOURD'HUI
            true,
            true,              // mais aucun chevauchement sur la fenêtre
            null,
            self::FIN
        );

        $this->assertSame('libre', $v['display_status']);
        $this->assertTrue($v['selectable'], "Le statut du jour ne doit jamais bloquer une période future.");
    }

    public function test_occupation_commencant_apres_la_periode_le_panneau_est_libre(): void
    {
        // Campagne qui démarre en septembre 2027 : hors fenêtre.
        $v = $this->verdict(true, null, 'confirme');

        $this->assertSame('libre', $v['display_status']);
        $this->assertTrue($v['selectable']);
    }

    // ══════════════════════════════════════════════════════════════
    // CAS « LIBRE DÈS LE … » — sélectionnable, avec la date
    // ══════════════════════════════════════════════════════════════

    public function test_occupation_qui_se_libere_pendant_la_periode_reste_selectionnable(): void
    {
        // Occupé jusqu'au 15/04/2027, fenêtre jusqu'au 30/06/2027.
        $v = $this->verdict(false, '2027-04-15');

        $this->assertSame('libre_des', $v['display_status']);
        $this->assertTrue($v['selectable']);
        $this->assertSame('2027-04-16', $v['libre_le'], "Libre le LENDEMAIN de la fin d'occupation.");
    }

    public function test_liberation_la_veille_de_la_fin_de_fenetre_reste_selectionnable(): void
    {
        // Un seul jour disponible (le 30/06) : c'est peu, mais c'est libre.
        $v = $this->verdict(false, '2027-06-29');

        $this->assertSame('libre_des', $v['display_status']);
        $this->assertTrue($v['selectable']);
        $this->assertSame('2027-06-30', $v['libre_le']);
    }

    // ══════════════════════════════════════════════════════════════
    // CAS OCCUPÉS — bloqués
    // ══════════════════════════════════════════════════════════════

    public function test_occupation_jusqu_au_dernier_jour_de_la_fenetre_est_bloquee(): void
    {
        // Libre le 01/07, soit APRÈS la fin de fenêtre : rien à vendre.
        $v = $this->verdict(false, '2027-06-30');

        $this->assertSame('occupe', $v['display_status']);
        $this->assertFalse($v['selectable']);
        $this->assertSame('2027-07-01', $v['libre_le'], "La date de libération reste affichée pour informer.");
    }

    public function test_occupation_debordant_la_fenetre_est_bloquee(): void
    {
        $v = $this->verdict(false, '2028-01-31');

        $this->assertSame('occupe', $v['display_status']);
        $this->assertFalse($v['selectable']);
        $this->assertSame('2028-02-01', $v['libre_le']);
    }

    public function test_occupation_sans_date_de_fin_connue_est_bloquee(): void
    {
        $v = $this->verdict(false, null);

        $this->assertSame('occupe', $v['display_status']);
        $this->assertFalse($v['selectable']);
        $this->assertNull($v['libre_le']);
    }

    /**
     * Chevauchement qui n'occupe QUE la fin de la fenêtre (libre au
     * début, pris ensuite). release_date déborde la fenêtre → bloqué.
     * Le devis porte sur toute la période : on ne vend pas un panneau
     * qui sera repris au milieu.
     */
    public function test_panneau_pris_en_cours_de_fenetre_et_au_dela_est_bloque(): void
    {
        $v = $this->verdict(false, '2027-08-15');

        $this->assertSame('occupe', $v['display_status']);
        $this->assertFalse($v['selectable']);
    }

    // ══════════════════════════════════════════════════════════════
    // MAINTENANCE & ABSENCE DE PÉRIODE
    // ══════════════════════════════════════════════════════════════

    public function test_maintenance_bloque_meme_si_aucune_occupation(): void
    {
        $v = AvailabilityService::verdictSelection('maintenance', true, true, null, self::FIN);

        $this->assertSame('maintenance', $v['display_status']);
        $this->assertFalse($v['selectable'], "Un panneau HS terrain n'est pas vendable, période ou pas.");
    }

    public function test_maintenance_bloque_meme_sans_periode_saisie(): void
    {
        $v = AvailabilityService::verdictSelection('maintenance', false, true, null, null);

        $this->assertSame('maintenance', $v['display_status']);
        $this->assertFalse($v['selectable']);
    }

    /**
     * Sans dates, on ne peut rien affirmer : on n'interdit donc rien,
     * sinon le commercial ne pourrait plus rien saisir tant qu'il n'a
     * pas la période définitive du client.
     */
    public function test_sans_periode_rien_n_est_bloque(): void
    {
        $v = AvailabilityService::verdictSelection('occupe', false, false, '2027-04-15', null);

        $this->assertSame('sans_periode', $v['display_status']);
        $this->assertTrue($v['selectable']);
    }

    public function test_periode_incomplete_ne_bloque_pas(): void
    {
        // hasPeriod = true mais periodEnd manquant : on ne devine pas.
        $v = AvailabilityService::verdictSelection('libre', true, false, '2027-04-15', null);

        $this->assertSame('sans_periode', $v['display_status']);
        $this->assertTrue($v['selectable']);
    }

    public function test_statut_null_est_traite_comme_libre(): void
    {
        $v = AvailabilityService::verdictSelection(null, true, true, null, self::FIN);

        $this->assertSame('libre', $v['display_status']);
        $this->assertTrue($v['selectable']);
    }

    // ══════════════════════════════════════════════════════════════
    // SENTINELLES — la période part bien du formulaire au serveur
    // ══════════════════════════════════════════════════════════════

    private const FORM_DEVIS = __DIR__ . '/../../resources/views/admin/quotes/partials/_form.blade.php';

    public function test_le_formulaire_devis_envoie_la_periode_au_serveur(): void
    {
        $js = file_get_contents(self::FORM_DEVIS);

        $this->assertStringContainsString('period_start: p.start', $js,
            "Le Select2 panneau doit envoyer la période du devis.");
        $this->assertStringContainsString('period_end:   p.end', $js);
    }

    /**
     * Le cache Select2 resservirait les badges calculés pour l'ANCIENNE
     * période après un changement de dates — c'est précisément le bug
     * qu'on corrige, en pire car invisible.
     */
    public function test_le_select2_panneau_ne_met_pas_les_resultats_en_cache(): void
    {
        $js = file_get_contents(self::FORM_DEVIS);

        $this->assertStringContainsString('cache: false', $js);
        $this->assertStringNotContainsString('cache: true', $js,
            "Un cache ici fige les statuts de l'ancienne période.");
    }

    public function test_searchPanels_delegue_la_regle_a_AvailabilityService(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Admin/QuoteController.php');

        $this->assertStringContainsString('verdictSelection(', $src,
            "La règle ne doit jamais être recopiée dans le contrôleur.");
        $this->assertStringContainsString('getPanelAvailabilityData(', $src);
        $this->assertStringContainsString('refuserPanneauxOccupes', $src,
            "Garde-fou serveur : l'UI seule ne suffit pas.");
    }

    public function test_l_inventaire_annote_la_disponibilite_sur_la_periode(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Admin/PanelController.php');

        $this->assertStringContainsString('verdictSelection(', $src);
        $this->assertStringContainsString('periodeDemandee(', $src);
    }

    // ══════════════════════════════════════════════════════════════
    // PÉRIODE OBLIGATOIRE DÈS QU'UN PANNEAU EST ENGAGÉ (2026-11-13)
    // ══════════════════════════════════════════════════════════════
    // Les dates inventées par QuoteConverter n'alimentaient pas que le
    // contrôle de disponibilité : elles devenaient start_date/end_date de
    // la réservation FERME. Et le client accepte seul, depuis son espace
    // ou le lien public. Trois verrous : saisie, envoi, conversion.

    public function test_la_saisie_exige_la_periode_quand_un_panneau_est_engage(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Admin/QuoteController.php');

        $this->assertStringContainsString('$aUnPanneau', $src);
        $this->assertStringContainsString("'period_start' => \"Renseigne la période de campagne", $src,
            "La validation doit refuser un devis à panneaux sans dates.");
    }

    public function test_l_envoi_exige_la_periode_quand_un_panneau_est_engage(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Admin/QuoteController.php');

        $this->assertStringContainsString('$engagePanneaux', $src,
            "send() doit rattraper les devis créés avant la règle.");
    }

    /**
     * Le cœur du correctif : plus aucune date inventée dès qu'un panneau
     * est engagé. Le fallback ne survit que pour un devis de services
     * seuls, qui ne bloque aucun emplacement.
     */
    public function test_le_convertisseur_n_invente_plus_de_periode_avec_des_panneaux(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Services/QuoteConverter.php');

        $this->assertStringContainsString('periode_absente', $src,
            "La conversion doit refuser explicitement, pas inventer.");
        $this->assertStringContainsString('$engagePanneaux = !empty($panelIds) || !empty($extPanelIds);', $src);

        // Le refus doit précéder le fallback, sinon il ne sert à rien.
        $posRefus    = strpos($src, "return ['status' => 'conflict', 'motif' => 'periode_absente'");
        $posFallback = strpos($src, '$periodStart = $quote->period_start?->toDateString() ?? now()');
        $this->assertIsInt($posRefus);
        $this->assertIsInt($posFallback);
        $this->assertLessThan($posFallback, $posRefus,
            "Le refus doit être évalué AVANT le fallback des dates inventées.");
    }

    public function test_les_deux_espaces_client_distinguent_periode_absente_et_conflit(): void
    {
        foreach ([
            'app/Http/Controllers/Client/ClientQuoteController.php',
            'app/Http/Controllers/PublicQuoteController.php',
        ] as $fichier) {
            $src = file_get_contents(__DIR__ . '/../../' . $fichier);
            $this->assertStringContainsString("periode_absente", $src,
                "$fichier doit distinguer « dates à préciser » de « panneau indisponible ».");
        }
    }

    public function test_le_formulaire_bloque_l_enregistrement_sans_periode_avec_panneaux(): void
    {
        $js = file_get_contents(self::FORM_DEVIS);

        $this->assertStringContainsString('devisEngageDesPanneaux', $js);
        $this->assertStringContainsString('Période obligatoire', $js);
    }
}
