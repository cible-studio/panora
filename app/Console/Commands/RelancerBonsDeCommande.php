<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use App\Services\BonDeCommandeService;
use App\Services\NotificationMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Relances « bon de commande attendu » — 2026-11-13.
 *
 * RÈGLE VALIDÉE PAR ÉCRIT : pendant les 30 jours d'attente du BC, des
 * rappels partent aux COMMERCIAUX, le MP en COPIE, à J-15, J-7 et J-2
 * avant l'échéance. Passé le délai, l'option expire et les panneaux
 * repartent au catalogue (cf. reservations:expire-bc).
 *
 * Chaque palier n'est envoyé qu'une fois : la date d'envoi est mémorisée
 * sur la réservation (bc_relance_j15_at / _j7_at / _j2_at). Une commande
 * rejouée le même jour ne renvoie donc rien.
 */
class RelancerBonsDeCommande extends Command
{
    protected $signature = 'reservations:relancer-bc
                            {--dry-run : Afficher sans envoyer ni marquer}';

    protected $description = "Relance les commerciaux (MP en copie) sur les bons de commande attendus — J-15, J-7, J-2";

    public function handle(BonDeCommandeService $bc, NotificationMailer $mailer): int
    {
        $simulation = (bool) $this->option('dry-run');
        $total = 0;

        foreach (BonDeCommandeService::paliersRelance() as $jours) {
            $dossiers = $bc->aRelancer($jours);

            if ($dossiers->isEmpty()) {
                $this->line("J-{$jours} : aucun dossier.");
                continue;
            }

            $this->info("J-{$jours} : {$dossiers->count()} dossier(s).");

            foreach ($dossiers as $resa) {
                $client    = $resa->client?->name ?? 'Client';
                $echeance  = $resa->bc_attendu_jusqu_au
                    ? \Carbon\Carbon::parse($resa->bc_attendu_jusqu_au)->format('d/m/Y')
                    : '—';
                $concurrence = $bc->messageConcurrence($resa);

                $titre = sprintf('📄 Bon de commande attendu — %s (J-%d)', $resa->reference, $jours);
                $corps = sprintf(
                    "Le client %s a accepté, mais son bon de commande n'est toujours pas enregistré.\n"
                    . "Échéance : %s (%d jour%s).\n"
                    . "Sans BC à cette date, les panneaux repartent automatiquement au catalogue.%s",
                    $client,
                    $echeance,
                    $jours,
                    $jours > 1 ? 's' : '',
                    $concurrence ? "\n\n" . $concurrence : ''
                );

                $this->line("   → {$resa->reference} · {$client} · échéance {$echeance}");

                if ($simulation) {
                    $total++;
                    continue;
                }

                // Alerte in-app, visible par le commercial du dossier.
                // notify() (et non create()) : c'est elle qui accepte le
                // destinataire et la clé de déduplication — sans cette clé,
                // J-7 serait considérée comme un doublon de J-15.
                try {
                    AlertService::notify('bc_attendu', $titre, $corps, $resa, [
                        'niveau'      => 'warning',
                        'user_id'     => $resa->commercial_user_id,
                        'dedup_extra' => 'j' . $jours,
                        'lien'        => route('admin.reservations.show', $resa->id),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('bc.relance.alerte', ['resa' => $resa->id, 'erreur' => $e->getMessage()]);
                }

                // Mail : commercial du dossier en destinataire, MP en copie.
                // sendNow() n'accepte qu'UNE adresse en copie : on prend le
                // premier MP, les autres ont l'alerte in-app.
                $destinataires = array_values(array_filter([$resa->commercial?->email]));
                $mps           = self::adressesMp();
                $copie         = $destinataires ? ($mps[0] ?? null) : null;

                // Sans commercial sur le dossier, les MP deviennent
                // destinataires : une relance sans personne à prévenir ne
                // sert à rien.
                $envoyerA = $destinataires ?: $mps;

                if ($envoyerA) {
                    try {
                        $mailer->sendNow(
                            $envoyerA,
                            new \App\Mail\AdminAlertMail(
                                severity: 'warning',
                                title:    $titre,
                                summary:  $corps,
                                ctaLabel: 'Ouvrir le dossier',
                                ctaUrl:   route('admin.reservations.show', $resa->id),
                                emoji:    '📄',
                            ),
                            cc: $copie,
                            context: [
                                'action'         => 'bc.relance',
                                'palier'         => 'J-' . $jours,
                                'reservation_id' => $resa->id,
                            ],
                        );
                    } catch (\Throwable $e) {
                        Log::error('bc.relance.mail', ['resa' => $resa->id, 'erreur' => $e->getMessage()]);
                    }
                } else {
                    $this->warn("      ⚠ Aucun destinataire pour {$resa->reference}.");
                }

                $bc->noterRelance($resa, $jours);
                $total++;
            }
        }

        $this->info($simulation
            ? "{$total} relance(s) seraient envoyées (simulation)."
            : "{$total} relance(s) envoyée(s).");

        return self::SUCCESS;
    }

    /** Adresses des média-planneurs, mis en copie de chaque relance. */
    private static function adressesMp(): array
    {
        return \App\Models\User::query()
            ->where('role', 'mediaplanner')
            ->whereNotNull('email')
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active'),
                fn ($q) => $q->where('is_active', true)
            )
            ->pluck('email')
            ->all();
    }
}
