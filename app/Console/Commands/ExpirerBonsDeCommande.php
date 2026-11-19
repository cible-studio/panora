<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use App\Services\AvailabilityService;
use App\Services\BonDeCommandeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Expiration des options restées sans bon de commande — 2026-11-13.
 *
 * RÈGLE VALIDÉE PAR ÉCRIT : « Si aucune validation n'a lieu avant la fin
 * du délai, la réservation expire et les panneaux redeviennent
 * disponibles dans le catalogue. »
 *
 * Ne touche QUE les dossiers passés par le nouveau flux (ceux qui portent
 * une échéance BC). Les autres options restent gérées par
 * `reservations:expire-old-options`, qui les ignore désormais.
 */
class ExpirerBonsDeCommande extends Command
{
    protected $signature = 'reservations:expire-bc
                            {--dry-run : Afficher sans rien modifier}';

    protected $description = "Annule les options dont le délai de bon de commande est dépassé et libère les panneaux";

    public function handle(BonDeCommandeService $bc, AvailabilityService $availability): int
    {
        $simulation = (bool) $this->option('dry-run');

        // 2026-11-19 — Confirmées sans BC (exception admin) : jamais annulées,
        // mais l'admin et le commercial sont prévenus une fois l'échéance passée.
        $this->signalerConfirmeesSansBc($bc, $simulation);
        $dossiers   = $bc->aExpirer();

        if ($dossiers->isEmpty()) {
            $this->info('Aucune option à expirer.');
            return self::SUCCESS;
        }

        $this->info("{$dossiers->count()} option(s) sans bon de commande à l'échéance.");

        foreach ($dossiers as $resa) {
            $panelIds = $resa->panels->pluck('id')->all();
            $extIds   = $resa->externalPanels->pluck('id')->all();
            $client   = $resa->client?->name ?? 'Client';
            $echeance = \Carbon\Carbon::parse($resa->bc_attendu_jusqu_au)->format('d/m/Y');

            $this->line(sprintf(
                '   → %s · %s · échéance %s · %d panneau(x)',
                $resa->reference, $client, $echeance, count($panelIds) + count($extIds)
            ));

            if ($simulation) continue;

            $resa->update(['status' => 'annule']);

            // Libération : les panneaux repartent au catalogue.
            if ($panelIds) $availability->syncPanelStatuses($panelIds);
            if ($extIds)   $availability->syncExternalPanelStatuses($extIds);

            try {
                AlertService::notify(
                    'bc_expire',
                    '⌛ Option expirée faute de bon de commande — ' . $resa->reference,
                    sprintf(
                        "Le délai de réception du bon de commande de %s est dépassé depuis le %s.\n"
                        . "La réservation est annulée et les %d panneau(x) sont de nouveau disponibles au catalogue.",
                        $client, $echeance, count($panelIds) + count($extIds)
                    ),
                    $resa,
                    [
                        'user_id' => $resa->commercial_user_id,
                        'lien'    => route('admin.reservations.show', $resa->id),
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('bc.expire.alerte', ['resa' => $resa->id, 'erreur' => $e->getMessage()]);
            }

            Log::info('bc.expire', [
                'reservation_id' => $resa->id,
                'reference'      => $resa->reference,
                'echeance'       => $resa->bc_attendu_jusqu_au,
                'panneaux'       => count($panelIds),
            ]);
        }

        $this->info($simulation
            ? 'Simulation terminée — rien modifié.'
            : "{$dossiers->count()} option(s) expirée(s), panneaux libérés.");

        return self::SUCCESS;
    }

    /** Prévenir (une fois) l'admin et le commercial des confirmées sans BC échues. */
    private function signalerConfirmeesSansBc(BonDeCommandeService $bc, bool $simulation): void
    {
        foreach ($bc->aSignalerEchus() as $resa) {
            $client   = $resa->client?->name ?? 'Client';
            $echeance = \Carbon\Carbon::parse($resa->bc_attendu_jusqu_au)->format('d/m/Y');
            $this->line("   ⚠ {$resa->reference} · {$client} · confirmée sans BC, échéance {$echeance} dépassée");
            if ($simulation) continue;

            $titre = '📄 BC toujours absent — ' . $resa->reference;
            $corps = sprintf(
                "La réservation de %s a été confirmée sans bon de commande (motif : %s).\n"
                . "L'échéance du %s est dépassée et le BC n'est toujours pas enregistré. La réservation reste confirmée.",
                $client, $resa->confirme_sans_bc_motif ?: '—', $echeance
            );
            try {
                // Ciblée sur le commercial ; l'admin voit toutes les alertes.
                AlertService::notify('bc_echeance_confirme', $titre, $corps, $resa, [
                    'niveau'  => 'warning',
                    'user_id' => $resa->commercial_user_id,
                    'lien'    => route('admin.reservations.show', $resa->id),
                ]);
            } catch (\Throwable $e) {
                Log::error('bc.echeance_confirme.alerte', ['resa' => $resa->id, 'erreur' => $e->getMessage()]);
            }
            if ($resa->commercial?->email) {
                app(\App\Services\NotificationMailer::class)->sendSilently(
                    $resa->commercial->email,
                    new \App\Mail\AdminAlertMail(
                        severity: 'warning', title: 'BC toujours absent — ' . $resa->reference, summary: $corps,
                        ctaLabel: 'Ouvrir la réservation', ctaUrl: route('admin.reservations.show', $resa->id), emoji: '📄',
                    ),
                    context: ['reservation_id' => $resa->id],
                );
            }
            $resa->forceFill(['bc_echeance_signalee_at' => now()])->save();
            Log::info('bc.echeance_confirme', ['reservation_id' => $resa->id]);
        }
    }
}
