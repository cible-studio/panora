<?php

namespace Tests\Unit;

use App\Mail\CampaignEndingSoonMail;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\DiffusionDesinscription;
use App\Services\Diffusion\RelanceFinCampagneService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Mail « Votre campagne se termine » (J-7) : destinataire, contenu,
 * requête Brevo, lien de désinscription (2026-10-21). Sans base de données.
 */
class RelanceFinCampagneMailTest extends TestCase
{
    private function client(array $contacts, ?string $email = 'contact@orange.ci', ?string $contactName = null): Client
    {
        $client = new Client(['name' => 'ORANGE CI', 'email' => $email, 'contact_name' => $contactName]);
        $client->setRelation('contacts', collect(array_map(fn($c) => new ClientContact($c), $contacts)));

        return $client;
    }

    private function mail(?string $noteTest = null, array $copie = ['commercial@cible-ci.com']): CampaignEndingSoonMail
    {
        $campagne = new Campaign(['name' => 'Rentrée & Fêtes', 'end_date' => '2026-11-01']);
        $campagne->id = 42;
        $campagne->setRelation('client', $this->client([['name' => 'Awa Koné', 'email' => 'awa@orange.ci', 'is_primary' => true]]));

        return new CampaignEndingSoonMail(
            $campagne, 7, 'Awa Koné', DiffusionDesinscription::lien('awa@orange.ci'),
            'k.yao@cible-ci.com', $copie, $noteTest
        );
    }

    // ── Destinataire (règle commune avec la diffusion des disponibilités) ──

    public function test_le_destinataire_est_le_premier_interlocuteur_avec_une_adresse_valide(): void
    {
        $client = $this->client([
            ['name' => 'Sans mail', 'email' => '', 'is_primary' => true],
            ['name' => 'Awa Koné', 'email' => 'Awa.Kone@Orange.ci', 'is_primary' => false],
        ]);

        $this->assertSame(['email' => 'awa.kone@orange.ci', 'contact' => 'Awa Koné'], $client->destinataireCommercial());
    }

    public function test_sans_interlocuteur_on_ecrit_a_l_adresse_de_la_fiche(): void
    {
        $client = $this->client([], 'Direction@Orange.ci', 'M. Yao');

        $this->assertSame(['email' => 'direction@orange.ci', 'contact' => 'M. Yao'], $client->destinataireCommercial());
    }

    public function test_sans_aucune_adresse_le_destinataire_est_vide(): void
    {
        $this->assertSame('', $this->client([], null)->destinataireCommercial()['email']);
    }

    // ── Contenu ──

    public function test_l_objet_annonce_la_date_de_fin_sans_emoji(): void
    {
        $this->assertSame('Votre campagne « Rentrée & Fêtes » se termine le 1er novembre 2026', $this->mail()->sujet());
        $this->assertStringStartsWith('[TEST] ', $this->mail('note')->sujet());
    }

    public function test_dates_et_echeances_en_toutes_lettres(): void
    {
        $this->assertSame('1er novembre 2026', CampaignEndingSoonMail::dateEnLettres(Carbon::parse('2026-11-01')));
        $this->assertSame('31 octobre', CampaignEndingSoonMail::dateEnLettres(Carbon::parse('2026-10-31'), false));
        $this->assertSame('dans 7 jours', CampaignEndingSoonMail::echeance(7));
        $this->assertSame('demain', CampaignEndingSoonMail::echeance(1));
    }

    public function test_le_mail_est_leger_et_sans_offre_de_fidelite(): void
    {
        $html = $this->mail()->render();

        $this->assertStringContainsString('Bonjour Awa Koné', $html);
        $this->assertStringContainsString('répondre à ce mail', $html);
        $this->assertStringContainsString('Ne plus recevoir nos messages commerciaux', $html);
        $this->assertStringNotContainsStringIgnoringCase('fidélité', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('Mail de test', $html);
        $this->assertStringContainsString('Mail de test', $this->mail('En production…')->render());
    }

    // ── Requête Brevo ──

    public function test_requete_brevo_production(): void
    {
        $p = RelanceFinCampagneService::payloadBrevo($this->mail(), 'awa@orange.ci');

        $this->assertSame([['email' => 'awa@orange.ci', 'name' => 'Awa Koné']], $p['to']);
        $this->assertSame([['email' => 'commercial@cible-ci.com']], $p['cc']);
        $this->assertSame(['email' => 'k.yao@cible-ci.com'], $p['replyTo']);
        $this->assertSame('List-Unsubscribe=One-Click', $p['headers']['List-Unsubscribe-Post']);
        $this->assertStringContainsString('/desinscription/', $p['headers']['List-Unsubscribe']);
        $this->assertStringContainsString('Bonjour Awa Koné', $p['textContent']);
        $this->assertStringNotContainsString('&amp;', $p['textContent']);
    }

    public function test_requete_brevo_test_sans_copie(): void
    {
        $p = RelanceFinCampagneService::payloadBrevo($this->mail('note', []), 'studio@cible-ci.com');

        $this->assertArrayNotHasKey('cc', $p);
    }

    // ── Lien de désinscription ──

    public function test_le_lien_de_desinscription_porte_l_adresse_et_refuse_toute_alteration(): void
    {
        $lien = DiffusionDesinscription::lien('Awa@Orange.ci');
        [, , $e, $sig] = explode('/', parse_url($lien, PHP_URL_PATH));

        $this->assertSame('awa@orange.ci', DiffusionDesinscription::emailDuLien($e, $sig));
        $this->assertNull(DiffusionDesinscription::emailDuLien($e, str_repeat('0', 32)));

        $autre = rtrim(strtr(base64_encode('autre@orange.ci'), '+/', '-_'), '=');
        $this->assertNull(DiffusionDesinscription::emailDuLien($autre, $sig));
    }
}
