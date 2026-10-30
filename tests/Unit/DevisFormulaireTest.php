<?php

namespace Tests\Unit;

use App\Models\QuoteService;
use App\Services\InvoiceCalculator;
use Tests\TestCase;

/**
 * Optimisations du module Devis (2026-10-30).
 *
 * Quatre demandes de la direction :
 *   1. TVA au choix sur chaque service annexe, comme sur la facture ;
 *   2. numéro attribué par Panora, titre proposé mais modifiable ;
 *   3. recherche de panneaux identique à celle de l'inventaire ;
 *   4. récapitulatif en direct, taxes détaillées, et un formulaire qui
 *      reste lisible quand les colonnes ne tiennent plus.
 *
 * Le point qui compte le plus ici : le récapitulatif doit être calculé
 * par le SERVEUR. Recopier les formules en JavaScript ferait diverger ce
 * que le commercial voit de ce qui sera réellement facturé.
 */
class DevisFormulaireTest extends TestCase
{
    // ── 1. TVA au choix sur les services ───────────────────────────

    public function test_un_service_sans_tva_vaut_son_prix_ht(): void
    {
        $service = new QuoteService(['prix_ht' => 50000, 'tva_applicable' => false]);

        $this->assertSame(50000, $service->montantTtc(18));
    }

    public function test_un_service_avec_tva_la_porte(): void
    {
        $service = new QuoteService(['prix_ht' => 50003, 'tva_applicable' => true]);

        // 50 003 × 1,18 = 59 003,54 → 59 004, comme sur la facture.
        $this->assertSame(59004, $service->montantTtc(18));
    }

    public function test_le_devis_transmet_le_choix_au_calculateur(): void
    {
        // InvoiceCalculator sait traiter tva_applicable depuis le
        // 2026-08-03 ; le devis se contentait de ne pas le lui passer.
        $source = file_get_contents(base_path('app/Services/QuoteBuilder.php'));

        $this->assertStringContainsString("'tva_applicable' => (bool) \$s->tva_applicable", $source);
    }

    public function test_les_deux_choix_donnent_des_totaux_differents(): void
    {
        $calculateur = app(InvoiceCalculator::class);
        $ligne = [[
            'pu_ht_mensuel' => 100000, 'quantite' => 1, 'duree_mois' => 1,
            'dimension_m2' => 12, 'odp_rate_applique' => 0, 'tm_rate_applique' => 0,
        ]];

        $avec = $calculateur->calculateInvoice($ligne, [
            'services' => [['label' => 'Impression', 'prix_ht' => 50000, 'tva_applicable' => true]],
        ]);
        $sans = $calculateur->calculateInvoice($ligne, [
            'services' => [['label' => 'Impression', 'prix_ht' => 50000, 'tva_applicable' => false]],
        ]);

        $this->assertSame(59000, $avec['services_ttc_total']);
        $this->assertSame(50000, $sans['services_ttc_total']);
        $this->assertGreaterThan($sans['total_a_payer'], $avec['total_a_payer']);
    }

    // ── 2. Numéro et titre ─────────────────────────────────────────

    public function test_le_numero_est_affiche_mais_pas_saisissable(): void
    {
        $source = file_get_contents(base_path('resources/views/admin/quotes/partials/_form.blade.php'));

        $this->assertStringContainsString('Numéro du devis', $source);
        // Aucun champ de saisie pour la référence : un numéro tapé à la
        // main créerait des doublons.
        $this->assertStringNotContainsString('name="reference"', $source);
    }

    public function test_le_titre_est_propose_puis_laisse_libre(): void
    {
        $source = file_get_contents(base_path('resources/views/admin/quotes/partials/_form.blade.php'));

        $this->assertStringContainsString('data-auto=', $source);
        $this->assertStringContainsString('proposerTitre', $source);
        // Dès qu'on écrit dedans, Panora n'y touche plus.
        $this->assertStringContainsString("titre.dataset.auto = '0'", $source);
    }

    // ── 3. Recherche de panneaux ───────────────────────────────────

    public function test_la_recherche_du_devis_est_celle_de_l_inventaire(): void
    {
        foreach ([
            'app/Http/Controllers/Admin/QuoteController.php',
            'app/Http/Controllers/Admin/PanelController.php',
        ] as $fichier) {
            $this->assertStringContainsString(
                'RecherchePanneaux',
                file_get_contents(base_path($fichier)),
                "{$fichier} doit passer par le support partagé."
            );
        }

        // Et le LIKE d'avant a disparu de searchPanels. On vise cette
        // méthode seule : la recherche de la LISTE des devis (référence
        // ou titre) garde son LIKE, c'est un autre sujet.
        $source = file_get_contents(base_path('app/Http/Controllers/Admin/QuoteController.php'));
        $methode = substr($source, strpos($source, 'public function searchPanels'));
        $methode = substr($methode, 0, strpos($methode, 'public function edit'));

        $this->assertStringNotContainsString("'like'", $methode,
            'searchPanels doit passer par RecherchePanneaux, plus par un LIKE.');
    }

    // ── 4. Récapitulatif calculé par le serveur ────────────────────

    public function test_le_recapitulatif_ne_recopie_aucune_formule_en_javascript(): void
    {
        $source = file_get_contents(base_path('resources/views/admin/quotes/partials/_form.blade.php'));

        // Le JS ne fait qu'appeler la route et afficher ce qu'elle rend.
        $this->assertStringContainsString('admin.quotes.totaux', $source);
        $this->assertStringContainsString('chargerTotaux', $source);

        // Aucune des formules du calculateur ne doit apparaître côté client.
        foreach (['1.18', '* 1.18', 'odp_rate', 'tm_rate', '/ 100 * 18'] as $formule) {
            $this->assertStringNotContainsString($formule, $source,
                "La formule « {$formule} » ne doit pas être recopiée en JavaScript.");
        }
    }

    public function test_le_recapitulatif_ignore_les_reponses_depassees(): void
    {
        // Deux frappes rapprochées : seule la dernière réponse compte,
        // sinon le récapitulatif peut revenir à un état antérieur.
        $source = file_get_contents(base_path('resources/views/admin/quotes/partials/_form.blade.php'));

        $this->assertStringContainsString('demandeEnCours', $source);
        $this->assertStringContainsString('if (mien !== demandeEnCours) return;', $source);
    }

    public function test_un_recapitulatif_en_panne_ne_bloque_pas_la_saisie(): void
    {
        $source = file_get_contents(base_path('resources/views/admin/quotes/partials/_form.blade.php'));

        $this->assertMatchesRegularExpression('/catch\s*\(e\)\s*\{[^}]*console\.warn/s', $source);
    }

    public function test_la_route_des_totaux_existe_et_n_enregistre_rien(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.quotes.totaux'));

        $source = file_get_contents(base_path('app/Http/Controllers/Admin/QuoteController.php'));
        $apercu = substr($source, strpos($source, 'public function totaux'));
        $apercu = substr($apercu, 0, strpos($apercu, 'protected function syncServices'));

        foreach (['->save(', '->create(', '->update(', '->delete('] as $ecriture) {
            $this->assertStringNotContainsString($ecriture, $apercu,
                "Le récapitulatif ne doit rien enregistrer ({$ecriture}).");
        }
    }

    // ── Affichage : les défauts signalés le 2026-10-30 ─────────────

    public function test_le_total_a_payer_reste_visible_avec_beaucoup_de_services(): void
    {
        // Seul le CORPS du récapitulatif défile. Avant, toute la colonne
        // défilait : dès trois services, la barre du total sortait de
        // l'écran et on ne voyait plus le montant.
        $source = $this->formulaire();

        $this->assertMatchesRegularExpression(
            '/\.qf-recap-corps\s*\{[^}]*overflow-y:\s*auto/s',
            $source,
            'Le corps du récapitulatif doit défiler seul.'
        );
        $this->assertMatchesRegularExpression(
            '/\.qf-recap-total\s*\{[^}]*flex-shrink:\s*0/s',
            $source,
            'La barre du total ne doit jamais être comprimée ni masquée.'
        );
    }

    public function test_les_taxes_sont_detaillees_une_par_une(): void
    {
        // « Taxes (TSP · TM · ODP) » en une seule ligne ne disait pas ce
        // que couvrait le montant.
        $source = $this->formulaire();

        foreach (['qf-ligne-tsp', 'qf-ligne-tm', 'qf-ligne-odp'] as $ligne) {
            $this->assertStringContainsString($ligne, $source, "Ligne de taxe manquante : {$ligne}.");
        }
        $this->assertStringContainsString('Taxe municipale', $source);
        $this->assertStringContainsString('Occupation du domaine public', $source);
    }

    public function test_les_tableaux_defilent_horizontalement(): void
    {
        // Sans cela, les colonnes de droite (m², PU, Qté, Mois) étaient
        // écrasées puis coupées dès que la fenêtre rétrécissait.
        $source = $this->formulaire();

        $this->assertMatchesRegularExpression('/\.ql-defilement\s*\{[^}]*overflow-x:\s*auto/s', $source);
        $this->assertMatchesRegularExpression('/\.ql-table\s*\{[^}]*min-width:\s*\d+px/s', $source);
        $this->assertStringNotContainsString('<div style="overflow:visible">', $source,
            'Le tableau des lignes ne doit plus être dans un conteneur sans défilement.');
    }

    public function test_les_champs_chiffres_sont_assez_larges_pour_etre_lus(): void
    {
        // Un tarif à six chiffres était tronqué dans la colonne PU.
        $source = $this->formulaire();

        foreach (['ql-col-m2', 'ql-col-pu', 'ql-col-qte', 'ql-col-mois'] as $colonne) {
            $this->assertStringContainsString(".{$colonne}", $source, "Largeur non définie : {$colonne}.");
        }
        // Les flèches natives des champs nombre mangeaient la place utile.
        $this->assertStringContainsString('-webkit-inner-spin-button', $source);
    }

    public function test_la_colonne_de_droite_repasse_dessous_sur_ecran_etroit(): void
    {
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 1280px\)\s*\{\s*\.qf-colonnes\s*\{\s*grid-template-columns:\s*1fr/s',
            $this->formulaire()
        );
    }

    public function test_la_grille_laisse_le_tableau_defiler(): void
    {
        // minmax(0,1fr) : sans lui, une colonne de grille prend la largeur
        // de son contenu le plus large, et le tableau pousse toute la page
        // au lieu de défiler dans son cadre.
        $this->assertStringContainsString('grid-template-columns:minmax(0,1fr)', $this->formulaire());
    }

    private function formulaire(): string
    {
        return file_get_contents(base_path('resources/views/admin/quotes/partials/_form.blade.php'));
    }
}
