<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ScheduleGenerator — service dédié à la génération d'échéanciers (§6 cahier).
 *
 * Modes supportés :
 *
 *   1. PERSONNALISÉ PAR JALON (custom_milestones)
 *      L'utilisateur saisit N jalons libres :
 *        [
 *          ['label' => 'Acompte signature', 'due_date' => '2026-06-15', 'amount' => 5_000_000],
 *          ['label' => 'Solde fin pose',    'due_date' => '2026-07-30', 'amount' => 5_000_000],
 *        ]
 *      Total des jalons DOIT couvrir le total à payer (±1 FCFA tolérance).
 *
 *   2. TRIMESTRIEL CALENDAIRE (quarterly)
 *      Quatre échéances posées sur les 1er des mois suivants :
 *        01/01, 01/04, 01/07, 01/10
 *      du PREMIER quart calendaire >= issued_at (ou date paramétrée).
 *      Chaque échéance = total / 4 (le dernier corrige le solde pour
 *      éviter les écarts d'arrondi).
 *
 *   3. MENSUEL N ÉCHÉANCES (monthly)
 *      N mensualités libres à partir d'une date de départ, espacées d'un
 *      mois calendaire. Chaque mensualité = total / N (la dernière
 *      corrige les arrondis).
 *
 *   4. PRESETS RAPIDES (legacy : 30_70, 50_50, monthly_3)
 *      Conservés pour rétrocompat — délégués à generate() avec mode
 *      'milestones' et jalons pré-construits.
 *
 * Génération atomique : delete + insert dans une transaction. Si la
 * génération échoue (validation, somme incorrecte), l'échéancier
 * précédent est restauré (rollback).
 */
class ScheduleGenerator
{
    /**
     * @param Invoice $invoice
     * @param array{
     *   mode: 'custom_milestones'|'quarterly'|'monthly'|'30_70'|'50_50'|'monthly_3',
     *   start_date?: string,           // pour quarterly/monthly
     *   count?: int,                   // pour monthly (nb d'échéances)
     *   milestones?: array<int, array{label?:string, due_date:string, amount:int|float}> // pour custom_milestones
     * } $opts
     *
     * @return array Liste des InvoiceSchedule créés (frais).
     *
     * @throws \DomainException si validation foirée
     */
    public function generate(Invoice $invoice, array $opts): array
    {
        // L'échéancier planifie le SOLDE RESTANT DÛ, pas le total brut.
        // Si des acomptes ont déjà été encaissés, on ne les replanifie pas —
        // la modale UI et le backend doivent rester strictement alignés
        // pour éviter "somme des jalons ≠ total" alors que l'utilisateur
        // a saisi le bon solde.
        $total = (int) round($invoice->remainingAmount());

        // Si rien n'a encore été encaissé, remainingAmount = total_a_payer
        // (le cas normal au moment de la création initiale de l'échéancier).
        if ($total <= 0) {
            $brut = (int) ($invoice->total_a_payer ?: $invoice->amount_ttc ?: 0);
            if ($brut <= 0) {
                throw new \DomainException(
                    'Cette facture a un total de 0 — pas d\'échéancier possible.'
                );
            }
            throw new \DomainException(
                'Cette facture est déjà entièrement payée — plus rien à planifier.'
            );
        }

        $mode = $opts['mode'] ?? null;
        if (!$mode) throw new \DomainException('Mode d\'échéancier requis.');

        $schedules = match ($mode) {
            'custom_milestones' => $this->buildCustomMilestones($opts['milestones'] ?? [], $total),
            'quarterly'         => $this->buildQuarterly($total, $this->parseDate($opts['start_date'] ?? null, $invoice)),
            'monthly'           => $this->buildMonthly(
                $total,
                $this->parseDate($opts['start_date'] ?? null, $invoice),
                (int) ($opts['count'] ?? 0)
            ),
            // Presets legacy
            '30_70' => $this->buildLegacy30_70($total, $this->parseDate($opts['start_date'] ?? null, $invoice)),
            '50_50' => $this->buildLegacy50_50($total, $this->parseDate($opts['start_date'] ?? null, $invoice)),
            'monthly_3' => $this->buildMonthly(
                $total,
                $this->parseDate($opts['start_date'] ?? null, $invoice),
                3
            ),
            default => throw new \DomainException("Mode d'échéancier inconnu : '$mode'"),
        };

        if (empty($schedules)) {
            throw new \DomainException('Échéancier vide — impossible à générer.');
        }

        // Replace atomique (delete + create dans transaction)
        return DB::transaction(function () use ($invoice, $schedules) {
            $invoice->schedules()->delete();
            $created = [];
            foreach ($schedules as $i => $s) {
                $created[] = $invoice->schedules()->create([
                    'due_date'    => $s['due']->toDateString(),
                    'amount'      => (int) round($s['amount']),
                    'label'       => $s['label'],
                    'order_index' => $i,
                ]);
            }
            return $created;
        });
    }

    // ──────────────────────────────────────────────────────────────
    // CONSTRUCTEURS PAR MODE
    // ──────────────────────────────────────────────────────────────

    /**
     * Jalons libres : chacun a (label, due_date, amount).
     * Total des amounts doit matcher le total facture (±1 F).
     */
    protected function buildCustomMilestones(array $milestones, int $total): array
    {
        if (empty($milestones)) {
            throw new \DomainException('Au moins un jalon est requis.');
        }
        if (count($milestones) > 24) {
            throw new \DomainException('Maximum 24 jalons.');
        }

        $sum = 0;
        $out = [];
        foreach ($milestones as $i => $m) {
            $amount = (int) round((float) ($m['amount'] ?? 0));
            if ($amount <= 0) {
                throw new \DomainException("Le jalon #" . ($i + 1) . " a un montant invalide.");
            }
            $sum += $amount;
            $out[] = [
                'label'  => trim((string) ($m['label'] ?? "Jalon " . ($i + 1))),
                'due'    => Carbon::parse($m['due_date']),
                'amount' => $amount,
            ];
        }

        // Tolérance ±1 FCFA
        if (abs($sum - $total) > 1) {
            throw new \DomainException(
                'La somme des jalons (' . number_format($sum, 0, ',', ' ')
                . ' F) doit égaler le total à payer ('
                . number_format($total, 0, ',', ' ') . ' F).'
            );
        }

        // Réajuste le dernier jalon de quelques francs si arrondi
        if ($sum !== $total && !empty($out)) {
            $out[count($out) - 1]['amount'] += ($total - $sum);
        }

        // Tri par date d'échéance
        usort($out, fn($a, $b) => $a['due']->lt($b['due']) ? -1 : 1);
        return $out;
    }

    /**
     * Trimestriel calendaire 01/01, 01/04, 01/07, 01/10.
     * On démarre au 1er trimestre dont la date d'effet est >= $start.
     */
    protected function buildQuarterly(int $total, Carbon $start): array
    {
        // Trouve le prochain 01 des trimestres : 01/01, 01/04, 01/07, 01/10
        $quarters = [];
        $year = $start->year;
        foreach ([1, 4, 7, 10] as $m) {
            $quarters[] = Carbon::create($year, $m, 1);
        }
        foreach ([1, 4, 7, 10] as $m) {
            $quarters[] = Carbon::create($year + 1, $m, 1);
        }

        // Garde les 4 prochaines à partir de $start
        $next = array_values(array_filter($quarters, fn($q) => $q->gte($start->copy()->startOfDay())));
        $next = array_slice($next, 0, 4);

        if (count($next) < 4) {
            throw new \DomainException('Impossible de planifier 4 trimestres à partir de cette date.');
        }

        $part = (int) floor($total / 4);
        $last = $total - $part * 3; // corrige les arrondis

        $out = [];
        foreach ($next as $i => $q) {
            $out[] = [
                'label'  => 'Trimestre ' . ($i + 1) . ' (' . $q->format('01/m') . ')',
                'due'    => $q,
                'amount' => $i === 3 ? $last : $part,
            ];
        }
        return $out;
    }

    /**
     * Mensuel N échéances : N mensualités libres espacées d'un mois.
     */
    protected function buildMonthly(int $total, Carbon $start, int $n): array
    {
        if ($n < 2 || $n > 24) {
            throw new \DomainException('Le nombre de mensualités doit être entre 2 et 24.');
        }

        $part = (int) floor($total / $n);
        $last = $total - $part * ($n - 1);

        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = [
                'label'  => 'Mensualité ' . ($i + 1) . '/' . $n,
                'due'    => $start->copy()->addMonths($i),
                'amount' => $i === $n - 1 ? $last : $part,
            ];
        }
        return $out;
    }

    // ── Presets legacy (rétrocompat avec generateSchedule existant) ──
    protected function buildLegacy30_70(int $total, Carbon $start): array
    {
        $a = (int) round($total * 0.30);
        $b = $total - $a;
        return [
            ['label' => 'Acompte 30 %', 'due' => $start->copy(),               'amount' => $a],
            ['label' => 'Solde 70 %',   'due' => $start->copy()->addDays(30),  'amount' => $b],
        ];
    }

    protected function buildLegacy50_50(int $total, Carbon $start): array
    {
        $a = (int) round($total * 0.50);
        $b = $total - $a;
        return [
            ['label' => 'Acompte 50 %', 'due' => $start->copy(),               'amount' => $a],
            ['label' => 'Solde 50 %',   'due' => $start->copy()->addDays(30),  'amount' => $b],
        ];
    }

    protected function parseDate(?string $date, Invoice $invoice): Carbon
    {
        if ($date) return Carbon::parse($date);
        return $invoice->issued_at ? $invoice->issued_at->copy() : now();
    }
}
