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
            'period_start'=> $request->query('period_start'),
            'period_end'  => $request->query('period_end'),
        ];

        // Préselection de panneaux depuis la vue Disponibilités.
        // On charge les Panels + leur commune pour pré-remplir les lignes du form.
        $prefilledLines = [];
        $panelIds = array_filter(array_map('intval', (array) $request->query('panel_ids', [])));
        if (!empty($panelIds)) {
            $panels = \App\Models\Panel::with(['commune', 'format'])->whereIn('id', $panelIds)->get();
            foreach ($panels as $p) {
                $addr    = $p->adresse ?: $p->quartier ?: $p->name;
                $surface = (float) ($p->format?->surface ?? (($p->format?->width ?? 0) * ($p->format?->height ?? 0)));
                $prefilledLines[] = [
                    'panel_id'         => $p->id,
                    'designation'      => trim(($p->reference ?? '') . ' — ' . ($addr ?? '')),
                    'dimension_m2'     => $surface,
                    'pu_ht_mensuel'    => (int) ($p->monthly_rate ?? 0),
                    'quantite'         => 1,
                    'duree_mois'       => 1,
                    'commune_id'       => $p->commune_id,
                    'commune_name'     => $p->commune?->name,
                ];
            }
        }

        $clients  = Client::orderBy('name')->get(['id', 'name', 'ncc', 'email']);
        $campaigns = Campaign::orderBy('name')->get(['id', 'name', 'client_id']);
        $communes = Commune::orderBy('name')->get(['id', 'name']);
        $validDays = (int) config('billing.quote_valid_days_default', 30);

        return view('admin.quotes.create', compact('clients', 'campaigns', 'communes', 'preselect', 'validDays', 'prefilledLines'));
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

    // ─── AJAX : recherche de panneaux (autocomplete Select2 dans le form) ─
    //
    // GET /admin/quotes/search-panels?q=CDY
    // Retour : [{ id, text, reference, name, address, commune_id, commune_name,
    //            dimension_m2, monthly_rate, status }]
    //
    // Utilisé par _form.blade.php pour proposer au commercial une recherche
    // live des panneaux du parc. Au choix, la ligne est pré-remplie avec les
    // caractéristiques du panneau (surface, tarif catalogue, commune) — le
    // commercial peut encore ajuster le prix négocié avant enregistrement.
    public function searchPanels(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Quote::class);
        $q = trim((string) $request->input('q', ''));

        // Colonnes réelles dans Panora : adresse (fr), format_id (belongsTo PanelFormat).
        // Surface m² = format.surface (colonne calculée) OU format.width * format.height.
        $panels = \App\Models\Panel::with(['commune:id,name', 'format:id,width,height,surface,name'])
            ->when($q !== '', fn($qr) => $qr->where(fn($s) =>
                $s->where('reference', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
                  ->orWhere('adresse', 'like', "%{$q}%")
                  ->orWhere('quartier', 'like', "%{$q}%")
            ))
            ->whereNull('deleted_at')
            ->orderBy('reference')
            ->limit(30)
            ->get(['id', 'reference', 'name', 'adresse', 'quartier', 'commune_id', 'format_id', 'monthly_rate', 'status']);

        return response()->json([
            'results' => $panels->map(function ($p) {
                $addr    = $p->adresse ?: $p->quartier ?: $p->name;
                $surface = (float) ($p->format?->surface ?? (($p->format?->width ?? 0) * ($p->format?->height ?? 0)));
                return [
                    'id'           => $p->id,
                    'text'         => trim(($p->reference ?? '') . ' — ' . ($addr ?? '')),
                    'reference'    => $p->reference,
                    'name'         => $p->name,
                    'address'      => $addr,
                    'commune_id'   => $p->commune_id,
                    'commune_name' => $p->commune?->name,
                    'dimension_m2' => $surface,
                    'monthly_rate' => (int) $p->monthly_rate,
                    'status'       => $p->status,
                ];
            })->values(),
        ]);
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

    /**
     * Édition ciblée des prix d'un devis (lignes panneau + services).
     * Ouverte au Comptable via QuotePolicy::updatePrice (User::canEditPrices())
     * pour coordonner l'anticipation de facturation, sans qu'il puisse
     * modifier la structure (panneaux ajoutés/retirés, période, remise,
     * client, envoi, conversion — ces actions restent au commercial owner).
     *
     * Payload attendu :
     *   line_prices[<quote_line_id>]    = pu_ht_mensuel (integer >= 0)
     *   service_prices[<quote_service_id>] = prix_ht (integer >= 0)
     * IDs doivent appartenir au devis (contrôle strict, ferme IDOR).
     */
    public function updatePrice(Request $request, Quote $quote, QuoteBuilder $builder)
    {
        $this->authorize('updatePrice', $quote);

        $data = $request->validate([
            'line_prices'          => 'array',
            'line_prices.*'        => 'integer|min:0',
            'service_prices'       => 'array',
            'service_prices.*'     => 'integer|min:0',
        ]);

        $lineIds    = $quote->lines()->pluck('id')->all();
        $serviceIds = $quote->services()->pluck('id')->all();

        return DB::transaction(function () use ($quote, $data, $lineIds, $serviceIds, $builder) {
            foreach ($data['line_prices'] ?? [] as $id => $price) {
                if (!in_array((int) $id, $lineIds, true)) continue;
                $quote->lines()->whereKey($id)->update(['pu_ht_mensuel' => (int) $price]);
            }
            foreach ($data['service_prices'] ?? [] as $id => $price) {
                if (!in_array((int) $id, $serviceIds, true)) continue;
                $quote->services()->whereKey($id)->update(['prix_ht' => (int) $price]);
            }

            $builder->recalculateAndPersist($quote->fresh());

            return back()->with('success', 'Prix du devis mis à jour.');
        });
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

        // Envoi du mail au client avec PDF joint + lien direct.
        // Try/catch pour ne pas bloquer si le SMTP est down — le devis
        // reste envoyé côté app, le commercial peut renvoyer manuellement.
        $mailSent = false;
        $mailError = null;
        if (!empty($quote->client?->email)) {
            try {
                \Illuminate\Support\Facades\Mail::to($quote->client->email)
                    ->send(new \App\Mail\QuoteSentMail($quote));
                $mailSent = true;
            } catch (\Throwable $e) {
                $mailError = $e->getMessage();
                \Illuminate\Support\Facades\Log::error('quote.send.mail_failed', [
                    'quote_id' => $quote->id,
                    'client_email' => $quote->client->email,
                    'error' => $mailError,
                ]);
            }
        }

        AlertService::notify(
            'devis_envoye',
            '📤 Devis envoyé — ' . $quote->reference,
            auth()->user()->name . ' a envoyé le devis ' . $quote->reference . ' à ' . ($quote->client?->name ?? '—')
            . ($mailSent ? ' (mail envoyé)' : ($mailError ? ' (mail KO : ' . $mailError . ')' : ' (mail non envoyé — client sans email)')),
            $quote,
            [
                'user_id' => $quote->commercial_user_id,
                'lien'    => route('admin.quotes.show', $quote->id),
            ]
        );

        $baseMsg = "Devis {$quote->reference} marqué envoyé. Expire le "
                 . $quote->fresh()->expires_at->format('d/m/Y') . '.';

        if ($mailSent) {
            return back()->with('success', $baseMsg . " 📧 Un email avec le PDF a été envoyé à {$quote->client->email}.");
        }
        if ($mailError) {
            return back()->with('warning', $baseMsg . " ⚠️ Erreur envoi mail : {$mailError}. Télécharge le PDF et envoie manuellement.");
        }
        return back()->with('warning', $baseMsg . ' Le client n\'a pas d\'email — télécharge le PDF et envoie manuellement.');
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
        $data = $request->validate([
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

        // Défense en profondeur : refuse deux lignes qui pointent sur le même
        // panneau interne (ou externe). Le JS bloque déjà en amont, ceci
        // couvre les cas de bypass (bouton back+submit, curl, script tiers).
        $seenPanels = [];
        $seenExtern = [];
        foreach (($data['lines'] ?? []) as $i => $l) {
            if (!empty($l['panel_id'])) {
                if (isset($seenPanels[$l['panel_id']])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "lines.$i.panel_id" =>
                            "Le panneau #{$l['panel_id']} apparaît plusieurs fois. "
                            . "Fusionne les lignes et augmente la quantité (Qté) à la place.",
                    ]);
                }
                $seenPanels[$l['panel_id']] = true;
            }
            if (!empty($l['external_panel_id'])) {
                if (isset($seenExtern[$l['external_panel_id']])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "lines.$i.external_panel_id" =>
                            "Le panneau externe #{$l['external_panel_id']} apparaît plusieurs fois. "
                            . "Fusionne les lignes et augmente la quantité (Qté) à la place.",
                    ]);
                }
                $seenExtern[$l['external_panel_id']] = true;
            }
        }

        return $data;
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
                // TX-9 (2026-07-29) — Propagation des dates campagne du devis
                // vers chaque ligne. Permet à InvoiceCalculator/QuoteBuilder
                // de calculer TM (mois anniversaire) et ODP (trimestres × 3)
                // automatiquement au lieu d'utiliser duree_mois.
                'campaign_start'        => $quote->period_start,
                'campaign_end'          => $quote->period_end,
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
