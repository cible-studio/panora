<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Panel;
use App\Services\CampaignPdfImporter;
use App\Support\NomEntreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Import d'une campagne depuis un PDF "Liste des panneaux commandes".
 *
 * Flux en 2 étapes :
 *   1) preview()  — upload PDF, parse, affiche un récap avec alertes
 *      (client trouvé/à créer, panneaux trouvés/manquants).
 *   2) apply()    — transaction : crée client si absent, crée campagne,
 *      attache les panneaux. Bloque si un seul panneau est introuvable.
 */
class CampaignImportController extends Controller
{
    public function __construct(protected CampaignPdfImporter $importer) {}

    public function form()
    {
        return view('admin.campaigns.import-pdf.form');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:10240', // 10 MB max
        ]);

        try {
            $data = $this->importer->extract($request->file('pdf'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['pdf' => $e->getMessage()]);
        }

        // Résolution client — même nom, même écrit autrement ; s'il ressemble
        // seulement à des fiches, l'utilisateur choisit (NomEntreprise, 2026-10-29).
        $clientName = mb_strtoupper(trim($data['client']));
        $verifNom = app(NomEntreprise::class)->verifier($clientName);
        $existingClient = $verifNom['identique'] ? Client::find($verifNom['identique']['id']) : null;
        $prochesClient = $existingClient ? [] : $verifNom['proches'];

        // Résolution panneaux — recherche par reference, casse insensible.
        $codesUpper = array_map(fn($c) => mb_strtoupper($c), $data['codes']);
        $foundPanels = Panel::whereIn(DB::raw('UPPER(reference)'), $codesUpper)
            ->whereNull('deleted_at')
            ->get(['id', 'reference', 'name', 'commune_id', 'monthly_rate']);

        $foundCodes = $foundPanels->pluck('reference')
            ->map(fn($r) => mb_strtoupper($r))
            ->toArray();
        $missingCodes = array_values(array_diff($codesUpper, $foundCodes));

        // Panneaux déjà pris fermement sur la période (2026-10-30) : l'import
        // est refusé tant qu'il en reste — sinon deux dossiers tiennent le
        // même panneau (c'est ainsi que sont nés les doublons ADDOHA, AH…).
        $conflits = app(\App\Services\AvailabilityService::class)->conflitsFermes(
            $foundPanels->pluck('id')->all(),
            $data['start_date']->format('Y-m-d'),
            $data['end_date']->format('Y-m-d'),
        );

        // Détection de campagne homonyme existante (pour avertir l'utilisateur)
        $existingCampaign = Campaign::where('name', $data['campaign'])
            ->where('start_date', $data['start_date']->format('Y-m-d'))
            ->whereNull('deleted_at')
            ->first();

        // On stocke les données du PDF en session pour le step apply().
        // Évite de re-uploader le PDF + re-parser.
        session([
            'pdf_import' => [
                'client'     => $data['client'],
                'campaign'   => $data['campaign'],
                'start_date' => $data['start_date']->format('Y-m-d'),
                'end_date'   => $data['end_date']->format('Y-m-d'),
                'codes'      => $codesUpper,
                'proches_ids' => array_column($prochesClient, 'id'),
                'expires_at' => now()->addMinutes(15)->timestamp,
            ],
        ]);

        return view('admin.campaigns.import-pdf.preview', [
            'data'             => $data,
            'existingClient'   => $existingClient,
            'prochesClient'    => $prochesClient,
            'foundPanels'      => $foundPanels,
            'missingCodes'     => $missingCodes,
            'existingCampaign' => $existingCampaign,
            'conflits'         => $conflits,
            'canImport'        => empty($missingCodes) && empty($conflits),
        ]);
    }

    public function apply(Request $request)
    {
        $session = session('pdf_import');
        if (!$session || ($session['expires_at'] ?? 0) < now()->timestamp) {
            return redirect()->route('admin.campaigns.import-pdf.form')
                ->withErrors(['pdf' => 'La session d\'import a expiré. Re-uploade le PDF.']);
        }

        // Re-vérifie tous les codes côté serveur avant la transaction —
        // évite qu'un panneau ait été supprimé entre preview et apply.
        $foundPanels = Panel::whereIn(DB::raw('UPPER(reference)'), $session['codes'])
            ->whereNull('deleted_at')
            ->get(['id', 'reference']);

        $foundCodes = $foundPanels->pluck('reference')
            ->map(fn($r) => mb_strtoupper($r))
            ->toArray();
        $missing = array_values(array_diff($session['codes'], $foundCodes));

        if (!empty($missing)) {
            return back()->withErrors([
                'pdf' => 'Import bloqué — codes panneaux introuvables : ' . implode(', ', $missing),
            ]);
        }

        // Revérifié ici : un panneau a pu être pris depuis l'aperçu (2026-10-30).
        $conflits = app(\App\Services\AvailabilityService::class)->conflitsFermes(
            $foundPanels->pluck('id')->all(), $session['start_date'], $session['end_date'],
        );
        if ($conflits) {
            return back()->withErrors([
                'pdf' => 'Import bloqué — ' . \App\Services\AvailabilityService::messageConflitsFermes($conflits),
            ]);
        }

        // Nom proche de fiches existantes : choix obligatoire (fiche ou nouvelle).
        $proches = $session['proches_ids'] ?? [];
        $choix = (string) $request->input('client_choix', '');
        if ($proches && $choix !== 'nouveau' && !in_array((int) $choix, $proches, true)) {
            return back()->withErrors(['pdf' => 'Choisissez la fiche du client : une fiche existante ou « Nouvelle fiche ».']);
        }

        try {
            $campaign = DB::transaction(function () use ($session, $foundPanels, $proches, $choix) {
                // 1. Client : fiche choisie, sinon même nom (même écrit
                //    autrement), sinon créée.
                $clientName = mb_strtoupper(trim($session['client']));
                $client = in_array((int) $choix, $proches, true) ? Client::find((int) $choix) : null;
                if (!$client && ($id = app(NomEntreprise::class)->identique($clientName)) !== null) {
                    $client = Client::find($id);
                }

                if (!$client) {
                    $client = Client::create([
                        'name'    => $clientName,
                        'user_id' => auth()->id(),
                    ]);
                    Log::info('campaigns.import_pdf.client_created', [
                        'client_id' => $client->id,
                        'name'      => $clientName,
                    ]);
                }

                // 2. Campagne
                $campaign = Campaign::create([
                    'name'         => $session['campaign'],
                    'client_id'    => $client->id,
                    'user_id'      => auth()->id(),
                    'start_date'   => $session['start_date'],
                    'end_date'     => $session['end_date'],
                    'status'       => 'planifie',
                    'total_amount' => 0,
                ]);

                // 3. Attache les panneaux (pivot campaign_panels type='interne')
                $panelIds = $foundPanels->pluck('id')->toArray();
                $pivotData = [];
                foreach ($panelIds as $pid) {
                    $pivotData[$pid] = ['type' => 'interne'];
                }
                $campaign->panels()->attach($pivotData);

                // 4. Calcul du total_amount au tarif catalogue (proratisé sur la durée).
                // Heuristique simple : monthly_rate × nb_mois entamés. La patronne
                // pourra ajuster manuellement après si nécessaire.
                $startDay = \Carbon\Carbon::parse($session['start_date'])->startOfDay();
                $endDay   = \Carbon\Carbon::parse($session['end_date'])->startOfDay();
                $days     = (int) round($startDay->diffInDays($endDay));
                $months   = max(1, (int) ceil($days / 30));
                $total    = (float) $foundPanels->sum('monthly_rate') * $months;
                $campaign->update(['total_amount' => $total]);

                return $campaign;
            });

            session()->forget('pdf_import');

            Log::info('campaigns.import_pdf.applied', [
                'campaign_id' => $campaign->id,
                'client_id'   => $campaign->client_id,
                'panels'      => count($foundPanels),
                'user_id'     => auth()->id(),
            ]);

            return redirect()->route('admin.campaigns.show', $campaign)
                ->with('success', "Campagne « {$campaign->name } » importée — " .
                    count($foundPanels) . " panneau(x) lié(s).");

        } catch (\Throwable $e) {
            Log::error('campaigns.import_pdf.failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return back()->withErrors([
                'pdf' => 'Erreur lors de la création : ' . $e->getMessage(),
            ]);
        }
    }
}
