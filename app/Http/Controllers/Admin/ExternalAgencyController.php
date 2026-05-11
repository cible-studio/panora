<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalAgency\StoreExternalAgencyRequest;
use App\Http\Requests\ExternalAgency\UpdateExternalAgencyRequest;
use App\Http\Requests\ExternalAgency\StoreExternalPanelRequest;
use App\Http\Requests\ExternalAgency\UpdateExternalPanelRequest;
use App\Models\PanelCategory;
use App\Models\Commune;
use App\Models\ExternalAgency;
use App\Models\Client;
use App\Models\ExternalPanel;
use App\Models\PanelFormat;
use App\Models\Zone;
use App\Exports\ExternalPanelsExport;
use App\Services\PdfExportService;
use App\Support\PdfAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExternalAgencyController extends Controller
{
    use PdfAssets;

    // ── Liste des régies ──────────────────────────────────────
    public function index(Request $request)
    {
        $query = ExternalAgency::query()
            ->withCount('externalPanels')
            ->with(['externalPanels.campaigns.client', 'externalPanels.campaigns']);

        // Recherche : mot entier en début de mot (LIKE 'terme%') sur plusieurs colonnes
        if ($request->filled('search')) {
            $term     = trim($request->input('search'));
            $startLike = $term . '%';
            $anyLike  = '%' . $term . '%';

            $query->where(function ($q) use ($startLike, $anyLike) {
                $q->where('name', 'like', $startLike)
                  ->orWhere('manager_name', 'like', $startLike)
                  ->orWhere('commercial_name', 'like', $startLike)
                  ->orWhere('email', 'like', $startLike)
                  ->orWhere('commercial_email', 'like', $startLike)
                  ->orWhere('contact', 'like', $startLike)
                  // Recherche large sur ville/adresse pour catch les partials utiles
                  ->orWhere('city', 'like', $anyLike)
                  ->orWhere('address', 'like', $anyLike);
            });
        }

        // Filtre statut depuis les KPI cliquables : ?status=active|inactive
        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $agencies = $query->orderBy('name')->paginate(15)->withQueryString();

        // Stats pour les KPI cliquables
        $stats = [
            'total'    => ExternalAgency::count(),
            'active'   => ExternalAgency::where('is_active', true)->count(),
            'inactive' => ExternalAgency::where('is_active', false)->count(),
        ];

        return view('admin.external-agencies.index', compact('agencies', 'stats'));
    }

    // ── Fiche régie + ses panneaux ────────────────────────────
    public function show(ExternalAgency $externalAgency)
    {
        $externalAgency->load([
            'externalPanels.commune',
            'externalPanels.format',
            'externalPanels.category',
            'externalPanels.client',
            'externalPanels.campaign',
        ]);
        $communes   = Commune::orderBy('name')->get();
        $zones      = Zone::orderBy('name')->get();
        $formats    = PanelFormat::orderBy('name')->get();
        $categories = PanelCategory::orderBy('name')->get();
        $clients    = Client::with('campaigns')->orderBy('name')->get();

        return view('admin.external-agencies.show', [
            'agency'     => $externalAgency,
            'communes'   => $communes,
            'zones'      => $zones,
            'formats'    => $formats,
            'categories' => $categories,
            'clients'    => $clients,
        ]);
    }

    // ── Créer régie ───────────────────────────────────────────
    public function store(StoreExternalAgencyRequest $request)
    {
        ExternalAgency::create($request->validated());

        return redirect()
            ->route('admin.external-agencies.index')
            ->with('success', 'Régie créée avec succès.');
    }

    // ── Modifier régie ────────────────────────────────────────
    public function update(UpdateExternalAgencyRequest $request, ExternalAgency $externalAgency)
    {
        $externalAgency->update($request->validated());

        return redirect()
            ->route('admin.external-agencies.index')
            ->with('success', 'Régie mise à jour.');
    }

    // ── Supprimer régie ───────────────────────────────────────
    public function destroy(ExternalAgency $externalAgency)
    {
        $externalAgency->delete();

        return redirect()
            ->route('admin.external-agencies.index')
            ->with('success', 'Régie supprimée.');
    }

    // ══════════════════════════════════════════════════════════
    // PANNEAUX EXTERNES (actions imbriquées)
    // ══════════════════════════════════════════════════════════

    // ── Ajouter un panneau à une régie ────────────────────────
    public function storePanel(StoreExternalPanelRequest $request, ExternalAgency $externalAgency)
    {
        $data = array_merge($request->validated(), [
            'client_id'   => $request->client_id ?: null,
            'campaign_id' => $request->campaign_id ?: null,
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('external-panels', 'public');
        }

        $externalAgency->externalPanels()->create($data);

        return redirect()
            ->route('admin.external-agencies.show', $externalAgency)
            ->with('success', 'Panneau ajouté avec succès.');
    }

    // ── Modifier un panneau ───────────────────────────────────
    public function updatePanel(UpdateExternalPanelRequest $request, ExternalAgency $externalAgency, ExternalPanel $panel)
    {
        abort_if($panel->agency_id !== $externalAgency->id, 403);

        $data = $request->validated();

        if ($request->hasFile('photo')) {
            if ($panel->photo_path) {
                Storage::disk('public')->delete($panel->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('external-panels', 'public');
        }

        $panel->update($data);

        return redirect()
            ->route('admin.external-agencies.show', $externalAgency)
            ->with('success', 'Panneau modifié.');
    }

    // ── Supprimer un panneau ──────────────────────────────────
    public function destroyPanel(ExternalAgency $externalAgency, ExternalPanel $panel)
    {
        abort_if($panel->agency_id !== $externalAgency->id, 403);
        $panel->delete();

        return redirect()
            ->route('admin.external-agencies.show', $externalAgency)
            ->with('success', 'Panneau supprimé.');
    }

    // ══════════════════════════════════════════════════════════
    // EXPORTS — parité avec les régies internes
    //   • PDF images   (fiches détaillées)
    //   • PDF liste    (tableau récap)
    //   • Excel
    // Règle métier : par défaut, prix + statut sont MASQUÉS
    // (proposition propre). show_pricing=1 pour les inclure (interne).
    // ══════════════════════════════════════════════════════════

    private function loadAgencyPanels(ExternalAgency $agency, Request $request)
    {
        $panelIds = $request->input('panel_ids');

        $query = ExternalPanel::with([
            'commune:id,name',
            'zone:id,name',
            'format:id,name,width,height',
            'category:id,name',
            'client:id,name',
            'campaign:id,name',
            'agency:id,name',
        ])->where('agency_id', $agency->id);

        if (is_array($panelIds) && !empty($panelIds)) {
            $ids = array_values(array_filter(array_map('intval', $panelIds)));
            if ($ids) {
                $query->whereIn('id', $ids)
                      ->orderByRaw('FIELD(id,' . implode(',', $ids) . ')');
            }
        } else {
            $query->orderBy('code_panneau');
        }

        return $query->get();
    }

    private function resolveFlags(Request $request): array
    {
        $showPricing = $request->boolean('show_pricing');
        // Compat ascendante avec hide_status (par défaut : on masque le statut)
        $hideStatus = $request->has('show_pricing')
            ? !$showPricing
            : (bool) $request->boolean('hide_status', true);

        return [$showPricing, $hideStatus];
    }

    public function pdfImages(Request $request, ExternalAgency $externalAgency, PdfExportService $pdfService)
    {
        $request->validate([
            'panel_ids'    => 'nullable|array',
            'panel_ids.*'  => 'integer|exists:external_panels,id',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date',
            'show_pricing' => 'nullable|boolean',
            'hide_status'  => 'nullable|boolean',
        ]);

        $panels = $this->loadAgencyPanels($externalAgency, $request);

        if ($panels->isEmpty()) {
            return back()->with('error', 'Aucun panneau à exporter pour cette régie.');
        }

        [$showPricing, $hideStatus] = $this->resolveFlags($request);

        $pdf = Pdf::loadView('admin.reservations.pdf.disponibilites-images', [
            'panels'          => $panels->map(fn($p) => $pdfService->enrichExternalPanel($p)),
            'startDate'       => $request->start_date,
            'endDate'         => $request->end_date,
            'generated'       => now()->format('d/m/Y à H:i'),
            'reservation_ref' => null,
            'client_name'     => null,
            'agency_name'     => $externalAgency->name,
            'logoSrc'         => $this->getLogoPdf(),
            'hideStatus'      => $hideStatus,
            'showPricing'     => $showPricing,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
                'defaultFont'          => 'DejaVu Sans',
                'dpi'                  => 96,
            ]);

        $filename = 'regie-' . \Illuminate\Support\Str::slug($externalAgency->name)
                  . '-fiches-' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    public function pdfListe(Request $request, ExternalAgency $externalAgency)
    {
        $request->validate([
            'panel_ids'    => 'nullable|array',
            'panel_ids.*'  => 'integer|exists:external_panels,id',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date',
            'show_pricing' => 'nullable|boolean',
            'hide_status'  => 'nullable|boolean',
        ]);

        $panels = $this->loadAgencyPanels($externalAgency, $request);

        if ($panels->isEmpty()) {
            return back()->with('error', 'Aucun panneau à exporter pour cette régie.');
        }

        // Adaptation pour la vue liste : elle attend reference/name (pas
        // code_panneau/designation). On expose une projection légère.
        $rows = $panels->map(fn($p) => (object) [
            'reference'      => $p->code_panneau,
            'name'           => $p->designation,
            'commune'        => $p->commune,
            'zone'           => $p->zone,
            'format'         => $p->format,
            'category'       => $p->category,
            'monthly_rate'   => $p->monthly_rate,
            'daily_traffic'  => $p->daily_traffic,
            'is_lit'         => (bool) $p->is_lit,
            'status'         => (object) ['value' => $p->availability_status ?? 'libre'],
        ]);

        $startDate = $request->start_date;
        $endDate   = $request->end_date;
        $dureeEnMois = ($startDate && $endDate)
            ? max(1, (int) ceil(\Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) / 30))
            : 1;
        $totalMensuel = (float) $panels->sum(fn($p) => (float) ($p->monthly_rate ?? 0));
        $totalPeriode = $totalMensuel * $dureeEnMois;

        [$showPricing, $hideStatus] = $this->resolveFlags($request);

        $pdf = Pdf::loadView('admin.reservations.pdf.disponibilites-list', [
            'panels'          => $rows,
            'startDate'       => $startDate,
            'endDate'         => $endDate,
            'dureeEnMois'     => $dureeEnMois,
            'totalMensuel'    => $totalMensuel,
            'totalPeriode'    => $totalPeriode,
            'generated'       => now()->format('d/m/Y à H:i'),
            'reservation_ref' => null,
            'client_name'     => $externalAgency->name,
            'logoSrc'         => $this->getLogoPdf(),
            'hideStatus'      => $hideStatus,
            'showPricing'     => $showPricing,
        ]);

        $pdf->setPaper('A4', 'landscape')->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'DejaVu Sans',
            'dpi'                  => 96,
        ]);

        $suffix   = $hideStatus ? '-proposition' : '';
        $filename = 'regie-' . \Illuminate\Support\Str::slug($externalAgency->name)
                  . '-liste' . $suffix . '-' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    public function exportExcel(Request $request, ExternalAgency $externalAgency)
    {
        $request->validate([
            'panel_ids'    => 'nullable|array',
            'panel_ids.*'  => 'integer|exists:external_panels,id',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date',
            'show_pricing' => 'nullable|boolean',
            'hide_status'  => 'nullable|boolean',
        ]);

        $panels = $this->loadAgencyPanels($externalAgency, $request);

        if ($panels->isEmpty()) {
            return back()->with('error', 'Aucun panneau à exporter pour cette régie.');
        }

        [, $hideStatus] = $this->resolveFlags($request);

        $filename = 'regie-' . \Illuminate\Support\Str::slug($externalAgency->name)
                  . '-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new ExternalPanelsExport(
                $panels,
                $request->start_date,
                $request->end_date,
                $hideStatus,
                $externalAgency->name
            ),
            $filename
        );
    }
}
