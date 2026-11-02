<?php

namespace App\Console\Commands;

use App\Models\DiffusionDesinscription;
use App\Services\Brevo\BrevoClient;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use Illuminate\Console\Command;

/**
 * Nettoyage des adresses de diffusion (2026-11-02, après l'incident « test #8 » :
 * 15 % de rebonds sur 300 envois, Brevo exige moins de 2 %).
 *
 * Écarte des prochains envois, sans rien supprimer :
 *   1. les adresses que Brevo a bloquées dans la liste clients (rebond
 *      définitif, désinscription, plainte) — utile si le webhook ne les a
 *      pas transmises ;
 *   2. les adresses dont le domaine n'a pas de serveur de mail (DNS) :
 *      elles rebondiraient à coup sûr.
 *
 * Par défaut : simple rapport. --appliquer enregistre les exclusions
 * (motif « Adresse invalide »), réversibles depuis l'écran Diffusion dispos.
 * Une adresse déjà exclue garde son motif d'origine.
 */
class NettoyerAdressesDiffusion extends Command
{
    protected $signature = 'dispos:nettoyer-adresses
        {--appliquer : Enregistrer les exclusions (sinon simple rapport)}';

    protected $description = 'Écarte des envois les adresses bloquées par Brevo et celles dont le domaine ne reçoit pas de mail';

    public function handle(DiffusionDisponibilitesService $diffusion, BrevoClient $brevo): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $this->info($appliquer ? 'Nettoyage — les exclusions seront enregistrées.' : 'Nettoyage — SIMPLE RAPPORT (ajouter --appliquer pour enregistrer).');
        $this->newLine();

        $aExclure = []; // email => source

        // ── 1. Bloquées par Brevo ─────────────────────────────────
        $liste = (int) config('brevo.lists.clients');
        if ($liste && $brevo->estConfigure()) {
            $bloques = $brevo->emailsBloquesDeLaListe($liste);
            $this->line(sprintf('1. Bloquées par Brevo dans la liste #%d : %d', $liste, count($bloques)));
            foreach ($bloques as $email) {
                $aExclure[$email] = 'brevo';
            }
        } else {
            $this->warn('1. Brevo non configuré ou BREVO_LIST_CLIENTS vide : étape sautée.');
        }

        // ── 2. Domaines sans serveur de mail ──────────────────────
        $destinataires = $diffusion->destinataires()->pluck('email');
        $parDomaine = $destinataires->groupBy(fn($e) => mb_strtolower(substr(strrchr($e, '@'), 1)));
        $this->line(sprintf('2. Vérification de %d domaines (%d adresses)…', $parDomaine->count(), $destinataires->count()));

        $morts = [];
        $barre = $this->output->createProgressBar($parDomaine->count());
        foreach ($parDomaine as $domaine => $emails) {
            if (!checkdnsrr($domaine, 'MX') && !checkdnsrr($domaine, 'A')) {
                $morts[$domaine] = $emails->all();
                foreach ($emails as $email) {
                    $aExclure[$email] ??= 'verification';
                }
            }
            $barre->advance();
        }
        $barre->finish();
        $this->newLine();

        if ($morts) {
            $this->table(['Domaine sans serveur de mail', 'Adresses'],
                collect($morts)->map(fn($e, $d) => [$d, count($e)])->values()->all());
        }

        // ── Bilan ─────────────────────────────────────────────────
        $dejaExclues = DiffusionDesinscription::whereIn('email', array_keys($aExclure))->pluck('email')->flip();
        $nouvelles = array_diff_key($aExclure, $dejaExclues->all());

        $this->newLine();
        $this->line(sprintf('Adresses à écarter : %d (dont %d déjà exclues).', count($aExclure), count($aExclure) - count($nouvelles)));

        if (!$appliquer) {
            $this->info(count($nouvelles) . ' nouvelle(s) exclusion(s) seraient enregistrées. Relancer avec --appliquer.');

            return self::SUCCESS;
        }

        foreach ($nouvelles as $email => $source) {
            DiffusionDesinscription::firstOrCreate(
                ['email' => $email],
                ['motif' => 'rebond', 'source' => $source, 'survenu_at' => now()]
            );
        }

        $this->info(count($nouvelles) . ' exclusion(s) enregistrée(s). Destinataires restants : '
            . $diffusion->destinataires()->count() . '.');

        return self::SUCCESS;
    }
}
