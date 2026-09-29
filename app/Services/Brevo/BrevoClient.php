<?php

namespace App\Services\Brevo;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Connecteur Brevo — SEUL endroit de Panora qui parle à l'API Brevo.
 *
 * Si Brevo change son API, c'est le seul fichier à adapter.
 * Documentation : https://developers.brevo.com/reference
 *
 * Choix de conception :
 *   • Aucune nouvelle tentative automatique sur l'envoi d'une campagne :
 *     un sendNow rejoué après un délai d'attente peut partir DEUX fois
 *     chez les clients. On préfère un échec franc, signalé à l'équipe.
 *   • Chaque erreur remonte le message exact de Brevo, pour que l'admin
 *     sache quoi corriger (attribut manquant, expéditeur non validé…).
 */
class BrevoClient
{
    public function estConfigure(): bool
    {
        return !empty(config('brevo.api_key')) && !empty(config('brevo.sender.email'));
    }

    /**
     * Vérifie la clé API. Utilisé par le bouton « Tester la connexion ».
     *
     * @return array{email: ?string, societe: ?string, plan: ?string}
     */
    public function compte(): array
    {
        $r = $this->reponse($this->client()->get('/account'), 'lecture du compte');

        return [
            'email'   => $r->json('email'),
            'societe' => $r->json('companyName'),
            'plan'    => collect($r->json('plan', []))->pluck('type')->filter()->implode(', ') ?: null,
        ];
    }

    /**
     * Crée ou met à jour un contact et l'ajoute aux listes.
     *
     * N'envoie JAMAIS emailBlacklisted : un contact qui s'est désinscrit
     * dans Brevo reste désinscrit, même si Panora le resynchronise.
     *
     * @param array<string, scalar|null> $attributs  noms d'attributs Brevo (CONTACT, SOCIETE…)
     * @param int[]                      $listIds
     */
    public function enregistrerContact(string $email, array $attributs, array $listIds): void
    {
        $this->reponse($this->client()->post('/contacts', [
            'email'         => $email,
            'attributes'    => (object) array_filter($attributs, fn($v) => $v !== null && $v !== ''),
            'listIds'       => array_values($listIds),
            'updateEnabled' => true,
        ]), "enregistrement du contact {$email}");
    }

    /** @return string[]  adresses présentes dans une liste */
    public function emailsDeLaListe(int $listId): array
    {
        $emails = [];
        $offset = 0;
        $limite = 500;

        do {
            $r = $this->reponse(
                $this->client()->get("/contacts/lists/{$listId}/contacts", [
                    'limit'  => $limite,
                    'offset' => $offset,
                ]),
                "lecture de la liste {$listId}"
            );

            $lot = collect($r->json('contacts', []))->pluck('email')->filter()->all();
            array_push($emails, ...$lot);
            $offset += $limite;
        } while (count($lot) === $limite);

        return array_values(array_unique(array_map('mb_strtolower', $emails)));
    }

    /** @param string[] $emails */
    public function retirerDeLaListe(int $listId, array $emails): void
    {
        // L'API limite le nombre d'adresses par appel.
        foreach (array_chunk(array_values($emails), 150) as $lot) {
            $this->reponse(
                $this->client()->post("/contacts/lists/{$listId}/contacts/remove", ['emails' => $lot]),
                "retrait de contacts de la liste {$listId}"
            );
        }
    }

    /**
     * Crée une campagne e-mail (sans l'envoyer).
     *
     * @return int  identifiant de la campagne dans Brevo
     */
    public function creerCampagne(array $payload): int
    {
        $r = $this->reponse($this->client()->post('/emailCampaigns', $payload), 'création de la campagne');

        $id = (int) $r->json('id');
        if ($id <= 0) {
            throw new BrevoException('Brevo n\'a pas renvoyé d\'identifiant de campagne.');
        }

        return $id;
    }

    /**
     * Lance l'envoi d'une campagne. PAS de nouvelle tentative : en cas
     * de délai dépassé, on ne sait pas si Brevo a déjà envoyé.
     */
    public function envoyerCampagne(int $campaignId): void
    {
        $this->reponse(
            $this->client()->post("/emailCampaigns/{$campaignId}/sendNow"),
            "envoi de la campagne {$campaignId}"
        );
    }

    /**
     * Lien vers le rapport de la campagne dans Brevo (délivrés, ouvertures,
     * clics, désinscriptions). Nécessite un accès au compte Brevo.
     *
     * 2026-09-29 : remplace l'appel à /emailCampaigns/{id}/sharedUrl, qui
     * renvoie en réalité une page « Voulez-vous importer ce template ? »
     * affichant les variables brutes — constaté au premier test réel.
     */
    public static function lienRapportCampagne(int $campaignId): string
    {
        return "https://app.brevo.com/marketing-reports/email/{$campaignId}/overview";
    }

    // ══════════════════════════════════════════════════════════════

    private function client(): PendingRequest
    {
        if (!$this->estConfigure()) {
            throw new BrevoException(
                'Brevo n\'est pas configuré : BREVO_API_KEY et BREVO_SENDER_EMAIL doivent être renseignés dans le .env.'
            );
        }

        return Http::baseUrl(rtrim((string) config('brevo.base_url'), '/'))
            ->withHeaders([
                'api-key' => (string) config('brevo.api_key'),
                'accept'  => 'application/json',
            ])
            ->asJson()
            ->timeout((int) config('brevo.timeout', 30));
    }

    /** Transforme une réponse en erreur lisible si Brevo refuse. */
    private function reponse(Response $r, string $operation): Response
    {
        if ($r->successful()) {
            return $r;
        }

        $detail = $r->json('message') ?: $r->body();

        Log::warning('brevo.erreur', [
            'operation' => $operation,
            'status'    => $r->status(),
            'detail'    => mb_substr((string) $detail, 0, 500),
        ]);

        $conseil = match ($r->status()) {
            401     => ' — la clé API est refusée : vérifiez BREVO_API_KEY.',
            403     => ' — accès refusé : l\'adresse IP du serveur est peut-être absente des IP autorisées dans Brevo.',
            default => '',
        };

        throw new BrevoException("Brevo a refusé la {$operation} (HTTP {$r->status()}) : {$detail}{$conseil}");
    }
}
