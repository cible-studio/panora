<?php

namespace App\Mail;

use App\Models\Campaign;

/**
 * Mail au client : « Votre campagne se termine le … » (J-7), pour les
 * campagnes de moins de 12 mois.
 *
 * Refait le 2026-10-21 (format léger, réponse au commercial, plus d'« offre
 * de fidélité »). Depuis le 2026-10-22, c'est un cas de MailClientCampagne
 * (clé « fin ») : son texte se modifie dans Panora avec ceux de la séquence
 * de renouvellement. Envoyé par Brevo uniquement, tag « fin-campagne ».
 */
class CampaignEndingSoonMail extends MailClientCampagne
{
    /**
     * @param string[] $copie
     * @param ?string  $noteTest  en mode test : à qui le mail serait parti
     */
    public function __construct(
        Campaign $campaign,
        int $daysRemaining,
        ?string $contact = null,
        ?string $lienDesinscription = null,
        ?string $repondreA = null,
        array $copie = [],
        ?string $noteTest = null,
        ?array $modele = null,
    ) {
        parent::__construct(
            campaign: $campaign,
            cle: 'fin',
            daysRemaining: $daysRemaining,
            contact: $contact,
            lienDesinscription: $lienDesinscription,
            repondreA: $repondreA,
            copie: $copie,
            noteTest: $noteTest,
            modele: $modele,
        );
    }
}
