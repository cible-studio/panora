<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Spatie\Backup\BackupDestination\BackupDestination;

/**
 * Rattrapage de la sauvegarde de la nuit (2026-11-23).
 *
 * La nuit du 21/11, Hetzner (hel1) a répondu « 504 Gateway Timeout »
 * pendant plus de trois heures : les trois essais de 4h40, 5h10 et 5h45 ont
 * échoué, et la copie hors serveur a manqué jusqu'au rattrapage à la main.
 *
 * Chaque après-midi (config backup.heure_rattrapage, 14h par défaut), cette
 * commande regarde, disque par disque, si la copie du jour existe :
 *   · tout est là                → rien ;
 *   · un seul disque manque      → backup:run --only-to-disk=<ce disque> ;
 *   · tous les disques manquent  → backup:run complet.
 * Un disque qu'on ne peut pas lire (Hetzner encore en panne) compte comme
 * manquant : l'essai est fait, et spatie envoie son mail d'échec s'il rate.
 *
 * Limite : elle passe par le même planificateur que la sauvegarde. Si le
 * planificateur lui-même s'arrête (22-23/11, `docker exec` en panne), elle
 * ne tourne pas non plus. Seul un contrôle extérieur au serveur le voit.
 *
 *   php artisan sauvegardes:rattraper --simuler   dit ce qu'elle ferait
 */
class RattraperSauvegarde extends Command
{
    protected $signature = 'sauvegardes:rattraper {--simuler : Affiche la décision sans lancer de sauvegarde}';

    protected $description = 'Refait la sauvegarde du jour sur les disques où elle manque';

    public function handle(): int
    {
        $disques = (array) config('backup.backup.destination.disks', []);
        $nom     = (string) config('backup.backup.name');

        $dernieres = [];
        foreach ($disques as $disque) {
            try {
                $dernieres[$disque] = BackupDestination::create($disque, $nom)->newestBackup()?->date();
            } catch (\Throwable $e) {
                $dernieres[$disque] = null;
                $this->warn("{$disque} : illisible ({$e->getMessage()}), compté comme manquant.");
            }
        }

        $aRefaire = self::disquesARattraper($dernieres, now());

        foreach ($dernieres as $disque => $date) {
            $this->line("{$disque} : dernière copie " . ($date ? $date->format('d/m/Y H:i') : '—'));
        }

        if ($aRefaire === []) {
            $this->info('Copie du jour présente partout : rien à rattraper.');
            return self::SUCCESS;
        }

        $complete = count($aRefaire) === count($disques);

        if ($this->option('simuler')) {
            $this->info('Simulation : sauvegarde ' . ($complete ? 'complète' : 'sur ' . implode(', ', $aRefaire) . ' seulement') . ' à refaire.');
            return self::SUCCESS;
        }

        if ($complete) {
            $this->info('Sauvegarde complète relancée.');
            return Artisan::call('backup:run', [], $this->output);
        }

        // Seulement les disques où la copie manque, l'un après l'autre.
        $code = self::SUCCESS;
        foreach ($aRefaire as $disque) {
            $this->info("Sauvegarde relancée sur {$disque}.");
            $code = max($code, Artisan::call('backup:run', ['--only-to-disk' => $disque], $this->output));
        }

        return $code;
    }

    /**
     * Disques dont la copie du jour manque (dernière copie avant minuit, ou
     * aucune, ou disque illisible).
     *
     * @param  array<string, ?CarbonInterface> $dernieres  disque => date de la dernière copie
     * @return list<string>
     */
    public static function disquesARattraper(array $dernieres, CarbonInterface $maintenant): array
    {
        $minuit = Carbon::instance($maintenant)->startOfDay();

        return array_values(array_keys(array_filter(
            $dernieres,
            fn (?CarbonInterface $date) => $date === null || $date->lt($minuit)
        )));
    }
}
