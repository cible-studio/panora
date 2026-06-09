<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceSchedule;
use App\Models\Relance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ReminderService — centralise l'enregistrement des relances de
 * recouvrement (§9 cahier).
 *
 * Une relance = trace d'une action de recouvrement (téléphone, email,
 * WhatsApp, visite, courrier). N'est PAS automatique : c'est l'admin
 * ou le commercial qui enregistre après avoir contacté le client.
 *
 * Distinct des invoice_alerts (Phase 4) qui sont des notifications
 * SYSTÈME automatiques. Les relances sont des traces d'action HUMAINES.
 *
 * Si schedule_id fourni : la relance cible une échéance précise. Le
 * service met à jour schedule.reminder_count + reminded_at pour le
 * cumul (utilisé par les badges "🔔 N relances" sur la fiche facture).
 */
class ReminderService
{
    public const CANALS = ['telephone', 'email', 'whatsapp', 'visite', 'courrier', 'autre'];
    public const OUTCOMES = [
        'promesse_paiement', 'paiement_recu', 'desaccord',
        'sans_reponse', 'a_relancer', 'autre',
    ];

    /**
     * Enregistre une relance.
     *
     * @param array{
     *   invoice_id?: int|null,
     *   schedule_id?: int|null,
     *   client_id: int,
     *   relance_date: string,
     *   canal: string,
     *   note: string,
     *   outcome?: string,
     *   suite_donnee?: string,
     * } $data
     */
    public function register(array $data): Relance
    {
        if (!in_array($data['canal'] ?? '', self::CANALS, true)) {
            throw new \DomainException('Canal de relance invalide.');
        }
        if (isset($data['outcome']) && !in_array($data['outcome'], self::OUTCOMES, true)) {
            throw new \DomainException('Résultat (outcome) invalide.');
        }

        return DB::transaction(function () use ($data) {
            $relance = Relance::create([
                'client_id'    => $data['client_id'],
                'invoice_id'   => $data['invoice_id']   ?? null,
                'schedule_id'  => $data['schedule_id']  ?? null,
                'relance_date' => $data['relance_date'],
                'canal'        => $data['canal'],
                'note'         => $data['note'],
                'outcome'      => $data['outcome']      ?? null,
                'suite_donnee' => $data['suite_donnee'] ?? null,
                'user_id'      => auth()->id(),
            ]);

            // Si la relance cible une échéance précise, on incrémente
            // reminder_count + reminded_at sur celle-ci (cumul pour les
            // badges "🔔 N relances" sur la fiche facture).
            if (!empty($data['schedule_id'])) {
                $schedule = InvoiceSchedule::find($data['schedule_id']);
                if ($schedule) {
                    $schedule->update([
                        'reminder_count' => ($schedule->reminder_count ?? 0) + 1,
                        'reminded_at'    => $data['relance_date'],
                    ]);
                }
            }

            Log::info('reminder.registered', [
                'relance_id' => $relance->id,
                'invoice_id' => $relance->invoice_id,
                'schedule_id'=> $relance->schedule_id,
                'client_id'  => $relance->client_id,
                'canal'      => $relance->canal,
                'outcome'    => $relance->outcome,
                'by'         => auth()->id(),
            ]);

            return $relance;
        });
    }

    /**
     * Niveau de priorité d'une créance selon l'ancienneté du retard
     * (cahier §8) :
     *   - Critique  : retard > 90 j   → rouge
     *   - Élevé     : 60-90 j         → orange
     *   - Moyen     : 30-60 j         → ambre
     *   - Faible    : < 30 j          → vert
     *
     * "retard" = nombre de jours depuis l'échéance la plus ancienne
     * impayée (ou depuis issued_at si pas d'échéancier).
     */
    public static function priorityForOverdueDays(int $days): array
    {
        if ($days > 90) {
            return ['key' => 'critique', 'label' => 'Critique', 'color' => '#b91c1c', 'bg' => 'rgba(239,68,68,.12)', 'icon' => '🔴'];
        }
        if ($days >= 60) {
            return ['key' => 'eleve', 'label' => 'Élevé', 'color' => '#b45309', 'bg' => 'rgba(245,158,11,.14)', 'icon' => '🟠'];
        }
        if ($days >= 30) {
            return ['key' => 'moyen', 'label' => 'Moyen', 'color' => '#a16207', 'bg' => 'rgba(234,179,8,.12)', 'icon' => '🟡'];
        }
        return ['key' => 'faible', 'label' => 'Faible', 'color' => '#16a34a', 'bg' => 'rgba(34,197,94,.10)', 'icon' => '🟢'];
    }

    /** Libellé canal humain. */
    public static function canalLabel(string $canal): string
    {
        return match ($canal) {
            'telephone' => '📞 Téléphone',
            'email'     => '📧 Email',
            'whatsapp'  => '💬 WhatsApp',
            'visite'    => '🚶 Visite',
            'courrier'  => '✉ Courrier',
            default     => '📝 Autre',
        };
    }
}
