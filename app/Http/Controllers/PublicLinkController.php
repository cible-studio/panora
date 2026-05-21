<?php

namespace App\Http\Controllers;

use App\Services\PublicLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PublicLinkController — point d'entrée unique pour les liens publics
 * envoyés par email (factures, piges, etc.).
 *
 * URL : GET /p/{token}
 *
 * Sécurité :
 *   - Throttle 20/min/IP sur route (cf. routes/web.php)
 *   - Vérification expiration / révocation / usage max via isUsable()
 *   - Audit consigné dans public_links + canal log 'public_link'
 *   - Headers Cache-Control no-store sur les pages sensibles
 *
 * Dispatch par type :
 *   invoice       → view factures publiques
 *   pige          → view pige photo client
 *   reservation   → view réservation confirmée
 *   decap         → view récap décappage
 */
class PublicLinkController extends Controller
{
    public function show(string $token, Request $request)
    {
        $link = PublicLinkService::resolve($token, $request);

        if (!$link) {
            // Token inexistant — page 404 générique (ne fuite pas l'info)
            return response()->view('public.link-invalid', [
                'reason' => 'Lien introuvable.',
            ], 404);
        }

        if (!$link->isUsable()) {
            Log::info('public_link.refused', [
                'id'     => $link->id,
                'type'   => $link->type,
                'reason' => $link->statusReason(),
                'ip'     => $request->ip(),
            ]);
            return response()->view('public.link-invalid', [
                'reason' => $link->statusReason() ?? 'Lien indisponible.',
            ], 410); // Gone
        }

        // Dispatch selon le type
        $view = match ($link->type) {
            'invoice'       => $this->showInvoice($link),
            'pige'          => $this->showPige($link),
            'pige_bundle'   => $this->showPigeBundle($link),
            'reservation'   => $this->showReservation($link),
            'decap'         => $this->showDecap($link),
            default         => response()->view('public.link-invalid', [
                'reason' => 'Type de lien non géré.',
            ], 400),
        };

        // Headers anti-cache sur pages sensibles (facture/paiement)
        if (in_array($link->type, ['invoice', 'invoice_pay'], true)) {
            $view->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $view->header('Pragma', 'no-cache');
        }

        return $view;
    }

    // ── Dispatchers par type ─────────────────────────────────────────

    protected function showInvoice($link)
    {
        $invoice = $link->target;
        if (!$invoice) {
            return response()->view('public.link-invalid', [
                'reason' => 'Document supprimé.',
            ], 410);
        }
        return response()->view('public.invoice', [
            'invoice' => $invoice->loadMissing('client', 'items', 'reservation'),
            'link'    => $link,
        ]);
    }

    protected function showPige($link)
    {
        $pige = $link->target;
        if (!$pige) {
            return response()->view('public.link-invalid', [
                'reason' => 'Pige supprimée.',
            ], 410);
        }
        return response()->view('public.pige', [
            'pige' => $pige->loadMissing('panel', 'campaign.client'),
            'link' => $link,
        ]);
    }

    protected function showPigeBundle($link)
    {
        $campaign = $link->target;
        if (!$campaign) {
            return response()->view('public.link-invalid', ['reason' => 'Campagne introuvable.'], 410);
        }
        return response()->view('public.pige-bundle', [
            'campaign' => $campaign->loadMissing('client', 'panels'),
            'link'     => $link,
        ]);
    }

    protected function showReservation($link)
    {
        $reservation = $link->target;
        if (!$reservation) {
            return response()->view('public.link-invalid', ['reason' => 'Réservation introuvable.'], 410);
        }
        return response()->view('public.reservation', [
            'reservation' => $reservation->loadMissing('client', 'reservationPanels.panel.commune'),
            'link'        => $link,
        ]);
    }

    protected function showDecap($link)
    {
        $campaign = $link->target;
        if (!$campaign) {
            return response()->view('public.link-invalid', ['reason' => 'Campagne introuvable.'], 410);
        }
        return response()->view('public.decap', [
            'campaign' => $campaign->loadMissing('client', 'panels.commune'),
            'link'     => $link,
        ]);
    }
}
