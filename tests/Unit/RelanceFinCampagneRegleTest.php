<?php

namespace Tests\Unit;

use App\Services\Diffusion\RelanceFinCampagneService;
use Tests\TestCase;

/**
 * Mail « Votre campagne se termine » (J-7) : qui le reçoit (2026-10-21).
 *
 * Règles validées : pas de mail si le client a une autre campagne
 * planifiée, ou active qui continue après celle-ci ; deux campagnes qui
 * finissent le même jour reçoivent chacune le leur ; pas de mail aux
 * adresses exclues ; sans adresse, le mail est retenté le lendemain.
 */
class RelanceFinCampagneRegleTest extends TestCase
{
    private const FIN = '2026-10-28';
    private const AUJOURDHUI = '2026-10-21';

    private function regle(array $autres = [], string $email = 'awa@client.ci', ?string $exclusion = null): ?array
    {
        return RelanceFinCampagneService::exclusion($email, self::FIN, $autres, $exclusion, self::AUJOURDHUI);
    }

    private function campagne(string $statut, ?string $fin, string $nom = 'Autre'): array
    {
        return ['nom' => $nom, 'statut' => $statut, 'fin' => $fin];
    }

    public function test_un_client_sans_autre_campagne_recoit_le_mail(): void
    {
        $this->assertNull($this->regle());
    }

    public function test_une_campagne_planifiee_exclut(): void
    {
        [$statut, $motif] = $this->regle([$this->campagne('planifie', '2027-01-31', 'Noël')]);

        $this->assertSame('exclu', $statut);
        $this->assertStringContainsString('planifiée', $motif);
        $this->assertStringContainsString('Noël', $motif);
    }

    public function test_une_campagne_planifiee_dont_la_fin_est_passee_n_exclut_pas(): void
    {
        // Statut pas encore resynchronisé : ce n'est plus vraiment « planifié ».
        $this->assertNull($this->regle([$this->campagne('planifie', '2026-10-01')]));
    }

    public function test_une_campagne_active_qui_continue_apres_exclut(): void
    {
        [$statut, $motif] = $this->regle([$this->campagne('actif', '2026-12-31', 'Fêtes')]);

        $this->assertSame('exclu', $statut);
        $this->assertStringContainsString('continue', $motif);
        $this->assertStringContainsString('31 décembre 2026', $motif);
    }

    public function test_deux_campagnes_qui_finissent_le_meme_jour_recoivent_chacune_le_mail(): void
    {
        $this->assertNull($this->regle([$this->campagne('actif', self::FIN)]));
    }

    public function test_une_campagne_active_qui_finit_avant_n_exclut_pas(): void
    {
        $this->assertNull($this->regle([$this->campagne('actif', '2026-10-24')]));
    }

    public function test_les_campagnes_terminees_annulees_ou_en_pause_n_excluent_pas(): void
    {
        // Le service ne passe que les actives et planifiées ; la règle les
        // ignore de toute façon.
        $this->assertNull($this->regle([
            $this->campagne('termine', '2027-01-31'),
            $this->campagne('annule', '2027-01-31'),
            $this->campagne('pause', '2027-01-31'),
        ]));
    }

    public function test_sans_adresse_valide_le_mail_est_a_retenter(): void
    {
        [$statut, $motif] = $this->regle([], '');
        $this->assertSame('echec', $statut);
        $this->assertStringContainsString('Aucune adresse', $motif);

        $this->assertSame('echec', $this->regle([], 'pas-une-adresse')[0]);
    }

    public function test_une_adresse_exclue_ne_recoit_pas_le_mail(): void
    {
        [$statut, $motif] = $this->regle([], 'awa@client.ci', 'Désinscrit');

        $this->assertSame('exclu', $statut);
        $this->assertStringContainsString('Désinscrit', $motif);
    }

    public function test_l_autre_campagne_prime_sur_l_adresse_manquante(): void
    {
        // Exclusion définitive plutôt qu'un « à retenter » qui reviendrait
        // chaque jour pour rien.
        $this->assertSame('exclu', $this->regle([$this->campagne('planifie', '2027-01-31')], '')[0]);
    }
}
