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

        // Récap filtres pour l'en-tête PDF — structure lisible pour l'affichage
        // « fiche synthétique » (labels FR) + chaîne compacte legacy conservée
        // pour l'export Excel et l'ancien nom de fichier.
        $filterMeta  = [];
        $filterParts = [];
        if (!empty($filters['commune_id'])) {
            $c = Commune::find($filters['commune_id']);
            if ($c) {
                $filterMeta[]  = ['label' => 'Commune',  'value' => $c->name];
                $filterParts[] = "commune={$c->name}";
            }
        }
        if (!empty($filters['client_id'])) {
            $cl = Client::find($filters['client_id']);
            if ($cl) {
                $filterMeta[]  = ['label' => 'Client',   'value' => $cl->name];
                $filterParts[] = "client={$cl->name}";
            }
        }
        if (!empty($filters['campaign_id'])) {
            $cm = Campaign::find($filters['campaign_id']);
            if ($cm) {
                $filterMeta[]  = ['label' => 'Campagne', 'value' => $cm->name];
                $filterParts[] = "campagne={$cm->name}";
            }
        }
        // 2026-11-13 — La maintenance est désormais incluse par défaut
        // (l'ODP court tant que le mât est planté) : c'est l'EXCLUSION
        // qui mérite d'être écrite sur le PDF.
        if (!empty($filters['exclure_maintenance'])) {
            $filterMeta[]  = ['label' => 'Maintenance', 'value' => 'Panneaux en maintenance exclus'];
            $filterParts[] = 'maintenance=exclue';
        }
        if (!empty($filters['inclure_lumipub'])) {
            $filterMeta[]  = ['label' => 'Lumipub', 'value' => 'Pieds des axes inclus (une ligne par mât)'];
            $filterParts[] = 'lumipub=inclus';
        }
        // 2026-11-24 (demande de l'utilisateur) : les catégories exclues
        // (chevalets…) ne sont plus jamais écrites sur le document. Le filtre
        // s'applique toujours aux lignes ; seule sa mention disparaît.
        if (!empty($filters['type'])) {
            $typeLabels = ['tm' => 'Taxe Municipale (TM)', 'odp' => 'Occupation Domaine Public (ODP)'];
            $filterMeta[]  = ['label' => 'Nature',   'value' => $typeLabels[$filters['type']] ?? strtoupper($filters['type'])];
            $filterParts[] = "type=" . strtoupper($filters['type']);
        }
        if ($selection) {
            $filterMeta[]  = ['label' => 'Sélection', 'value' => $selection];
            $filterParts[] = 'selection=' . $selection;
        }
        $filterSummary = implode(' · ', $filterParts);

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

        // Récap filtres pour l'en-tête imprimable de la feuille
        $parts = [];
        if (!empty($filters['commune_id']))  { $c = Commune::find($filters['commune_id']);  if ($c) $parts[] = "commune={$c->name}"; }
        if (!empty($filters['client_id']))   { $c = Client::find($filters['client_id']);    if ($c) $parts[] = "client={$c->name}"; }
        if (!empty($filters['campaign_id'])) { $c = Campaign::find($filters['campaign_id']);if ($c) $parts[] = "campagne={$c->name}"; }
        if (!empty($filters['type']))        $parts[] = 'type=' . strtoupper($filters['type']);
        if (!empty($filters['exclure_maintenance'])) $parts[] = 'maintenance=exclue';
        if (!empty($filters['inclure_lumipub']))     $parts[] = 'lumipub=inclus';
        // 2026-11-24 : catégories exclues jamais écrites (cf. detailsPdf).
        if ($selection) $parts[] = "selection={$selection}";
        $filterSummary = implode(' · ', $parts);

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
        // 2026-11-26 (point 23 validé) — la vue admin.taxes.show n'existe
        // pas : /admin/taxes/{id} finissait en erreur 500. Retour à la page
        // Taxes, sur l'année de l'écriture.
        return redirect()
            ->route('admin.taxes.index', array_filter(['year' => $tax->year]))
            ->with('info', 'Cette écriture n\'a pas de page à elle : voici la page Taxes de son année.');
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

        // Charge les communes avec leurs panneaux opérables. On limite les
        // colonnes pour rester léger : un parc de 1000 panneaux × 34
        // communes = ~30k cellules, l'agrégation est instantanée en mémoire.
        //
        // TX-12 (2026-09-23) — La maintenance est pilotable, comme sur
        // /admin/taxes/details : les deux écrans doivent répondre au même
        // filtre, sinon leurs totaux ne se raccordent pas.
        // 2026-11-13 — Le sens par défaut s'inverse : la maintenance est
        // INCLUSE (l'ODP court tant que le mât occupe le sol), et c'est
        // 'exclure_maintenance' qui la retire. (L'ancien
        // 'include_maintenance', sans effet depuis, a été retiré le
        // 2026-11-17.)
        $exclureMaintenance = $request->boolean('exclure_maintenance');
        $inclureLumipub     = $request->boolean('inclure_lumipub');
        $communes = Commune::query()
            ->with([
                'panels' => fn($q) => $q
                    ->whereNull('deleted_at')
                    ->avecTaxesAutomatiques($inclureLumipub)
                    ->when($exclureMaintenance, fn($qq) => $qq->whereNotIn('status', ['maintenance']))
                    ->with('format:id,name,width,height'),
            ])
            ->whereNotNull('odp_rate')
            ->get(['id', 'name', 'odp_rate', 'tm_rate', 'odp_rate_eclaire', 'tm_rate_eclaire']);

        // ── 2026-11-16 — TM : SOURCE UNIQUE (TaxCalculationService) ──────
        // Avant, ce tableau recalculait la TM avec sa propre formule : mois
        // CALENDAIRES occupés × tarif × m² (un jour de campagne dans le mois
        // = un mois), alors que le détail, le PDF, l'Excel et la fiche
        // commune comptent les mois de campagne de date à date (règle
        // validée le 2026-07-29). Deux TM pour la même période. Elle vient
        // désormais de generateLines(), comme l'ODP depuis TX-12 — avec la
        // règle du 2026-11-16 : toutes les campagnes du panneau, chaque mois
        // rangé dans la période où il commence.

        // ── 2026-11-16 — Payé, solde et statut : ceux de l'EXERCICE ──────
        // Source unique : TaxeExerciceService (Suivi des mairies). Avant, ce
        // tableau lisait commune_tax_payments par period_year, avec un
        // prorata quand un versement débordait la période, pendant que la
        // fiche commune rangeait les mêmes versements par date de paiement :
        // deux écrans, deux « payé ». Un règlement se rattache désormais à un
        // exercice, pas à un mois ; le dû théorique de la PÉRIODE reste
        // affiché à part (colonnes ODP / TM / Total).
        $queryMonths = self::monthsForPeriod($periodType, $periodValue, $periodEndValue);

        $suivi = app(\App\Services\Taxes\TaxeExerciceService::class);
        $exercicesSuivis = $suivi->exercices($periodYear);
        $reglementsAnnee = \App\Models\TaxeReglement::valides()
            ->where('exercice', $periodYear)
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

        // ── TX-12 (2026-09-23) — SOURCE UNIQUE POUR L'ODP ──────────
        // Avant : ce dashboard recalculait l'ODP avec sa propre formule
        // ($odpRate × m² × qty × nbMois) pendant que /admin/taxes/details
        // utilisait TaxCalculationService. Deux moteurs → deux résultats
        // (divergence ×3 en mensuel, désaccord sur la maintenance et sur
        // les mâts double-face depuis TX-10). On consomme désormais le
        // service, agrégé par (commune, format) pour alimenter le tableau.
        // 2026-11-16 — Même chose pour la TM : un seul appel, les deux types.
        $theoParCommuneFormat = $calc
            ->generateLines($periodType, $periodValue, $periodYear, [
                'exclure_maintenance' => $exclureMaintenance,
                'inclure_lumipub'     => $inclureLumipub,
            ], $periodEndValue)
            ->groupBy(fn($l) => $l['commune_id'] . '|' . $l['format_id'] . '|' . $l['type'])
            ->map(fn($g) => (float) $g->sum('amount'));

        $rows = $communes->map(function ($commune) use ($periodYear, $rateDate, $theoParCommuneFormat, $suivi, $exercicesSuivis, $reglementsAnnee) {
            // Phase audit 8E — tarifs HISTORISÉS, pas courants.
            // Avant : $commune->odp_rate (valeur actuelle) → divergeait
            // avec la facturation qui utilise ratesAt(issued_at).
            $rates   = $commune->ratesAt($rateDate);
            $odpRate = (float) ($rates['odp'] ?? 0);
            // 2026-11-26 — Sans tarif TM, la base nationale s'applique
            // (generateLines() aussi, règle confirmée le 26/11) : on l'affiche,
            // avec un repère « par défaut » pour que le tarif soit renseigné.
            $tmRate  = (float) ($rates['tm'] ?? 0);
            $tmParDefaut = $tmRate <= 0;
            if ($tmParDefaut) {
                $tmRate = \App\Services\TaxCalculationService::tmParDefaut();
            }
            // 2026-11-18 — Tarif des panneaux éclairés, affiché s'il diffère
            // (le calcul, lui, vient de generateLines(), panneau par panneau).
            $ratesEclaire   = $commune->ratesAt($rateDate, true);
            $odpRateEclaire = (float) $ratesEclaire['odp'] !== $odpRate ? (float) $ratesEclaire['odp'] : null;
            $tmRateEclaire  = (float) $ratesEclaire['tm'] !== $tmRate ? (float) $ratesEclaire['tm'] : null;

            // Lignes détaillées par format (pour affichage tableau)
            $lignes = $commune->panels
                ->groupBy('format_id')
                ->map(function ($panels) use ($tmRate, $odpRate, $theoParCommuneFormat, $commune) {
                    $fmt = $panels->first()->format;
                    if (!$fmt?->width || !$fmt?->height) return null;

                    $m2  = round((float) $fmt->width * (float) $fmt->height, 2);
                    $qty = $panels->count();
                    $cle = $commune->id . '|' . $fmt->id . '|';
                    $odp = round($theoParCommuneFormat[$cle . TaxCalculationService::TYPE_ODP] ?? 0);
                    $tm  = round($theoParCommuneFormat[$cle . TaxCalculationService::TYPE_TM]  ?? 0);

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
                'odp_taux_eclaire' => $odpRateEclaire,
                'tm_taux_eclaire'  => $tmRateEclaire,
                'nb_panneaux'    => (int) $commune->panels->count(),
                'surface_totale' => (float) $lignes->sum(fn($l) => $l['m2'] * $l['qty']),
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
        ->filter(fn($r) => $r['nb_panneaux'] > 0) // hide communes vides
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
            ])
            ->values();
        // Taux de couverture : payé / dû de l'EXERCICE (2026-11-16), les deux
        // venant du Suivi des mairies.
        $totalDueYearGlobal = (float) $totExercice['total_du'];
        $kpi['taux_couverture'] = $totalDueYearGlobal > 0
            ? round(($kpi['paye_total'] / $totalDueYearGlobal) * 100, 1)
            : 0;

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
