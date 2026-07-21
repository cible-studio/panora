<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\Quote;
use App\Services\AlertService;
use App\Services\InvoiceCalculator;
use App\Services\QuoteBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QuoteController — gestion des devis commerciaux non-bloquants.
 *
 * Cf. app/Enums/QuoteStatus.php pour le cycle de vie.
 * Cf. app/Policies/QuotePolicy.php pour les autorisations par rôle.
 */
class QuoteController extends Controller
{
    // ─── LISTE ─────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->authorize('viewAny', Quote::class);
        $user = auth()->user();

        $query = Quote::with(['client', 'commercial'])
            ->orderByDesc('created_at');

        // Commercial : ses devis uniquement (défense en profondeur — la
        // policy view filtre déjà mais on économise une requête).
        if ($user->role === UserRole::COMMERCIAL) {
            $query->forCommercialUser((int) $user->id);
        }

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('q')) {
            $needle = '%' . $request->q . '%';
            $query->where(function ($q) use ($needle) {
                $q->where('reference', 'like', $needle)
                  ->orWhere('title', 'like', $needle);
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $quotes = $query->paginate(20)->withQueryString();

        // KPI pour le header (base = filtre appliqué sauf pagination)
        $baseKpi = clone $query;
        $kpi = [
            'total'         => (clone $baseKpi)->count(),
            'brouillon'     => (clone $baseKpi)->where('status', 'brouillon')->count(),
            'envoye'        => (clone $baseKpi)->where('status', 'envoye')->count(),
            'accepte'       => (clone $baseKpi)->whereIn('status', ['accepte', 'accepte_avec_conflit'])->count(),
            'refuse'        => (clone $baseKpi)->where('status', 'refuse')->count(),
            'expire_bientot'=> Quote::expiringSoon(7)
                ->when($user->role === UserRole::COMMERCIAL,
                    fn($q) => $q->forCommercialUser((int) $user->id))
                ->count(),
        ];

        $clients = Client::orderBy('name')->get(['id', 'name']);

        return view('admin.quotes.index', compact('quotes', 'kpi', 'clients'));
    }

    // ─── CRÉATION ──────────────────────────────────────────────────

    public function create(Request $request)
    {
        $this->authorize('create', Quote::class);

        // Préselection depuis querystring (ex: bouton "Devis" sur fiche client)
        $preselect = [
            'client_id'   => $request->query('client_id'),
            'campaign_id' => $request->query('campaign_id'),
        ];

        $clients  = Client::orderBy('name')->get(['id', 'name', 'ncc', 'email']);
        $campaigns = Campaign::orderBy('name')->get(['id', 'name', 'client_id']);
        $communes = Commune::orderBy('name')->get(['id', 'name']);
        $validDays = (int) config('billing.quote_valid_days_default', 30);

        return view('admin.quotes.create', compact('clients', 'campaigns', 'communes', 'preselect', 'validDays'));
    }

    public function store(Request $request, QuoteBuilder $builder)
    {
        $this->authorize('create', Quote::class);

        $data = $this->validateQuotePayload($request);

        $user = auth()->user();

        return DB::transaction(function () use ($data, $user, $builder) {

            $quote = Quote::create([
                'reference'          => Quote::generateReference(),
                'client_id'          => $data['client_id'],
                'campaign_id'        => $data['campaign_id'] ?? null,
                'commercial_user_id' => $user->id,
                'created_by'         => $user->id,
                'title'              => $data['title'],
                'status'             => QuoteStatus::BROUILLON->value,
                'version'            => 1,
                'period_start'       => $data['period_start'] ?? null,
                'period_end'         => $data['period_end'] ?? null,
                'valid_days'         => $data['valid_days'] ?? (int) config('billing.quote_valid_days_default', 30),
                'remise_pct'         => $data['remise_pct'] ?? 0,
                'tva'                => (float) config('billing.tva_rate', 18),
                'notes_client'       => $data['notes_client'] ?? null,
                'notes_internes'     => $data['notes_internes'] ?? null,
                'public_token'       => Quote::generatePublicToken(),
            ]);

            $this->syncLines($quote, $data['lines'] ?? []);
            $this->syncServices($quote, $data['services'] ?? []);

            $quote = $builder->recalculateAndPersist($quote);

            AlertService::create(
                'devis', 'info',
                '📝 Nouveau devis — ' . $quote->reference,
                $user->name . ' a créé le devis ' . $quote->reference . ' pour ' . ($quote->client?->name ?? '—'),
                $quote
            );

            return redirect()->route('admin.quotes.show', $quote)
                ->with('success', "Devis {$quote->reference} créé en brouillon. Ajuste-le et envoie-le au client quand il est prêt.");
        });
    }

    // ─── ÉDITION ───────────────────────────────────────────────────

    public function show(Quote $quote)
    {
        $this->authorize('view', $quote);
        $quote->load(['client', 'campaign', 'commercial', 'creator', 'lines', 'services', 'convertedReservation']);
        return view('admin.quotes.show', compact('quote'));
    }

    public function edit(Quote $quote)
    {
        $this->authorize('update', $quote);

        $quote->load(['lines', 'services']);
        $clients   = Client::orderBy('name')->get(['id', 'name', 'ncc', 'email']);
        $campaigns = Campaign::orderBy('name')->get(['id', 'name', 'client_id']);
        $communes  = Commune::orderBy('name')->get(['id', 'name']);
        $validDays = $quote->valid_days;

        return view('admin.quotes.edit', compact('quote', 'clients', 'campaigns', 'communes', 'validDays'));
    }

    public function update(Request $request, Quote $quote, QuoteBuilder $builder)
    {
        $this->authorize('update', $quote);

        $data = $this->validateQuotePayload($request);

        return DB::transaction(function () use ($quote, $data, $builder) {
            $wasSentBefore = $quote->status === QuoteStatus::EN_NEGOCIATION;

            $quote->update([
                'client_id'      => $data['client_id'],
                'campaign_id'    => $data['campaign_id'] ?? null,
                'title'          => $data['title'],
                'period_start'   => $data['period_start'] ?? null,
                'period_end'     => $data['period_end'] ?? null,
                'valid_days'     => $data['valid_days'] ?? $quote->valid_days,
                'remise_pct'     => $data['remise_pct'] ?? 0,
                'notes_client'   => $data['notes_client'] ?? null,
                'notes_internes' => $data['notes_internes'] ?? null,
                // Si on modifie un devis déjà envoyé (négociation), on passe
                // à v+1 pour tracer.
                'version'        => $wasSentBefore ? $quote->version + 1 : $quote->version,
            ]);

            $quote->lines()->delete();
            $quote->services()->delete();
            $this->syncLines($quote, $data['lines'] ?? []);
            $this->syncServices($quote, $data['services'] ?? []);

            $builder->recalculateAndPersist($quote);

            return redirect()->route('admin.quotes.show', $quote)
                ->with('success', 'Devis mis à jour.');
        });
    }

    // ─── ACTIONS DE STATUT ─────────────────────────────────────────

    public function send(Request $request, Quote $quote)
    {
        $this->authorize('send', $quote);

        // Marque le devis comme envoyé et calcule la date d'expiration.
        $quote->update([
            'status'     => QuoteStatus::ENVOYE->value,
            'sent_at'    => now(),
            'expires_at' => now()->addDays((int) $quote->valid_days),
        ]);

        // TODO Phase 2 : envoi du mail au client + notification espace
        // client si compte. Pour Phase 1, on marque envoyé et le
        // commercial peut télécharger le PDF pour l'envoyer manuellement.

        AlertService::create(
            'devis', 'info',
            '📤 Devis envoyé — ' . $quote->reference,
            auth()->user()->name . ' a envoyé le devis ' . $quote->reference . ' à ' . ($quote->client?->name ?? '—'),
            $quote
        );

        return back()->with('success',
            "Devis {$quote->reference} envoyé. Il expire le "
            . $quote->fresh()->expires_at->format('d/m/Y') . '. '
            . 'Télécharge le PDF ci-dessous pour l\'envoyer au client par email.'
        );
    }

    public function extend(Request $request, Quote $quote)
    {
        $this->authorize('extend', $quote);

        $data = $request->validate([
            'additional_days' => 'required|integer|min:1|max:180',
        ]);

        $newExpiry = ($quote->expires_at ?: now())->addDays($data['additional_days']);
        $quote->update(['expires_at' => $newExpiry]);

        return back()->with('success',
            "Validité prolongée de {$data['additional_days']} jours. "
            . "Nouvelle date d'expiration : " . $newExpiry->format('d/m/Y') . '.'
        );
    }

    public function duplicate(Quote $quote, QuoteBuilder $builder)
    {
        $this->authorize('duplicate', $quote);

        return DB::transaction(function () use ($quote, $builder) {
            $newQuote = $quote->replicate([
                'reference', 'status', 'version', 'sent_at', 'expires_at',
                'decision_at', 'decision_reason', 'public_token',
                'converted_reservation_id',
            ]);
            $newQuote->reference    = Quote::generateReference();
            $newQuote->status       = QuoteStatus::BROUILLON->value;
            $newQuote->version      = 1;
            $newQuote->public_token = Quote::generatePublicToken();
            $newQuote->created_by   = auth()->id();
            $newQuote->save();

            // Recopier lignes + services
            foreach ($quote->lines as $line) {
                $newLine = $line->replicate(['quote_id']);
                $newLine->quote_id = $newQuote->id;
                $newLine->save();
            }
            foreach ($quote->services as $svc) {
                $newSvc = $svc->replicate(['quote_id']);
                $newSvc->quote_id = $newQuote->id;
                $newSvc->save();
            }

            $builder->recalculateAndPersist($newQuote);

            return redirect()->route('admin.quotes.edit', $newQuote)
                ->with('success', "Devis dupliqué en {$newQuote->reference}. Ajuste et envoie.");
        });
    }

    public function archive(Quote $quote)
    {
        $this->authorize('archive', $quote);

        $quote->update(['status' => QuoteStatus::ARCHIVE->value]);
        return back()->with('success', "Devis {$quote->reference} archivé.");
    }

    // ─── PDF ───────────────────────────────────────────────────────

    public function exportPdf(Quote $quote)
    {
        $this->authorize('exportPdf', $quote);
        $quote->load(['client', 'campaign', 'commercial', 'creator', 'lines', 'services']);

        $pdf = Pdf::loadView('pdf.quote', ['quote' => $quote])
            ->setPaper('A4', 'portrait');

        return $pdf->download("devis-{$quote->reference}.pdf");
    }

    // ─── HELPERS ───────────────────────────────────────────────────

    protected function validateQuotePayload(Request $request): array
    {
        return $request->validate([
            'client_id'                    => 'required|exists:clients,id',
            'campaign_id'                  => 'nullable|exists:campaigns,id',
            'title'                        => 'required|string|max:200',
            'period_start'                 => 'nullable|date',
            'period_end'                   => 'nullable|date|after_or_equal:period_start',
            'valid_days'                   => 'nullable|integer|min:1|max:365',
            'remise_pct'                   => 'nullable|numeric|min:0|max:100',
            'notes_client'                 => 'nullable|string|max:2000',
            'notes_internes'               => 'nullable|string|max:2000',

            'lines'                        => 'required|array|min:1',
            'lines.*.designation'          => 'required|string|max:200',
            'lines.*.commune_id'           => 'nullable|exists:communes,id',
            'lines.*.dimension_m2'         => 'required|numeric|min:0',
            'lines.*.pu_ht_mensuel'        => 'required|numeric|min:0',
            'lines.*.quantite'             => 'required|integer|min:1',
            'lines.*.duree_mois'           => 'required|numeric|min:0.5',
            'lines.*.panel_id'             => 'nullable|exists:panels,id',
            'lines.*.external_panel_id'    => 'nullable|exists:external_panels,id',

            'services'                     => 'nullable|array|max:30',
            'services.*.label'             => 'required_with:services|string|max:200',
            'services.*.prix_ht'           => 'required_with:services|numeric|min:0',
        ], [
            'lines.required' => 'Au moins une ligne (panneau) est requise dans un devis.',
        ]);
    }

    protected function syncLines(Quote $quote, array $lines): void
    {
        $orderIdx = 0;
        foreach ($lines as $l) {
            $commune = !empty($l['commune_id']) ? Commune::find($l['commune_id']) : null;
            $rates   = $commune?->ratesAt(now()->toDateString()) ?? ['odp' => 0, 'tm' => (float) config('billing.tm_default', 1000)];

            $quote->lines()->create([
                'panel_id'              => !empty($l['panel_id']) ? (int) $l['panel_id'] : null,
                'external_panel_id'     => !empty($l['external_panel_id']) ? (int) $l['external_panel_id'] : null,
                'commune_id'            => $commune?->id,
                'snapshot_commune_name' => $commune?->name,
                'designation'           => $l['designation'],
                'dimension_m2'          => (float) $l['dimension_m2'],
                'pu_ht_mensuel'         => (int) round((float) $l['pu_ht_mensuel']),
                'quantite'              => (int) $l['quantite'],
                'duree_mois'            => (float) $l['duree_mois'],
                'odp_rate_applique'     => (int) $rates['odp'],
                'tm_rate_applique'      => (int) $rates['tm'],
                'order_index'           => $orderIdx++,
            ]);
        }
    }

    protected function syncServices(Quote $quote, array $services): void
    {
        $idx = 0;
        foreach ($services as $s) {
            $label = trim((string) ($s['label'] ?? ''));
            $prix  = (float) ($s['prix_ht'] ?? 0);
            if ($label === '' || $prix <= 0) continue;
            $quote->services()->create([
                'label'       => $label,
                'prix_ht'     => (int) round($prix),
                'order_index' => $idx++,
            ]);
        }
    }
}
