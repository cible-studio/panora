<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * 2026-11-26 — Depuis le 24/11, CampaignService::addPanels() passait
 * $entreeLe à l'historique sans le capturer dans sa fonction interne :
 * « Undefined variable $entreeLe », donc erreur 500 à chaque ajout de
 * panneau, duplication ou « Renouveler la campagne ». Le test du 24/11 ne
 * lisait que le contrôleur. Vérifié en réel sur une copie MySQL : ajout
 * accepté, date d'entrée inscrite dans l'historique.
 */
class AjoutPanneauCampagneTest extends TestCase
{
    public function test_la_date_d_entree_est_transmise_a_l_historique(): void
    {
        $source = file_get_contents(app_path('Services/CampaignService.php'));
        $methode = substr($source, strpos($source, 'public function addPanels('));
        $methode = substr($methode, 0, strpos($methode, 'public function', 10));

        $this->assertMatchesRegularExpression('/DB::transaction\(function \(\) use \([^)]*\$entreeLe[^)]*\)/', $methode,
            'la fonction interne doit recevoir $entreeLe');
        $this->assertStringContainsString('null, $entreeLe', $methode);
    }
}
