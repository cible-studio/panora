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
    public function __construct(protected TaxeExerciceService $exercices) {}

    public function index(Request $request)
    {
        $exercice = $this->exerciceDemande($request);
        $lignes   = $this->exercices->exercices($exercice);
        $zones    = $this->exercices->parZone($lignes);
        $onglet   = in_array($request->input('zone'), ['abidjan', 'interieur', 'tout'], true)
            ? $request->input('zone') : 'abidjan';

        return view('admin.taxes.suivi.index', [
            'exercice'    => $exercice,
            'disponibles' => $this->exercices->exercicesDisponibles(),
            'reprise'     => $this->exercices->estReprise($exercice),
            'onglet'      => $onglet,
            'zones'       => $zones,
            'totaux'      => [
                'abidjan'   => $this->exercices->totaux($zones['abidjan']),
                'interieur' => $this->exercices->totaux($zones['interieur']),
                'tout'      => $this->exercices->totaux($lignes),
            ],
            'aSaisir'     => $lignes->where('a_saisir', true)->count(),
        ]);
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

    public function releve(Request $request, Commune $commune)
    {
        $exercice = $this->exerciceDemande($request);
        $ligne    = $this->exercices->exercice($commune, $exercice);
        $reglements = TaxeReglement::where('commune_id', $commune->id)
            ->where('exercice', $exercice)
            ->orderBy('paid_at')->orderBy('id')
            ->get();

        $pdf = Pdf::loadView('pdf.taxes-releve-mairie', [
            'commune'    => $commune,
            'exercice'   => $exercice,
            'reprise'    => $this->exercices->estReprise($exercice),
            'ligne'      => $ligne,
            'reglements' => $reglements,
        ])->setPaper('a4', 'portrait');

        $nom = 'releve-taxes-' . Str::slug($commune->name) . '-' . $exercice;

        return $pdf->download(\App\Support\DownloadFilename::sanitize($nom, $nom, 'pdf'));
    }

    public function pdf(Request $request)
    {
        $exercice = $this->exerciceDemande($request);
        $lignes   = $this->exercices->exercices($exercice);
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
        ])->setPaper('a4', 'landscape');

        return $pdf->download('suivi-taxes-mairies-' . $exercice . '.pdf');
    }

    public function excel(Request $request)
    {
        $exercice = $this->exerciceDemande($request);
        $lignes   = $this->exercices->exercices($exercice);

        return Excel::download(
            new TaxesSuiviExport($exercice, $this->exercices->parZone($lignes), $this->exercices->estReprise($exercice)),
            'gestion-odp-tm-' . $exercice . '.xlsx'
        );
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
