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
 *   4. aperçu du devis en direct, à côté du récapitulatif.
 *
 * Le point qui compte le plus ici : l'aperçu doit être calculé par le
 * SERVEUR. Recopier les formules en JavaScript ferait diverger ce que le
 * commercial voit de ce qui sera réellement facturé.
 */
class DevisApercuTest extends TestCase
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

    // ── 4. Aperçu calculé par le serveur ───────────────────────────

    public function test_l_apercu_ne_recopie_aucune_formule_en_javascript(): void
    {
        $source = file_get_contents(base_path('resources/views/admin/quotes/partials/_form.blade.php'));

        // Le JS ne fait qu'appeler la route et afficher ce qu'elle rend.
        $this->assertStringContainsString('admin.quotes.apercu', $source);
        $this->assertStringContainsString('chargerApercu', $source);

        // Aucune des formules du calculateur ne doit apparaître côté client.
        foreach (['1.18', '* 1.18', 'odp_rate', 'tm_rate', '/ 100 * 18'] as $formule) {
            $this->assertStringNotContainsString($formule, $source,
                "La formule « {$formule} » ne doit pas être recopiée en JavaScript.");
        }
    }

    public function test_l_apercu_ignore_les_reponses_depassees(): void
    {
        // Deux frappes rapprochées : seule la dernière réponse compte,
        // sinon l'aperçu peut afficher un état antérieur.
        $source = file_get_contents(base_path('resources/views/admin/quotes/partials/_form.blade.php'));

        $this->assertStringContainsString('demandeEnCours', $source);
        $this->assertStringContainsString('if (mien !== demandeEnCours) return;', $source);
    }

    public function test_un_apercu_en_panne_ne_bloque_pas_la_saisie(): void
    {
        $source = file_get_contents(base_path('resources/views/admin/quotes/partials/_form.blade.php'));

        $this->assertMatchesRegularExpression('/catch\s*\(e\)\s*\{[^}]*console\.warn/s', $source);
    }

    public function test_la_route_d_apercu_existe_et_n_enregistre_rien(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.quotes.apercu'));

        $source = file_get_contents(base_path('app/Http/Controllers/Admin/QuoteController.php'));
        $apercu = substr($source, strpos($source, 'public function apercu'));
        $apercu = substr($apercu, 0, strpos($apercu, 'protected function syncServices'));

        foreach (['->save(', '->create(', '->update(', '->delete('] as $ecriture) {
            $this->assertStringNotContainsString($ecriture, $apercu,
                "L'aperçu ne doit rien enregistrer ({$ecriture}).");
        }
    }
}
