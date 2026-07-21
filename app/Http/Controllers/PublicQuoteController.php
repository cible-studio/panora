<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Services\AlertService;
use App\Services\QuoteConverter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PublicQuoteController — devis accessibles via lien public à token.
 *
 * Utilisé pour les clients SANS COMPTE (prospect email uniquement).
 * URL type : https://panora-cible.com/devis/{token 64hex}
 *
 * Protections :
 *   - Throttle 20 req/min/IP (attaques brute-force sur les tokens)
 *   - Token 256 bits unique = quasi impossible à deviner
 *   - Aucun listing possible (pas d'index public)
 *   - Devis expiré ou brouillon → 410 Gone (pas d'exposition)
 */
class PublicQuoteController extends Controller
{
    /** Résout un token en Quote OU 404 si introuvable/pas envoyé. */
    protected function resolveOrFail(string $token): Quote
    {
        $quote = Quote::where('public_token', $token)
            ->with(['client', 'commercial', 'lines', 'services'])
            ->first();

        abort_if(!$quote, 404, 'Devis introuvable.');

        // On accepte uniquement les statuts consultables publiquement.
        abort_if($quote->status === QuoteStatus::BROUILLON, 410,
            'Ce devis n\'a pas encore été envoyé.'
        );
        abort_if($quote->status === QuoteStatus::ARCHIVE, 410,
            'Ce devis a été archivé.'
        );

        return $quote;
    }

    public function show(string $token)
    {
        $quote = $this->resolveOrFail($token);
        return view('public.quote-show', ['quote' => $quote, 'isPublic' => true]);
    }

    public function pdf(string $token)
    {
        $quote = $this->resolveOrFail($token);
        $pdf = Pdf::loadView('pdf.quote', ['quote' => $quote])->setPaper('A4', 'portrait');
        return $pdf->download("devis-{$quote->reference}.pdf");
    }

    public function accept(Request $request, string $token, QuoteConverter $converter)
    {
        $quote = $this->resolveOrFail($token);
        $this->assertCanDecide($quote);

        try {
            $result = $converter->convertFromQuote($quote, decidedBy: 'public:' . $request->ip());
        } catch (\Throwable $e) {
            Log::error('quote.public.accept.failed', [
                'quote_id' => $quote->id, 'ip' => $request->ip(), 'error' => $e->getMessage(),
            ]);
            return back()->with('error',
                "Nous n'avons pas pu enregistrer votre acceptation : " . $e->getMessage()
                . ' Contactez ' . ($quote->commercial?->name ?? 'votre commercial CIBLE') . '.'
            );
        }

        return redirect()->route('public.quote.show', $token)
            ->with($result['status'] === 'converted' ? 'success' : 'warning',
                $result['status'] === 'converted'
                    ? 'Devis accepté ! Votre commercial vous contactera pour la suite.'
                    : "Devis accepté, mais certains panneaux ne sont plus disponibles. Votre commercial va vous contacter."
            );
    }

    public function refuse(Request $request, string $token)
    {
        $quote = $this->resolveOrFail($token);
        $this->assertCanDecide($quote);
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);

        $quote->update([
            'status'          => QuoteStatus::REFUSE->value,
            'decision_at'     => now(),
            'decision_reason' => $data['reason'] ?? null,
        ]);

        AlertService::create('devis', 'warning',
            '❌ Devis refusé — ' . $quote->reference,
            ($quote->client?->name ?? 'Client') . ' a refusé le devis ' . $quote->reference
            . ($data['reason'] ? " (motif: {$data['reason']})" : ''),
            $quote
        );

        return redirect()->route('public.quote.show', $token)
            ->with('info', 'Votre refus a été enregistré. Merci de nous avoir considérés.');
    }

    protected function assertCanDecide(Quote $quote): void
    {
        abort_unless($quote->status === QuoteStatus::ENVOYE, 403,
            'Ce devis n\'est plus dans un état permettant une décision.'
        );
        abort_if($quote->isExpired(), 410,
            'Ce devis a expiré. Contactez ' . ($quote->commercial?->name ?? 'votre commercial CIBLE') . '.'
        );
    }
}
