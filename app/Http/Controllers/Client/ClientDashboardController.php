<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\PropositionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientDashboardController extends Controller
{
    public function __construct(
        protected PropositionService $propositionService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // DASHBOARD PRINCIPAL
    // ══════════════════════════════════════════════════════════════

    public function index()
    {
        $client = Auth::guard('client')->user();

        // Propositions en attente (réservations en_attente avec token)
        $propositions = $client->reservations()
            ->where('status', 'en_attente')
            ->whereNotNull('proposition_token')
            ->where('end_date', '>=', now()->toDateString())
            ->with(['panels.photos', 'panels.commune', 'panels.format'])
            ->orderByDesc('proposition_sent_at')
            ->get();

        // Campagnes actives
        $campagnesActives = $client->campaigns()
            ->whereIn('status', ['actif', 'pose'])
            ->with(['panels'])
            ->orderByDesc('start_date')
            ->limit(5)
            ->get();

        // Campagnes récentes (toutes)
        $campagnesRecentes = $client->campaigns()
            ->with(['panels'])
            ->orderByDesc('created_at')
            ->paginate(10);

        // Stats rapides
        $stats = [
            'propositions_en_attente' => $propositions->count(),
            'campagnes_actives'       => $client->campaigns()->whereIn('status', ['actif', 'pose'])->count(),
            'campagnes_total'         => $client->campaigns()->count(),
            'panneaux_actifs'         => $client->campaigns()
                ->whereIn('status', ['actif', 'pose'])
                ->withCount('panels')
                ->get()
                ->sum('panels_count'),
        ];

        return view('client.dashboard', compact(
            'client', 'propositions', 'campagnesActives', 'campagnesRecentes', 'stats'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // PROPOSITIONS — liste
    // ══════════════════════════════════════════════════════════════

    public function propositions()
    {
        $client = Auth::guard('client')->user();

        $propositions = $client->reservations()
            ->whereNotNull('proposition_token')
            ->with(['panels.photos', 'panels.commune', 'panels.format'])
            ->orderByDesc('proposition_sent_at')
            ->paginate(10);

        return view('client.propositions', compact('client', 'propositions'));
    }

    // ══════════════════════════════════════════════════════════════
    // PROPOSITION — détail (réutilise PropositionService)
    // ══════════════════════════════════════════════════════════════

    public function propositionDetail(string $token)
    {
        $client = Auth::guard('client')->user();

        // Vérifier que le token appartient bien à CE client (sécurité)
        $reservation = $client->reservations()
            ->where('proposition_token', $token)
            ->first();

        if (!$reservation) {
            abort(403, 'Cette proposition ne vous appartient pas.');
        }

        // Déléguer au PropositionController public pour affichage
        // On réutilise la même vue proposition/show
        try {
            $reservation = $this->propositionService->validerToken($token);
        } catch (\RuntimeException $e) {
            // Proposition expirée/traitée → afficher le statut
        }

        $this->propositionService->marquerVue($reservation);

        $months = $this->monthsBetween($reservation->start_date, $reservation->end_date);
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
                'photo_url'    => $photo ? asset('storage/' . ltrim($photo->path, '/')) : null,
            ];
        });

        $joursRestants = now()->startOfDay()->diffInDays(
            $reservation->end_date->startOfDay(), false
        );

        // Vue spéciale espace client (avec navbar client, pas la proposition publique)
        return view('client.proposition-detail', compact(
            'reservation', 'panels', 'months', 'joursRestants', 'token', 'client'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // CAMPAGNES
    // ══════════════════════════════════════════════════════════════

    public function campagnes()
    {
        $client = Auth::guard('client')->user();

        $campagnes = $client->campaigns()
            ->with(['panels.commune', 'panels.format'])
            ->orderByDesc('start_date')
            ->paginate(10);

        return view('client.campagnes', compact('client', 'campagnes'));
    }

    public function campagneDetail(\App\Models\Campaign $campaign)
    {
        $client = Auth::guard('client')->user();

        // Sécurité : vérifier que la campagne appartient au client connecté
        if ($campaign->client_id !== $client->id) {
            abort(403, 'Accès non autorisé.');
        }

        $campaign->load(['panels.photos', 'panels.commune', 'panels.format', 'invoices']);

        return view('client.campagne-detail', compact('client', 'campaign'));
    }

    // ══════════════════════════════════════════════════════════════
    // PROFIL CLIENT
    // ══════════════════════════════════════════════════════════════

    public function profil()
    {
        $client = Auth::guard('client')->user();
        return view('client.profil', compact('client'));
    }

    public function updateProfil(Request $request)
    {
        $client = Auth::guard('client')->user();

        $data = $request->validate([
            'phone'        => 'nullable|string|max:20',
            'address'      => 'nullable|string|max:255',
            'city'         => 'nullable|string|max:100',
            'contact_name' => 'nullable|string|max:150',
        ]);

        // Le client ne peut modifier QUE ses infos de contact, pas name/email/company
        $client->update($data);

        return back()->with('success', 'Profil mis à jour.');
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

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