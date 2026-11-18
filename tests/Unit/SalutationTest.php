<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientContact;
use App\Support\Salutation;
use Tests\TestCase;

/**
 * Salutation des mails au client (règle de la direction du 2026-10-29,
 * complétée le 2026-11-18 pour tous les mails) :
 *   · un vrai nom d'interlocuteur → « Awa Koné (ORANGE CI) » ;
 *   · pas d'interlocuteur, ou un « nom » qui n'en est pas un (« contact »,
 *     « audreyasseke », « Comptabilité RCG ») → le client seul.
 */
class SalutationTest extends TestCase
{
    private function client(string $nom): Client
    {
        $c = new Client(['name' => $nom]);
        $c->id = 7;

        return $c;
    }

    public function test_un_vrai_nom_avec_le_client(): void
    {
        $this->assertSame('Awa Koné (ORANGE CI)', Salutation::nom($this->client('ORANGE CI'), 'Awa Koné'));
        $this->assertSame('Koffi (ADDOHA)', Salutation::nom($this->client('ADDOHA'), 'Koffi', 'koffi@addoha.ci'));
    }

    public function test_le_client_seul_quand_le_nom_n_en_est_pas_un(): void
    {
        foreach ([
            ['MANOOVA', 'contact', 'contact@manoova.io'],
            ['ASSEKE ORO', 'audreyasseke', null],
            ['LE CABINET WM', 'esta.fatokun', null],
            ['RCG', 'Comptabilité RCG', 'comptabilite@agencercg.com'],
            ['LIGHT MEDIA', 'Direction LIGHT MEDIA', null],
            ['PRODIGE COM', 'Prodigecom', null],
            ['ORANGE CI', 'orange ci', null],
            ['ORANGE CI', '', null],
            ['ORANGE CI', null, null],
        ] as [$societe, $nom, $email]) {
            $this->assertSame($societe, Salutation::nom($this->client($societe), $nom, $email), "« {$nom} » n'est pas une personne");
        }
        $this->assertSame('Madame, Monsieur', Salutation::nom(null, 'contact'));
    }

    public function test_les_mails_de_suivi_passent_par_la_regle(): void
    {
        $client = $this->client('MANOOVA');
        $faux = new ClientContact(['name' => 'contact', 'email' => 'contact@manoova.io', 'client_id' => 7]);
        $faux->id = 3;
        $vrai = new ClientContact(['name' => 'Lucas RAOILY', 'email' => 'lucas@manoova.io', 'client_id' => 7]);
        $vrai->id = 4;

        $this->assertSame('MANOOVA', Salutation::suivi($client, $faux));
        $this->assertSame('Lucas RAOILY (MANOOVA)', Salutation::suivi($client, $vrai));
    }

    /**
     * Garde-fou : chaque mail du groupe validé le 18/11 salue par
     * Salutation (et non par un nom recopié à la main).
     */
    public function test_chaque_mail_de_suivi_salue_par_salutation(): void
    {
        $vues = [
            'emails/campaign-started.blade.php',
            'emails/client-campaign-started.blade.php',
            'emails/plain/client-campaign-started.blade.php',
            'emails/campaign-ended.blade.php',
            'emails/plain/campaign-ended.blade.php',
            'emails/campaign-decapped.blade.php',
            'emails/client-maintenance.blade.php',
            'emails/satisfaction-survey.blade.php',
            'emails/plain/satisfaction-survey.blade.php',
            'emails/barometre.blade.php',
            'emails/plain/barometre.blade.php',
        ];
        foreach ($vues as $vue) {
            $this->assertStringContainsString('Salutation::', file_get_contents(resource_path('views/' . $vue)), $vue);
        }
        // J-7, renouvellement, accusé : le champ [Nom du client] des modèles.
        $this->assertStringContainsString("'[Nom du client]'      => \\App\\Support\\Salutation::nom(",
            file_get_contents(app_path('Mail/MailClientCampagne.php')));
    }
}
