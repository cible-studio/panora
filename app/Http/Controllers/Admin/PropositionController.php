<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\PropositionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PropositionController extends Controller
{
    public function __construct(
        protected PropositionService $propositionService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // ADMIN — liste
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $query = Reservation::with(['client', 'user'])
            ->whereNotNull('proposition_token')
            ->withCount('panels');

        if ($request->status)
            $query->where('status', $request->status);
        if ($request->search)
            $query->where(fn($q) =>
                $q->where('reference', 'like', "%{$request->search}%")
                  ->orWhereHas('client', fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            );

        $propositions = $query->orderByDesc('proposition_sent_at')->paginate(20)->withQueryString();
        return view('admin.propositions.index', compact('propositions'));
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — détail
    // ══════════════════════════════════════════════════════════════
    public function show(string $token)
    {
        try {
            $reservation = $this->propositionService->validerToken($token);
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.reservations.index')->with('error', $e->getMessage());
        }

        $reservation->loadMissing([
            'panels.photos', 'panels.commune', 'panels.zone', 'panels.format', 'panels.category',
            'externalPanels.commune', 'externalPanels.zone', 'externalPanels.format', 'externalPanels.category',
        ]);

        $this->propositionService->marquerVue($reservation);
        $months    = $this->monthsBetween($reservation->start_date, $reservation->end_date);
        $expiresIn = $reservation->proposition_expires_at
            ? now()->diffInHours($reservation->proposition_expires_at, false)
            : null;

        // proposalPanels() : projection unifiée internes + externes (cf. modèle).
        $panels = $reservation->proposalPanels($months);

        $joursRestants = now()->startOfDay()->diffInDays(
            $reservation->end_date->startOfDay(), false
        );

        return view('admin.propositions.show', compact(
            'reservation', 'panels', 'months', 'joursRestants', 'token', 'expiresIn'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — statut / PDF
    // ══════════════════════════════════════════════════════════════
    public function updateStatus(Request $request, Reservation $proposition)
    {
        $request->validate(['status' => 'required|in:en_attente,confirme,annule,refuse']);

        $newStatus = $request->status;

        // Confirmation admin → même logique que le flux client (campagne + sync + auto-cancel concurrents)
        // Couvre toutes les transitions → confirme (en_attente, refuse, annule), pas seulement en_attente.
        if ($newStatus === 'confirme' && $proposition->status->value !== 'confirme') {
            $proposition->loadMissing(['panels', 'externalPanels', 'campaign']);
            try {
                $this->propositionService->confirmer($proposition);
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            } catch (\Exception $e) {
                Log::error('admin.proposition.updateStatus.error', ['error' => $e->getMessage()]);
                return back()->with('error', 'Erreur lors de la confirmation.');
            }
            return back()->with('success', 'Proposition confirmée.');
        }

        // Pour annule/refuse/en_attente : mise à jour directe + sync panneaux
        $proposition->update(['status' => $newStatus]);

        if (in_array($newStatus, ['annule', 'refuse'])) {
            $availability = app(\App\Services\AvailabilityService::class);
            $panelIds = $proposition->panels->pluck('id')->toArray();
            if (!empty($panelIds)) {
                $availability->syncPanelStatuses($panelIds);
            }
            $extIds = $proposition->externalPanels->pluck('id')->toArray();
            if (!empty($extIds)) {
                $availability->syncExternalPanelStatuses($extIds);
            }
        }

        return back()->with('success', 'Statut mis à jour.');
    }

    public function exportPdf(Reservation $proposition)
    {
        $proposition->load([
            'client',
            'panels.photos', 'panels.commune', 'panels.zone', 'panels.format', 'panels.category',
            'externalPanels.commune', 'externalPanels.zone', 'externalPanels.format', 'externalPanels.category',
        ]);
        $months = $this->monthsBetween($proposition->start_date, $proposition->end_date);
        $panels = $proposition->proposalPanels($months);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.propositions.pdf', [
            'reservation' => $proposition,
            'panels'      => $panels,
            'months'      => $months,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("proposition-{$proposition->reference}.pdf");
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — Soumettre la proposition au commercial (MP)
    //
    // Le MP termine sa construction et clique "Soumettre au commercial".
    // La proposition passe en pending_send → apparaît dans l'inbox du
    // commercial qui pourra cliquer "Envoyer au client" (signe avec son
    // nom + ses coordonnées dans le mail).
    // ══════════════════════════════════════════════════════════════
    public function submitProposition(Request $request, Reservation $reservation)
    {
        $this->authorize('proposition.submit', $reservation);

        if (!$reservation->client) {
            return back()->with('error', 'Pas de client associé.');
        }
        if (empty($reservation->client->email)) {
            return back()->with('error', "Ce client n'a pas d'email — un commercial ne pourra pas envoyer.");
        }
        if ($reservation->panels()->count() === 0) {
            return back()->with('error', 'Ajoutez au moins un panneau avant de soumettre au commercial.');
        }

        $reservation->update([
            'proposition_status' => Reservation::PROPOSITION_PENDING_SEND,
        ]);

        \App\Services\AlertService::create(
            'proposition',
            'info',
            '📋 Proposition prête à envoyer — ' . $reservation->reference,
            auth()->user()->name . ' a soumis la proposition ' . $reservation->reference
                . ' (' . ($reservation->client->name ?? '?') . ') pour envoi au client.',
            $reservation
        );

        \Illuminate\Support\Facades\Log::info('proposition.submitted_to_commercial', [
            'reservation_id' => $reservation->id,
            'by'             => auth()->id(),
        ]);

        return back()->with('success',
            '✅ Proposition soumise. Le commercial peut maintenant l\'envoyer au client.');
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — envoyer proposition
    // ══════════════════════════════════════════════════════════════
    public function envoyerProposition(Request $request, Reservation $reservation)
    {
        // Helper interne : retour JSON si AJAX, redirect back() sinon.
        $respond = function (string $level, string $message, array $extra = []) use ($request) {
            if ($request->expectsJson() || $request->ajax()) {
                $httpStatus = match ($level) {
                    'success' => 200,
                    'warning' => 200,        // mail KO mais lien fourni — pas une vraie erreur HTTP
                    'error'   => 422,
                    default   => 200,
                };
                return response()->json(array_merge([
                    'success' => $level === 'success',
                    'level'   => $level,
                    'message' => $message,
                ], $extra), $httpStatus);
            }
            return back()->with($level, $message);
        };

        if (!$reservation->client)
            return $respond('error', 'Pas de client associé.');
        if ($reservation->client->trashed())
            return $respond('error', 'Client supprimé — envoi impossible.');
        if (empty($reservation->client->email))
            return $respond('error', "Ce client n'a pas d'email.");
        if (!in_array($reservation->status->value, ['en_attente', 'confirme']))
            return $respond('error', "Impossible d'envoyer pour une réservation {$reservation->status->value}.");

        // Générer token long (sécurité BD) + slug court (URL lisible)
        $token = $reservation->proposition_token ?? Str::random(64);
        $slug  = $reservation->proposition_slug  ?? Str::random(8);

        $reservation->update([
            'proposition_token'           => $token,
            'proposition_slug'            => $slug,
            'proposition_sent_at'         => now(),
            'proposition_status'          => Reservation::PROPOSITION_SENT,
            'proposition_expires_at'      => $this->computeExpirationDate($reservation),
            // Reset des flags rappels — un nouvel envoi redémarre le cycle
            // 7 jours, donc on doit pouvoir re-déclencher les rappels J+2/J+5
            'proposition_reminded_j2_at'  => null,
            'proposition_reminded_j5_at'  => null,
        ]);

        // Envoi via NotificationMailer.
        // sendNow() force l'envoi synchrone (bypass queue) → l'admin sait
        // immédiatement si le mail est parti, pas un faux positif "queued".
        $mailer = app(\App\Services\NotificationMailer::class);
        $result = $mailer->sendNow(
            $reservation->client->email,
            new \App\Mail\PropositionMail($reservation),
            context: [
                'action'         => 'proposition.sent',
                'reservation_id' => $reservation->id,
                'reference'      => $reservation->reference,
                'sent_by'        => auth()->id(),
            ]
        );

        if ($result->ok) {
            return $respond('success', "✅ Proposition envoyée à {$reservation->client->email}.");
        }

        // Échec → on donne à l'admin le lien public à partager manuellement
        $link = route('proposition.show', [$reservation->reference, $slug]);
        return $respond('warning',
            $result->message . ' Lien à partager manuellement : ' . $link,
            ['fallback_link' => $link]
        );
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — réinitialiser
    // ══════════════════════════════════════════════════════════════
    public function reinitialiserProposition(Reservation $reservation)
    {
        if (!$reservation->proposition_token)
            return back()->with('error', 'Aucune proposition active.');

        $reservation->update([
            'proposition_token'           => null,
            'proposition_slug'            => null,
            'proposition_sent_at'         => null,
            'proposition_expires_at'      => null,
            'proposition_viewed_at'       => null,
            'proposition_reminded_j2_at'  => null,
            'proposition_reminded_j5_at'  => null,
        ]);

        return back()->with('success', 'Proposition réinitialisée. Le lien précédent ne fonctionne plus.');
    }

    // ══════════════════════════════════════════════════════════════
    // PUBLIC — afficher proposition (nouvelle URL)
    // GET /proposition/{reference}/{slug}
    // ══════════════════════════════════════════════════════════════
    public function showPublic(string $reference, string $slug)
    {
        // Double vérification : reference + slug doivent matcher
        $reservation = Reservation::where('reference', $reference)
            ->where('proposition_slug', $slug)
            ->whereNotNull('proposition_token')
            ->with(['client',
                    'panels.photos', 'panels.commune', 'panels.zone', 'panels.format', 'panels.category',
                    'externalPanels.commune', 'externalPanels.zone', 'externalPanels.format', 'externalPanels.category'])
            ->first();

        if (!$reservation) {
            abort(404, 'Proposition introuvable ou lien invalide.');
        }

        $this->propositionService->marquerVue($reservation);

        $months = $this->monthsBetween($reservation->start_date, $reservation->end_date);
        $panels = $reservation->proposalPanels($months);

        $expiresIn = null;
        if ($reservation->proposition_expires_at) {
            $expiresIn = (int) now()->diffInHours($reservation->proposition_expires_at, false);
        }

        // La proposition est-elle encore active ?
        $isExpired = $expiresIn !== null && $expiresIn <= 0;
        $isActif   = !$isExpired
            && in_array($reservation->status->value, ['en_attente']);

        return view('admin.propositions.show', compact(
            'reservation', 'panels', 'months',
            'reference', 'slug', 'expiresIn', 'isExpired', 'isActif'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // PUBLIC — confirmer
    // ══════════════════════════════════════════════════════════════
    public function confirmer(string $reference, string $slug)
    {
        $reservation = $this->findBySlug($reference, $slug);
        if (!$reservation)
            abort(404);

        // ✋ Garde commune : status + expiration + duplicate check
        if ($block = $this->assertActionable($reservation, $reference, $slug, 'confirmer')) {
            return $block;
        }

        try {
            $campaign = $this->propositionService->confirmer($reservation);
        } catch (\RuntimeException $e) {
            Log::warning('admin.propositions.conflict', ['reservation' => $reference, 'error' => $e->getMessage()]);
            return redirect()->route('proposition.show', [$reference, $slug])
                ->with('error', 'Ce panneau a déjà été confirmé par une autre proposition. Veuillez contacter votre commercial pour trouver une alternative.');
        } catch (\Exception $e) {
            Log::error('admin.propositions.error', ['error' => $e->getMessage()]);
            return redirect()->route('proposition.show', [$reference, $slug])
                ->with('error', 'Erreur lors de la confirmation. Contactez votre commercial.');
        }

        $reservation = $reservation->fresh(['client', 'panels', 'user']);
        $this->notifyDecision($reservation, \App\Mail\PropositionDecisionMail::DECISION_ACCEPTED, null, $campaign?->name);

        // Alerte in-app pour le commercial concerné (en plus du mail)
        \App\Services\AlertService::create(
            'reservation',
            'success',
            '✅ Proposition acceptée — ' . ($reservation->client?->name ?? 'Client'),
            sprintf(
                'Le client a accepté la proposition %s. Consultez votre boîte mail pour les détails complets et créez la campagne dans la foulée.',
                $reservation->reference
            ),
            $reservation
        );

        return view('admin.propositions.confirmed', [
            'reservation' => $reservation,
            'client'      => $reservation->client,
            'campaign'    => $campaign,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // PUBLIC — refuser
    // ══════════════════════════════════════════════════════════════
    public function refuser(Request $request, string $reference, string $slug)
    {
        $reservation = $this->findBySlug($reference, $slug);
        if (!$reservation)
            abort(404);

        // ✋ Même garde que confirmer
        if ($block = $this->assertActionable($reservation, $reference, $slug, 'refuser')) {
            return $block;
        }

        $motif      = trim((string) $request->input('motif', '')) ?: null;
        // reason_code n'est persisté que s'il est dans la liste blanche
        // (validation finale dans PropositionService::refuser pour rester
        // tolérant aux formulaires legacy qui n'envoient pas ce champ).
        $reasonCode = $request->input('reason_code') ?: null;

        $this->propositionService->refuser($reservation, $motif, $reasonCode);

        $reservation = $reservation->fresh(['client', 'panels', 'user']);
        $this->notifyDecision(
            $reservation,
            \App\Mail\PropositionDecisionMail::DECISION_REFUSED,
            $motif,
            null,
            $reservation->refus_reason_code
        );

        // Alerte in-app pour le commercial — niveau "warning" car action de
        // suivi possible (relance, ajustement, replanification…).
        $reasonLabel = $reservation->refus_reason_code
            ? (Reservation::REFUS_REASONS[$reservation->refus_reason_code] ?? null)
            : null;

        \App\Services\AlertService::create(
            'reservation',
            'warning',
            '❌ Proposition refusée — ' . ($reservation->client?->name ?? 'Client'),
            sprintf(
                'Le client a refusé la proposition %s%s%s. Consultez votre boîte mail et envisagez une relance.',
                $reservation->reference,
                $reasonLabel ? ' (' . $reasonLabel . ')' : '',
                $motif ? ' — message client joint' : ''
            ),
            $reservation
        );

        return view('admin.propositions.refused', [
            'reservation' => $reservation,
            'client'      => $reservation->client,
        ]);
    }

    /**
     * Garde commune pour confirmer / refuser / retirer-panneau.
     *
     * Empêche d'agir sur une proposition :
     *   - déjà traitée (statut ≠ en_attente)
     *   - expirée (date < maintenant)
     *   - sans token (proposition jamais envoyée)
     *
     * Retourne null si OK, ou une RedirectResponse vers la page show
     * si l'action doit être bloquée.
     */
    private function assertActionable(\App\Models\Reservation $reservation, string $reference, string $slug, string $action = 'agir'): mixed
    {
        // Statut : la réservation doit être en attente d'une décision
        if ($reservation->status->value !== 'en_attente') {
            $stateLabel = match ($reservation->status->value) {
                'confirme' => 'déjà confirmée',
                'refuse'   => 'déjà refusée',
                'annule'   => 'annulée',
                'termine'  => 'terminée',
                default    => 'dans un état non modifiable (' . $reservation->status->value . ')',
            };

            Log::warning('proposition.action.blocked.status', [
                'reservation_id' => $reservation->id,
                'reference'      => $reference,
                'action'         => $action,
                'current_status' => $reservation->status->value,
            ]);

            return redirect()->route('proposition.show', [$reference, $slug])
                ->with('error', "Cette proposition est {$stateLabel}. Impossible de la {$action}.");
        }

        // Expiration : si proposition_expires_at est passée → bloqué
        if ($reservation->proposition_expires_at && $reservation->proposition_expires_at->isPast()) {
            $expiredAt = $reservation->proposition_expires_at->format('d/m/Y à H:i');

            Log::warning('proposition.action.blocked.expired', [
                'reservation_id' => $reservation->id,
                'reference'      => $reference,
                'action'         => $action,
                'expired_at'     => $reservation->proposition_expires_at->toIso8601String(),
            ]);

            return redirect()->route('proposition.show', [$reference, $slug])
                ->with('error', "Cette proposition a expiré le {$expiredAt}. Contactez votre commercial pour en recevoir une nouvelle.");
        }

        // Token absent : la proposition n'a jamais été émise
        if (empty($reservation->proposition_token)) {
            Log::warning('proposition.action.blocked.no_token', [
                'reservation_id' => $reservation->id,
                'reference'      => $reference,
                'action'         => $action,
            ]);
            abort(404, 'Lien invalide.');
        }

        return null; // OK pour agir
    }

    /**
     * Notifie par mail le commercial qui a créé la proposition.
     * Si pas de user_id sur la réservation → fallback : tous les admins actifs.
     * Échec d'envoi silencieux (le client a déjà fait sa décision, on ne casse rien).
     */
    private function notifyDecision(\App\Models\Reservation $reservation, string $decision, ?string $reason = null, ?string $campaignName = null, ?string $reasonCode = null): void
    {
        $mailer = app(\App\Services\NotificationMailer::class);

        $recipients = [];
        if ($reservation->user?->email && filter_var($reservation->user->email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $reservation->user->email;
        }

        // Fallback : si le créateur n'existe plus / pas d'email, on prévient les admins
        if (empty($recipients)) {
            $recipients = \App\Models\User::query()
                ->where('is_active', true)
                ->where('role', 'admin')
                ->pluck('email')
                ->filter(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
                ->all();
        }

        if (empty($recipients)) {
            Log::warning('proposition.decision.no_recipient', [
                'reservation_id' => $reservation->id,
                'decision'       => $decision,
            ]);
            return;
        }

        $mailer->sendSilently(
            $recipients,
            new \App\Mail\PropositionDecisionMail($reservation, $decision, $reason, $campaignName, $reasonCode),
            context: [
                'action'         => 'proposition.decision',
                'reservation_id' => $reservation->id,
                'decision'       => $decision,
                'reason_code'    => $reasonCode,
            ]
        );
    }

    // ══════════════════════════════════════════════════════════════
    // PUBLIC — retirer un panneau de la proposition
    // DELETE /proposition/{reference}/{slug}/panneau/{panelId}
    // ══════════════════════════════════════════════════════════════
    public function retirerPanneau(string $reference, string $slug, int $panelId)
    {
        $reservation = $this->findBySlug($reference, $slug);
        if (!$reservation) abort(404);

        // ✋ Même garde : status + expiration
        if ($block = $this->assertActionable($reservation, $reference, $slug, 'retirer un panneau de')) {
            return $block;
        }

        // Vérifier que le panneau appartient bien à cette réservation
        if (!$reservation->panels->contains('id', $panelId))
            abort(403, 'Panneau non trouvé dans cette proposition.');

        // Empêcher de retirer le dernier panneau
        if ($reservation->panels->count() <= 1)
            return redirect()->route('proposition.show', [$reference, $slug])
                ->with('error', 'Impossible de retirer le dernier panneau.');

        // Retirer le panneau
        $reservation->panels()->detach($panelId);

        // Recalculer le total
        $months   = $this->monthsBetween($reservation->start_date, $reservation->end_date);
        $newTotal = $reservation->panels()
            ->get()
            ->sum(fn($p) => (float) ($p->pivot->total_price ?? ($p->monthly_rate * $months)));

        $reservation->update(['total_amount' => $newTotal]);

        Log::info('admin.propositions.panneau_retire', [
            'reservation_id' => $reservation->id,
            'panel_id'       => $panelId,
        ]);

        return redirect()->route('proposition.show', [$reference, $slug])
            ->with('success', 'Panneau retiré de la proposition.');
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

    private function findBySlug(string $reference, string $slug): ?Reservation
    {
        return Reservation::where('reference', $reference)
            ->where('proposition_slug', $slug)
            ->whereNotNull('proposition_token')
            ->with(['client', 'panels.photos', 'panels.commune',
                    'panels.zone', 'panels.format', 'panels.category',
                    'externalPanels.commune', 'externalPanels.zone',
                    'externalPanels.format', 'externalPanels.category'])
            ->first();
    }

    /**
     * Calcule la date d'expiration d'une proposition.
     *
     * Règle métier CIBLE CI : 7 jours de validité par défaut, capés à
     * `end_date` (la propal ne peut jamais survivre à la fin de la
     * réservation — sinon le client clique sur un lien obsolète).
     *
     * Les rappels J+2 et J+5 sont envoyés par la commande
     * `propositions:send-reminders` (cron daily 09:00).
     */
    private function computeExpirationDate(Reservation $reservation): Carbon
    {
        $defaultExpiration = now()->addDays(7);
        $endOfPeriod       = $reservation->end_date->copy()->endOfDay();

        return $defaultExpiration->lt($endOfPeriod) ? $defaultExpiration : $endOfPeriod;
    }

    private function monthsBetween($start, $end): float
    {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();

        // Nombre de jours réels
        $totalDays = (int) $s->diffInDays($e);

        if ($totalDays <= 0) return 0.5;

        // Mois entiers
        $fullMonths = (int) floor($totalDays / 30);

        // Jours restants après les mois entiers
        $remainDays = $totalDays % 30;

        // Règle CIBLE CI :
        // 1-15j restants  → + 0.5 mois
        // 16-30j restants → + 1 mois
        $fraction = 0;
        if ($remainDays >= 1 && $remainDays <= 15) {
            $fraction = 0.5;
        } elseif ($remainDays > 15) {
            $fraction = 1;
        }

        $result = $fullMonths + $fraction;

        // Minimum : 0.5 mois (demi-mois)
        return max($result, 0.5);
    }

}