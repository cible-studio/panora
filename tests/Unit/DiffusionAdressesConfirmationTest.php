<?php

namespace Tests\Unit;

use App\Services\Diffusion\DiffusionDisponibilitesService;
use PHPUnit\Framework\TestCase;

/**
 * Adresses qui reçoivent la confirmation interne de chaque envoi
 * (DIFFUSION_CONFIRMATION_EMAIL). Depuis 2026-10-01 : plusieurs adresses
 * possibles, pour que studio@ (compte admin) la reçoive avec commercial@.
 */
class DiffusionAdressesConfirmationTest extends TestCase
{
    public function test_une_seule_adresse(): void
    {
        $this->assertSame(
            ['commercial@cible-ci.com'],
            DiffusionDisponibilitesService::adresses('commercial@cible-ci.com')
        );
    }

    public function test_plusieurs_adresses_separees_par_des_virgules(): void
    {
        $this->assertSame(
            ['commercial@cible-ci.com', 'studio@cible-ci.com'],
            DiffusionDisponibilitesService::adresses('commercial@cible-ci.com, studio@cible-ci.com')
        );
    }

    public function test_points_virgules_majuscules_et_doublons(): void
    {
        $this->assertSame(
            ['commercial@cible-ci.com', 'studio@cible-ci.com'],
            DiffusionDisponibilitesService::adresses('Commercial@cible-ci.com;studio@cible-ci.com ; commercial@cible-ci.com')
        );
    }

    public function test_guillemets_gardes_par_coolify(): void
    {
        $this->assertSame(
            ['commercial@cible-ci.com', 'studio@cible-ci.com'],
            DiffusionDisponibilitesService::adresses('"commercial@cible-ci.com,studio@cible-ci.com"')
        );
    }

    public function test_adresses_invalides_ou_vide_ignorees(): void
    {
        $this->assertSame(['studio@cible-ci.com'], DiffusionDisponibilitesService::adresses('pas-une-adresse, studio@cible-ci.com'));
        $this->assertSame([], DiffusionDisponibilitesService::adresses(null));
        $this->assertSame([], DiffusionDisponibilitesService::adresses(''));
    }
}
