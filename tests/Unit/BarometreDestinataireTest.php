<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Baromètre clients — à qui part le mail (règle validée le 2026-11-17,
 * mesurée en prod le 16/11 : principal 11, dernier dossier 17, dernier
 * échange 115, fiche 1, aucun e-mail 21, aucun cas ambigu).
 * Client::destinataireBarometre() est la source unique : sans base.
 */
class BarometreDestinataireTest extends TestCase
{
    private function client(array $contacts, array $attributs = []): Client
    {
        $client = new Client(['name' => 'ADDOHA'] + $attributs);
        $client->id = 7;
        $liste = [];
        foreach ($contacts as $i => $c) {
            $ct = new ClientContact($c + ['client_id' => 7, 'is_primary' => false]);
            $ct->id = $c['id'] ?? $i + 1;
            if (isset($c['dernier_echange'])) {
                $ct->dernier_echange = Carbon::parse($c['dernier_echange']);
            }
            $liste[] = $ct;
        }
        $client->setRelation('contacts', collect($liste));

        return $client;
    }

    public function test_le_principal_passe_avant_tout(): void
    {
        $c = $this->client([
            ['id' => 1, 'name' => 'Awa', 'email' => 'awa@addoha.ci', 'dernier_echange' => '2026-11-10'],
            ['id' => 2, 'name' => 'Koffi', 'email' => 'K.Kouadjo@Addoha.ci ', 'is_primary' => true],
        ]);

        $d = $c->destinataireBarometre(1);
        $this->assertSame(['email' => 'k.kouadjo@addoha.ci', 'contact' => 'Koffi', 'contact_id' => 2, 'source' => 'principal'], $d);
    }

    public function test_puis_l_interlocuteur_du_dernier_dossier(): void
    {
        $c = $this->client([
            ['id' => 1, 'name' => 'Awa', 'email' => 'awa@addoha.ci', 'dernier_echange' => '2026-11-10'],
            ['id' => 2, 'name' => 'Koffi', 'email' => 'koffi@addoha.ci', 'dernier_echange' => '2025-01-01'],
        ]);

        $this->assertSame('dossier', $c->destinataireBarometre(2)['source']);
        $this->assertSame('koffi@addoha.ci', $c->destinataireBarometre(2)['email']);
        // Interlocuteur du dossier sans adresse utilisable : on passe à la suite.
        $this->assertSame('echange', $c->destinataireBarometre(99)['source']);
    }

    public function test_puis_l_echange_le_plus_recent(): void
    {
        $c = $this->client([
            ['id' => 1, 'name' => 'Ancien', 'email' => 'ancien@addoha.ci', 'dernier_echange' => '2024-03-01'],
            ['id' => 2, 'name' => 'Récent', 'email' => 'recent@addoha.ci', 'dernier_echange' => '2026-11-02'],
            ['id' => 3, 'name' => 'Sans date', 'email' => 'sansdate@addoha.ci'],
        ]);

        $this->assertSame(['email' => 'recent@addoha.ci', 'contact' => 'Récent', 'contact_id' => 2, 'source' => 'echange'], $c->destinataireBarometre());
    }

    public function test_adresses_de_la_regie_et_ecartees_jamais_utilisees(): void
    {
        $c = $this->client([
            ['id' => 1, 'name' => 'Tanella (CIBLE)', 'email' => 'tanella@cible-ci.com', 'is_primary' => true],
            ['id' => 2, 'name' => 'Désinscrit', 'email' => 'parti@addoha.ci', 'dernier_echange' => '2026-11-15'],
            ['id' => 3, 'name' => 'Koffi', 'email' => 'koffi@addoha.ci', 'dernier_echange' => '2026-01-01'],
        ]);

        $d = $c->destinataireBarometre(null, ['parti@addoha.ci' => true]);
        $this->assertSame('koffi@addoha.ci', $d['email'], 'ni l\'adresse interne (même principale), ni l\'adresse écartée');
        $this->assertSame('echange', $d['source']);
    }

    public function test_un_seul_interlocuteur_ou_plusieurs_sans_indice(): void
    {
        $seul = $this->client([['name' => 'Koffi', 'email' => 'koffi@addoha.ci'], ['name' => 'Sans adresse', 'email' => null]]);
        $this->assertSame('seul', $seul->destinataireBarometre()['source']);

        $deux = $this->client([['name' => 'A', 'email' => 'a@addoha.ci'], ['name' => 'B', 'email' => 'b@addoha.ci']]);
        $this->assertSame(['email' => null, 'contact' => null, 'contact_id' => null, 'source' => 'a_choisir'], $deux->destinataireBarometre());
    }

    public function test_sans_interlocuteur_la_fiche_puis_rien(): void
    {
        $fiche = $this->client([['name' => 'Sans adresse', 'email' => '']], ['email' => 'Contact@Addoha.ci', 'contact_name' => 'Accueil']);
        $this->assertSame(['email' => 'contact@addoha.ci', 'contact' => 'Accueil', 'contact_id' => null, 'source' => 'fiche'], $fiche->destinataireBarometre());

        $interne = $this->client([], ['email' => 'studio@cible-ci.com']);
        $this->assertSame('aucun', $interne->destinataireBarometre()['source'], 'l\'adresse de la fiche peut être une adresse de la régie');

        $this->assertSame('aucun', $this->client([])->destinataireBarometre()['source']);
    }
}
