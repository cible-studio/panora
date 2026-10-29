<?php

namespace Tests\Unit;

use App\Models\DiffusionEnvoi;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Deux catalogues par envoi — Abidjan et l'intérieur du pays (2026-10-29).
 *
 * Règle validée : le mail client porte un lien par zone. Une zone sans
 * aucun emplacement disponible n'envoie NI lien NI nombre, pour que le
 * modèle Brevo masque tout son bloc plutôt que d'afficher un bouton vers
 * une adresse vide.
 *
 * Le catalogue complet (params.LIEN_PDF) reste fourni : il sert au mail
 * interne, au journal, et de filet de sécurité tant qu'un modèle Brevo
 * encore en ligne s'en sert.
 */
class DiffusionCataloguesZonesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-29 10:00:00');

        Schema::create('diffusion_envois', function (Blueprint $t) {
            $t->id(); $t->date('creneau'); $t->date('periode_debut'); $t->date('periode_fin'); $t->string('mode', 10);
            $t->unsignedBigInteger('declenche_par')->nullable(); $t->string('statut', 12)->default('prepare');
            $t->unsignedInteger('nb_destinataires')->default(0); $t->unsignedInteger('nb_panneaux')->default(0);
            $t->unsignedInteger('nb_panneaux_abidjan')->default(0); $t->unsignedInteger('nb_panneaux_interieur')->default(0);
            $t->string('pdf_path')->nullable(); $t->string('pdf_token')->nullable();
            $t->string('pdf_path_abidjan')->nullable(); $t->string('pdf_token_abidjan')->nullable();
            $t->string('pdf_path_interieur')->nullable(); $t->string('pdf_token_interieur')->nullable();
            $t->string('brevo_campaign_id')->nullable();
            $t->text('erreur')->nullable(); $t->timestamp('envoye_at')->nullable(); $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function envoi(array $attributs = []): DiffusionEnvoi
    {
        return DiffusionEnvoi::create($attributs + [
            'creneau'               => '2026-11-01',
            'periode_debut'         => '2026-11-01',
            'periode_fin'           => '2026-11-30',
            'mode'                  => DiffusionEnvoi::MODE_AUTO,
            'statut'                => DiffusionEnvoi::STATUT_PREPARE,
            'nb_panneaux'           => 60,
            'nb_panneaux_abidjan'   => 48,
            'nb_panneaux_interieur' => 12,
            'pdf_path'              => 'diffusions/complet.pdf',
            'pdf_token'             => str_repeat('a', 48),
            'pdf_path_abidjan'      => 'diffusions/abidjan.pdf',
            'pdf_token_abidjan'     => str_repeat('b', 48),
            'pdf_path_interieur'    => 'diffusions/interieur.pdf',
            'pdf_token_interieur'   => str_repeat('c', 48),
        ]);
    }

    /** contenuCampagne() est privée : c'est le contrat avec Brevo qu'on vérifie. */
    private function params(DiffusionEnvoi $envoi): array
    {
        $service = app(DiffusionDisponibilitesService::class);
        $methode = new \ReflectionMethod($service, 'contenuCampagne');
        $methode->setAccessible(true);

        $payload = $methode->invoke($service, $envoi, 3);

        return $payload['params'] ?? [];
    }

    public function test_les_deux_liens_et_les_deux_nombres_partent_a_brevo(): void
    {
        config(['brevo.template_id' => 14]);
        $params = $this->params($this->envoi());

        $this->assertSame(48, $params['NB_ABIDJAN']);
        $this->assertSame(12, $params['NB_INTERIEUR']);
        $this->assertSame(60, $params['NB_PANNEAUX'], 'Le total reste fourni.');

        $this->assertStringEndsWith(str_repeat('b', 48), $params['LIEN_ABIDJAN']);
        $this->assertStringEndsWith(str_repeat('c', 48), $params['LIEN_INTERIEUR']);

        // Trois jetons distincts : Brevo compte les clics par zone.
        $this->assertCount(3, array_unique([
            $params['LIEN_PDF'], $params['LIEN_ABIDJAN'], $params['LIEN_INTERIEUR'],
        ]));
    }

    public function test_une_zone_sans_emplacement_n_envoie_ni_lien_ni_nombre(): void
    {
        config(['brevo.template_id' => 14]);
        $params = $this->params($this->envoi([
            'nb_panneaux'           => 48,
            'nb_panneaux_interieur' => 0,
            'pdf_path_interieur'    => null,
            'pdf_token_interieur'   => null,
        ]));

        $this->assertArrayNotHasKey('LIEN_INTERIEUR', $params);
        $this->assertArrayNotHasKey('NB_INTERIEUR', $params);
        $this->assertArrayHasKey('LIEN_ABIDJAN', $params);
    }

    public function test_le_catalogue_complet_reste_fourni_pour_les_modeles_pas_encore_remplaces(): void
    {
        config(['brevo.template_id' => 14]);
        $params = $this->params($this->envoi());

        $this->assertNotNull($params['LIEN_PDF'],
            'Sans LIEN_PDF, le bouton d\'un modèle Brevo encore en ligne partirait vers une adresse vide.');
        $this->assertStringEndsWith(str_repeat('a', 48), $params['LIEN_PDF']);
    }

    public function test_un_envoi_anterieur_n_a_pas_de_lien_de_zone(): void
    {
        // Envoi parti avant le 2026-10-29 : seul le catalogue complet existe.
        $envoi = $this->envoi([
            'nb_panneaux_abidjan'   => 0,
            'nb_panneaux_interieur' => 0,
            'pdf_path_abidjan'      => null,
            'pdf_token_abidjan'     => null,
            'pdf_path_interieur'    => null,
            'pdf_token_interieur'   => null,
        ]);

        $this->assertNull($envoi->lienPdfZone(DiffusionEnvoi::ZONE_ABIDJAN));
        $this->assertNull($envoi->lienPdfZone(DiffusionEnvoi::ZONE_INTERIEUR));
        $this->assertNotNull($envoi->lienPdf(), 'Son lien historique continue de marcher.');
        $this->assertSame(['complet' => 'diffusions/complet.pdf'], $envoi->cheminsCatalogues());
    }

    public function test_le_gabarit_de_secours_affiche_un_bouton_par_zone(): void
    {
        // Sans modèle Brevo configuré, Panora envoie son propre HTML.
        config(['brevo.template_id' => null]);
        $envoi = $this->envoi();

        $service = app(DiffusionDisponibilitesService::class);
        $methode = new \ReflectionMethod($service, 'contenuCampagne');
        $methode->setAccessible(true);
        $html = $methode->invoke($service, $envoi, 3)['htmlContent'];

        $this->assertStringContainsString('Disponibilités — Abidjan (48)', $html);
        $this->assertStringContainsString('Disponibilités — Intérieur du pays (12)', $html);
        $this->assertStringContainsString($envoi->lienPdfZone(DiffusionEnvoi::ZONE_ABIDJAN), $html);
        $this->assertStringContainsString($envoi->lienPdfZone(DiffusionEnvoi::ZONE_INTERIEUR), $html);
    }
}
