<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Rules\InterlocuteurDuClient;
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

        // Corbeille (2026-10-29) : réservée à l'admin.
        $corbeille = $request->boolean('corbeille') && $user->role === UserRole::ADMIN;

        // Filtres, appliqués à la liste et aux compteurs.
        $filtrer = function ($query) use ($request, $user) {
            // Commercial : ses devis uniquement (défense en profondeur — la
            // policy view filtre déjà mais on économise une requête).
            if ($user->role === UserRole::COMMERCIAL) {
                $query->forCommercialUser((int) $user->id);
            }
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
            return $query;
        };

        $query = $filtrer(($corbeille ? Quote::onlyTrashed() : Quote::query())
            ->with(['client', 'commercial'])
            ->orderByDesc($corbeille ? 'deleted_at' : 'created_at'));

        $quotes = $query->paginate(20)->withQueryString();

        // KPI pour le header (base = filtre appliqué sauf pagination). Toujours
        // sur les devis en cours, même quand la liste montre la corbeille.
        $baseKpi = $corbeille ? $filtrer(Quote::query()) : clone $query;
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

        $nbCorbeille = $user->role === UserRole::ADMIN ? Quote::onlyTrashed()->count() : 0;

        return view('admin.quotes.index', compact('quotes', 'kpi', 'clients', 'corbeille', 'nbCorbeille'));
    }

    // ─── CORBEILLE (2026-10-29) ────────────────────────────────────

    /** Met le devis à la corbeille : il quitte les listes, l'admin peut le restaurer. */
    public function destroy(Quote $quote)
    {
        if ($quote->aEteAccepte()) {
            return back()->with('error', "Le devis {$quote->reference} a été accepté : il ne se supprime pas. Archivez-le pour le retirer de la liste.");
        }
        $this->authorize('delete', $quote);

        $quote->delete();
        Log::info('quote.trashed', ['quote_id' => $quote->id, 'reference' => $quote->reference, 'by' => auth()->id()]);

        return redirect()->route('admin.quotes.index')
            ->with('success', "Devis {$quote->reference} placé dans la corbeille.");
    }

    /** Sort un devis de la corbeille (admin). */
    public function restore(int $id)
    {
        $quote = Quote::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $quote);

        $quote->restore();
        Log::info('quote.restored', ['quote_id' => $quote->id, 'reference' => $quote->reference, 'by' => auth()->id()]);

        return redirect()->route('admin.quotes.show', $quote)
            ->with('success', "Devis {$quote->reference} restauré.");
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
            // Axes (2026-11-12) : les pieds cochés dans Disponibilités font
            // UNE ligne par axe, qui retient ses pieds.
            $axes = app(\App\Services\Axes\AxeService::class);
            foreach ($panels->whereNotNull('axe_id')->groupBy('axe_id') as $axeId => $pieds) {
                $axe = \App\Models\Panel::find($axeId);
                if ($axe) {
                    $prefilledLines[] = $axes->ligneDevis($axe, $pieds->sortBy('pied_numero')->values());
                }
            }
            foreach ($panels->whereNull('axe_id') as $p) {
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
        // Numéro proposé, affiché en lecture seule dans le formulaire
        // (2026-10-30). Le numéro définitif est attribué à
        // l'enregistrement : si un autre devis est créé entre-temps,
        // c'est celui-là qui fait foi.
        $referenceProposee = Quote::generateReference();

        return view('admin.quotes.create', compact('clients', 'campaigns', 'communes', 'preselect', 'validDays', 'prefilledLines', 'referenceProposee'));
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
                'client_contact_id'  => $data['client_contact_id'] ?? null,
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

        // ─── Période visée (2026-11-13) ──────────────────────────────
        // Le badge doit dire si le panneau est libre SUR LA PÉRIODE DU
        // DEVIS, pas aujourd'hui. Avant, searchPanels() renvoyait
        // panels.status (l'état du jour) : un panneau occupé jusqu'au
        // 22/11/2026 s'affichait OCCUPE pour un devis de 2027.
        // La règle vit dans AvailabilityService — jamais recopiée ici.
        $periodStart = $request->input('period_start');
        $periodEnd   = $request->input('period_end');
        $hasPeriod   = false;
        if ($periodStart && $periodEnd) {
            try {
                $d1 = \Carbon\Carbon::parse($periodStart)->startOfDay();
                $d2 = \Carbon\Carbon::parse($periodEnd)->startOfDay();
                // Dates inversées : on refuse la période plutôt que de
                // calculer n'importe quoi (le formulaire le signale aussi).
                if ($d1->lte($d2)) {
                    $periodStart = $d1->toDateString();
                    $periodEnd   = $d2->toDateString();
                    $hasPeriod   = true;
                }
            } catch (\Throwable $e) {
                $hasPeriod = false;
            }
        }
        if (!$hasPeriod) { $periodStart = $periodEnd = null; }

        // Le client du devis : son propre renouvellement confirmé ne doit
        // pas le bloquer (même règle que réservation et campagne).
        $clientId = $request->integer('client_id') ?: null;

        // Colonnes réelles dans Panora : adresse (fr), format_id (belongsTo PanelFormat).
        // Surface m² = format.surface (colonne calculée) OU format.width * format.height.
        //
        // 2026-10-30 : même recherche que l'inventaire — mot entier, sans
        // accents, commune comprise (App\Support\RecherchePanneaux). Avant,
        // un simple LIKE : « bouet » ne trouvait pas « Port Bouët », et
        // « ABG » remontait aussi CABG-001.
        // Axes multi-pieds (2026-11-11) : un devis se fait au pied.
        $requete = \App\Models\Panel::vendables()
            ->with(['commune:id,name', 'format:id,width,height,surface,name']);
        $colonnesAxe = app(\App\Services\Axes\AxeService::class)->colonnesPresentes();

        // Revalidation (2026-11-13) : le formulaire redemande le verdict
        // pour des panneaux DÉJÀ choisis quand les dates changent. Même
        // route, même règle — on filtre par id au lieu du texte.
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        $colonnes = array_merge(
            ['id', 'reference', 'name', 'adresse', 'quartier', 'commune_id', 'format_id', 'monthly_rate', 'status'],
            $colonnesAxe ? ['axe_id', 'pied_numero'] : []
        );

        if ($ids) {
            // Sans vendables() : la revalidation porte sur ce qui est DÉJÀ
            // dans le devis, axes compris (une ligne d'axe porte l'id de
            // l'axe, que vendables() écarterait — l'alerte ne sortirait
            // jamais pour un axe).
            $panels = \App\Models\Panel::with(['commune:id,name', 'format:id,width,height,surface,name'])
                ->whereIn('id', array_slice($ids, 0, 60))
                ->whereNull('deleted_at')
                ->orderBy('reference')
                ->get($colonnes);
        } else {
            $panels = \App\Support\RecherchePanneaux::appliquer($requete, $q)
                ->whereNull('deleted_at')
                ->orderBy('reference')
                ->limit(30)
                ->get($colonnes);
        }

        // Axes (2026-11-12) : un résultat par AXE, avec tous ses pieds à
        // cocher dans la ligne — une ligne de devis par axe.
        $axes = app(\App\Services\Axes\AxeService::class);
        $axeIds = $colonnesAxe ? $panels->pluck('axe_id')->filter()->unique()->values()->all() : [];
        $piedsParAxe = collect();
        if ($axeIds) {
            $piedsParAxe = \App\Models\Panel::whereIn('axe_id', $axeIds)->whereNull('deleted_at')
                ->orderBy('pied_numero')
                ->get(['id', 'reference', 'axe_id', 'pied_numero', 'monthly_rate', 'status'])
                ->groupBy('axe_id');
        }

        // ─── Disponibilité sur la période (2026-11-13) ───────────────
        // Un seul appel pour tout le monde : panneaux trouvés, axes
        // parents et pieds de ces axes. includeCampaignBlockings = true,
        // sinon un panneau engagé dans une campagne directe (sans
        // réservation) passerait pour libre — c'est le cas de ta capture.
        $dispo = collect();
        if ($hasPeriod) {
            $idsDispo = $panels->pluck('id')
                ->merge($axeIds)
                ->merge($piedsParAxe->flatten(1)->pluck('id'))
                ->filter()->unique()->values()->all();
            if ($idsDispo) {
                $dispo = app(\App\Services\AvailabilityService::class)->getPanelAvailabilityData(
                    $idsDispo, $periodStart, $periodEnd,
                    null,      // pas de réservation à exclure
                    true,      // inclure les blocages de campagne directe
                    null,
                    $clientId  // son propre renouvellement ne bloque pas
                );
            }
        }

        // Verdict d'un panneau : quoi afficher + a-t-on le droit de le
        // choisir. La règle vit dans AvailabilityService::verdictSelection().
        $verdict = function ($panelId, $rawStatus) use ($dispo, $hasPeriod, $periodEnd) {
            $a = $dispo->get($panelId, ['available' => true, 'release_date' => null, 'occupations' => []]);
            $v = \App\Services\AvailabilityService::verdictSelection(
                is_object($rawStatus) ? ($rawStatus->value ?? null) : $rawStatus,
                $hasPeriod,
                (bool) ($a['available'] ?? true),
                $a['release_date'] ?? null,
                $periodEnd
            );
            $v['libre_le_fr'] = $v['libre_le']
                ? \Carbon\Carbon::parse($v['libre_le'])->format('d/m/Y')
                : null;
            $v['occupations'] = array_slice($a['occupations'] ?? [], 0, 3);
            return $v;
        };

        $resultatsAxes = [];
        if ($axeIds) {
            foreach (\App\Models\Panel::with(['commune:id,name', 'format:id,width,height,surface,name'])->whereIn('id', $axeIds)->get() as $axe) {
                $piedsAxe = $piedsParAxe->get($axe->id, collect());
                $ligne = $axes->ligneDevis($axe, $piedsAxe);

                // Chaque pied a son propre verdict ; l'axe est proposable
                // tant qu'il reste au moins un pied sélectionnable.
                $piedsPayload = $piedsAxe->map(function ($x) use ($verdict) {
                    $v = $verdict($x->id, $x->status);
                    return [
                        'id'             => $x->id,
                        'reference'      => $x->reference,
                        'numero'         => $x->pied_numero,
                        'monthly_rate'   => (int) ($x->monthly_rate ?? 0),
                        'selectable'     => $v['selectable'],
                        'display_status' => $v['display_status'],
                        'libre_le_fr'    => $v['libre_le_fr'],
                    ];
                })->values()->all();

                $libres = array_values(array_filter($piedsPayload, fn ($x) => $x['selectable']));
                $vAxe   = $verdict($axe->id, $axe->status);

                $resultatsAxes[$axe->id] = [
                    'id'             => $axe->id,
                    'text'           => $ligne['designation'],
                    'reference'      => $axe->reference,
                    'name'           => $axes->nomCourt($axe->name),
                    'address'        => $axes->nomCourt($axe->name),
                    'commune_id'     => $axe->commune_id,
                    'commune_name'   => $axe->commune?->name,
                    'dimension_m2'   => $ligne['dimension_m2'],
                    'monthly_rate'   => (int) ($axe->monthly_rate ?? 0),
                    'status'         => null,
                    'est_axe'        => true,
                    'has_period'     => $hasPeriod,
                    // L'axe reste proposable s'il a au moins un pied libre,
                    // même si l'axe parent porte un blocage hérité.
                    'selectable'     => $hasPeriod ? count($libres) > 0 : true,
                    'display_status' => $hasPeriod
                        ? (count($libres) > 0 ? 'libre' : 'occupe')
                        : 'sans_periode',
                    'libre_le_fr'    => $vAxe['libre_le_fr'],
                    'pieds_total'    => count($piedsPayload),
                    'pieds_libres'   => count($libres),
                    'occupations'    => $vAxe['occupations'],
                    'pieds'          => $piedsPayload,
                ];
            }
        }

        $resultats = collect();
        $poses = [];
        foreach ($panels as $p) {
            $axeId = $colonnesAxe ? $p->axe_id : null;
            if ($axeId && isset($resultatsAxes[$axeId])) {
                if (!isset($poses[$axeId])) {
                    $poses[$axeId] = true;
                    $resultats->push($resultatsAxes[$axeId]);
                }
                continue;
            }
            $resultats->push($p);
        }

        return response()->json([
            // Le formulaire a besoin de savoir si le serveur a pu juger
            // sur une période, pour afficher « statut du jour » sinon.
            'has_period' => $hasPeriod,
            'period'     => $hasPeriod ? ['start' => $periodStart, 'end' => $periodEnd] : null,
            'results'    => $resultats->map(function ($p) use ($verdict, $hasPeriod) {
                if (is_array($p)) return $p; // entrée d'axe, déjà prête
                $addr    = $p->adresse ?: $p->quartier ?: $p->name;
                $surface = (float) ($p->format?->surface ?? (($p->format?->width ?? 0) * ($p->format?->height ?? 0)));
                $v       = $verdict($p->id, $p->status);
                return [
                    'id'             => $p->id,
                    'text'           => trim(($p->reference ?? '') . ' — ' . ($addr ?? '')),
                    'reference'      => $p->reference,
                    'name'           => $p->name,
                    'address'        => $addr,
                    'commune_id'     => $p->commune_id,
                    'commune_name'   => $p->commune?->name,
                    'dimension_m2'   => $surface,
                    'monthly_rate'   => (int) $p->monthly_rate,
                    // Statut brut conservé (compat : rien d'existant ne casse).
                    'status'         => $p->status,
                    // Nouveau — verdict calculé SUR LA PÉRIODE du devis.
                    'has_period'     => $hasPeriod,
                    'display_status' => $v['display_status'],
                    'selectable'     => $v['selectable'],
                    'libre_le_fr'    => $v['libre_le_fr'],
                    'occupations'    => $v['occupations'],
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
     * Commercial propriétaire (admin via before) — QuotePolicy::updatePrice.
     * 2026-11-02 : plus le comptable ni le MP (config/acces.php). Ne permet pas
     * de modifier la structure (panneaux ajoutés/retirés, période, remise,
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

        $data = $this->validateQuotePayload($request, $quote);

        return DB::transaction(function () use ($quote, $data, $builder) {
            $wasSentBefore = $quote->status === QuoteStatus::EN_NEGOCIATION;

            $quote->update([
                'client_id'      => $data['client_id'],
                'client_contact_id' => $data['client_contact_id'] ?? null,
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
        $adresses = $quote->adressesClient(); // fiche + interlocuteur du devis (2026-10-29)
        if ($adresses) {
            try {
                \Illuminate\Support\Facades\Mail::to($adresses)
                    ->send(new \App\Mail\QuoteSentMail($quote));
                $mailSent = true;
            } catch (\Throwable $e) {
                $mailError = $e->getMessage();
                \Illuminate\Support\Facades\Log::error('quote.send.mail_failed', [
                    'quote_id' => $quote->id,
                    'client_email' => implode(', ', $adresses),
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
            return back()->with('success', $baseMsg . " 📧 Un email avec le PDF a été envoyé à " . implode(', ', $adresses) . '.');
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

    protected function validateQuotePayload(Request $request, ?Quote $quote = null): array
    {
        // Client supprimé (soft delete) refusé (2026-10-30) : `exists` seul
        // acceptait une fiche à la corbeille. En modification ($quote), le
        // client actuel du devis reste accepté tel quel ; seul le passage
        // VERS une autre fiche supprimée est refusé.
        $garderClientActuel = $quote !== null
            && (int) $request->input('client_id') === (int) $quote->client_id;

        $data = $request->validate([
            'client_id'                    => ['required', $garderClientActuel
                ? \Illuminate\Validation\Rule::exists('clients', 'id')
                : \Illuminate\Validation\Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'client_contact_id'            => InterlocuteurDuClient::regles($request->integer('client_id') ?: null), // 2026-10-29
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
            // Axes Lumipub (2026-11-12) : pieds proposés, ODP/TM saisies.
            'lines.*.pieds'                => 'nullable|array',
            'lines.*.pieds.*'              => 'integer|exists:panels,id',
            'lines.*.odp_amount_override'  => 'nullable|numeric|min:0',
            'lines.*.tm_amount_override'   => 'nullable|numeric|min:0',

            'services'                     => 'nullable|array|max:30',
            'services.*.label'             => 'required_with:services|string|max:200',
            'services.*.prix_ht'           => 'required_with:services|numeric|min:0',
            // TVA au choix sur chaque service (2026-10-30). Absent = soumis.
            'services.*.tva_applicable'    => 'nullable|boolean',
        ], [
            'lines.required' => 'Au moins une ligne (panneau) est requise dans un devis.',
            'client_id.exists' => 'Ce client est introuvable ou a été supprimé.',
            ...InterlocuteurDuClient::MESSAGES,
        ]);

        // Défense en profondeur : refuse deux lignes qui pointent sur le même
        // panneau interne (ou externe). Le JS bloque déjà en amont, ceci
        // couvre les cas de bypass (bouton back+submit, curl, script tiers).
        $seenPanels = [];
        $seenExtern = [];
        foreach (($data['lines'] ?? []) as $i => $l) {
            // Axe (2026-11-12) : les pieds cochés doivent être ceux de l'axe
            // de la ligne — sinon la réservation prendrait d'autres pieds.
            if (!empty($l['pieds'])) {
                $pieds = array_values(array_unique(array_map('intval', $l['pieds'])));
                $justes = !empty($l['panel_id'])
                    && \App\Models\Panel::whereIn('id', $pieds)->where('axe_id', (int) $l['panel_id'])->count() === count($pieds);
                if (!$justes) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "lines.$i.pieds" => "Ligne " . ($i + 1) . " : les pieds cochés n'appartiennent pas à l'axe choisi.",
                    ]);
                }
            }
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

        // ═══ Panneau occupé sur la période — refus serveur (2026-11-13) ═══
        // RÈGLE VALIDÉE PAR ÉCRIT : « occupé sur la période visée → pas
        // moyen de le sélectionner ». Le Select2 le bloque déjà, mais une
        // page restée ouverte pendant qu'un autre commercial réservait
        // passerait outre. On refuse donc aussi ici.
        //
        // On ne contrôle QUE les panneaux nouvellement ajoutés : un devis
        // existant dont un panneau s'est fait prendre entre-temps doit
        // rester modifiable (sinon on ne pourrait plus ni le corriger ni
        // retirer le panneau fautif). Le conflit reste de toute façon
        // bloqué à la conversion par QuoteConverter.
        $this->refuserPanneauxOccupes($data, $quote);

        return $data;
    }

    /**
     * Refuse les panneaux occupés sur la période du devis, parmi ceux qui
     * n'y étaient pas déjà. Source de vérité : AvailabilityService — la
     * règle n'est pas recopiée ici.
     */
    protected function refuserPanneauxOccupes(array $data, ?Quote $quote): void
    {
        $debut = $data['period_start'] ?? null;
        $fin   = $data['period_end']   ?? null;
        // Sans période, on ne peut rien affirmer : on ne bloque pas.
        if (!$debut || !$fin) return;

        // Panneaux déjà présents sur le devis : laissés tels quels.
        $dejaLa = $quote
            ? $quote->lines()->whereNotNull('panel_id')->pluck('panel_id')
                ->map(fn ($id) => (int) $id)->all()
            : [];

        $nouveaux = [];
        foreach (($data['lines'] ?? []) as $i => $l) {
            $pid = (int) ($l['panel_id'] ?? 0);
            if ($pid && !in_array($pid, $dejaLa, true)) $nouveaux[$i] = $pid;
            // Les pieds cochés d'un axe sont vendus eux aussi : ils comptent.
            foreach ((array) ($l['pieds'] ?? []) as $pied) {
                $pied = (int) $pied;
                if ($pied && !in_array($pied, $dejaLa, true)) $nouveaux[$i . ':' . $pied] = $pied;
            }
        }
        if (!$nouveaux) return;

        $dispo = app(\App\Services\AvailabilityService::class)->getPanelAvailabilityData(
            array_values(array_unique($nouveaux)),
            \Carbon\Carbon::parse($debut)->toDateString(),
            \Carbon\Carbon::parse($fin)->toDateString(),
            null,
            true,                                     // blocages de campagne directe inclus
            null,
            (int) ($data['client_id'] ?? 0) ?: null   // son propre renouvellement ne bloque pas
        );

        // Référence ET statut : un panneau en maintenance doit être refusé
        // lui aussi, et verdictSelection() a besoin du statut pour ça.
        $infos = \App\Models\Panel::whereIn('id', array_values($nouveaux))
            ->get(['id', 'reference', 'status'])->keyBy('id');
        $erreurs = [];

        foreach ($nouveaux as $cle => $pid) {
            $a = $dispo->get($pid, ['available' => true, 'release_date' => null]);
            $panneau = $infos->get($pid);
            $v = \App\Services\AvailabilityService::verdictSelection(
                $panneau?->status?->value,
                true,
                (bool) ($a['available'] ?? true),
                $a['release_date'] ?? null,
                \Carbon\Carbon::parse($fin)->toDateString()
            );
            if ($v['selectable']) continue;

            $ref   = $panneau?->reference ?? ('Panneau #' . $pid);
            $champ = 'lines.' . explode(':', (string) $cle)[0] . '.panel_id';
            $erreurs[$champ] = $v['display_status'] === 'maintenance'
                ? $ref . ' est en maintenance — il n\'est pas vendable.'
                : $ref . ' est occupé sur la période du devis'
                    . ($v['libre_le'] ? ' (libre le ' . \Carbon\Carbon::parse($v['libre_le'])->format('d/m/Y') . ')' : '')
                    . ' — retire-le ou change les dates.';
        }

        if ($erreurs) {
            throw \Illuminate\Validation\ValidationException::withMessages($erreurs);
        }
    }

    /**
     * Axes Lumipub (2026-11-12) : ODP et TM jamais calculées pour un axe —
     * 0 tant que rien n'est saisi. Les autres lignes : NULL = calcul auto.
     * Utilisé par syncLines() ET totaux(), pour que l'aperçu dise la même
     * chose que le devis enregistré.
     *
     * @return array{0: ?int, 1: ?int}  [odp, tm]
     */
    protected function taxesSaisies(array $l): array
    {
        $lire = fn ($v) => ($v !== null && $v !== '') ? (int) round((float) $v) : null;
        $odp = $lire($l['odp_amount_override'] ?? null);
        $tm  = $lire($l['tm_amount_override'] ?? null);

        if (!empty($l['panel_id'])
            && \App\Models\Panel::withTrashed()->find((int) $l['panel_id'])?->taxesSaisiesALaMain()) {
            $odp ??= 0;
            $tm  ??= 0;
        }

        return [$odp, $tm];
    }

    protected function syncLines(Quote $quote, array $lines): void
    {
        $orderIdx = 0;
        // Colonnes du 2026-11-12 : tant que la migration n'est pas jouée,
        // on n'y écrit pas (le devis doit continuer de s'enregistrer).
        $colonnesAxes = \Illuminate\Support\Facades\Schema::hasColumn('quote_lines', 'pieds');
        foreach ($lines as $l) {
            $commune = !empty($l['commune_id']) ? Commune::find($l['commune_id']) : null;
            $rates   = $commune?->ratesAt(now()->toDateString()) ?? ['odp' => 0, 'tm' => (float) config('billing.tm_default', 1000)];
            [$odpSaisie, $tmSaisie] = $this->taxesSaisies($l);
            $axes = $colonnesAxes ? [
                'pieds'               => !empty($l['pieds']) ? array_values(array_unique(array_map('intval', $l['pieds']))) : null,
                'odp_amount_override' => $odpSaisie,
                'tm_amount_override'  => $tmSaisie,
            ] : [];

            $quote->lines()->create($axes + [
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

    /**
     * Récapitulatif en direct du devis pendant sa saisie (2026-10-30).
     *
     * Le formulaire envoie son contenu, le SERVEUR calcule : les totaux
     * viennent d'InvoiceCalculator, exactement comme à l'enregistrement.
     * Recopier ces formules en JavaScript ferait tôt ou tard diverger le
     * récapitulatif du devis réellement émis — c'est interdit par le
     * CLAUDE.md, et c'est le genre d'écart qui finit en litige client.
     *
     * Rien n'est enregistré : les lignes et services sont construits en
     * mémoire. Un formulaire incomplet renvoie des totaux à zéro plutôt
     * qu'une erreur de validation.
     */
    public function totaux(Request $request, InvoiceCalculator $calculator): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Quote::class);

        $debut = $request->input('period_start') ?: null;
        $fin   = $request->input('period_end') ?: null;

        // Lignes : on reprend l'enrichissement de syncLines (taux figés à
        // la date du jour via Commune::ratesAt), sans rien persister.
        $lignes = [];
        foreach ((array) $request->input('lines', []) as $l) {
            $pu = (float) ($l['pu_ht_mensuel'] ?? 0);
            $designation = trim((string) ($l['designation'] ?? ''));
            if ($designation === '' && $pu <= 0) {
                continue;
            }

            $commune = !empty($l['commune_id']) ? Commune::find($l['commune_id']) : null;
            $taux    = $commune?->ratesAt(now()->toDateString())
                ?? ['odp' => 0, 'tm' => (float) config('billing.tm_default', 1000)];

            $ligne = [
                'designation'           => $designation,
                'snapshot_commune_name' => $commune?->name,
                'dimension_m2'          => (float) ($l['dimension_m2'] ?? 0),
                'pu_ht_mensuel'         => $pu,
                'quantite'              => (int) ($l['quantite'] ?? 1),
                'duree_mois'            => (float) ($l['duree_mois'] ?? 1),
                'odp_rate_applique'     => (float) $taux['odp'],
                'tm_rate_applique'      => (float) $taux['tm'],
                'campaign_start'        => $debut,
                'campaign_end'          => $fin,
            ];
            // Axe Lumipub (2026-11-12) : même règle qu'à l'enregistrement.
            [$ligne['odp_amount_override'], $ligne['tm_amount_override']] = $this->taxesSaisies($l);

            $lignes[] = $ligne + $calculator->calculateLine($ligne);
        }

        $services = [];
        foreach ((array) $request->input('services', []) as $s) {
            $label = trim((string) ($s['label'] ?? ''));
            $prix  = (float) ($s['prix_ht'] ?? 0);
            if ($label === '' || $prix <= 0) {
                continue;
            }
            $services[] = [
                'label'          => $label,
                'prix_ht'        => $prix,
                'tva_applicable' => !array_key_exists('tva_applicable', $s)
                    || filter_var($s['tva_applicable'], FILTER_VALIDATE_BOOLEAN),
            ];
        }

        $totaux = $calculator->calculateInvoice($lignes, [
            'remise_pct' => (float) $request->input('remise_pct', 0),
            'services'   => $services ?: null,
        ]);

        $tva = (float) ($totaux['tva'] ?? 18);

        return response()->json([
            'vide'   => $lignes === [] && $services === [],
            'totaux' => $totaux,
            // Le détail nommé des services (2026-10-30) : un montant
            // global ne dit pas d'où il vient. Le TTC est calculé ici,
            // avec la même règle que le total — jamais côté navigateur.
            'services' => array_map(fn (array $s) => [
                'label'          => $s['label'],
                'tva_applicable' => $s['tva_applicable'],
                'ht'             => (int) round($s['prix_ht']),
                'ttc'            => (int) round($s['tva_applicable'] ? $s['prix_ht'] * (1 + $tva / 100) : $s['prix_ht']),
            ], $services),
        ]);
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
                // TVA au choix, service par service (2026-10-30). Absent du
                // formulaire = soumis à TVA, comme avant.
                'tva_applicable' => array_key_exists('tva_applicable', $s)
                    ? (bool) filter_var($s['tva_applicable'], FILTER_VALIDATE_BOOLEAN)
                    : true,
                'order_index' => $idx++,
            ]);
        }
    }
}
