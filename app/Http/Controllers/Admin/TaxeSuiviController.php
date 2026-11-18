<?php

namespace App\Http\Controllers\Admin;

use App\Exports\TaxesSuiviExport;
use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Models\TaxeReglement;
use App\Models\TaxeReprise;
use App\Services\Taxes\TaxeExerciceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Suivi des mairies (2026-11-16) — la fiche de la direction, tenue par
 * Panora : une ligne par mairie et par exercice (arriérés, ODP, TM par
 * trimestre, règlements, solde), le relevé à remettre à la mairie et
 * l'export au format de la fiche.
 *
 * Tous les montants viennent de TaxeExerciceService ; ce contrôleur ne
 * calcule rien.
 */
class TaxeSuiviController extends Controller
{
    /**
     * Cartes filtres de la liste (2026-11-18, « les cartes doivent afficher
     * les listes spécifiques ») : un clic sur une carte n'affiche que les
     * mairies concernées. Second clic, ou « Total dû » : toutes.
     */
    public const FILTRES = [
        'tous'     => 'Toutes les mairies',
        'paye'     => 'Mairies qui ont reçu au moins un versement',
        'reste'    => 'Mairies avec un reste à payer',
        'exigible' => 'Mairies avec un montant dû à ce jour',
        'a_venir'  => 'Mairies avec de la TM à venir',
        'a_saisir' => 'Mairies sans montant réclamé saisi',
    ];

    public function __construct(protected TaxeExerciceService $exercices) {}

    public function index(Request $request)
    {
        $exercice = $this->exerciceDemande($request);
        $lignes   = $this->exercices->exercices($exercice);
        $onglet   = in_array($request->input('zone'), ['abidjan', 'interieur', 'tout'], true)
            ? $request->input('zone') : 'abidjan';
        $filtre   = array_key_exists((string) $request->input('filtre'), self::FILTRES)
            ? (string) $request->input('filtre') : 'tous';

        // La liste affichée : l'onglet de zone, puis le filtre de la carte.
        // Les totaux du pied de tableau suivent (même service, moins de lignes).
        $filtrees = self::filtrer($lignes, $filtre);
        $zonesF   = $this->exercices->parZone($filtrees);
        $liste    = $onglet === 'tout' ? $filtrees : $zonesF[$onglet];
        $tableau  = [
            'lignes'   => $liste,
            't'        => $this->exercices->totaux($liste),
            'exercice' => $exercice,
            'reprise'  => $this->exercices->estReprise($exercice),
        ];
        $onglets = [
            'abidjan'   => $zonesF['abidjan']->count(),
            'interieur' => $zonesF['interieur']->count(),
            'tout'      => $filtrees->count(),
        ];

        if ($request->boolean('ajax')) {
            return response()->json([
                'tableau' => view('admin.taxes.suivi.partials.tableau', $tableau)->render(),
                'onglets' => $onglets,
                'titre'   => self::FILTRES[$filtre],
            ]);
        }

        return view('admin.taxes.suivi.index', $tableau + [
            'disponibles' => $this->exercices->exercicesDisponibles(),
            'onglet'      => $onglet,
            'filtre'      => $filtre,
            'onglets'     => $onglets,
            // Nombre de mairies derrière chaque carte (tout l'exercice, toutes zones).
            'nombres'     => collect(self::FILTRES)->map(fn ($l, $f) => self::filtrer($lignes, $f)->count())->all(),
            'totaux'      => ['tout' => $this->exercices->totaux($lignes)],
            'aSaisir'     => $lignes->where('a_saisir', true)->count(),
        ]);
    }

    /** Les mairies d'une carte filtre (la liste seulement : aucun montant n'est recalculé). */
    public static function filtrer(Collection $lignes, string $filtre): Collection
    {
        return match ($filtre) {
            'paye'     => $lignes->filter(fn ($l) => $l['nb_reglements'] > 0),
            'reste'    => $lignes->filter(fn ($l) => $l['solde'] > 0),
            'exigible' => $lignes->filter(fn ($l) => $l['exigible'] > 0),
            'a_venir'  => $lignes->filter(fn ($l) => $l['prevu_tm'] > 0),
            'a_saisir' => $lignes->filter(fn ($l) => !empty($l['a_saisir'])),
            default    => $lignes,
        };
    }

    public function commune(Request $request, Commune $commune)
    {
        $exercice = $this->exerciceDemande($request);
        $ligne    = $this->exercices->exercice($commune, $exercice);

        // La chaîne des arriérés : chaque exercice suivi jusqu'à celui-ci.
        $chaine = collect(range($this->exercices->exerciceReprise(), $exercice))
            ->map(fn (int $a) => $this->exercices->exercice($commune, $a));

        $reglements = TaxeReglement::with(['saisiPar:id,name', 'annulePar:id,name'])
            ->where('commune_id', $commune->id)
            ->where('exercice', $exercice)
            ->orderBy('paid_at')->orderBy('id')
            ->get();

        return view('admin.taxes.suivi.commune', [
            'commune'     => $commune,
            'exercice'    => $exercice,
            'disponibles' => $this->exercices->exercicesDisponibles(),
            'reprise'     => $this->exercices->estReprise($exercice),
            'ligne'       => $ligne,
            'chaine'      => $chaine,
            'reglements'  => $reglements,
            'rates'       => $commune->ratesAt(now()),
            // Panneaux éclairés (2026-11-18) : affiché s'il diffère.
            'ratesEclaire' => $commune->ratesAt(now(), true),
        ]);
    }

    public function storeReglement(Request $request, Commune $commune): RedirectResponse
    {
        $data = $request->validate([
            'exercice'    => 'required|integer|min:' . $this->exercices->exerciceReprise() . '|max:' . ((int) date('Y') + 1),
            'montant'     => 'required|integer|min:1|max:9999999999',
            'paid_at'     => 'required|date|before_or_equal:today',
            'objet'       => 'required|in:' . implode(',', array_keys(TaxeReglement::OBJETS)),
            'mode'        => 'nullable|in:' . implode(',', array_keys(TaxeReglement::MODES)),
            'reference'   => 'nullable|string|max:100',
            'commentaire' => 'nullable|string|max:2000',
            'quittance'   => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ], [
            'montant.min'            => 'Le montant doit être supérieur à zéro.',
            'paid_at.before_or_equal'=> 'La date du versement ne peut pas être dans le futur.',
            'exercice.min'           => 'Le suivi commence à l\'exercice ' . $this->exercices->exerciceReprise() . '.',
        ]);

        $quittance = $request->file('quittance');
        if ($quittance) {
            $data['quittance_nom']  = mb_substr($quittance->getClientOriginalName(), 0, 190);
            $data['quittance_path'] = $quittance->storeAs(
                "taxes/quittances/{$data['exercice']}/{$commune->id}",
                now()->format('Ymd-His') . '-' . Str::random(6) . '.' . $quittance->getClientOriginalExtension(),
                'local'
            );
        }
        unset($data['quittance']);

        $reglement = TaxeReglement::create($data + [
            'commune_id'  => $commune->id,
            'recorded_by' => auth()->id(),
            'origine'     => TaxeReglement::ORIGINE_SAISIE,
        ]);

        Log::info('taxes.reglement.saisi', [
            'reglement_id' => $reglement->id,
            'commune_id'   => $commune->id,
            'exercice'     => $reglement->exercice,
            'montant'      => $reglement->montant,
            'by'           => auth()->id(),
        ]);

        return redirect()
            ->route('admin.taxes.suivi.commune', ['commune' => $commune, 'exercice' => $reglement->exercice])
            ->with('success', 'Règlement de ' . number_format($reglement->montant, 0, ',', ' ') . ' FCFA enregistré.');
    }

    public function annulerReglement(Request $request, TaxeReglement $reglement): RedirectResponse
    {
        $data = $request->validate([
            'motif' => 'required|string|min:3|max:500',
        ], [
            'motif.required' => 'Indique pourquoi ce règlement est annulé.',
        ]);

        if ($reglement->estAnnule()) {
            return back()->with('error', 'Ce règlement est déjà annulé.');
        }

        $reglement->annuler($data['motif'], auth()->id());

        Log::info('taxes.reglement.annule', [
            'reglement_id' => $reglement->id,
            'montant'      => $reglement->montant,
            'motif'        => $data['motif'],
            'by'           => auth()->id(),
        ]);

        return redirect()
            ->route('admin.taxes.suivi.commune', ['commune' => $reglement->commune_id, 'exercice' => $reglement->exercice])
            ->with('success', 'Règlement annulé : il reste visible, barré, et sort des totaux.');
    }

    public function quittance(TaxeReglement $reglement)
    {
        $chemin = $reglement->quittance_path;
        abort_unless($chemin && Storage::disk('local')->exists($chemin), 404, 'Quittance introuvable.');

        return Storage::disk('local')->response(
            $chemin,
            $reglement->quittance_nom ?: basename($chemin)
        );
    }

    public function saveReprise(Request $request, Commune $commune): RedirectResponse
    {
        $exercice = (int) $request->input('exercice');
        abort_unless($this->exercices->estReprise($exercice), 422,
            'La saisie de ce que la mairie a réclamé ne concerne que l\'exercice de reprise.');

        $data = $request->validate([
            'arrieres'    => 'required|integer|min:0|max:9999999999',
            'odp'         => 'required|integer|min:0|max:9999999999',
            'tm_t1'       => 'required|integer|min:0|max:9999999999',
            'tm_t2'       => 'required|integer|min:0|max:9999999999',
            'tm_t3'       => 'required|integer|min:0|max:9999999999',
            'tm_t4'       => 'required|integer|min:0|max:9999999999',
            'commentaire' => 'nullable|string|max:2000',
        ]);

        TaxeReprise::updateOrCreate(
            ['commune_id' => $commune->id, 'exercice' => $exercice],
            $data + ['saisi_par' => auth()->id()]
        );

        Log::info('taxes.reprise.saisie', ['commune_id' => $commune->id, 'exercice' => $exercice, 'by' => auth()->id()] + $data);

        return redirect()
            ->route('admin.taxes.suivi.commune', ['commune' => $commune, 'exercice' => $exercice])
            ->with('success', 'Montants réclamés par la mairie enregistrés.');
    }

    /**
     * Relevé d'une mairie. Deux versions (2026-11-18) :
     *   · mairie (défaut) : à lui remettre — ce qui est dû, les versements, le solde ;
     *   · interne (?version=interne) : en plus, le calcul Panora de contrôle
     *     (année de reprise), les versements annulés avec leur motif, qui a saisi.
     */
    public function releve(Request $request, Commune $commune)
    {
        $exercice = $this->exerciceDemande($request);
        $interne  = $request->input('version') === 'interne';
        $ligne    = $this->exercices->exercice($commune, $exercice);
        $reglements = TaxeReglement::with(['saisiPar:id,name', 'annulePar:id,name'])
            ->where('commune_id', $commune->id)
            ->where('exercice', $exercice)
            ->orderBy('paid_at')->orderBy('id')
            ->get();

        $pdf = Pdf::loadView('pdf.taxes-releve-mairie', [
            'commune'    => $commune,
            'exercice'   => $exercice,
            'reprise'    => $this->exercices->estReprise($exercice),
            'ligne'      => $ligne,
            'reglements' => $reglements,
            'interne'    => $interne,
        ])->setPaper('a4', 'portrait');

        $nom = 'releve-taxes-' . Str::slug($commune->name) . '-' . $exercice . ($interne ? '-interne' : '');

        return $pdf->download(\App\Support\DownloadFilename::sanitize($nom, $nom, 'pdf'));
    }

    public function pdf(Request $request)
    {
        $exercice = $this->exerciceDemande($request);
        [$lignes, $selection] = $this->appliquerSelection($request, $this->exercices->exercices($exercice));
        $zones    = $this->exercices->parZone($lignes);

        $pdf = Pdf::loadView('pdf.taxes-suivi', [
            'exercice' => $exercice,
            'reprise'  => $this->exercices->estReprise($exercice),
            'zones'    => $zones,
            'totaux'   => [
                'abidjan'   => $this->exercices->totaux($zones['abidjan']),
                'interieur' => $this->exercices->totaux($zones['interieur']),
                'tout'      => $this->exercices->totaux($lignes),
            ],
            'selection' => $selection,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('suivi-taxes-mairies-' . $exercice . ($selection ? '-selection' : '') . '.pdf');
    }

    public function excel(Request $request)
    {
        $exercice = $this->exerciceDemande($request);
        [$lignes, $selection] = $this->appliquerSelection($request, $this->exercices->exercices($exercice));

        return Excel::download(
            new TaxesSuiviExport($exercice, $this->exercices->parZone($lignes), $this->exercices->estReprise($exercice), $selection),
            'gestion-odp-tm-' . $exercice . ($selection ? '-selection' : '') . '.xlsx'
        );
    }

    /**
     * Sélection de mairies (2026-11-18, « juste Cocody et Abobo ») : les
     * exports ne gardent que les mairies cochées (communes[] = ids). Sans
     * sélection : tout l'exercice, comme avant.
     *
     * @return array{0: Collection, 1: ?string} lignes gardées, libellé pour l'en-tête
     */
    private function appliquerSelection(Request $request, Collection $lignes): array
    {
        $ids = collect((array) $request->input('communes', []))
            ->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();
        if ($ids->isEmpty()) {
            return [$lignes, null];
        }

        $gardees = $lignes->only($ids->all());
        $n = $gardees->count();

        return [$gardees, $n . ' mairie' . ($n > 1 ? 's' : '') . ' sur ' . $lignes->count()];
    }

    /** Exercice demandé, ramené dans la plage suivie. */
    private function exerciceDemande(Request $request): int
    {
        $disponibles = $this->exercices->exercicesDisponibles();
        $defaut = in_array((int) date('Y'), $disponibles, true) ? (int) date('Y') : max($disponibles);
        $demande = (int) $request->input('exercice', $defaut);

        return in_array($demande, $disponibles, true) ? $demande : $defaut;
    }
}
