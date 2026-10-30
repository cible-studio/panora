<?php
// ══════════════════════════════════════════════════════════════════════
// app/Console/Commands/HandleQuoteExpiry.php
//
// Cycle de vie automatique des devis commerciaux :
//
//   1. Devis ENVOYE / EN_NEGOCIATION dont expires_at est aujourd'hui → J-3
//      Note : nous alertons "dans 3 jours" quand expires_at == today+3.
//   2. Devis ENVOYE / EN_NEGOCIATION dont expires_at < today → statut EXPIRE
//      + alerte finale au commercial.
//
// Les devis en statut BROUILLON, ACCEPTE, ACCEPTE_AVEC_CONFLIT, REFUSE,
// EXPIRE ou ARCHIVE sont ignorés — leur cycle est terminé.
//
// Chaque devis expiré déclenche AUSSI la libération éventuelle des
// panneaux réservés en amont ? Non — un devis NE réserve PAS de
// panneaux (c'est la différence fondamentale avec Reservation type
// option). Rien à libérer, seule la vitrine bascule.
// ══════════════════════════════════════════════════════════════════════

namespace App\Console\Commands;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Services\AlertService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HandleQuoteExpiry extends Command
{
    protected $signature   = 'quotes:handle-expiry';
    protected $description = 'Alerte les devis qui expirent dans 3 jours et bascule en EXPIRE ceux qui sont dépassés';

    public function handle(): int
    {
        $today  = Carbon::today();
        $inJ3   = $today->copy()->addDays(3);

        // ── 1) Devis expirant DANS 3 JOURS ─────────────────────────
        //     (alerte non bloquante au commercial pour relance client)
        $j3Query = Quote::query()
            ->whereIn('status', [
                QuoteStatus::ENVOYE->value,
                QuoteStatus::EN_NEGOCIATION->value,
            ])
            ->whereDate('expires_at', $inJ3->toDateString())
            ->with(['client', 'commercial']);

        $j3Count = 0;
        foreach ($j3Query->get() as $quote) {
            AlertService::notify(
                'devis_expire_bientot',
                '⏰ Devis expire dans 3 jours — ' . $quote->reference,
                'Le devis ' . $quote->reference . ' pour ' . ($quote->client?->name ?? '—')
                    . ' expire le ' . $quote->expires_at->format('d/m/Y')
                    . '. Pense à relancer le client.',
                $quote,
                [
                    'user_id'     => $quote->commercial_user_id,
                    'lien'        => route('admin.quotes.show', $quote->id),
                    'dedup_extra' => $quote->expires_at->format('Y-m-d'),
                ]
            );
            $j3Count++;
        }

        // ── 2) Devis EXPIRÉS (date de validité passée) ─────────────
        $expiredQuery = Quote::query()
            ->whereIn('status', [
                QuoteStatus::ENVOYE->value,
                QuoteStatus::EN_NEGOCIATION->value,
            ])
            ->whereDate('expires_at', '<', $today->toDateString())
            ->with(['client', 'commercial']);

        $expiredCount = 0;
        foreach ($expiredQuery->get() as $quote) {
            $quote->update(['status' => QuoteStatus::EXPIRE->value]);

            AlertService::notify(
                'devis_expire',
                '⌛ Devis expiré — ' . $quote->reference,
                'Le devis ' . $quote->reference . ' pour ' . ($quote->client?->name ?? '—')
                    . ' a expiré le ' . $quote->expires_at->format('d/m/Y') . ' sans décision.'
                    . ' Tu peux le dupliquer pour repartir sur une nouvelle version.',
                $quote,
                [
                    'user_id' => $quote->commercial_user_id,
                    'lien'    => route('admin.quotes.show', $quote->id),
                ]
            );

            Log::info('quote.auto_expired', [
                'quote_id'   => $quote->id,
                'reference'  => $quote->reference,
                'expires_at' => $quote->expires_at->format('Y-m-d'),
                'commercial' => $quote->commercial?->name,
            ]);
            $expiredCount++;
        }

        $this->info("Devis alertés (J-3) : {$j3Count}. Devis basculés en EXPIRE : {$expiredCount}.");
        return Command::SUCCESS;
    }
}
