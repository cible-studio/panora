<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pas d'inscription libre (2026-11-11) : /register donnait à n'importe qui
 * un compte Commercial actif. Seul un administrateur crée les comptes.
 */
class InscriptionFermeeTest extends TestCase
{
    public function test_la_page_d_inscription_n_existe_plus(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
    }

    public function test_on_ne_peut_pas_s_inscrire_en_envoyant_le_formulaire(): void
    {
        $this->post('/register', [
            'name'                  => 'Inconnu',
            'email'                 => 'inconnu@example.com',
            'password'              => 'MotDePasse123',
            'password_confirmation' => 'MotDePasse123',
        ])->assertNotFound();

        $this->assertGuest();
    }

    public function test_la_connexion_reste_ouverte(): void
    {
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('password.request'));
    }
}
