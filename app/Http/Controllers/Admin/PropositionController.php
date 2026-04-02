<?php

namespace App\Http\Controllers\Admin;

use App\Services\PropositionService;
use App\Http\Controllers\Controller;    
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PropositionController — Page publique accessible via token uniquement.
 * Pas d'authentification — le token EST l'authentification.
 *
 * Routes (dans routes/admin.php, AVANT le groupe auth) :
 *   GET  /proposition/{token}            → show()
 *   POST /proposition/{token}/confirmer  → confirmer()
 *   POST /proposition/{token}/refuser    → refuser()
 */
class PropositionController extends Controller
{
    public function __construct(
        protected PropositionService $propositionService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // SHOW
    // ══════════════════════════════════════════════════════════════

    public function show(string $token)
    {
        try {
            $reservation = $this->propositionService->validerToken($token);
        } catch (\RuntimeException $e) {
            return $this->pageErreur($e->getMessage());
        }

        $this->propositionService->marquerVue($reservation);

        $months = $this->monthsBetween($reservation->start_date, $reservation->end_date);
        // Calcul du temps restant avant expiration de la proposition (en heures)
        $expiresIn  = $reservation->proposition_expires_at
            ? now()->diffInHours($reservation->proposition_expires_at, false)
            : null;

        $panels = $reservation->panels->map(function ($panel) use ($months) {
            $photo = $panel->photos->sortBy('ordre')->first();
            return [
                'id'           => $panel->id,
                'reference'    => $panel->reference,
                'name'         => $panel->name,
                'commune'      => $panel->commune?->name ?? '—',
                'zone'         => $panel->zone?->name ?? '—',
                'format'       => $panel->format?->name ?? '—',
                'dimensions'   => $this->formatDims($panel->format),
                'category'     => $panel->category?->name ?? '—',
                'is_lit'       => (bool) $panel->is_lit,
                'monthly_rate' => (float) ($panel->monthly_rate ?? 0),
                'total'        => (float) ($panel->monthly_rate ?? 0) * $months,
                'photo_url'    => $photo
                    ? asset('storage/' . ltrim($photo->path, '/'))
                    : null,
            ];
        });

        // Jours restants basé sur end_date (pas d'expires_at)
        $joursRestants = now()->startOfDay()->diffInDays(
            $reservation->end_date->startOfDay(), false
        );

        return view('admin.propositions.show', compact(
            'reservation', 'panels', 'months', 'joursRestants', 'token', 'expiresIn'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // CONFIRMER
    // ══════════════════════════════════════════════════════════════

    public function confirmer(Request $request, string $token)
    {
        // Re-valider le token au moment du POST
        try {
            $reservation = $this->propositionService->validerToken($token);
        } catch (\RuntimeException $e) {
            $rawCode = explode(':', $e->getMessage())[0];

            // Si déjà confirmée → afficher la page success directement
            if ($rawCode === 'ALREADY_CONFIRMED') {
                $reservation = Reservation::where('proposition_token', $token)
                    ->with(['client', 'panels', 'campaign'])
                    ->first();
                if ($reservation) {
                    return view('admin.propositions.confirmed', [
                        'reservation' => $reservation,
                        'campaign'    => $reservation->campaign,
                        'client'      => $reservation->client,
                    ]);
                }
            }

            return redirect()
                ->route('proposition.show', $token)
                ->with('error', $this->codeToMessage($rawCode));
        }

        $result = $this->propositionService->confirmer($reservation);

        if (!$result['ok']) {
            $errorMsg = match($result['reason']) {
                'panels_taken'      => 'Certains panneaux viennent d\'être réservés par un autre client. Notre équipe vous contactera avec d\'autres disponibilités.',
                'already_processed' => 'Cette proposition a déjà été traitée.',
                default             => 'Une erreur est survenue. Veuillez contacter notre équipe.',
            };

            return redirect()
                ->route('proposition.show', $token)
                ->with('error', $errorMsg);
        }

        return view('admin.propositions.confirmed', [
            'reservation' => $result['reservation'],
            'campaign'    => $result['campaign'],
            'client'      => $result['reservation']->client,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // REFUSER
    // ══════════════════════════════════════════════════════════════

    public function refuser(Request $request, string $token)
    {

        //dd("REFUSER - token: $token, motif: " . $request->input('motif', ''));
        try {
            $reservation = $this->propositionService->validerToken($token);
        } catch (\RuntimeException $e) {
            $rawCode = explode(':', $e->getMessage())[0];
            return redirect()
                ->route('proposition.show', $token)
                ->with('error', $this->codeToMessage($rawCode));
        }

        $motif = $request->input('motif', '');
        $this->propositionService->refuser($reservation, $motif);

        return view('admin.propositions.refused', [
            'reservation' => $reservation,
            'client'      => $reservation->client,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════════

    private function pageErreur(string $code)
    {
        $rawCode = explode(':', $code)[0];
        [$title, $message, $type] = match($rawCode) {
            'TOKEN_INVALID'     => ['Lien invalide',        'Ce lien de proposition est invalide ou n\'existe pas.', 'warning'],
            'TOKEN_EXPIRED'     => ['Proposition expirée',  'La période de campagne proposée est dépassée. Contactez notre équipe commerciale.', 'warning'],
            'ALREADY_CONFIRMED' => ['Déjà confirmée',       'Vous avez déjà confirmé cette proposition. Merci !', 'success'],
            'ALREADY_REFUSED'   => ['Proposition refusée',  'Cette proposition a été refusée ou annulée.', 'info'],
            default             => ['Erreur',               'Une erreur est survenue.', 'error'],
        };

        return view('admin.propositions.error', compact('title', 'message', 'type'));
    }

    private function codeToMessage(string $rawCode): string
    {
        return match($rawCode) {
            'TOKEN_EXPIRED'     => 'La période de cette proposition est dépassée.',
            'ALREADY_CONFIRMED' => 'Cette proposition a déjà été confirmée.',
            'ALREADY_REFUSED'   => 'Cette proposition a déjà été refusée ou annulée.',
            'TOKEN_INVALID'     => 'Lien invalide.',
            default             => 'Une erreur est survenue.',
        };
    }

    private function monthsBetween($start, $end): float
    {
        $s      = \Carbon\Carbon::parse($start)->startOfDay();
        $e      = \Carbon\Carbon::parse($end)->endOfDay();
        $months = (int) $s->diffInMonths($e);
        $remain = $s->copy()->addMonths($months)->diffInDays($e);
        return max((float) ($remain > 0 ? $months + 1 : $months), 1.0);
    }

    private function formatDims($format): ?string
    {
        if (!$format?->width || !$format?->height) return null;
        $w = rtrim(rtrim(number_format($format->width, 2, '.', ''), '0'), '.');
        $h = rtrim(rtrim(number_format($format->height, 2, '.', ''), '0'), '.');
        return "{$w}×{$h}m";
    }
}