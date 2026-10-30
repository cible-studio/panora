<?php

namespace Tests\Unit;

use App\Mail\Concerns\MailCommercial;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Copie commerciale et coordonnées des mails (2026-10-30).
 *
 * Demande de la direction :
 *   · stephane.oponou@cible-ci.com en copie des mails COMMERCIAUX et de
 *     FACTURATION envoyés au client ;
 *   · le numéro commercial dans le pied de page ;
 *   · « Service Commercial » avec un C majuscule.
 *
 * La copie est posée par un écouteur de MessageSending, le seul point par
 * lequel passent tous les envois — NotificationMailer, Mail::to()->send()
 * et la file d'attente. Ces garde-fous tiennent le marquage : un mail
 * commercial qui perdrait son interface partirait sans copie, en silence.
 */
class CopieCommercialeTest extends TestCase
{
    /** Les mails commerciaux et de facturation envoyés au client. */
    private const COMMERCIAUX = [
        // Documents commerciaux
        'QuoteSentMail',
        'PropositionMail',
        'PropositionRappelMail',
        'PropositionReminderMail',
        'PropositionDateChangeMail',
        'PropositionDecisionMail',
        // Vie de la campagne
        'CampaignStartedMail',
        'ClientCampaignStartedMail',
        'ReservationConfirmedMail',
        'MailClientCampagne',        // renouvellement, accusé
        'CampaignEndingSoonMail',    // J-7, hérite de MailClientCampagne
        // Facturation
        'InvoiceIssuedMail',    // émission ET relances d'impayés
        'InvoiceScheduleReminderMail',
    ];

    /**
     * Mails qui NE doivent PAS porter la copie : internes au personnel,
     * suivi d'exécution, ou techniques.
     */
    private const SANS_COPIE = [
        // Internes
        'AdminAlertMail', 'CampaignAssignedMail', 'CampaignEndedCommercialMail',
        'CampaignEndingCommercialMail', 'DiffusionDisponibilitesMail',
        'RenouvellementDigestMail', 'RenouvellementReponseMail',
        'ReservationAssignedMail', 'PropositionAssignedMail', 'UserWelcomeMail',
        'RappelTestRestaurationMail', 'DemoRequestMail', 'CibleContactMail',
        'ClientContactReceivedMail',
        // Suivi d'exécution
        'PigeValidatedMail', 'CampaignDecappedMail', 'CampaignEndedMail',
        'ClientPanelMaintenanceMail', 'SatisfactionSurveyMail', 'MaintenanceMail',
        // Techniques
        'ClientAccountMail', 'ClientUserInvitationMail', 'ClientContactReplyMail',
    ];

    // ── Le marquage ────────────────────────────────────────────────

    public function test_les_mails_commerciaux_sont_marques(): void
    {
        foreach (self::COMMERCIAUX as $mail) {
            $classe = 'App\\Mail\\' . $mail;
            $this->assertTrue(class_exists($classe), "Mailable introuvable : {$mail}");
            $this->assertContains(
                MailCommercial::class,
                class_implements($classe),
                "{$mail} doit porter MailCommercial, sinon il part sans copie commerciale."
            );
        }
    }

    public function test_les_autres_mails_ne_sont_pas_marques(): void
    {
        foreach (self::SANS_COPIE as $mail) {
            $classe = 'App\\Mail\\' . $mail;
            if (!class_exists($classe)) {
                continue;
            }
            $this->assertNotContains(
                MailCommercial::class,
                class_implements($classe),
                "{$mail} n'est ni commercial ni de facturation : pas de copie."
            );
        }
    }

    public function test_aucun_mailable_n_est_oublie_dans_ce_test(): void
    {
        // Un nouveau mailable doit être classé d'un côté ou de l'autre,
        // sinon la règle se perd au fil des ajouts.
        $existants = collect(File::files(base_path('app/Mail')))
            ->map(fn ($f) => $f->getFilenameWithoutExtension())
            ->sort()
            ->values()
            ->all();

        $classes = array_merge(self::COMMERCIAUX, self::SANS_COPIE);
        $oublies = array_diff($existants, $classes);

        $this->assertSame([], array_values($oublies),
            'Mailable(s) non classé(s) : ' . implode(', ', $oublies)
            . ' — les ajouter à COMMERCIAUX ou à SANS_COPIE.');
    }

    // ── La source de la copie ──────────────────────────────────────

    public function test_la_copie_vient_d_une_seule_source(): void
    {
        $this->assertSame(
            'stephane.oponou@cible-ci.com',
            config('mails.copie_commerciale'),
            'Défaut attendu — surchargeable par MAIL_COPIE_COMMERCIALE.'
        );
    }

    public function test_l_ecouteur_ajoute_sans_remplacer(): void
    {
        // MailClientCampagne pose déjà sa propre copie (fin de campagne) :
        // l'écouteur doit s'y ajouter, pas l'écraser.
        $source = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));

        $this->assertStringContainsString('addCc(', $source);
        $this->assertStringNotContainsString('->cc($copies)', $source);
        $this->assertStringContainsString('MailCommercial', $source);
    }

    public function test_pas_de_doublon_avec_un_destinataire_direct(): void
    {
        // Si l'adresse est déjà destinataire ou déjà en copie, on ne
        // l'ajoute pas une seconde fois.
        $source = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));

        $this->assertStringContainsString('getTo()', $source);
        $this->assertStringContainsString('getCc()', $source);
        $this->assertStringContainsString('array_diff', $source);
    }

    public function test_l_apercu_avant_envoi_montre_la_copie(): void
    {
        // Sinon l'écran annoncerait des destinataires incomplets.
        $source = file_get_contents(base_path('app/Http/Controllers/Admin/ApercuEnvoiController.php'));

        $this->assertSame(3, substr_count($source, 'self::copieCommerciale()'),
            'Les trois aperçus (campagne, devis, proposition) doivent montrer la copie.');
    }

    // ── Coordonnées et libellés ────────────────────────────────────

    public function test_le_numero_commercial_est_dans_la_charte(): void
    {
        // Source unique : pied des mails, des PDF et des pages publiques.
        $this->assertStringContainsString('+225 07 00 78 06 27', config('charte.coordonnees.telephones'));
    }

    public function test_le_numero_commercial_est_dans_les_modeles_brevo(): void
    {
        // Brevo ne lit pas la charte : ses modèles portent les numéros en dur.
        foreach ([
            'docs/diffusion/modele-brevo-disponibilites-leger.html',
            'docs/diffusion/modele-brevo-disponibilites.html',
            'docs/brevo/modele-relance-campagne-terminee.html',
        ] as $modele) {
            $source = file_get_contents(base_path($modele));
            $this->assertMatchesRegularExpression(
                '/\+225(&nbsp;|\s)07(&nbsp;|\s)00(&nbsp;|\s)78(&nbsp;|\s)06(&nbsp;|\s)27/',
                $source,
                "{$modele} doit porter le numéro commercial."
            );
        }
    }

    public function test_le_service_commercial_prend_un_c_majuscule(): void
    {
        $this->assertSame('Service Commercial', config('brevo.sender.name'),
            'Défaut attendu — surchargeable par BREVO_SENDER_NAME.');

        foreach ([
            'docs/diffusion/modele-brevo-disponibilites-leger.html',
            'docs/diffusion/modele-brevo-disponibilites.html',
            'docs/brevo/modele-relance-campagne-terminee.html',
        ] as $modele) {
            $source = file_get_contents(base_path($modele));
            $this->assertStringNotContainsString('service commercial de CIBLE', $source,
                "{$modele} : la signature garde un c minuscule.");
        }
    }
    public function test_les_mails_brevo_portent_aussi_la_copie_commerciale(): void
    {
        // Le J-7 et la séquence de renouvellement partent par l API Brevo,
        // pas par le mailer de Laravel : l écouteur de MessageSending ne les
        // voit pas. Leur copie est donc réunie à la source.
        config([
            "diffusion.fin_campagne.copie" => "commercial@cible-ci.com",
            "mails.copie_commerciale"      => "stephane.oponou@cible-ci.com",
        ]);

        $copie = app(\App\Services\Diffusion\RelanceFinCampagneService::class)->copie();

        $this->assertContains("commercial@cible-ci.com", $copie);
        $this->assertContains("stephane.oponou@cible-ci.com", $copie);
        $this->assertSame($copie, array_unique($copie), "Pas de doublon dans la copie.");
    }
}
