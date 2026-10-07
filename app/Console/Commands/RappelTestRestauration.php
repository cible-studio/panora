<?php

namespace App\Console\Commands;

use App\Mail\RappelTestRestaurationMail;
use App\Services\NotificationMailer;
use Illuminate\Console\Command;
use Spatie\Backup\BackupDestination\BackupDestination;

/**
 * Rappel trimestriel du test de restauration des sauvegardes (2026-10-07).
 *
 * Une sauvegarde qu'on n'a jamais restaurée ne prouve rien : ce mail
 * rappelle, le 1er de chaque trimestre, de restaurer une copie sur le
 * staging (procédure : docs/SAUVEGARDES.md § 4). Il joint l'état des
 * copies sur chaque disque pour repérer d'un coup d'œil un problème.
 *
 * Destinataires : ceux des alertes de sauvegarde (BACKUP_NOTIFICATION_EMAIL).
 */
class RappelTestRestauration extends Command
{
    protected $signature = 'sauvegardes:rappel-restauration';

    protected $description = 'Envoie le rappel trimestriel du test de restauration des sauvegardes';

    public function handle(NotificationMailer $mailer): int
    {
        $destinataires = (array) config('backup.notifications.mail.to', []);
        if (! $destinataires) {
            $this->warn('Aucun destinataire (BACKUP_NOTIFICATION_EMAIL vide) : rappel non envoyé.');

            return self::SUCCESS;
        }

        $mail = new RappelTestRestaurationMail($this->etatDesCopies());
        $ok = $mailer->sendSilently($destinataires, $mail, context: ['rappel' => 'test-restauration']);

        $this->info($ok ? 'Rappel envoyé à ' . implode(', ', $destinataires) . '.' : 'Échec de l\'envoi du rappel (voir le journal).');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * État des sauvegardes par disque. Un disque injoignable ne bloque pas
     * le rappel : il apparaît comme tel dans le mail.
     *
     * @return array<int, array{disque:string, joignable:bool, nombre:int, derniere:?string, taille_mo:?float}>
     */
    private function etatDesCopies(): array
    {
        $nom = (string) config('backup.backup.name');

        return collect((array) config('backup.backup.destination.disks', []))
            ->map(function (string $disque) use ($nom) {
                try {
                    $destination = BackupDestination::create($disque, $nom);
                    $joignable = $destination->isReachable();
                    $derniere = $joignable ? $destination->newestBackup() : null;

                    return [
                        'disque'    => $disque,
                        'joignable' => $joignable,
                        'nombre'    => $joignable ? $destination->backups()->count() : 0,
                        'derniere'  => $derniere?->date()->format('d/m/Y à H\hi'),
                        'taille_mo' => $joignable ? round($destination->usedStorage() / 1024 / 1024, 1) : null,
                    ];
                } catch (\Throwable) {
                    return ['disque' => $disque, 'joignable' => false, 'nombre' => 0, 'derniere' => null, 'taille_mo' => null];
                }
            })
            ->values()
            ->all();
    }
}
