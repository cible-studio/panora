<?php

namespace App\Console\Commands;

use App\Mail\RenouvellementDigestMail;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use App\Services\Diffusion\RelanceFinCampagneService;
use App\Services\Diffusion\RenouvellementService;
use App\Services\NotificationMailer;
use Illuminate\Console\Command;

/**
 * Mail du matin sur les renouvellements (2026-10-22), jours ouvrés à 8h
 * (routes/console.php § 9-ter) :
 *   · à chaque commercial : ses clients sans réponse à appeler (dès J-25),
 *     les demandes de rappel, les réponses reçues ;
 *   · aux MP : les renouvellements à confirmer.
 *
 * Mail interne : respecte MAIL_STAFF_ALERTS_ENABLED. En mode test
 * (RELANCE_FIN_MODE ≠ production), tout part vers
 * DIFFUSION_CONFIRMATION_EMAIL.
 */
class RenouvellementsDigest extends Command
{
    protected $signature = 'renouvellements:digest {--dry-run : Affiche sans envoyer}';

    protected $description = 'Mail du matin aux commerciaux et aux MP sur les renouvellements des campagnes annuelles';

    public function handle(RenouvellementService $service, RelanceFinCampagneService $socle, NotificationMailer $mailer): int
    {
        if (!config('mail.staff_alerts_enabled', true)) {
            $this->info('Mails internes désactivés (MAIL_STAFF_ALERTS_ENABLED=false) : rien à envoyer.');

            return self::SUCCESS;
        }

        $mails = $service->digest();
        $test  = $socle->estModeTest();
        $tests = DiffusionDisponibilitesService::adresses(config('diffusion.confirmation_email'));

        foreach ($mails as $contenu) {
            $resume = "{$contenu['nom']} <{$contenu['email']}> : "
                . ($contenu['role'] === 'mediaplanner'
                    ? "{$contenu['a_confirmer']->count()} à confirmer"
                    : "{$contenu['a_rappeler']->count()} rappel(s) prévu(s) · {$contenu['appeler']->count()} à appeler · {$contenu['rappels']->count()} rappel(s) · {$contenu['reponses']->count()} réponse(s)");

            if ($this->option('dry-run')) {
                $this->line("  [→] {$resume}");
                continue;
            }

            $destinataires = $test ? $tests : [$contenu['email']];
            $note = $test ? "En production, ce mail irait à {$contenu['email']}." : null;

            foreach ($destinataires as $email) {
                $mailer->sendSilently($email, new RenouvellementDigestMail($contenu, $note), context: ['digest' => 'renouvellements']);
            }
            $this->info("  ✓ {$resume}" . ($test ? ' (envoyé en test)' : ''));
        }

        if ($mails->isEmpty()) {
            $this->line('Rien à signaler aujourd\'hui.');
        }

        return self::SUCCESS;
    }
}
