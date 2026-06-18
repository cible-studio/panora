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

    /** Libellé résultat (outcome) humain. */
    public static function outcomeLabel(?string $outcome): string
    {
        return match ($outcome) {
            'promesse_paiement' => '✅ Promesse de paiement',
            'paiement_recu'     => '💰 Paiement reçu',
            'desaccord'         => '⚠ Désaccord',
            'sans_reponse'      => '📵 Sans réponse',
            'a_relancer'        => '🔁 À relancer',
            'autre'             => '📝 Autre',
            default             => '—',
        };
    }

    /**
     * Liste paginée des relances d'un client (Bloc 3 — Famille D, 2026-06-18).
     * Utilisée par l'onglet "Relances" sur la fiche client + page Recouvrement.
     *
     * @param array{from?:string,to?:string,outcome?:string,canal?:string,invoice_id?:int} $filters
     */
    public function listByClient(int $clientId, array $filters = [], int $perPage = 20)
    {
        return Relance::query()
            ->where('client_id', $clientId)
            ->when(!empty($filters['from']),    fn ($q) => $q->whereDate('relance_date', '>=', $filters['from']))
            ->when(!empty($filters['to']),      fn ($q) => $q->whereDate('relance_date', '<=', $filters['to']))
            ->when(!empty($filters['outcome']), fn ($q) => $q->where('outcome', $filters['outcome']))
            ->when(!empty($filters['canal']),   fn ($q) => $q->where('canal', $filters['canal']))
            ->when(!empty($filters['invoice_id']), fn ($q) => $q->where('invoice_id', $filters['invoice_id']))
            ->with([
                'invoice:id,reference,total_a_payer,status',
                'schedule:id,invoice_id,due_date,amount',
                'user:id,name',
            ])
            ->orderByDesc('relance_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Stats rapides pour le bandeau "Relances client" (compteurs).
     * Bloc 3 — Famille D, 2026-06-18.
     */
    public function statsByClient(int $clientId): array
    {
        $rows = Relance::query()
            ->where('client_id', $clientId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN outcome = "promesse_paiement" THEN 1 ELSE 0 END) as promesses,
                SUM(CASE WHEN outcome = "a_relancer" THEN 1 ELSE 0 END) as a_relancer,
                SUM(CASE WHEN outcome = "sans_reponse" THEN 1 ELSE 0 END) as sans_reponse,
                MAX(relance_date) as derniere
            ')
            ->first();

        return [
            'total'        => (int) ($rows->total ?? 0),
            'promesses'    => (int) ($rows->promesses ?? 0),
            'a_relancer'   => (int) ($rows->a_relancer ?? 0),
            'sans_reponse' => (int) ($rows->sans_reponse ?? 0),
            'derniere'     => $rows->derniere ?? null,
        ];
    }
}
