<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Source UNIQUE de vérité pour le statut des échéances de paiement
 * (invoice_schedules) d'une facture.
 *
 * RÈGLE MÉTIER (validée mission BUG_ECHEANCIER phase 2) :
 *   FIFO avec débordement + état "partielle" intermédiaire.
 *
 *   1. Cumul = somme de TOUS les invoice_payments.montant de la facture.
 *   2. Distribution FIFO : on remplit la 1re échéance (due_date ASC,
 *      order_index ASC) jusqu'à concurrence de son amount, l'excédent
 *      déborde sur la suivante.
 *   3. Statut dérivé par échéance :
 *        - paid_amount == 0           → "À venir"      (state 'upcoming'/'soon'/'overdue')
 *        - 0 < paid_amount < amount   → "Partielle X %" (state 'partial')
 *        - paid_amount == amount      → "Soldée"        (state 'paid', paid_at posé)
 *   4. paid_at + paid_payment_id ne sont posés QUE si l'échéance est
 *      entièrement couverte (sinon paid_at reste NULL → les jobs J-7/J-3
 *      continuent d'envoyer leurs rappels).
 *
 * Cette méthode est appelée :
 *   - après chaque création de versement (PaymentService::register)
 *   - après chaque suppression de versement (PaymentService::delete)
 *   - par la commande artisan `schedules:recompute-all` pour le recalcul
 *     rétroactif des factures historiques.
 *
 * Aucun marquage manuel d'échéance n'est plus toléré : InvoiceController
 * ::markScheduleEntryPaid est désactivé. Le statut DÉRIVE des versements.
 */
class ScheduleAllocationService
{
    /**
     * Recalcule from-scratch l'état de tous les schedules d'une facture
     * à partir du cumul de ses versements (FIFO).
     *
     * Retourne un descriptif des changements opérés (utile pour le dry-run
     * de la commande artisan).
     *
     * @return array{
     *   schedules_total: int,
     *   schedules_changed: int,
     *   diffs: array<int, array{
     *     schedule_id: int,
     *     label: string,
     *     amount: int,
     *     before: array{paid_amount: int, paid_at: ?string, state: string},
     *     after:  array{paid_amount: int, paid_at: ?string, state: string},
     *   }>,
     * }
     */
    public function recomputeFromPayments(Invoice $invoice): array
    {
        return DB::transaction(function () use ($invoice) {
            $schedules = $invoice->schedules()
                ->orderBy('due_date')
                ->orderBy('order_index')
                ->get();

            if ($schedules->isEmpty()) {
                return ['schedules_total' => 0, 'schedules_changed' => 0, 'diffs' => []];
            }

            // Snapshot AVANT (utilisé pour le diff retourné)
            $before = $schedules->mapWithKeys(fn($s) => [$s->id => [
                'paid_amount'     => (int) $s->paid_amount,
                'paid_at'         => $s->paid_at?->toDateString(),
                'paid_payment_id' => $s->paid_payment_id,
            ]])->all();

            $payments = $invoice->payments()
                ->orderBy('paid_at')
                ->orderBy('id')
                ->get();

            // Algo pur (testable sans DB)
            $scheduleInputs = $schedules->map(fn($s) => [
                'id'     => $s->id,
                'amount' => (int) $s->amount,
            ])->all();
            $paymentInputs = $payments->map(fn($p) => [
                'id'      => $p->id,
                'montant' => (int) $p->montant,
                'paid_at' => $p->paid_at instanceof \DateTimeInterface
                            ? $p->paid_at->toDateString()
                            : (string) $p->paid_at,
            ])->all();
            $allocations = self::computeAllocations($scheduleInputs, $paymentInputs);

            // Persiste les changements
            $changed = 0;
            $diffs   = [];
            foreach ($schedules as $sch) {
                $alloc = $allocations[$sch->id];
                $newAmount = $alloc['paid_amount'];
                $newPaidAt = $alloc['paid_at'];
                $newPmtId  = $alloc['paid_payment_id'];

                $hasChanged = (
                       (int) $before[$sch->id]['paid_amount']    !== $newAmount
                    ||      $before[$sch->id]['paid_at']          !== $newPaidAt
                    || (int) $before[$sch->id]['paid_payment_id'] !== (int) ($newPmtId ?? 0)
                );

                if ($hasChanged) {
                    $sch->paid_amount     = $newAmount;
                    $sch->paid_at         = $newPaidAt;
                    $sch->paid_payment_id = $newPmtId;
                    $sch->save();
                    $changed++;
                }

                $diffs[] = [
                    'schedule_id' => $sch->id,
                    'label'       => $sch->label ?? 'Échéance',
                    'amount'      => (int) $sch->amount,
                    'before' => [
                        'paid_amount' => (int) $before[$sch->id]['paid_amount'],
                        'paid_at'     => $before[$sch->id]['paid_at'],
                        'state'       => self::deriveState((int) $before[$sch->id]['paid_amount'], (int) $sch->amount, $before[$sch->id]['paid_at']),
                    ],
                    'after' => [
                        'paid_amount' => $newAmount,
                        'paid_at'     => $newPaidAt,
                        'state'       => self::deriveState($newAmount, (int) $sch->amount, $newPaidAt),
                    ],
                    'changed' => $hasChanged,
                ];
            }

            if ($changed > 0) {
                Log::info('schedules.recomputed', [
                    'invoice_id' => $invoice->id,
                    'reference'  => $invoice->reference,
                    'changed'    => $changed,
                    'total'      => $schedules->count(),
                ]);
            }

            return [
                'schedules_total'   => $schedules->count(),
                'schedules_changed' => $changed,
                'diffs'             => $diffs,
            ];
        });
    }

    /**
     * Algo pur d'allocation FIFO — testable sans BDD.
     *
     * @param array<int, array{id:int, amount:int}> $schedules    Triés due_date ASC / order_index ASC
     * @param array<int, array{id:int, montant:int, paid_at:string}> $payments   Triés paid_at ASC / id ASC
     * @return array<int, array{paid_amount:int, paid_at:?string, paid_payment_id:?int}>
     *         Indexé par schedule_id.
     */
    public static function computeAllocations(array $schedules, array $payments): array
    {
        $result = [];
        foreach ($schedules as $s) {
            $result[$s['id']] = [
                'paid_amount'     => 0,
                'paid_at'         => null,
                'paid_payment_id' => null,
            ];
        }

        foreach ($payments as $p) {
            $budget = (int) $p['montant'];
            if ($budget <= 0) continue;

            foreach ($schedules as $s) {
                $sid       = $s['id'];
                $remaining = (int) $s['amount'] - $result[$sid]['paid_amount'];
                if ($remaining <= 0) continue;

                $impute = min($budget, $remaining);
                $result[$sid]['paid_amount'] += $impute;
                $budget                       -= $impute;

                if ($result[$sid]['paid_amount'] >= (int) $s['amount']) {
                    $result[$sid]['paid_at']         = $p['paid_at'];
                    $result[$sid]['paid_payment_id'] = $p['id'];
                }

                if ($budget <= 0) break;
            }
        }

        return $result;
    }

    /** Dérive le state textuel ('paid'/'partial'/'upcoming') sans BDD. */
    public static function deriveState(int $paidAmount, int $amount, ?string $paidAt): string
    {
        if ($amount <= 0) return 'upcoming';
        if ($paidAmount >= $amount && $paidAt !== null) return 'paid';
        if ($paidAmount > 0) return 'partial';
        return 'upcoming';
    }
}
