<?php
// ══════════════════════════════════════════════════════════════════
// app/Http/Controllers/Admin/PropositionController.php
//
// Contient TOUTES les méthodes liées aux propositions :
//   - index, show, updateStatus, exportPdf  (admin CRUD)
//   - envoyerProposition                    (depuis reservation/show)
//   - reinitialiserProposition              (annuler l'envoi)
//   - show (public), confirmer, refuser     (côté client public)
// ══════════════════════════════════════════════════════════════════

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
    // ADMIN — liste des propositions
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $query = Reservation::with(['client', 'user'])
            ->whereNotNull('proposition_token')
            ->withCount('panels');

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $query->where(fn($q) =>
                $q->where('reference', 'like', "%{$request->search}%")
                  ->orWhereHas('client', fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            );
        }

        $propositions = $query->orderByDesc('proposition_sent_at')->paginate(20)->withQueryString();

        return view('admin.propositions.index', compact('propositions'));
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — détail d'une proposition
    // ══════════════════════════════════════════════════════════════

    // Method App\Http\Controllers\Admin\PropositionController::pageErreur does not exist.
    // corrige en remplace par une message flash et redirection vers tableau de bord ou liste des propositions
    public function show(string $token)
    {
        try {
            $reservation = $this->propositionService->validerToken($token);
        } catch (\RuntimeException $e) {
           return redirect()->route('client.dashboard')->with('error', $e->getMessage());
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
    // ADMIN — changer le statut d'une proposition
    // ══════════════════════════════════════════════════════════════

    public function updateStatus(Request $request, Reservation $proposition)
    {
        $request->validate(['status' => 'required|in:en_attente,confirme,annule,refuse']);
        $proposition->update(['status' => $request->status]);
        return back()->with('success', 'Statut mis à jour.');
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — exporter une proposition en PDF
    // ══════════════════════════════════════════════════════════════

    public function exportPdf(Reservation $proposition)
    {
        $proposition->load(['client', 'panels.photos', 'panels.commune', 'panels.format', 'panels.category']);

        $months = $this->monthsBetween($proposition->start_date, $proposition->end_date);
        $panels = $proposition->panels->map(function ($panel) use ($months) {
            $photo = $panel->photos->sortBy('ordre')->first();
            return [
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
                'photo_url'    => $photo ? asset('storage/' . ltrim($photo->path, '/')) : null,
            ];
        });

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.propositions.pdf', [
            'reservation' => $proposition,
            'panels'      => $panels,
            'months'      => $months,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("proposition-{$proposition->reference}.pdf");
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — ENVOYER une proposition à un client
    // POST /admin/reservations/{reservation}/proposition/envoyer
    // ══════════════════════════════════════════════════════════════

    public function envoyerProposition(Request $request, Reservation $reservation)
    {
        // Vérifications métier
        if (!$reservation->client) {
            return back()->with('error', 'Cette réservation n\'a pas de client associé.');
        }
        if ($reservation->client->trashed()) {
            return back()->with('error', 'Client supprimé — envoi impossible.');
        }
        if (empty($reservation->client->email)) {
            return back()->with('error', "Ce client n'a pas d'email. Ajoutez-en un d'abord.");
        }
        if (!in_array($reservation->status->value, ['en_attente', 'confirme'])) {
            return back()->with('error', "Impossible d'envoyer une proposition pour une réservation {$reservation->status->value}.");
        }

        // Si déjà une proposition envoyée, on régénère le token
        $token = $reservation->proposition_token ?? Str::random(64);

        $reservation->update([
            'proposition_token'   => $token,
            'proposition_sent_at' => now(),
            'proposition_expires_at' => now()->addDays(30),
        ]);

        // Envoi email
        try {
            \Mail::to($reservation->client->email)->send(
                new \App\Mail\PropositionMail($reservation, $token)
            );

            Log::info('proposition.sent', [
                'reservation_id' => $reservation->id,
                'reference'      => $reservation->reference,
                'client_id'      => $reservation->client_id,
                'client_email'   => $reservation->client->email,
                'user_id'        => auth()->id(),
            ]);

            return back()->with('success', "✅ Proposition envoyée à {$reservation->client->email}.");

        } catch (\Exception $e) {
            Log::error('proposition.send_failed', [
                'reservation_id' => $reservation->id,
                'error'          => $e->getMessage(),
            ]);

            // Token généré mais email échoué → on retourne le lien quand même
            $link = route('proposition.show', $token);
            return back()->with('warning', "⚠️ Erreur email. Lien de proposition : {$link}");
        }
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — RÉINITIALISER une proposition (supprimer le token)
    // POST /admin/reservations/{reservation}/proposition/reinitialiser
    // ══════════════════════════════════════════════════════════════

    public function reinitialiserProposition(Reservation $reservation)
    {
        if (!$reservation->proposition_token) {
            return back()->with('error', 'Aucune proposition active à réinitialiser.');
        }

        $reservation->update([
            'proposition_token'      => null,
            'proposition_sent_at'    => null,
            'proposition_expires_at' => null,
            'proposition_viewed_at'  => null,
        ]);

        Log::info('proposition.reset', [
            'reservation_id' => $reservation->id,
            'user_id'        => auth()->id(),
        ]);

        return back()->with('success', 'Proposition réinitialisée. Le lien précédent ne fonctionne plus.');
    }

    // ══════════════════════════════════════════════════════════════
    // PUBLIC — afficher la proposition (côté client, lien email)
    // GET /proposition/{token}
    // ══════════════════════════════════════════════════════════════

    public function showPublic(string $token)
    {
        try {
            $reservation = $this->propositionService->validerToken($token);
        } catch (\RuntimeException $e) {
            // Proposition expirée ou traitée — on affiche quand même en lecture seule
            $reservation = Reservation::where('proposition_token', $token)->firstOrFail();
        }

        $this->propositionService->marquerVue($reservation);

        $months = $this->monthsBetween($reservation->start_date, $reservation->end_date);

        $panels = $reservation->panels->map(function ($panel) use ($months) {
            $photo = $panel->photos->sortBy('ordre')->first();
            return [
                'reference'    => $panel->reference,
                'name'         => $panel->name,
                'commune'      => $panel->commune?->name ?? '—',
                'zone'         => $panel->zone?->name    ?? '—',
                'format'       => $panel->format?->name  ?? '—',
                'dimensions'   => $this->formatDims($panel->format),
                'category'     => $panel->category?->name ?? '—',
                'is_lit'       => (bool) $panel->is_lit,
                'monthly_rate' => (float) ($panel->monthly_rate ?? 0),
                'total'        => (float) ($panel->monthly_rate ?? 0) * $months,
                'photo_url'    => $photo ? asset('storage/' . ltrim($photo->path, '/')) : null,
            ];
        });

        // Calcul expiration
        $expiresIn = null;
        if ($reservation->proposition_expires_at) {
            $expiresIn = (int) now()->diffInHours($reservation->proposition_expires_at, false);
        }

        return view('proposition.show', compact('reservation', 'panels', 'months', 'token', 'expiresIn'));
    }

    // ══════════════════════════════════════════════════════════════
    // PUBLIC — confirmer la proposition
    // POST /proposition/{token}/confirmer
    // ══════════════════════════════════════════════════════════════

    public function confirmer(string $token)
    {
        try {
            $reservation = $this->propositionService->validerToken($token);
        } catch (\RuntimeException $e) {
            return redirect()->route('proposition.show', $token)
                ->with('error', $e->getMessage());
        }

        $campaign = null;

        try {
            $campaign = $this->propositionService->confirmer($reservation);
        } catch (\Exception $e) {
            Log::error('proposition.confirmer_failed', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('proposition.show', $token)
                ->with('error', 'Erreur lors de la confirmation. Contactez votre commercial.');
        }

        return view('proposition.confirmed', [
            'reservation' => $reservation->fresh(['client', 'panels']),
            'client'      => $reservation->client,
            'campaign'    => $campaign,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // PUBLIC — refuser la proposition
    // POST /proposition/{token}/refuser
    // ══════════════════════════════════════════════════════════════

    public function refuser(Request $request, string $token)
    {
        try {
            $reservation = $this->propositionService->validerToken($token);
        } catch (\RuntimeException $e) {
            return redirect()->route('proposition.show', $token)
                ->with('error', $e->getMessage());
        }

        $this->propositionService->refuser($reservation, $request->input('motif'));

        return view('proposition.refused', [
            'reservation' => $reservation,
            'client'      => $reservation->client,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════════

    private function monthsBetween($start, $end): float
    {
        $s      = Carbon::parse($start)->startOfDay();
        $e      = Carbon::parse($end)->endOfDay();
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