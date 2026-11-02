<?php

namespace Tests\Unit;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fraîcheur des adresses de diffusion (décision du 2026-11-02) : une adresse
 * dont le dernier mail échangé est antérieur au 01/01/2025 ne reçoit plus le
 * catalogue. Date inconnue = reçoit. Si TOUS les interlocuteurs clients d'une
 * entreprise sont anciens, elle ne reçoit rien (pas de repli sur la fiche).
 *
 * Base : ORANGE CI (client 10), fiche contact@orange.ci, Awa Koné cliente.
 */
class DiffusionFraicheurAdressesTest extends TestCase
{
    use Concerns\BaseRenouvellement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerBaseRenouvellement();
        config(['diffusion.dernier_echange_min' => '2025-01-01']);

        DB::table('client_contacts')->insert([
            'client_id' => 10, 'name' => 'Jean Kouassi', 'email' => 'jean@orange.ci', 'statut' => 'client', 'receives_notifications' => false,
        ]);
    }

    private function adresses(int $id = 10): array
    {
        return array_column(Client::with('contacts')->findOrFail($id)->destinatairesDiffusion(), 'email');
    }

    private function dater(string $email, ?string $date): void
    {
        DB::table('client_contacts')->where('email', $email)->update(['dernier_echange' => $date]);
    }

    public function test_seules_les_adresses_recentes_ou_sans_date_recoivent(): void
    {
        $this->dater('awa@orange.ci', '2023-05-02');
        $this->dater('jean@orange.ci', '2026-01-10');
        $this->assertSame(['jean@orange.ci'], $this->adresses());

        // Sans date connue : l'adresse reçoit.
        $this->dater('awa@orange.ci', null);
        $this->assertEqualsCanonicalizing(['awa@orange.ci', 'jean@orange.ci'], $this->adresses());

        // Le 1er janvier 2025 lui-même compte comme récent.
        $this->dater('awa@orange.ci', '2025-01-01');
        $this->assertContains('awa@orange.ci', $this->adresses());
    }

    public function test_tous_anciens_l_entreprise_ne_recoit_rien(): void
    {
        $this->dater('awa@orange.ci', '2022-09-22');
        $this->dater('jean@orange.ci', '2024-12-31');

        // Pas de repli sur l'adresse de la fiche (contact@orange.ci).
        $this->assertSame([], $this->adresses());
    }

    public function test_sans_interlocuteur_la_fiche_suit_la_meme_regle(): void
    {
        DB::table('client_contacts')->where('client_id', 10)->delete();
        $this->assertSame(['contact@orange.ci'], $this->adresses());

        DB::table('clients')->where('id', 10)->update(['email_dernier_echange' => '2023-01-15']);
        $this->assertSame([], $this->adresses());
    }

    public function test_regle_desactivee_sans_date_limite(): void
    {
        config(['diffusion.dernier_echange_min' => null]);
        $this->dater('awa@orange.ci', '2022-09-22');
        $this->dater('jean@orange.ci', '2022-09-22');

        $this->assertEqualsCanonicalizing(['awa@orange.ci', 'jean@orange.ci'], $this->adresses());
    }
}
