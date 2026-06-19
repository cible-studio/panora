<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\CommuneTaxPayment;
use App\Models\Panel;
use App\Services\TaxCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class TaxController extends Controller
{
    /**
     * Liste des mois (1..12) couverts par une (period_type, period_value).
     * Sert à l'agrégation des paiements multi-périodicité — un paiement
     * mensuel-Y-2 couvre [2], un trimestriel-Y-1 couvre [1,2,3], un
     * annuel-Y-0 couvre [1..12].
     */
    private static function monthsForPeriod(string $type, int $value): array
    {
        return match ($type) {
            'mensuel'     => [$value],
            'trimestriel' => range(($value - 1) * 3 + 1, $value * 3),
            'annuel'      => range(1, 12),
            default       => [],
        };
    }

    /**
     * Vue principale module Taxes — refonte 2025 :
     * - Tabs Mensuel / Trimestriel / Annuel avec sélecteur de période
     * - Calcul live ODP + TM via /admin/taxes/calcul (AJAX)
     * - Suivi paiement par commune avec modale "Enregistrer paiement"
     *
     * Le tableau `taxes` legacy (paiement individuel) reste accessible
     * via /admin/taxes/historique pour l'historique des écritures.
     */
    public function index(Request $request)
    {
        $communes = Commune::orderBy('name')->get(['id', 'name', 'odp_rate', 'tm_rate']);

        // Année par défaut : courante (utile pour pré-sélectionner le filtre)
        $year         = (int) ($request->input('year', date('Y')));
        $periodType   = $request->input('period_type', 'mensuel');
        $periodValue  = (int) ($request->input('period_value', date('n')));
        $anneesDispos = range(date('Y') + 1, max(2020, date('Y') - 5));

        return view('admin.taxes.index', compact(
            'communes', 'year', 'periodType', 'periodValue', 'anneesDispos'
        ));
    }

    /**
     * Vue détaillée par panneau (Évolution 4 — point 5.6).
     *
     * Affiche pour chaque panneau éligible une ligne par type de taxe
     * (TM / ODP) avec commune, dimensions, statut, client, campagne,
     * période, montant — chaque montant peut être justifié à partir de
     * cette vue lors d'un contrôle commune.
     *
     * Filtres combinables (point 5.7 — rapports) : commune, client,
     * campagne, type, période.
     */
    public function details(Request $request, TaxCalculationService $calc)
    {
        $year         = (int) ($request->input('year', date('Y')));
        $periodType   = $request->input('period_type', TaxCalculationService::PERIOD_MONTHLY);
        $periodValue  = (int) ($request->input('period_value', date('n')));

        $filters = array_filter([
            'commune_id'  => $request->input('commune_id') ?: null,
            'client_id'   => $request->input('client_id')  ?: null,
            'campaign_id' => $request->input('campaign_id') ?: null,
            'type'        => $request->input('type')        ?: null,
        ]);

        $lines  = $calc->generateLines($periodType, $periodValue, $year, $filters);
        $totals = $calc->summarize($lines);

        // Tri stable pour la lecture : commune > panneau > type.
        $lines = $lines->sortBy([
            ['commune', 'asc'],
            ['reference', 'asc'],
            ['type', 'asc'],
        ])->values();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'lines'  => $lines,
                'totals' => $totals,
            ]);
        }

        // Filtres pour les selects de la vue
        $communes  = Commune::orderBy('name')->get(['id', 'name']);
        $clients   = Client::orderBy('name')->get(['id', 'name']);
        $campaigns = Campaign::whereYear('start_date', '<=', $year)
            ->whereYear('end_date', '>=', $year)
            ->orderBy('name')
            ->get(['id', 'name', 'client_id']);
        $anneesDispos = range(date('Y') + 1, max(2020, date('Y') - 5));

        // Pagination du tableau (les totals/PDF/Excel restent sur la
        // collection complète — la pagination est purement UI).
        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(10, min(200, $perPage));
        $page    = max(1, (int) $request->input('page', 1));
        $totalLines = $lines->count();
        $linesPage  = $lines->forPage($page, $perPage)->values();
        $paginator  = new \Illuminate\Pagination\LengthAwarePaginator(
            $linesPage,
            $totalLines,
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('admin.taxes.details', compact(
            'lines', 'totals', 'year', 'periodType', 'periodValue',
            'communes', 'clients', 'campaigns', 'anneesDispos', 'filters',
            'paginator', 'perPage'
        ));
    }

    /**
     * Export PDF de la vue détaillée — document à transmettre aux
     * administrations communales pour justifier les montants taxés.
     * Reprend exactement les filtres de /admin/taxes/details.
     */
    public function detailsPdf(Request $request, TaxCalculationService $calc)
    {
        $year         = (int) ($request->input('year', date('Y')));
        $periodType   = $request->input('period_type', TaxCalculationService::PERIOD_MONTHLY);
        $periodValue  = (int) ($request->input('period_value', date('n')));

        $filters = array_filter([
            'commune_id'  => $request->input('commune_id') ?: null,
            'client_id'   => $request->input('client_id')  ?: null,
            'campaign_id' => $request->input('campaign_id') ?: null,
            'type'        => $request->input('type')        ?: null,
        ]);

        $lines  = $calc->generateLines($periodType, $periodValue, $year, $filters);
        $totals = $calc->summarize($lines);
        $lines  = $lines->sortBy([['commune', 'asc'], ['reference', 'asc'], ['type', 'asc']])->values();

        // Libellé période lisible (titre PDF + nom du fichier)
        $periodLabel = match ($periodType) {
            TaxCalculationService::PERIOD_MONTHLY   =>
                \Carbon\Carbon::create()->month($periodValue)->translatedFormat('F') . ' ' . $year,
            TaxCalculationService::PERIOD_QUARTERLY => "T{$periodValue} {$year}",
            TaxCalculationService::PERIOD_ANNUAL    => "Année {$year}",
            default => $year,
        };

        // Récap filtres pour l'en-tête PDF
        $filterParts = [];
        if (!empty($filters['commune_id'])) {
            $c = Commune::find($filters['commune_id']);
            if ($c) $filterParts[] = "commune={$c->name}";
        }
        if (!empty($filters['client_id'])) {
            $cl = Client::find($filters['client_id']);
            if ($cl) $filterParts[] = "client={$cl->name}";
        }
        if (!empty($filters['campaign_id'])) {
            $cm = Campaign::find($filters['campaign_id']);
            if ($cm) $filterParts[] = "campagne={$cm->name}";
        }
        if (!empty($filters['type'])) {
            $filterParts[] = "type=" . strtoupper($filters['type']);
        }
        $filterSummary = implode(' · ', $filterParts);

        $pdf = Pdf::loadView('pdf.taxes-details', compact(
            'lines', 'totals', 'periodLabel', 'filterSummary'
        ))->setPaper('a4', 'landscape');

        $filename = 'taxes-details-' . str_replace(' ', '-', strtolower($periodLabel)) . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Export Excel du rapport détaillé (mêmes filtres que la vue HTML
     * et le PDF). Pour l'admin comptable qui veut retravailler les
     * données dans son tableur (TVA, formules, agrégats personnels).
     */
    public function detailsExcel(Request $request, TaxCalculationService $calc)
    {
        $year         = (int) ($request->input('year', date('Y')));
        $periodType   = $request->input('period_type', TaxCalculationService::PERIOD_MONTHLY);
        $periodValue  = (int) ($request->input('period_value', date('n')));

        $filters = array_filter([
            'commune_id'  => $request->input('commune_id') ?: null,
            'client_id'   => $request->input('client_id')  ?: null,
            'campaign_id' => $request->input('campaign_id') ?: null,
            'type'        => $request->input('type')        ?: null,
        ]);

        $lines = $calc->generateLines($periodType, $periodValue, $year, $filters)
            ->sortBy([['commune', 'asc'], ['reference', 'asc'], ['type', 'asc']])
            ->values();

        $periodLabel = match ($periodType) {
            TaxCalculationService::PERIOD_MONTHLY   =>
                \Carbon\Carbon::create()->month($periodValue)->translatedFormat('F') . ' ' . $year,
            TaxCalculationService::PERIOD_QUARTERLY => "T{$periodValue} {$year}",
            TaxCalculationService::PERIOD_ANNUAL    => "Année {$year}",
            default => (string) $year,
        };

        // Récap filtres pour l'en-tête imprimable de la feuille
        $parts = [];
        if (!empty($filters['commune_id']))  { $c = Commune::find($filters['commune_id']);  if ($c) $parts[] = "commune={$c->name}"; }
        if (!empty($filters['client_id']))   { $c = Client::find($filters['client_id']);    if ($c) $parts[] = "client={$c->name}"; }
        if (!empty($filters['campaign_id'])) { $c = Campaign::find($filters['campaign_id']);if ($c) $parts[] = "campagne={$c->name}"; }
        if (!empty($filters['type']))        $parts[] = 'type=' . strtoupper($filters['type']);
        $filterSummary = implode(' · ', $parts);

        $filename = 'taxes-details-' . str_replace(' ', '-', strtolower($periodLabel)) . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\TaxesDetailsExport($lines, $periodLabel, $filterSummary),
            $filename
        );
    }

    /**
     * Historique des écritures Tax legacy (avant refonte 2025) — accessible
     * via /admin/taxes/historique pour les comptes ayant déjà saisi des
     * paiements ligne à ligne.
     */
    public function historique(Request $request)
    {
        $query = Tax::with('commune');

        if ($request->filled('commune_id')) $query->where('commune_id', $request->commune_id);
        if ($request->filled('type'))       $query->where('type', $request->type);
        if ($request->filled('year'))       $query->where('year', $request->year);
        if ($request->filled('status'))     $query->where('status', $request->status);

        $taxes = $query->orderBy('year', 'desc')->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $communes = Commune::orderBy('name')->get();
        $totalEnAttente = Tax::where('status', 'en_attente')->count();
        $totalPayees    = Tax::where('status', 'payee')->count();
        $totalEnRetard  = Tax::where('status', 'en_retard')->count();
        $montantTotal   = Tax::where('status', '!=', 'payee')->sum('amount');

        if ($request->ajax() || $request->input('ajax')) {
            return response()->json([
                'html'       => view('admin.taxes.partials.table-rows', compact('taxes'))->render(),
                'pagination' => $taxes->hasPages() ? $taxes->links()->render() : '',
                'total'      => $taxes->total(),
            ]);
        }

        return view('admin.taxes.historique', compact('taxes', 'communes', 'totalEnAttente', 'totalPayees', 'totalEnRetard', 'montantTotal'));
    }

    public function create()
    {
        $communes = Commune::orderBy('name')->get();
        return view('admin.taxes.create', compact('communes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'commune_id' => 'required|exists:communes,id',
            'year'       => 'required|integer|min:2000|max:2099',
            'type'       => 'required|in:odp,tm,db',
            'amount'     => 'required|numeric|min:0',
            'due_date'   => 'nullable|date',
        ]);

        Tax::create($request->all());

        return redirect()->route('admin.taxes.index')
            ->with('success', 'Taxe créée avec succès !');
    }

    public function show(Tax $tax)
    {
        $tax->load('commune');
        return view('admin.taxes.show', compact('tax'));
    }

    public function edit(Tax $tax)
    {
        $communes = Commune::orderBy('name')->get();
        return view('admin.taxes.edit', compact('tax', 'communes'));
    }

    public function update(Request $request, Tax $tax)
    {
        $request->validate([
            'commune_id' => 'required|exists:communes,id',
            'year'       => 'required|integer|min:2000|max:2099',
            'type'       => 'required|in:odp,tm,db',
            'amount'     => 'required|numeric|min:0',
            'due_date'   => 'nullable|date',
            'status'     => 'required|in:en_attente,payee,en_retard',
        ]);

        $tax->update($request->all());

        return redirect()->route('admin.taxes.index')
            ->with('success', 'Taxe modifiée !');
    }

    public function destroy(Tax $tax)
    {
        $tax->delete();
        return redirect()->route('admin.taxes.index')
            ->with('success', 'Taxe supprimée !');
    }

    public function markPaid(Request $request, Tax $tax)
    {
        $tax->update([
            'status'  => 'payee',
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Taxe marquée comme payée ! ✅');
    }

    /**
     * Pré-visualise les lignes Tax qui seraient créées pour l'année donnée.
     * Sans effet de bord — l'admin déclenche la création via generateAuto().
     */
    public function previewAuto(Request $request, \App\Services\TaxAutoService $service)
    {
        $year = (int) $request->input('year', date('Y'));
        if ($year < 2000 || $year > 2099) {
            return response()->json(['error' => 'Année invalide.'], 422);
        }
        return response()->json($service->preview($year));
    }

    /**
     * Applique la génération automatique des taxes pour l'année donnée.
     * Idempotent : ré-appel sur la même année ne crée rien si les lignes
     * existent déjà.
     */
    public function generateAuto(Request $request, \App\Services\TaxAutoService $service)
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2099',
        ]);

        $result = $service->apply((int) $request->input('year'));

        $msg = $result['created'] > 0
            ? sprintf('%d taxe(s) créée(s) pour %d (%s FCFA).',
                     $result['created'], $result['year'], number_format($result['total_amount'], 0, ',', ' '))
            : 'Aucune nouvelle taxe à créer pour ' . $result['year'] . '.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(array_merge(['success' => true, 'message' => $msg], $result));
        }
        return redirect()->route('admin.taxes.index', ['year' => $result['year']])
            ->with('success', $msg);
    }

    /**
     * Calcul live ODP + TM sur une période donnée. Retourne le tableau
     * commune × format avec lignes détaillées + KPIs globaux.
     *
     * Formules (CIBLE CI / mairies CI 2025) :
     *   ODP = commune.odp_rate × surface_m² × nb_panneaux × nb_mois
     *   TM  = 1000 × surface_m² × nb_panneaux × nb_mois  (fixe national)
     *
     * nb_mois selon period_type : mensuel=1, trimestriel=3, annuel=12.
     *
     * Endpoint AJAX appelé depuis l'UI pour basculer entre les vues.
     */
    public function calcul(Request $request): JsonResponse
    {
        $request->validate([
            'period_type'  => 'required|in:mensuel,trimestriel,annuel',
            'period_year'  => 'required|integer|min:2000|max:2099',
            'period_value' => 'required|integer|min:0|max:12',
        ]);

        $periodType  = $request->input('period_type');
        $periodYear  = (int) $request->input('period_year');
        $periodValue = (int) $request->input('period_value');

        $nbMois = match ($periodType) {
            'mensuel'     => 1,
            'trimestriel' => 3,
            'annuel'      => 12,
            default       => 1,
        };

        // Charge les communes avec leurs panneaux opérables (exclut
        // maintenance + supprimés). On limite les colonnes pour rester
        // léger : un parc de 1000 panneaux × 34 communes = ~30k cellules,
        // l'agrégation est instantanée en mémoire.
        $communes = Commune::query()
            ->with([
                'panels' => fn($q) => $q
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['maintenance'])
                    ->with('format:id,name,width,height'),
            ])
            ->whereNotNull('odp_rate')
            ->get(['id', 'name', 'odp_rate', 'tm_rate']);

        // ── Agrégation des paiements multi-périodicité ──────────────
        // Bug avant : on ne lookupait que les paiements de la périodicité
        // EXACTE demandée (mensuel/trimestriel/annuel). Conséquence : payer
        // ADIAKE en mensuel février → la vue trimestre Q1 et la vue annuelle
        // affichaient "Non payé" comme si rien n'avait été versé.
        //
        // Fix : on charge TOUS les paiements de l'année et on agrège ceux
        // qui couvrent la fenêtre de mois de la période demandée. Prorata
        // appliqué quand un paiement déborde la fenêtre (ex: paiement
        // annuel vu en Q1 = 25% du montant payé).
        $queryMonths = self::monthsForPeriod($periodType, $periodValue);

        $allPayments = CommuneTaxPayment::where('period_year', $periodYear)
            ->whereIn('period_type', ['mensuel', 'trimestriel', 'annuel'])
            ->get()
            ->groupBy('commune_id');

        // Date de référence pour le snapshot tarifaire : on prend le
        // 1er jour du mois de la période demandée. Garantit que les
        // rapports historiques utilisent les tarifs en vigueur À CETTE
        // DATE (cohérent avec Invoice::syncLines qui fige les taux à
        // issued_at via Commune::ratesAt — règle d'or §3).
        $rateDate = \Carbon\Carbon::create(
            $periodYear,
            $queryMonths[0] ?? 1,
            1
        )->toDateString();

        $rows = $communes->map(function ($commune) use ($nbMois, $allPayments, $queryMonths, $periodType, $periodYear, $periodValue, $rateDate) {
            // Phase audit 8E — tarifs HISTORISÉS, pas courants.
            // Avant : $commune->odp_rate (valeur actuelle) → divergeait
            // avec la facturation qui utilise ratesAt(issued_at).
            $rates   = $commune->ratesAt($rateDate);
            $odpRate = (float) ($rates['odp'] ?? 0);
            $tmRate  = (float) ($rates['tm']  ?: config('billing.tm_default', 1000));

            // Lignes détaillées par format (pour affichage tableau)
            $lignes = $commune->panels
                ->groupBy('format_id')
                ->map(function ($panels) use ($tmRate, $odpRate, $nbMois) {
                    $fmt = $panels->first()->format;
                    if (!$fmt?->width || !$fmt?->height) return null;

                    $m2  = round((float) $fmt->width * (float) $fmt->height, 2);
                    $qty = $panels->count();
                    $odp = round($odpRate * $m2 * $qty * $nbMois);
                    $tm  = round($tmRate  * $m2 * $qty * $nbMois);

                    return [
                        'format_id'  => $fmt->id,
                        'format'     => $fmt->name,
                        'dimensions' => rtrim(rtrim(number_format($fmt->width, 2, '.', ''), '0'), '.')
                                      . '×'
                                      . rtrim(rtrim(number_format($fmt->height, 2, '.', ''), '0'), '.') . 'm',
                        'm2'         => $m2,
                        'qty'        => $qty,
                        'odp_taux'   => $odpRate,
                        'tm_taux'    => $tmRate,
                        'odp'        => $odp,
                        'tm'         => $tm,
                        'total'      => $odp + $tm,
                    ];
                })
                ->filter()
                ->values();

            $odpTheo = (float) $lignes->sum('odp');
            $tmTheo  = (float) $lignes->sum('tm');
            $totalTheo = $odpTheo + $tmTheo;

            // Agrégation des paiements de cette commune × intersection
            // avec la fenêtre de la période demandée.
            $communePayments = $allPayments->get($commune->id) ?? collect();
            $odpPaye = 0.0;
            $tmPaye  = 0.0;
            $latestPaidAt = null;
            $anyAttestation = false;
            $directPaymentId = null;

            foreach ($communePayments as $p) {
                $paymentMonths = self::monthsForPeriod($p->period_type, $p->period_value);
                $overlap = array_values(array_intersect($paymentMonths, $queryMonths));
                if (empty($overlap)) continue;
                $prorata = count($overlap) / count($paymentMonths);

                $odpPaye += ((float) $p->odp_paye) * $prorata;
                $tmPaye  += ((float) $p->tm_paye)  * $prorata;

                if ($p->paid_at) {
                    if (!$latestPaidAt || $p->paid_at->gt($latestPaidAt)) {
                        $latestPaidAt = $p->paid_at;
                    }
                }
                $anyAttestation = $anyAttestation || (bool) $p->attestation_recue;

                // ID du paiement direct (= même périodicité ET même valeur)
                // → utilisé par le bouton "Modifier" pour rééditer cette
                // ligne précise. S'il n'existe pas, le bouton restera "Payer".
                if ($p->period_type === $periodType && (int) $p->period_value === (int) $periodValue) {
                    $directPaymentId = $p->id;
                }
            }

            $odpPaye = round($odpPaye, 2);
            $tmPaye  = round($tmPaye,  2);
            $totalPaye = round($odpPaye + $tmPaye, 2);

            $statut = match (true) {
                $totalTheo === 0.0           => 'aucun',
                $totalPaye <= 0              => 'non_paye',
                $totalPaye >= $totalTheo - 1 => 'paye',
                default                      => 'partiel',
            };

            return [
                'commune'        => $commune->name,
                'commune_id'     => $commune->id,
                // 2026-06-19 — Exposé pour les filtres Zone (Abidjan/Intérieur)
                // et Commune côté JS. La règle métier "zone" reste cohérente
                // avec le reste de l'app : Abidjan = panneaux dans une commune
                // dont city='Abidjan', Intérieur = tout le reste.
                'city'           => $commune->city,
                'odp_taux'       => $odpRate,
                'tm_taux'        => $tmRate,
                'nb_panneaux'    => (int) $commune->panels->count(),
                'surface_totale' => (float) $lignes->sum(fn($l) => $l['m2'] * $l['qty']),
                'odp_theorique'  => $odpTheo,
                'tm_theorique'   => $tmTheo,
                'total_theorique'=> $totalTheo,
                'odp_paye'       => $odpPaye,
                'tm_paye'        => $tmPaye,
                'total_paye'     => $totalPaye,
                'solde'          => max(0, $totalTheo - $totalPaye),
                'paid_at'        => $latestPaidAt?->format('d/m/Y'),
                'attestation'    => $anyAttestation,
                'statut'         => $statut,
                'payment_id'     => $directPaymentId,
                'lignes'         => $lignes,
            ];
        })
        ->filter(fn($r) => $r['nb_panneaux'] > 0) // hide communes vides
        ->sortBy('commune')
        ->values();

        // KPIs globaux
        $kpi = [
            'odp_total'        => (float) $rows->sum('odp_theorique'),
            'tm_total'         => (float) $rows->sum('tm_theorique'),
            'grand_total'      => (float) $rows->sum('total_theorique'),
            'paye_total'       => (float) $rows->sum('total_paye'),
            'solde_total'      => (float) $rows->sum('solde'),
            'communes_actives' => $rows->count(),
            'panneaux_total'   => (int) $rows->sum('nb_panneaux'),
        ];

        return response()->json([
            'period_type'  => $periodType,
            'period_year'  => $periodYear,
            'period_value' => $periodValue,
            'nb_mois'      => $nbMois,
            'kpi'          => $kpi,
            'communes'     => $rows,
        ]);
    }

    /**
     * Enregistre / met à jour le paiement d'une commune pour une période
     * donnée. Idempotent via la contrainte unique (commune × période).
     */
    public function recordPayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'commune_id'        => 'required|exists:communes,id',
            'period_type'       => 'required|in:mensuel,trimestriel,annuel',
            'period_year'       => 'required|integer|min:2000|max:2099',
            'period_value'      => 'required|integer|min:0|max:12',
            'odp_paye'          => 'required|numeric|min:0',
            'tm_paye'           => 'required|numeric|min:0',
            'odp_theorique'     => 'required|numeric|min:0',
            'tm_theorique'      => 'required|numeric|min:0',
            'paid_at'           => 'nullable|date',
            'attestation_recue' => 'sometimes|boolean',
            'attestation_date'  => 'nullable|date',
            'notes'             => 'nullable|string|max:1000',
        ]);

        $payment = CommuneTaxPayment::updateOrCreate(
            [
                'commune_id'   => $data['commune_id'],
                'period_type'  => $data['period_type'],
                'period_year'  => $data['period_year'],
                'period_value' => $data['period_value'],
            ],
            [
                'odp_theorique'     => $data['odp_theorique'],
                'tm_theorique'      => $data['tm_theorique'],
                'odp_paye'          => $data['odp_paye'],
                'tm_paye'           => $data['tm_paye'],
                'paid_at'           => $data['paid_at'] ?? now(),
                'attestation_recue' => $data['attestation_recue'] ?? false,
                'attestation_date'  => $data['attestation_date'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'recorded_by'       => auth()->id(),
            ]
        );

        Log::info('commune_tax_payment.recorded', [
            'commune_id' => $data['commune_id'],
            'period'     => "{$data['period_type']}-{$data['period_year']}-{$data['period_value']}",
            'odp_paye'   => $data['odp_paye'],
            'tm_paye'    => $data['tm_paye'],
            'by'         => auth()->id(),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Paiement enregistré.',
            'payment' => [
                'id'                => $payment->id,
                'odp_paye'          => (float) $payment->odp_paye,
                'tm_paye'           => (float) $payment->tm_paye,
                'paid_at'           => $payment->paid_at?->format('d/m/Y'),
                'attestation_recue' => (bool) $payment->attestation_recue,
                'status'            => $payment->status,
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $query = Tax::with('commune');

        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->commune_id);
        }
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        $taxes    = $query->orderBy('year', 'desc')->get();
        $commune  = $request->filled('commune_id')
            ? Commune::find($request->commune_id)
            : null;

        $pdf = Pdf::loadView('pdf.taxes-report', compact('taxes', 'commune'));
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('rapport-taxes.pdf');
    }
}
