<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Contrôle de cohérence du tableau de bord financier (2026-10-29).
 *
 * LECTURE SEULE : cette commande ne modifie rien. Elle compare les
 * sources de chiffres entre elles et signale ce qui ne concorde pas, ce
 * qu'aucun écran ne peut montrer puisque chacun ne voit que sa propre
 * moitié du problème.
 *
 * Lancer :  php artisan finance:verifier
 *           php artisan finance:verifier --annee=2026
 */
class VerifierFinance extends Command
{
    protected $signature = 'finance:verifier
        {--annee= : Restreindre aux factures émises cette année-là}';

    protected $description = 'Contrôle de cohérence des chiffres du tableau de bord financier (lecture seule)';

    /** Statuts écartés des totaux par la page Finance. */
    private const ECARTES = ['brouillon', 'annulee'];

    private int $anomalies = 0;

    public function handle(): int
    {
        $annee = $this->option('annee');

        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║  Contrôle de cohérence — tableau de bord financier       ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->line($annee ? "Périmètre : factures émises en {$annee}." : 'Périmètre : toutes les factures.');
        $this->newLine();

        $this->parStatut($annee);
        $this->montantsADeduire($annee);
        $this->avoirs($annee);
        $this->restantDu($annee);
        $this->surPaiements($annee);
        $this->clientsManquants($annee);

        $this->newLine();
        if ($this->anomalies === 0) {
            $this->info('✅ Aucune incohérence détectée.');
        } else {
            $this->warn("⚠  {$this->anomalies} point(s) à regarder — détail ci-dessus.");
        }

        return self::SUCCESS;
    }

    private function base(?string $annee)
    {
        return Invoice::query()
            ->when($annee, fn ($q) => $q->whereYear('issued_at', $annee));
    }

    private function signaler(string $message): void
    {
        $this->anomalies++;
        $this->warn('   ⚠  ' . $message);
    }

    // ══════════════════════════════════════════════════════════════

    /**
     * Combien de factures dans chaque statut, et pour quel montant.
     *
     * Sert à trancher une question restée ouverte : une facture
     * « Générée » ou « Validée » n'est pas encore partie chez le client,
     * mais la page Finance la compte dans « Facturé » et dans « Dû ».
     */
    private function parStatut(?string $annee): void
    {
        $this->line('── 1. Factures par statut ─────────────────────────────────');

        $lignes = $this->base($annee)
            ->whereNull('credit_note_for_id')
            ->selectRaw('status, COUNT(*) AS nb, SUM(' . Invoice::SQL_MONTANT_TOTAL . ') AS total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        if ($lignes->isEmpty()) {
            $this->line('   Aucune facture.');
            $this->newLine();

            return;
        }

        $this->table(
            ['Statut', 'Nb', 'Montant total (FCFA)', 'Compté par Finance ?'],
            $lignes->map(fn ($l) => [
                Invoice::statusLabel($l->status),
                $l->nb,
                number_format((float) $l->total, 0, ',', ' '),
                in_array($l->status, self::ECARTES, true) ? 'non' : 'OUI',
            ])->all()
        );

        $aTrancher = $lignes->whereIn('status', ['generee', 'validee']);
        if ($aTrancher->isNotEmpty()) {
            $montant = $aTrancher->sum(fn ($l) => (float) $l->total);
            $this->signaler(sprintf(
                '%s facture(s) « Générée / Validée » pour %s FCFA sont comptées comme facturées'
                . ' alors qu\'elles ne sont pas encore parties chez le client. Règle à confirmer.',
                $aTrancher->sum('nb'),
                number_format($montant, 0, ',', ' ')
            ));
        }
        $this->newLine();
    }

    /**
     * Factures dont total_a_payer vaut 0 alors que amount_ttc est
     * renseigné : elles comptaient pour zéro dans les sommes SQL qui
     * n'appliquaient pas le repli de Invoice::montantTotal().
     */
    private function montantsADeduire(?string $annee): void
    {
        $this->line('── 2. Montant de référence (total_a_payer vs amount_ttc) ──');

        $suspectes = $this->base($annee)
            ->where(fn ($q) => $q->whereNull('total_a_payer')->orWhere('total_a_payer', '<=', 0))
            ->where('amount_ttc', '>', 0)
            ->get(['id', 'reference', 'status', 'total_a_payer', 'amount_ttc', 'issued_at']);

        if ($suspectes->isEmpty()) {
            $this->line('   ✔ Toutes les factures ont un total_a_payer renseigné.');
            $this->newLine();

            return;
        }

        $this->table(
            ['Référence', 'Statut', 'total_a_payer', 'amount_ttc', 'Émise le'],
            $suspectes->map(fn ($f) => [
                $f->reference,
                $f->status,
                (int) $f->total_a_payer,
                number_format((float) $f->amount_ttc, 0, ',', ' '),
                $f->issued_at?->format('d/m/Y') ?? '—',
            ])->all()
        );

        $this->signaler(sprintf(
            '%s facture(s) pour %s FCFA. Avant le 2026-10-29 elles comptaient pour ZÉRO dans'
            . ' les totaux de la page Finance, et pour leur vrai montant sur leur propre ligne.',
            $suspectes->count(),
            number_format($suspectes->sum(fn ($f) => (float) $f->amount_ttc), 0, ',', ' ')
        ));
        $this->newLine();
    }

    /** Les avoirs faussent les totaux : ils doivent être écartés partout. */
    private function avoirs(?string $annee): void
    {
        $this->line('── 3. Avoirs ──────────────────────────────────────────────');

        $avoirs = $this->base($annee)->whereNotNull('credit_note_for_id')
            ->get(['id', 'reference', 'status', 'total_a_payer', 'amount_ttc']);

        if ($avoirs->isEmpty()) {
            $this->line('   ✔ Aucun avoir.');
            $this->newLine();

            return;
        }

        $total = $avoirs->sum(fn ($f) => $f->montantTotal());
        $this->line(sprintf('   %d avoir(s), %s FCFA au total.',
            $avoirs->count(), number_format($total, 0, ',', ' ')));
        $this->line('   Écartés de tous les totaux (Finance et liste des factures).');
        $this->newLine();
    }

    /**
     * Le « Restant » de la liste des factures sommait le montant FACTURÉ
     * sans déduire les versements. Cette section chiffre l'écart.
     */
    private function restantDu(?string $annee): void
    {
        $this->line('── 4. Restant dû : ancienne formule vs réelle ─────────────');

        $ouvertes = $this->base($annee)
            ->whereIn('status', ['envoyee', 'partiellement_payee', 'en_retard'])
            ->whereNull('credit_note_for_id')
            ->with(['payments:id,invoice_id,montant', 'schedules:id,invoice_id,due_date,paid_at'])
            ->get();

        if ($ouvertes->isEmpty()) {
            $this->line('   Aucune facture ouverte.');
            $this->newLine();

            return;
        }

        $ancienne = $ouvertes->sum(fn (Invoice $f) => $f->montantTotal());
        $reelle   = $ouvertes->sum(fn (Invoice $f) => $f->remainingAmount());
        $verse    = $ancienne - $reelle;

        $this->table(['Mesure', 'FCFA'], [
            ['Montant facturé (ancienne formule)', number_format($ancienne, 0, ',', ' ')],
            ['Déjà versé',                         number_format($verse, 0, ',', ' ')],
            ['Reste réellement à encaisser',       number_format($reelle, 0, ',', ' ')],
        ]);

        if ($verse > 0.5) {
            $this->signaler(sprintf(
                'La liste des factures annonçait %s FCFA de « Restant » au lieu de %s :'
                . ' %s FCFA déjà encaissés n\'étaient pas déduits. Corrigé le 2026-10-29.',
                number_format($ancienne, 0, ',', ' '),
                number_format($reelle, 0, ',', ' '),
                number_format($verse, 0, ',', ' ')
            ));
        }
        $this->newLine();
    }

    /** Versements supérieurs au montant dû : saisie en double probable. */
    private function surPaiements(?string $annee): void
    {
        $this->line('── 5. Versements supérieurs au montant dû ─────────────────');

        $factures = $this->base($annee)
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->whereNull('credit_note_for_id')
            ->with('payments:id,invoice_id,montant')
            ->get();

        $trop = $factures->filter(fn (Invoice $f) => $f->paidAmount() > $f->montantTotal() + 1);

        if ($trop->isEmpty()) {
            $this->line('   ✔ Aucun sur-paiement.');
            $this->newLine();

            return;
        }

        $this->table(
            ['Référence', 'Dû', 'Versé', 'Écart'],
            $trop->map(fn (Invoice $f) => [
                $f->reference,
                number_format($f->montantTotal(), 0, ',', ' '),
                number_format($f->paidAmount(), 0, ',', ' '),
                number_format($f->paidAmount() - $f->montantTotal(), 0, ',', ' '),
            ])->all()
        );

        $this->signaler($trop->count() . ' facture(s) encaissée(s) au-delà du montant dû — à vérifier.');
        $this->newLine();
    }

    /** Une facture sans client rattaché n'apparaît dans aucun recouvrement. */
    private function clientsManquants(?string $annee): void
    {
        $this->line('── 6. Rattachement client ─────────────────────────────────');

        $orphelines = $this->base($annee)
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->whereNull('credit_note_for_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('clients')
                ->whereColumn('clients.id', 'invoices.client_id'))
            ->get(['id', 'reference', 'client_id', 'total_a_payer', 'amount_ttc']);

        if ($orphelines->isEmpty()) {
            $this->line('   ✔ Toutes les factures actives ont un client existant.');
            $this->newLine();

            return;
        }

        $this->table(
            ['Référence', 'client_id', 'Montant'],
            $orphelines->map(fn (Invoice $f) => [
                $f->reference, $f->client_id ?? '—',
                number_format($f->montantTotal(), 0, ',', ' '),
            ])->all()
        );

        $this->signaler(sprintf(
            '%s facture(s) sans client existant, pour %s FCFA : elles comptent dans le « Dû »'
            . ' mais n\'apparaissent dans aucune ligne de recouvrement.',
            $orphelines->count(),
            number_format($orphelines->sum(fn (Invoice $f) => $f->montantTotal()), 0, ',', ' ')
        ));
        $this->newLine();
    }
}
