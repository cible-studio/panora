<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\CommuneTaxPayment;
use App\Models\Panel;
use App\Models\PanelCategory;
use App\Services\TaxCalculationService;
use App\Support\DownloadFilename;
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
    private static function monthsForPeriod(string $type, int $value, ?int $endValue = null): array
    {
        // Hotfix TX-2 v2 (2026-06-22) : bornes EXPLICITES (mensuel 1..12,
        // trimestriel 1..4) + exception sur type inconnu au lieu de
        // retourner []. Empêche les "division by zero" silencieuses en
        // aval quand un CommuneTaxPayment legacy a un period_type corrompu.
        //
        // FIX 2026-06-22 — Ajout du type 'personnalise' : range libre
        // de $value (mois début) à $endValue (mois fin). Permet par ex.
        // "Mai → Septembre" ou "Février → Août".
        return match ($type) {
            'mensuel'     => ($value >= 1 && $value <= 12)
                                ? [$value]
                                : throw new \InvalidArgumentException("Mois invalide : $value"),
            'trimestriel' => ($value >= 1 && $value <= 4)
                                ? range(($value - 1) * 3 + 1, $value * 3)
                                : throw new \InvalidArgumentException("Trimestre invalide : $value"),
            'annuel'      => range(1, 12),
            'personnalise' => ($value >= 1 && $value <= 12 && $endValue !== null && $endValue >= $value && $endValue <= 12)
                                ? range($value, $endValue)
                                : throw new \InvalidArgumentException("Période personnalisée invalide : $value → $endValue"),
            default       => throw new \InvalidArgumentException("Périodicité inconnue : $type"),
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
        $communes = Commune::orderBy('name')->get(['id', 'name', 'odp_rate', 'tm_rate', 'odp_rate_eclaire', 'tm_rate_eclaire']);

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
    /**
     * FIX 2026-06-22 — Normalise period_value pour qu'il soit cohérent
     * avec period_type. Évite les crashs 500 quand un lien externe (ex :
     * bouton "Détail panneau" du dashboard) passe un mois en mode trimestriel.
     * Source unique pour details(), detailsPdf(), detailsExcel().
     */
    private function normalizePeriodValue(string $periodType, int $periodValue): int
    {
        if ($periodType === TaxCalculationService::PERIOD_QUARTERLY) {
            return ($periodValue < 1 || $periodValue > 4)
                ? max(1, min(4, (int) ceil($periodValue / 3)))  // mois→trimestre
                : $periodValue;
        }
        if ($periodType === TaxCalculationService::PERIOD_MONTHLY) {
            return ($periodValue < 1 || $periodValue > 12)
                ? (int) date('n')
                : $periodValue;
        }
        if ($periodType === TaxCalculationService::PERIOD_ANNUAL) {
            return 0; // annuel ne prend pas de valeur
        }
        // FIX 2026-06-26 — Mode personnalisé : clamp mois début 1..12.
        if ($periodType === TaxCalculationService::PERIOD_CUSTOM) {
            return ($periodValue < 1 || $periodValue > 12) ? 1 : $periodValue;
        }
        return $periodValue;
    }

    /**
     * Filtres du détail des taxes — source unique pour l'écran, le PDF et
     * l'Excel (avant 2026-10-06 : trois copies identiques).
     *
     * Les drapeaux booléens sont posés APRÈS array_filter() : la valeur
     * false y serait sinon éliminée, alors que la vue doit pouvoir la
     * relire pour cocher ses cases.
     *
     * exclure_categories (2026-10-06) : ids de catégories à retirer.
     */
    private function detailFilters(Request $request): array
    {
        $filters = array_filter([
            'commune_id'  => $request->input('commune_id') ?: null,
            'client_id'   => $request->input('client_id')  ?: null,
            'campaign_id' => $request->input('campaign_id') ?: null,
            'type'        => $request->input('type')        ?: null,
        ]);
        // 2026-11-13 — La maintenance n'écarte plus le panneau par défaut
        // (l'ODP reste due, le mât occupe toujours le sol). Ce drapeau
        // permet de retrouver l'ancienne vue.
        //
        // 2026-11-17 — `include_maintenance` retiré : il n'avait plus
        // aucun effet depuis le 13/11 (le service ne lit que
        // `exclure_maintenance`), et deux noms opposés pour une seule case
        // étaient un piège pour la prochaine lecture.
        $filters['exclure_maintenance'] = $request->boolean('exclure_maintenance');
        // 2026-11-13 — Lumipub : une ligne par PIED, jamais la ligne d'axe.
        // Décochée par défaut (règle validée) pour ne rien changer aux
        // totaux déjà déclarés tant qu'on ne l'a pas demandé.
        $filters['inclure_lumipub'] = $request->boolean('inclure_lumipub');

        $exclues = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('exclure_categories', [])),
            fn ($id) => $id > 0
        )));
        if ($exclues) {
            $filters['exclure_categories'] = $exclues;
        }

        return $filters;
    }

    /**
     * Sélection multiple du détail par panneau (2026-11-18, « comme pour les
     * poses ») : les exports ne prennent que les lignes voulues.
     *   selection=cles  + cles[]  → seulement ces lignes ;
     *   selection=sauf  + cles[]  → toutes les lignes du filtre SAUF celles-ci
     *                               (« Toutes les lignes du filtre » puis
     *                               quelques-unes décochées).
     * Sans `selection` : toutes les lignes du filtre, comme avant.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: ?string} lignes gardées, libellé pour l'en-tête
     */
    private function appliquerSelection(Request $request, \Illuminate\Support\Collection $lines): array
    {
        $mode = $request->input('selection');
        if (!in_array($mode, ['cles', 'sauf'], true)) {
            return [$lines, null];
        }

        $cles = collect((array) $request->input('cles', []))
            ->map(fn ($c) => (string) $c)
            ->filter(fn ($c) => preg_match('/^\d+-(odp|tm)(-\d+)?$/', $c))
            ->take(20000)
            ->flip();

        $total  = $lines->count();
        $gardes = $lines->filter(function ($l) use ($cles, $mode) {
            $choisie = $cles->has(TaxCalculationService::cleLigne($l));

            return $mode === 'cles' ? $choisie : !$choisie;
        })->values();

        return [$gardes, $gardes->count() . ' ligne(s) choisie(s) sur ' . $total];
    }

    /** Noms des catégories exclues, pour l'en-tête des exports. */
    private function excludedCategoryNames(array $filters): string
    {
        return empty($filters['exclure_categories']) ? '' : PanelCategory::whereIn('id', $filters['exclure_categories'])
            ->orderBy('name')->pluck('name')->implode(', ');
    }

    public function details(Request $request, TaxCalculationService $calc)
    {
        $year         = (int) ($request->input('year', date('Y')));
        $periodType   = $request->input('period_type', TaxCalculationService::PERIOD_MONTHLY);
        $periodValue  = $this->normalizePeriodValue($periodType, (int) ($request->input('period_value', date('n'))));
        // FIX 2026-06-26 — Mode personnalisé : mois fin (1..12, ≥ début).
        $periodEndValue = null;
        if ($periodType === TaxCalculationService::PERIOD_CUSTOM) {
            $periodEndValue = (int) $request->input('period_end_value', $periodValue);
            if ($periodEndValue < $periodValue) $periodEndValue = $periodValue;
            if ($periodEndValue > 12)           $periodEndValue = 12;
        }

        $filters = $this->detailFilters($request);

        $lines  = $calc->generateLines($periodType, $periodValue, $year, $filters, $periodEndValue);
        $totals = $calc->summarize($lines);

        // Tri stable pour la lecture : commune > panneau > type.
        // 2026-11-18 — Clé de chaque ligne pour la sélection multiple.
        $lines = $lines->sortBy([
            ['commune', 'asc'],
            ['reference', 'asc'],
            ['type', 'asc'],
        ])->values()->map(fn ($l) => $l + ['cle' => TaxCalculationService::cleLigne($l)]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'lines'  => $lines,
                'totals' => $totals,
            ]);
        }

        // Filtres pour les selects de la vue
        $communes  = Commune::orderBy('name')->get(['id', 'name']);
        $categories = PanelCategory::orderBy('name')->get(['id', 'name']);
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

        // 2026-09-23 — Nom proposé dans le modal « renommer le fichier ».
        // Calculé ici et non dans la vue : c'est exactement le nom que le
        // serveur appliquera si l'utilisateur valide sans rien changer.
        $defaultExportName = $this->defaultExportBasename(
            $this->periodLabel($periodType, $periodValue, $periodEndValue, $year)
        );

        return view('admin.taxes.details', compact(
            'lines', 'totals', 'year', 'periodType', 'periodValue', 'periodEndValue',
            'communes', 'categories', 'clients', 'campaigns', 'anneesDispos', 'filters',
            'paginator', 'perPage', 'defaultExportName'
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
        // FIX 2026-06-22 — normalise period_value (cf. details() pour le détail).
        $periodValue  = $this->normalizePeriodValue($periodType, (int) ($request->input('period_value', date('n'))));
        // FIX 2026-06-26 — Mode personnalisé : propage mois fin.
        $periodEndValue = $periodType === TaxCalculationService::PERIOD_CUSTOM
            ? min(12, max($periodValue, (int) $request->input('period_end_value', $periodValue)))
            : null;

        $filters = $this->detailFilters($request);

        $lines  = $calc->generateLines($periodType, $periodValue, $year, $filters, $periodEndValue);
        [$lines, $selection] = $this->appliquerSelection($request, $lines);
        $totals = $calc->summarize($lines);
        $lines  = $lines->sortBy([['commune', 'asc'], ['reference', 'asc'], ['type', 'asc']])->values();

        // Libellé période lisible (titre PDF + nom du fichier)
        $periodLabel = $this->periodLabel($periodType, $periodValue, $periodEndValue, $year);

        // Récap filtres pour l'en-tête PDF (libellés en français). 2026-11-27
        // (point 16) : même description que l'en-tête de l'Excel.
        $filterMeta    = $this->filtresLisibles($filters, $selection);
        $filterSummary = self::resumeFiltres($filterMeta);

        // 2026-11-24 — Dates de début et de fin, et trimestres couverts, en
        // gras en tête du PDF. Mêmes bornes que le calcul des lignes.
        [$periodeDebut, $periodeFin] = $calc->resolvePeriod($periodType, $periodValue, $year, $periodEndValue);

        $pdf = Pdf::loadView('pdf.taxes-details', compact(
            'lines', 'totals', 'periodLabel', 'filterSummary', 'filterMeta', 'periodeDebut', 'periodeFin'
        ))->setPaper('a4', 'landscape'); // relevé, ancien design en paysage (24/11 soir)

        // 2026-09-23 — Le MP peut renommer le fichier avant de lancer le
        // téléchargement (même mécanique que les PDF de disponibilités).
        // Nom vide ou invalide → on retombe sur le nom auto.
        $filename = DownloadFilename::sanitize(
            $request->input('custom_filename'),
            $this->defaultExportBasename($periodLabel),
            'pdf'
        );
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
        // FIX 2026-06-22 — normalise period_value (cf. details() pour le détail).
        $periodValue  = $this->normalizePeriodValue($periodType, (int) ($request->input('period_value', date('n'))));
        // FIX 2026-06-26 — Mode personnalisé : propage mois fin.
        $periodEndValue = $periodType === TaxCalculationService::PERIOD_CUSTOM
            ? min(12, max($periodValue, (int) $request->input('period_end_value', $periodValue)))
            : null;

        $filters = $this->detailFilters($request);

        [$lines, $selection] = $this->appliquerSelection($request,
            $calc->generateLines($periodType, $periodValue, $year, $filters, $periodEndValue));
        $lines = $lines->sortBy([['commune', 'asc'], ['reference', 'asc'], ['type', 'asc']])->values();

        $periodLabel = $this->periodLabel($periodType, $periodValue, $periodEndValue, $year);

        // Récap filtres pour l'en-tête imprimable de la feuille. 2026-11-27
        // (point 16) : en français, comme le PDF (avant : « type=TM »).
        $filterSummary = self::resumeFiltres($this->filtresLisibles($filters, $selection));

        // 2026-09-23 — Nom personnalisable, cf. detailsPdf().
        $filename = DownloadFilename::sanitize(
            $request->input('custom_filename'),
            $this->defaultExportBasename($periodLabel),
            'xlsx'
        );
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
        // 2026-11-26 (point 23 validé) — l'ancienne page « Taxes — historique »
        // renvoie au Suivi des mairies, qui tient les règlements depuis le
        // 16/11. Avant, elle restait ouverte et montrait d'anciens statuts
        // qui contredisaient le suivi. (Code ci-dessous conservé, inutilisé.)
        if (!$request->ajax() && !$request->input('ajax')) {
            return redirect()
                ->route('admin.taxes.suivi.index', array_filter(['exercice' => $request->input('year')]))
                ->with('info', 'Les règlements aux mairies se suivent désormais ici, dans le Suivi des mairies.');
        }

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

    /**
     * LOT 3 (cahier 2026-06-19) — Historique des paiements d'UNE commune.
     *
     * Affiche tous les versements enregistrés (CommuneTaxPayment) chrono-
     * logiquement avec, pour chaque ligne, le cumul payé jusqu'à cette
     * date et le solde restant "as of payment" (recalculé en temps réel).
     *
     * Important : aucune opération n'est supprimée — les SoftDeletes
     * éventuels sont aussi inclus (avec marqueur visuel) pour respecter
     * la spec "Aucune opération ne doit être supprimée de l'historique".
     */
    public function communePaymentsHistory(Commune $commune, Request $request, TaxCalculationService $calc)
    {
        // 2026-11-16 — Remplacé par la fiche du Suivi des mairies : les
        // règlements y sont rattachés à l'exercice qu'ils règlent, avec les
        // arriérés reportés. L'ancien écran lisait commune_tax_payments par
        // date de versement et comparait à un dû recalculé sans arriérés —
        // le « solde après versement » ne correspondait donc à rien de ce
        // que la mairie réclame. Les anciennes lignes sont recopiées par
        // `taxes:reprendre-reglements`. L'adresse reste valable (liens
        // enregistrés) et mène au même endroit.
        return redirect()->to(
            route('admin.taxes.suivi.commune', ['commune' => $commune, 'exercice' => (int) $request->input('year', date('Y'))])
            . '#reglements'
        );
    }

    /**
     * Libellé lisible de la période demandée (« Septembre 2026 », « T3 2026 »,
     * « Année 2026 », « Mars → Juin 2026 »).
     *
     * 2026-09-23 — Extrait de detailsPdf() et detailsExcel(), qui en portaient
     * deux copies identiques. Sert aussi au nom de fichier proposé dans le
     * modal de renommage : la suggestion affichée à l'écran est ainsi
     * exactement ce que le serveur utilisera si l'utilisateur ne change rien.
     */
    /**
     * Filtres du Détail par panneau, en français, pour l'en-tête du PDF et de
     * l'Excel (source unique, 2026-11-27). Les catégories exclues ne sont
     * jamais écrites (demande du 24/11) ; la maintenance n'est écrite que
     * quand elle est exclue (incluse par défaut depuis le 13/11).
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function filtresLisibles(array $filters, ?string $selection): array
    {
        $meta = [];
        if (!empty($filters['commune_id']) && ($c = Commune::find($filters['commune_id']))) {
            $meta[] = ['label' => 'Commune', 'value' => $c->name];
        }
        if (!empty($filters['client_id']) && ($cl = Client::find($filters['client_id']))) {
            $meta[] = ['label' => 'Client', 'value' => $cl->name];
        }
        if (!empty($filters['campaign_id']) && ($cm = Campaign::find($filters['campaign_id']))) {
            $meta[] = ['label' => 'Campagne', 'value' => $cm->name];
        }
        if (!empty($filters['exclure_maintenance'])) {
            $meta[] = ['label' => 'Maintenance', 'value' => 'Panneaux en maintenance exclus'];
        }
        if (!empty($filters['inclure_lumipub'])) {
            $meta[] = ['label' => 'Lumipub', 'value' => 'Pieds des axes inclus (une ligne par mât)'];
        }
        if (!empty($filters['type'])) {
            $typeLabels = ['tm' => 'Taxe Municipale (TM)', 'odp' => 'Occupation Domaine Public (ODP)'];
            $meta[] = ['label' => 'Nature', 'value' => $typeLabels[$filters['type']] ?? strtoupper($filters['type'])];
        }
        if ($selection) {
            $meta[] = ['label' => 'Sélection', 'value' => $selection];
        }
        return $meta;
    }

    /** « Commune : Abidjan · Nature : Taxe Municipale (TM) ». */
    private static function resumeFiltres(array $meta): string
    {
        return implode(' · ', array_map(fn ($m) => $m['label'] . ' : ' . $m['value'], $meta));
    }

    private function periodLabel(string $periodType, int $periodValue, ?int $periodEndValue, int $year): string
    {
        return match ($periodType) {
            TaxCalculationService::PERIOD_MONTHLY   =>
                \Carbon\Carbon::create()->month($periodValue)->translatedFormat('F') . ' ' . $year,
            TaxCalculationService::PERIOD_QUARTERLY => "T{$periodValue} {$year}",
            TaxCalculationService::PERIOD_ANNUAL    => "Année {$year}",
            TaxCalculationService::PERIOD_CUSTOM    =>
                \Carbon\Carbon::create()->month($periodValue)->translatedFormat('F')
                . ' → '
                . \Carbon\Carbon::create()->month($periodEndValue ?? $periodValue)->translatedFormat('F')
                . ' ' . $year,
            default => (string) $year,
        };
    }

    /**
     * Nom de fichier par défaut (sans extension) des exports du détail taxes.
     * Source unique : utilisé par le PDF, l'Excel, et proposé dans le modal
     * de renommage côté vue.
     */
    private function defaultExportBasename(string $periodLabel): string
    {
        return 'taxes-details-' . str_replace(' ', '-', strtolower($periodLabel));
    }

    /**
     * Total dû annuel d'une commune — somme des 12 mois théoriques
     * (ODP + TM). Source unique de vérité = TaxCalculationService, donc
     * cohérent au franc près avec le dashboard live.
     */
    private function computeAnnualTotalDue(Commune $commune, int $year, TaxCalculationService $calc): int
    {
        $filters = ['commune_id' => $commune->id];

        // L'ODP passe par un SEUL appel annuel. Depuis TX-14 elle est
        // mensuelle, donc sommer les 12 mois donnerait le même résultat ;
        // on garde l'appel unique, c'est 12 requêtes de moins.
        $odp = (int) ($calc->summarize(
            $calc->generateLines(TaxCalculationService::PERIOD_ANNUAL, 0, $year, $filters)
        )['odp_total'] ?? 0);

        // La TM est réellement mensuelle (mois de campagne entamés) :
        // la somme des 12 mois reste la bonne valeur.
        $tm = 0;
        for ($mois = 1; $mois <= 12; $mois++) {
            $tm += (int) ($calc->summarize(
                $calc->generateLines(TaxCalculationService::PERIOD_MONTHLY, $mois, $year, $filters)
            )['tm_total'] ?? 0);
        }

        return $odp + $tm;
    }

    /**
     * LOT 2 (cahier 2026-06-19) — Fiche commune détaillée.
     * Depuis le 2026-11-16 : redirige vers la fiche du Suivi des mairies
     * (TaxeSuiviController::commune). La vue admin.taxes.commune-show reste
     * sur le disque, plus servie.
     */
    public function showCommune(Commune $commune, Request $request, TaxCalculationService $calc)
    {
        // 2026-11-16 — Remplacé par la fiche du Suivi des mairies (exercice,
        // arriérés, ODP et TM par trimestre, règlements, relevé PDF). Deux
        // défauts de l'ancienne fiche disparaissent avec elle :
        //   · le solde se calculait mois par mois avec max(0, dû − payé), sans
        //     compensation : une année réglée en une fois en mars affichait
        //     encore un solde sur les onze autres mois ;
        //   · les versements étaient rangés par date de paiement, quand la
        //     page Taxes les rangeait par année réglée.
        // L'adresse reste valable et mène à la nouvelle fiche.
        return redirect()->route('admin.taxes.suivi.commune', [
            'commune'  => $commune,
            'exercice' => (int) $request->input('year', date('Y')),
        ]);
    }

    /**
     * 2026-11-27 (point 20 validé) — Anciennes fonctions de la table `taxes`
     * (avant le Suivi des mairies) : créer / modifier / supprimer / payer une
     * écriture, génération automatique, PDF. Plus aucun écran ne les lit :
     * /admin/taxes/create enregistrait une ligne qui n'apparaissait nulle
     * part. Les adresses restent valables et ramènent à la page Taxes, comme
     * /admin/taxes/{id} (point 23 du 26/11).
     */
    private function ancienneFonctionTaxes(Request $request)
    {
        $message = 'Cette ancienne fonction des taxes n\'existe plus : les montants dus se lisent sur cette page, '
            . 'les règlements s\'enregistrent dans le Suivi des mairies.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['error' => $message], 410);
        }

        return redirect()->route('admin.taxes.index')->with('info', $message);
    }

    public function create()
    {
        return $this->ancienneFonctionTaxes(request());
    }

    public function store(Request $request)
    {
        return $this->ancienneFonctionTaxes($request);
    }

    public function show(Tax $tax)
    {
        // 2026-11-26 (point 23 validé) — la vue admin.taxes.show n'existe
        // pas : /admin/taxes/{id} finissait en erreur 500. Retour à la page
        // Taxes, sur l'année de l'écriture.
        return redirect()
            ->route('admin.taxes.index', array_filter(['year' => $tax->year]))
            ->with('info', 'Cette écriture n\'a pas de page à elle : voici la page Taxes de son année.');
    }

    public function edit(Tax $tax)
    {
        return $this->ancienneFonctionTaxes(request());
    }

    public function update(Request $request, Tax $tax)
    {
        return $this->ancienneFonctionTaxes($request);
    }

    public function destroy(Tax $tax)
    {
        return $this->ancienneFonctionTaxes(request());
    }

    public function markPaid(Request $request, Tax $tax)
    {
        return $this->ancienneFonctionTaxes($request);
    }

    /** Ancienne génération automatique (aperçu) : voir ancienneFonctionTaxes(). */
    public function previewAuto(Request $request, \App\Services\TaxAutoService $service)
    {
        return $this->ancienneFonctionTaxes($request);
    }

    /** Ancienne génération automatique : voir ancienneFonctionTaxes(). */
    public function generateAuto(Request $request, \App\Services\TaxAutoService $service)
    {
        return $this->ancienneFonctionTaxes($request);
    }

    /**
     * Calcul live ODP + TM sur une période donnée. Retourne le tableau
     * commune × format avec lignes détaillées + KPIs globaux.
     *
     * Depuis le 2026-11-27 : regroupe les lignes de
     * TaxCalculationService::generateLines() (même calcul que le Détail par
     * panneau et le Suivi des mairies) :
     *   ODP = tarif du mois × surface × mois calendaires où le mât est debout
     *   TM  = tarif du mois (1 000 par défaut) × surface × mois de campagne
     *
     * Endpoint AJAX appelé depuis l'UI pour basculer entre les vues.
     */
    public function calcul(Request $request, TaxCalculationService $calc): JsonResponse
    {
        // Hotfix TX-2 (2026-06-22) : validation period_value CONTEXTUELLE
        // au period_type. Avant : max:12 même en trimestriel → un
        // period_value=5 passait la validation puis monthsForPeriod
        // produisait range(13, 15) → Carbon::create(year, 13, …) avec
        // rollover silencieux et calcul incohérent (HTTP 500 sur Plateau
        // dans certains scénarios reproduits).
        $request->validate([
            // FIX 2026-06-22 — Ajout type 'personnalise' (mois début → mois fin libre).
            'period_type'  => 'required|in:mensuel,trimestriel,annuel,personnalise',
            'period_year'  => 'required|integer|min:2000|max:2099',
            'period_value' => 'required|integer|min:0|max:12',
            // Requis seulement en mode personnalisé.
            'period_end_value' => 'nullable|integer|min:1|max:12',
        ]);
        $periodType  = $request->input('period_type');
        $periodValue = (int) $request->input('period_value');
        $periodEndValue = $request->input('period_end_value') !== null
            ? (int) $request->input('period_end_value')
            : null;
        // Borne contextuelle : mensuel 1..12, trimestriel 1..4, annuel 0, personnalise 1..12.
        $maxByType = ['mensuel' => 12, 'trimestriel' => 4, 'annuel' => 0, 'personnalise' => 12];
        $minByType = ['mensuel' => 1,  'trimestriel' => 1, 'annuel' => 0, 'personnalise' => 1];
        if ($periodValue < $minByType[$periodType] || $periodValue > $maxByType[$periodType]) {
            abort(422, "Valeur de période invalide pour le type {$periodType} (attendu {$minByType[$periodType]}..{$maxByType[$periodType]}).");
        }
        if ($periodType === 'personnalise') {
            if ($periodEndValue === null || $periodEndValue < $periodValue) {
                abort(422, "Mois de fin invalide pour période personnalisée (doit être >= mois de début).");
            }
        }
        $periodYear  = (int) $request->input('period_year');

        $nbMois = match ($periodType) {
            'mensuel'     => 1,
            'trimestriel' => 3,
            'annuel'      => 12,
            'personnalise' => ($periodEndValue - $periodValue + 1),
            default       => 1,
        };

        // TX-12 (2026-09-23) — La maintenance est pilotable, comme sur
        // /admin/taxes/details. 2026-11-13 : incluse par défaut,
        // 'exclure_maintenance' la retire. Lumipub : pieds exclus par défaut.
        $exclureMaintenance = $request->boolean('exclure_maintenance');
        $inclureLumipub     = $request->boolean('inclure_lumipub');

        // ── 2026-11-16 — Payé, solde et statut : ceux de l'EXERCICE ──────
        // Source unique : TaxeExerciceService (Suivi des mairies). Un règlement
        // se rattache à un exercice, pas à un mois ; le dû théorique de la
        // PÉRIODE reste affiché à part (colonnes ODP / TM / Total).
        $queryMonths = self::monthsForPeriod($periodType, $periodValue, $periodEndValue);

        $suivi = app(\App\Services\Taxes\TaxeExerciceService::class);
        $exercicesSuivis = $suivi->exercices($periodYear);
        $reglementsAnnee = \App\Models\TaxeReglement::valides()
            ->where('exercice', $periodYear)
            ->get()
            ->groupBy('commune_id');

        // Tarifs AFFICHÉS : ceux du 1er jour de la période (Commune::ratesAt).
        // Le calcul, lui, prend le tarif de chaque mois (generateLines).
        $rateDate = \Carbon\Carbon::create(
            $periodYear,
            $queryMonths[0] ?? 1,
            1
        )->toDateString();

        // ── 2026-11-27 (point 4 validé) — LES LIGNES DU MOTEUR, telles quelles ──
        // Même calcul que le Détail par panneau et que le Suivi des mairies
        // (TaxCalculationService::generateLines) : ODP et TM, panneaux
        // démantelés en cours de période (ODP au prorata), formats sans
        // largeur/hauteur et communes sans tarif ODP compris. Avant, la page
        // repartait du parc ACTUEL (panneaux debout, formats complets) et
        // allait chercher les montants du moteur par (commune, format) : ce qui
        // n'avait plus de panneau debout disparaissait du total.
        $lignesMoteur = $calc->generateLines($periodType, $periodValue, $periodYear, [
            'exclure_maintenance' => $exclureMaintenance,
            'inclure_lumipub'     => $inclureLumipub,
        ], $periodEndValue);
        $parCommune = $lignesMoteur->groupBy('commune_id');
        $formats = \App\Models\PanelFormat::query()
            ->whereIn('id', $lignesMoteur->pluck('format_id')->filter()->unique()->values()->all())
            ->get(['id', 'name', 'width', 'height', 'surface'])
            ->keyBy('id');
        $communes = Commune::query()
            ->whereIn('id', $parCommune->keys()->all())
            ->get(['id', 'name', 'city', 'odp_rate', 'tm_rate', 'odp_rate_eclaire', 'tm_rate_eclaire']);

        // Faces d'une ligne ODP : même règle que le Suivi des mairies.
        $faces = fn (array $l) => TaxCalculationService::facesDeLigne($l);

        $rows = $communes->map(function ($commune) use ($periodYear, $rateDate, $parCommune, $formats, $faces, $suivi, $exercicesSuivis, $reglementsAnnee) {
            // Phase audit 8E — tarifs HISTORISÉS (Commune::ratesAt).
            $rates   = $commune->ratesAt($rateDate);
            $odpRate = (float) ($rates['odp'] ?? 0);
            // Sans tarif TM, la base nationale s'applique (règle du 26/11) :
            // on l'affiche, avec la mention « par défaut » (point 12, 27/11).
            $tmBrut      = (float) ($rates['tm'] ?? 0);
            $tmParDefaut = $tmBrut <= 0;
            $tmRate      = $tmParDefaut ? \App\Services\TaxCalculationService::tmParDefaut() : $tmBrut;
            // Tarif des panneaux éclairés, affiché s'il diffère — avec la même
            // base par défaut (avant : « TM 0 » s'affichait comme un tarif éclairé).
            $ratesEclaire   = $commune->ratesAt($rateDate, true);
            $tmEclaire      = (float) $ratesEclaire['tm'] > 0 ? (float) $ratesEclaire['tm'] : \App\Services\TaxCalculationService::tmParDefaut();
            $odpRateEclaire = (float) $ratesEclaire['odp'] !== $odpRate ? (float) $ratesEclaire['odp'] : null;
            $tmRateEclaire  = $tmEclaire !== $tmRate ? $tmEclaire : null;

            $lc   = $parCommune->get($commune->id, collect());
            $odpL = $lc->where('type', TaxCalculationService::TYPE_ODP);

            // Lignes par format (infobulle du tableau)
            $lignes = $lc
                ->groupBy(fn ($l) => (int) ($l['format_id'] ?? 0))
                ->map(function ($g, $formatId) use ($formats, $faces, $odpRate, $tmRate) {
                    $fmt  = $formats->get($formatId);
                    $odpG = $g->where('type', TaxCalculationService::TYPE_ODP);
                    $odp  = round($odpG->sum('amount'));
                    $tm   = round($g->where('type', TaxCalculationService::TYPE_TM)->sum('amount'));

                    return [
                        'format_id'  => $formatId ?: null,
                        'format'     => $fmt?->name ?? '—',
                        'dimensions' => $fmt?->dimensions_label ?? '',
                        // Même surface que le moteur et que la facture (point 5, 27/11).
                        'm2'         => (float) ($fmt?->surface_m2 ?? 0),
                        'qty'        => $odpG->count(),                           // mâts
                        'faces'      => (int) $odpG->sum(fn ($l) => $faces($l)),
                        'odp_taux'   => $odpRate,
                        'tm_taux'    => $tmRate,
                        'odp'        => $odp,
                        'tm'         => $tm,
                        'total'      => $odp + $tm,
                    ];
                })
                ->values();

            $odpTheo = (float) $lignes->sum('odp');
            $tmTheo  = (float) $lignes->sum('tm');
            $totalTheo = $odpTheo + $tmTheo;

            // 2026-11-16 — Payé / solde / statut de l'EXERCICE (Suivi des
            // mairies), arriérés compris. Exercice non suivi (avant la
            // reprise) : aucune donnée de règlement fiable → « aucun ».
            $ex   = $exercicesSuivis->get($commune->id);
            $regl = $reglementsAnnee->get($commune->id, collect());
            $totalPaye = (float) ($ex['total_paye'] ?? 0);
            // 2026-11-26 (point 24 validé) — même statut que le Suivi des
            // mairies, qui est la source : statutAffiche() applique la règle
            // de l'année de reprise (« ✍ Réclamé à saisir »). Avant, la page
            // lisait le statut brut : une mairie « à saisir » dans le suivi
            // s'affichait « ✓ Payé » ici dès qu'un versement existait.
            $statutSuivi = $ex ? \App\Services\Taxes\TaxeExerciceService::statutAffiche($ex) : null;
            $statutEx  = $statutSuivi
                ? \App\Services\Taxes\TaxeExerciceService::statutPageTaxes($statutSuivi)
                : 'aucun';
            $libelleSuivi = $statutSuivi
                ? (\App\Services\Taxes\TaxeExerciceService::LIBELLES_STATUT[$statutSuivi] ?? null)
                : null;
            $dernier = $regl->max('paid_at');

            return [
                'commune'        => $commune->name,
                'commune_id'     => $commune->id,
                // FIX 2026-06-22 — is_abidjan calculé via la liste blanche
                // officielle Commune::ABIDJAN_COMMUNES (cf. Commune::isAbidjan).
                // Le champ city brut était unreliable (Bingerville pouvait
                // être city='Bingerville' au lieu de city='Abidjan' selon l'import).
                'city'           => $commune->city,
                'is_abidjan'     => $commune->isAbidjan(),
                'odp_taux'       => $odpRate,
                'tm_taux'        => $tmRate,
                'tm_par_defaut'  => $tmParDefaut,
                'odp_taux_eclaire' => $odpRateEclaire,
                'tm_taux_eclaire'  => $tmRateEclaire,
                // 2026-11-27 (point 13) — Panneaux = mâts taxés sur la période
                // (comme le Suivi des mairies), faces à part ; surface = surface
                // taxable des mâts (ODP).
                'nb_panneaux'    => $odpL->count(),
                'nb_faces'       => (int) $odpL->sum(fn ($l) => $faces($l)),
                'surface_totale' => round((float) $odpL->sum('surface'), 2),
                'odp_theorique'  => $odpTheo,
                'tm_theorique'   => $tmTheo,
                'total_theorique'=> $totalTheo,
                // Ventilation indicative (objet du règlement) ; « global » et
                // « arriérés » ne sont ni ODP ni TM.
                'odp_paye'       => (float) $regl->where('objet', \App\Models\TaxeReglement::OBJET_ODP)->sum('montant'),
                'tm_paye'        => (float) $regl->where('objet', \App\Models\TaxeReglement::OBJET_TM)->sum('montant'),
                'total_paye'     => $totalPaye,
                'solde'          => max(0, (int) ($ex['solde'] ?? 0)),
                'paid_at'        => $dernier ? \Carbon\Carbon::parse($dernier)->format('d/m/Y') : null,
                'attestation'    => $regl->contains(fn ($r) => !empty($r->quittance_path)),
                // Un seul statut : celui de l'exercice. Les deux clés restent
                // pour le JS et les filtres KPI existants.
                'statut'         => $statutEx,
                'statut_year'    => $statutEx,
                // Libellé et explication du Suivi des mairies (même texte).
                'statut_libelle' => $libelleSuivi[1] ?? null,
                'statut_aide'    => $libelleSuivi[2] ?? null,
                'total_paye_year'      => $totalPaye,
                'total_theorique_year' => (float) ($ex['total_du'] ?? 0),
                'total_du_exercice'    => (float) ($ex['total_du'] ?? 0),
                'a_saisir'       => (bool) ($ex['a_saisir'] ?? false),
                // Plus de modale de paiement sur cet écran (2026-11-16).
                'payment_id'     => null,
                'mode'           => null,
                'reference'      => null,
                'comment'        => null,
                'lignes'         => $lignes,
            ];
        })
        ->filter(fn($r) => $r['nb_panneaux'] > 0 || $r['total_theorique'] > 0) // communes sans ligne : masquées
        // FIX 2026-06-22 — Tri alphabétique accent-insensitive (Adjamé, Attécoubé,
        // Port-Bouët triés correctement même avec accents/tirets/casse mixtes).
        ->sortBy(fn ($r) => Commune::normalizeForMatch($r['commune']))
        ->values();

        // KPIs globaux
        // 2026-11-16 — « Déjà payé » et « Solde restant » sont ceux de
        // l'EXERCICE, lus dans le Suivi des mairies (mêmes chiffres que son
        // tableau, mairies sans panneau comprises : une mairie qui n'a plus
        // que des arriérés reste due). Le solde est la somme des soldes
        // positifs : un trop-perçu chez l'une n'efface pas la dette d'une autre.
        $totExercice   = $suivi->totaux($exercicesSuivis);
        $zonesExercice = $suivi->parZone($exercicesSuivis);
        $kpi = [
            'odp_total'        => (float) $rows->sum('odp_theorique'),
            'tm_total'         => (float) $rows->sum('tm_theorique'),
            'grand_total'      => (float) $rows->sum('total_theorique'),
            'paye_total'       => (float) $totExercice['total_paye'],
            'solde_total'      => (float) $totExercice['reste'],
            'communes_actives' => $rows->count(),
            'panneaux_total'   => (int) $rows->sum('nb_panneaux'),
            'faces_total'      => (int) $rows->sum('nb_faces'),
            // FIX 2026-06-22 v2 — Compteurs basés sur le statut ANNUEL
            // (pas sur la période courante). Ainsi en mode Mensuel/Trim/Perso
            // on voit quand même les communes en cours de règlement sur
            // l'année, pas seulement celles à moitié payées sur 1 mois précis.
            'communes_soldees'    => $rows->where('statut_year', 'paye')->count(),
            'communes_partielles' => $rows->where('statut_year', 'partiel')->count(),
            'communes_non_payees' => $rows->where('statut_year', 'non_paye')->count(),
        ];

        // LOT 4 (cahier 2026-06-19) — KPIs supplémentaires.
        //   - Répartition Abidjan / Intérieur (montants théoriques)
        //   - Top 5 communes par montant dû
        //   - Taux de couverture global (payé / dû)
        // FIX 2026-06-22 — Liste blanche officielle Commune::ABIDJAN_COMMUNES
        // (cf. Commune::isAbidjan). Le champ city n'était pas fiable selon
        // l'import : Bingerville pouvait être city='Bingerville' au lieu de
        // city='Abidjan' → comptée à tort en Intérieur. is_abidjan est
        // calculé en amont via la liste blanche → plus de divergence.
        $abidjan   = $rows->filter(fn ($r) => !empty($r['is_abidjan']));
        $interieur = $rows->reject(fn ($r) => !empty($r['is_abidjan']));
        $totAbidjanEx   = $suivi->totaux($zonesExercice['abidjan']);
        $totInterieurEx = $suivi->totaux($zonesExercice['interieur']);
        $kpi['breakdown'] = [
            'abidjan' => [
                'total_du'   => (float) $abidjan->sum('total_theorique'),
                'total_paye' => (float) $totAbidjanEx['total_paye'],
                'solde'      => (float) $totAbidjanEx['reste'],
                'communes'   => $abidjan->count(),
                'panneaux'   => (int) $abidjan->sum('nb_panneaux'),
            ],
            'interieur' => [
                'total_du'   => (float) $interieur->sum('total_theorique'),
                'total_paye' => (float) $totInterieurEx['total_paye'],
                'solde'      => (float) $totInterieurEx['reste'],
                'communes'   => $interieur->count(),
                'panneaux'   => (int) $interieur->sum('nb_panneaux'),
            ],
        ];
        $kpi['top_communes'] = $rows
            ->sortByDesc('total_theorique')
            ->take(5)
            ->map(fn ($r) => [
                'commune_id'      => $r['commune_id'],
                'commune'         => $r['commune'],
                'total_theorique' => (float) $r['total_theorique'],
                'total_du_exercice' => (float) $r['total_du_exercice'],
                'total_paye'      => (float) $r['total_paye'],
                'solde'           => (float) $r['solde'],
                // 2026-11-27 (point 10) — réclamé non saisi : pas de %.
                'a_saisir'        => (bool) $r['a_saisir'],
            ])
            ->values();
        // Taux de couverture : payé / dû de l'EXERCICE (2026-11-16), les deux
        // venant du Suivi des mairies. 2026-11-27 (point 10 validé) : sans les
        // mairies dont le réclamé n'est pas saisi (dû 0 mais versements
        // comptés : le taux pouvait dépasser 100 %).
        $totCouverture = $suivi->totaux($exercicesSuivis->reject(fn ($e) => !empty($e['a_saisir'])));
        $totalDueYearGlobal = (float) $totCouverture['total_du'];
        $kpi['taux_couverture'] = $totalDueYearGlobal > 0
            ? round(((float) $totCouverture['total_paye'] / $totalDueYearGlobal) * 100, 1)
            : 0;
        $kpi['communes_a_saisir'] = $exercicesSuivis->filter(fn ($e) => !empty($e['a_saisir']))->count();

        // LOT 4 (cahier 2026-06-19) — Évolution mensuelle des paiements
        // sur l'année en cours, par mois de versement. Source depuis le
        // 2026-11-16 : les règlements du Suivi des mairies (non annulés),
        // quel que soit l'exercice réglé — c'est la trésorerie sortie.
        $paymentsThisYear = \App\Models\TaxeReglement::valides()
            ->whereYear('paid_at', $periodYear)
            ->get(['paid_at', 'montant']);
        $monthNamesShort = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        $paymentsEvolution = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly = $paymentsThisYear->filter(fn ($p) => (int) $p->paid_at->month === $m);
            $paymentsEvolution[] = [
                'mois'  => $m,
                'label' => $monthNamesShort[$m],
                'total' => (int) $monthly->sum('montant'),
            ];
        }
        $kpi['payments_evolution'] = $paymentsEvolution;

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
     * Ancien point d'entrée de la modale « Enregistrer paiement ».
     *
     * 2026-11-16 — La modale a quitté la page Taxes. Ce point d'entrée reste
     * (un onglet ouvert avant la mise en production peut encore l'appeler),
     * mais il n'écrit plus dans commune_tax_payments : son updateOrCreate
     * ÉCRASAIT le versement précédent de la même période. Chaque appel crée
     * désormais un règlement du Suivi des mairies (TaxeReglement), rattaché
     * à l'exercice period_year — jamais d'écrasement.
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
            'odp_theorique'     => 'nullable|numeric|min:0',
            'tm_theorique'      => 'nullable|numeric|min:0',
            'paid_at'           => 'nullable|date|before_or_equal:today',
            'mode'              => 'nullable|in:' . implode(',', array_keys(\App\Models\TaxeReglement::MODES)),
            'reference'         => 'nullable|string|max:100',
            'comment'           => 'nullable|string|max:2000',
            'attestation_recue' => 'sometimes|boolean',
            'attestation_date'  => 'nullable|date',
            'notes'             => 'nullable|string|max:1000',
        ]);

        $reprise = (int) config('taxes.exercice_reprise', 2026);
        if ((int) $data['period_year'] < $reprise) {
            return response()->json([
                'ok'      => false,
                'message' => "Le suivi des règlements commence à l'exercice {$reprise}.",
            ], 422);
        }

        $odp = (int) round((float) $data['odp_paye']);
        $tm  = (int) round((float) $data['tm_paye']);
        if ($odp + $tm <= 0) {
            return response()->json(['ok' => false, 'message' => 'Le montant versé doit être supérieur à zéro.'], 422);
        }

        $periode = $data['period_type'] . ' ' . $data['period_year']
            . ($data['period_type'] === 'annuel' ? '' : '-' . str_pad((string) $data['period_value'], 2, '0', STR_PAD_LEFT));

        $reglement = \App\Models\TaxeReglement::create([
            'commune_id'  => (int) $data['commune_id'],
            'exercice'    => (int) $data['period_year'],
            'montant'     => $odp + $tm,
            'paid_at'     => $data['paid_at'] ?? now()->toDateString(),
            'objet'       => $tm === 0 ? \App\Models\TaxeReglement::OBJET_ODP
                           : ($odp === 0 ? \App\Models\TaxeReglement::OBJET_TM : \App\Models\TaxeReglement::OBJET_GLOBAL),
            'mode'        => $data['mode'] ?? null,
            'reference'   => $data['reference'] ?? null,
            'commentaire' => trim('Saisi depuis l\'ancien formulaire (' . $periode
                           . ' · ODP ' . number_format($odp, 0, ',', ' ')
                           . ' · TM ' . number_format($tm, 0, ',', ' ') . ').'
                           . (!empty($data['comment']) ? "\n" . $data['comment'] : '')
                           . (!empty($data['notes']) ? "\n" . $data['notes'] : '')),
            'recorded_by' => auth()->id(),
            'origine'     => \App\Models\TaxeReglement::ORIGINE_SAISIE,
        ]);

        Log::info('taxes.reglement.saisi_ancien_formulaire', [
            'reglement_id' => $reglement->id,
            'commune_id'   => $reglement->commune_id,
            'exercice'     => $reglement->exercice,
            'montant'      => $reglement->montant,
            'by'           => auth()->id(),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Règlement enregistré dans le Suivi des mairies.',
            'payment' => [
                'id'       => $reglement->id,
                'odp_paye' => (float) $odp,
                'tm_paye'  => (float) $tm,
                'paid_at'  => $reglement->paid_at?->format('d/m/Y'),
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        return $this->ancienneFonctionTaxes($request);
    }
}
