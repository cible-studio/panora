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

        $this->propositionService->marquerVue($reservation);
        $months    = $this->monthsBetween($reservation->start_date, $reservation->end_date);
        $expiresIn = $reservation->proposition_expires_at
            ? now()->diffInHours($reservation->proposition_expires_at, false)
            : null;

        $panels = $this->buildPanels($reservation, $months);

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
        $proposition->update(['status' => $request->status]);
        return back()->with('success', 'Statut mis à jour.');
    }

    public function exportPdf(Reservation $proposition)
    {
        $proposition->load(['client', 'panels.photos', 'panels.commune', 'panels.format', 'panels.category']);
        $months = $this->monthsBetween($proposition->start_date, $proposition->end_date);
        $panels = $this->buildPanels($proposition, $months);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.propositions.pdf', [
            'reservation' => $proposition,
            'panels'      => $panels,
            'months'      => $months,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("proposition-{$proposition->reference}.pdf");
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
            'proposition_token'      => $token,
            'proposition_slug'       => $slug,
            'proposition_sent_at'    => now(),
            'proposition_expires_at' => now()->addDays(30),
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
            'proposition_token'      => null,
            'proposition_slug'       => null,
            'proposition_sent_at'    => null,
            'proposition_expires_at' => null,
            'proposition_viewed_at'  => null,
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
            ->with(['client', 'panels.photos', 'panels.commune',
                    'panels.zone', 'panels.format', 'panels.category'])
            ->first();

        if (!$reservation) {
            abort(404, 'Proposition introuvable ou lien invalide.');
        }

        $this->propositionService->marquerVue($reservation);

        $months = $this->monthsBetween($reservation->start_date, $reservation->end_date);
        $panels = $this->buildPanels($reservation, $months);

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

        try {
            $token    = $reservation->proposition_token;
            $campaign = $this->propositionService->confirmer($reservation);
        } catch (\Exception $e) {
            Log::error('admin.propositions.error', ['error' => $e->getMessage()]);
            return redirect()->route('admin.propositions.show', [$reference, $slug])
                ->with('error', 'Erreur lors de la confirmation. Contactez votre commercial.');
        }

        $reservation = $reservation->fresh(['client', 'panels', 'user']);
        $this->notifyDecision($reservation, \App\Mail\PropositionDecisionMail::DECISION_ACCEPTED);

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

        $motif = $request->input('motif');
        $this->propositionService->refuser($reservation, $motif);

        $reservation = $reservation->fresh(['client', 'panels', 'user']);
        $this->notifyDecision($reservation, \App\Mail\PropositionDecisionMail::DECISION_REFUSED, $motif);

        return view('admin.propositions.refused', [
            'reservation' => $reservation,
            'client'      => $reservation->client,
        ]);
    }

    /**
     * Notifie par mail le commercial qui a créé la proposition.
     * Si pas de user_id sur la réservation → fallback : tous les admins actifs.
     * Échec d'envoi silencieux (le client a déjà fait sa décision, on ne casse rien).
     */
    private function notifyDecision(\App\Models\Reservation $reservation, string $decision, ?string $reason = null): void
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
            new \App\Mail\PropositionDecisionMail($reservation, $decision, $reason),
            context: [
                'action'         => 'proposition.decision',
                'reservation_id' => $reservation->id,
                'decision'       => $decision,
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

        if (!$reservation || $reservation->status->value !== 'en_attente')
            abort(403, 'Impossible de modifier cette proposition.');

        // Vérifier que le panneau appartient bien à cette réservation
        if (!$reservation->panels->contains('id', $panelId))
            abort(403, 'Panneau non trouvé dans cette proposition.');

        // Empêcher de retirer le dernier panneau
        if ($reservation->panels->count() <= 1)
            return redirect()->route('admin.propositions.show', [$reference, $slug])
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

        return redirect()->route('admin.propositions.show', [$reference, $slug])
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
                    'panels.zone', 'panels.format', 'panels.category'])
            ->first();
    }

    private function buildPanels(Reservation $reservation, float $months): \Illuminate\Support\Collection
    {
        return $reservation->panels->map(function ($panel) use ($months) {
            $photo = $panel->photos->sortBy('ordre')->first();
            return [
                'id'           => $panel->id,
                'reference'    => $panel->reference,
                'name'         => $panel->name,
                'commune'      => $panel->commune?->name ?? '—',
                'zone'         => $panel->zone?->name    ?? '—',
                'format'       => $panel->format?->name  ?? '—',
                'dimensions'   => $this->formatDims($panel->format),
                'category'     => $panel->category?->name ?? '—',
                'is_lit'       => (bool) $panel->is_lit,
                // ← Utiliser le prix pivot (négocié) si disponible
                'monthly_rate' => (float) ($panel->pivot->unit_price  ?? $panel->monthly_rate ?? 0),
                'total'        => (float) ($panel->pivot->total_price ?? ($panel->monthly_rate ?? 0) * $months),
                'photo_url'    => $photo
                    ? asset('storage/' . ltrim($photo->path, '/'))
                    : null,
            ];
        });
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

    private function formatDims($format): ?string
    {
        if (!$format?->width || !$format?->height) return null;
        $w = rtrim(rtrim(number_format($format->width, 2, '.', ''), '0'), '.');
        $h = rtrim(rtrim(number_format($format->height, 2, '.', ''), '0'), '.');
        return "{$w}×{$h}m";
    }
}