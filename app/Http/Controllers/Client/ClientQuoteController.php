<?php

namespace App\Http\Controllers\Client;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Services\AlertService;
use App\Services\QuoteConverter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Espace client — consultation et décision sur les devis reçus.
 *
 * Contexte 2 flux :
 *   1. Client avec compte : /client/devis (auth requise, filtre client_id)
 *   2. Client sans compte : /devis/{token} (route publique, throttle IP)
 *
 * Ce controller gère UNIQUEMENT le flux authentifié.
 * Le flux public est dans App\Http\Controllers\PublicQuoteController.
 */
class ClientQuoteController extends Controller
{
    /** Liste des devis du client authentifié. */
    public function index()
    {
        $client = Auth::guard('client')->user();

        $quotes = Quote::where('client_id', $client->id)
            // Le client ne voit pas les brouillons (interne au commercial)
            ->whereNotIn('status', [QuoteStatus::BROUILLON->value, QuoteStatus::ARCHIVE->value])
            ->with('commercial:id,name,email,whatsapp_number')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('client.quotes.index', compact('client', 'quotes'));
    }

    /** Détail d'un devis (accessible seulement si appartient au client authentifié). */
    public function show(Quote $quote)
    {
        abort_unless($this->appartientAuClient($quote), 403, 'Ce devis ne vous appartient pas.');

        $quote->load(['commercial:id,name,email,whatsapp_number', 'lines', 'services']);

        return view('client.quotes.show', ['quote' => $quote, 'isPublic' => false]);
    }

    /** Télécharger le PDF d'un devis client. */
    public function pdf(Quote $quote)
    {
        abort_unless($this->appartientAuClient($quote), 403);

        $quote->load(['client', 'commercial', 'lines', 'services']);
        $pdf = Pdf::loadView('pdf.quote', ['quote' => $quote])->setPaper('A4', 'portrait');
        return $pdf->download("devis-{$quote->reference}.pdf");
    }

    /** Accepter un devis. Déclenche la conversion en réservation via QuoteConverter. */
    public function accept(Request $request, Quote $quote, QuoteConverter $converter)
    {
        $this->assertCanDecide($quote);

        try {
            $result = $converter->convertFromQuote($quote, decidedBy: 'client:' . $this->clientConnecteId());
        } catch (\Throwable $e) {
            Log::error('quote.accept.failed', [
                'quote_id' => $quote->id, 'error' => $e->getMessage(),
            ]);
            return back()->with('error',
                "Impossible d'enregistrer votre acceptation : " . $e->getMessage()
                . ' Veuillez contacter votre commercial ' . ($quote->commercial?->name ?? '') . '.'
            );
        }

        if ($result['status'] === 'converted') {
            return redirect()->route('client.devis.show', $quote)
                ->with('success', 'Devis accepté ! Une réservation a été créée. Votre commercial vous contactera pour la suite.');
        }

        return redirect()->route('client.devis.show', $quote)
            ->with('warning', "Devis accepté, mais certains panneaux ne sont plus disponibles. Votre commercial ({$quote->commercial?->name}) va vous contacter pour ajuster.");
    }

    /** Refuser un devis avec motif. */
    public function refuse(Request $request, Quote $quote)
    {
        $this->assertCanDecide($quote);
        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $quote->update([
            'status'          => QuoteStatus::REFUSE->value,
            'decision_at'     => now(),
            'decision_reason' => $data['reason'] ?? null,
        ]);

        AlertService::notify(
            'devis_refuse',
            '❌ Devis refusé — ' . $quote->reference,
            ($quote->client?->name ?? '') . ' a refusé le devis ' . $quote->reference
            . ($data['reason'] ? " (motif: {$data['reason']})" : ''),
            $quote,
            [
                'user_id' => $quote->commercial_user_id,
                'lien'    => route('admin.quotes.show', $quote->id),
            ]
        );

        return redirect()->route('client.devis.show', $quote)
            ->with('info', 'Votre refus a été enregistré. Merci de nous avoir considérés.');
    }

    /** Demander une modification (négociation). */
    public function requestModification(Request $request, Quote $quote)
    {
        $this->assertCanDecide($quote);
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $quote->update([
            'status'          => QuoteStatus::EN_NEGOCIATION->value,
            'decision_at'     => now(),
            'decision_reason' => $data['reason'],
        ]);

        AlertService::notify(
            'devis_en_negociation',
            '💬 Demande de modification — ' . $quote->reference,
            ($quote->client?->name ?? '') . ' demande une modification du devis ' . $quote->reference . ' : ' . $data['reason'],
            $quote,
            [
                'user_id' => $quote->commercial_user_id,
                'lien'    => route('admin.quotes.show', $quote->id),
            ]
        );

        return redirect()->route('client.devis.show', $quote)
            ->with('success', 'Votre demande a été transmise à votre commercial. Il vous recontactera pour ajuster le devis.');
    }

    // ─── HELPERS ──────────────────────────────────────────────────

    /**
     * Client connecté (2026-10-30) : même source que les autres écrans de
     * l'espace client, le guard 'client'. La clé de session `client_id`
     * lue auparavant n'était écrite nulle part : la liste était vide et
     * chaque devis renvoyait 403. Un membre d'équipe (ClientUser) est
     * connecté sur la fiche de son entreprise : il voit donc ses devis.
     */
    protected function clientConnecteId(): int
    {
        return (int) Auth::guard('client')->id();
    }

    /** Le devis est-il celui du client connecté ? (jamais celui d'un autre) */
    protected function appartientAuClient(Quote $quote): bool
    {
        $clientId = $this->clientConnecteId();

        return $clientId > 0 && (int) $quote->client_id === $clientId;
    }

    protected function assertCanDecide(Quote $quote): void
    {
        abort_unless($this->appartientAuClient($quote), 403);
        abort_unless($quote->status === QuoteStatus::ENVOYE, 403,
            'Ce devis n\'est plus dans un état permettant une décision.'
        );
        abort_if($quote->isExpired(), 403,
            'Ce devis a expiré. Contactez votre commercial pour une nouvelle proposition.'
        );
    }
}
