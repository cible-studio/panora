<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Panel;
use App\Models\Commune;
use App\Models\Zone;
use App\Models\PanelFormat;
use App\Models\PanelCategory;
use App\Models\PanelPhoto;

use App\Enums\PanelStatus;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Services\PdfExportService;
use App\Services\AlertService;

class PanelController extends Controller
{
    // ── LISTE ──
    // ── LISTE ──
    public function index(Request $request)
    {
        $source = $request->input('source', 'all');

        // ═══════════════════════════════════════════════════════════════
        // PANNEAUX INTERNES (CIBLE CI)
        // ═══════════════════════════════════════════════════════════════
        if ($source === 'externe') {
            $panels = collect();
            $totalPanneaux = 0;
            $panneauxLibres = 0;
            $panneauxOccupes = 0;
            $enMaintenance = 0;
        } else {
            $query = Panel::with('commune', 'zone', 'format', 'category', 'photos');

            // 🔍 RECHERCHE EXACTE SUR MOT ENTIER
            // Exemple : "ABG" trouve "ABG-002" mais pas "CABG-001"
            if ($request->filled('search')) {
                $search = strtolower(trim($request->search));
                $escapedSearch = preg_quote($search, '/');
                $pattern = '(^|[^a-zA-ZÀ-ÿ0-9])' . $escapedSearch . '([^a-zA-ZÀ-ÿ0-9]|$)';

                $query->where(function ($q) use ($pattern) {
                    $q->whereRaw('LOWER(reference) REGEXP ?', [$pattern])
                        ->orWhereRaw('LOWER(name) REGEXP ?', [$pattern])
                        ->orWhereRaw('LOWER(quartier) REGEXP ?', [$pattern])
                        ->orWhereRaw('LOWER(adresse) REGEXP ?', [$pattern])
                        ->orWhereHas('commune', function ($c) use ($pattern) {
                            $c->whereRaw('LOWER(name) REGEXP ?', [$pattern]);
                        });
                });
            }

            if ($request->filled('commune_id')) {
                $query->where('commune_id', $request->commune_id);
            }
            if ($request->filled('zone_id')) {
                $query->where('zone_id', $request->zone_id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }
            if ($request->filled('client_id')) {
                $query->where(function ($q) use ($request) {
                    $q->whereHas('reservations', fn($r) => $r->where('client_id', $request->client_id)
                        ->whereNotIn('status', ['annule', 'refuse']))
                        ->orWhereHas('campaigns', fn($c) => $c->where('client_id', $request->client_id)
                            ->whereNotIn('status', ['annule']));
                });
            }

            $panels = $query->latest()->paginate(15)->withQueryString();
            $totalPanneaux = Panel::count();
            $panneauxLibres = Panel::where('status', 'libre')->count();
            $panneauxOccupes = Panel::whereIn('status', ['occupe', 'option', 'confirme'])->count();
            $enMaintenance = Panel::where('status', 'maintenance')->count();
        }

        // ═══════════════════════════════════════════════════════════════
        // PANNEAUX EXTERNES
        // ═══════════════════════════════════════════════════════════════
        $externalQuery = \App\Models\ExternalPanel::with(['agency', 'commune', 'format', 'category']);

        if ($request->filled('search')) {
            $search = strtolower(trim($request->search));
            $escapedSearch = preg_quote($search, '/');
            $pattern = '(^|[^a-zA-ZÀ-ÿ0-9])' . $escapedSearch . '([^a-zA-ZÀ-ÿ0-9]|$)';

            $externalQuery->where(function ($q) use ($pattern) {
                $q->whereRaw('LOWER(code_panneau) REGEXP ?', [$pattern])
                    ->orWhereRaw('LOWER(designation) REGEXP ?', [$pattern]);
            });
        }
        if ($request->filled('commune_id')) {
            $externalQuery->where('commune_id', $request->commune_id);
        }
        if ($request->filled('zone_id')) {
            $externalQuery->where('zone_id', $request->zone_id);
        }

        $externalPanels = $externalQuery->get();
        $totalExternes = \App\Models\ExternalPanel::count();

        // ═══════════════════════════════════════════════════════════════
        // RÉPONSE AJAX
        // ═══════════════════════════════════════════════════════════════
        if ($request->ajax() || $request->input('ajax')) {
            $html = view('admin.panels.partials.table-rows', compact('panels', 'source', 'externalPanels', 'request'))->render();
            $paginationHtml = ($source !== 'externe' && $panels->hasPages()) ? $panels->links()->render() : '';

            return response()->json([
                'html' => $html,
                'pagination' => $paginationHtml,
                'total' => ($source === 'externe') ? $externalPanels->count() : $panels->total(),
                'stats_html' => $this->getStatsHtml($source, $panels, $externalPanels),
            ]);
        }

        $communes = Commune::orderBy('name')->get();
        $zones = Zone::orderBy('name')->get();
        $categories = PanelCategory::orderBy('name')->get();
        $clients = \App\Models\Client::orderBy('name')->get(['id', 'name']);

        return view('admin.panels.index', compact(
            'panels',
            'communes',
            'zones',
            'categories',
            'clients',
            'totalPanneaux',
            'panneauxLibres',
            'panneauxOccupes',
            'enMaintenance',
            'externalPanels',
            'totalExternes',
            'source'
        ));
    }

    private function getStatsHtml($source, $panels, $externalPanels)
    {
        if ($source === 'externe') {
            return '🏢 Panneaux Régies externes (' . $externalPanels->count() . ')';
        }
        return '🪧 Panneaux CIBLE CI (' . $panels->total() . ')';
    }

    // ── CRÉATION ──
    public function create()
    {
        $communes = Commune::orderBy('name')->get();
        $zones = Zone::orderBy('name')->get();
        $formats = PanelFormat::orderBy('name')->get();
        $categories = PanelCategory::orderBy('name')->get();

        return view('admin.panels.create', compact(
            'communes',
            'zones',
            'formats',
            'categories'
        ));
    }

    // ── SAUVEGARDER ──
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'photos.*' => 'nullable|image|max:35840',// 35MB max
            'commune_id' => 'required|exists:communes,id',
            'zone_id' => 'nullable|exists:zones,id',
            'format_id' => 'required|exists:panel_formats,id',
            'category_id' => 'nullable|exists:panel_categories,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'monthly_rate' => 'nullable|numeric|min:0',
            'daily_traffic' => 'nullable|integer|min:0',
            'is_lit' => 'boolean',
            'zone_description' => 'nullable|string',
        ]);

        // Générer référence automatique
        $reference = 'P-' . strtoupper(Str::random(3)) . '-' . rand(100, 999);

        $panel = Panel::create([
            ...$request->except('_token'),
            'reference' => $reference,
            'status' => PanelStatus::LIBRE,
            'created_by' => auth()->id(),
            'is_lit' => $request->boolean('is_lit'),
        ]);

        // Upload photos
        if ($request->hasFile('photos')) {

            $manager = new ImageManager(new Driver());

            foreach ($request->file('photos') as $index => $photo) {

                $image = $manager->read($photo->getPathname());

                $image->scaleDown(width: 1920);

                $filename = 'panels/' . Str::uuid() . '.jpg';

                Storage::disk('public')->put(
                    $filename,
                    $image->toJpeg(90)
                );

                PanelPhoto::create([
                    'panel_id' => $panel->id,
                    'path' => $filename,
                    'ordre' => $index,
                ]);
            }
        }
        AlertService::create(
            'panneau',
            'info',
            '🪧 Nouveau panneau créé — ' . $panel->reference,
            auth()->user()->name . ' a créé le panneau ' . $panel->reference . ' (' . $panel->name . ').',
            $panel
        );

        return redirect()->route('admin.panels.show', $panel)
            ->with('success', 'Panneau créé avec succès !');
    }

    // ── FICHE DÉTAILLÉE ──
    public function show(Panel $panel)
    {
        $panel->load(
            'commune',
            'zone',
            'format',
            'category',
            'photos',
            'createdBy',
            'maintenances',
            'piges'
        );

        // Qui occupe ce panneau ? (réservations + campagnes actives)
        $occupants = collect();

        // Via réservations
        $reservationPanels = \App\Models\ReservationPanel::with(['reservation.client'])
            ->where('panel_id', $panel->id)
            ->whereHas(
                'reservation',
                fn($q) =>
                $q->whereNotIn('status', ['annule', 'termine'])
                    ->where('end_date', '>=', now()->toDateString())
            )
            ->get();

        foreach ($reservationPanels as $rp) {
            $r = $rp->reservation;
            $occupants->push([
                'type' => 'reservation',
                'source_label' => 'Réservation',
                'reference' => $r->reference ?? '—',
                'source_id' => $r->id,
                'client' => $r->client,
                'start_date' => $r->start_date,
                'end_date' => $r->end_date,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
            ]);
        }

        // Via campagnes
        $campaignPanels = \App\Models\CampaignPanel::with(['campaign.client'])
            ->where('panel_id', $panel->id)
            ->where('type', 'interne')
            ->whereHas(
                'campaign',
                fn($q) =>
                $q->whereNotIn('status', ['annule', 'termine'])
                    ->where('end_date', '>=', now()->toDateString())
            )
            ->get();

        foreach ($campaignPanels as $cp) {
            $c = $cp->campaign;
            $occupants->push([
                'type' => 'campaign',
                'source_label' => 'Campagne',
                'reference' => $c->name ?? '—',
                'source_id' => $c->id,
                'client' => $c->client,
                'start_date' => $c->start_date,
                'end_date' => $c->end_date,
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
            ]);
        }

        return view('admin.panels.show', compact('panel', 'occupants'));
    }
    // ── FORMULAIRE MODIFICATION ──
    public function edit(Panel $panel)
    {
        $communes = Commune::orderBy('name')->get();
        $zones = Zone::orderBy('name')->get();
        $formats = PanelFormat::orderBy('name')->get();
        $categories = PanelCategory::orderBy('name')->get();

        return view('admin.panels.edit', compact(
            'panel',
            'communes',
            'zones',
            'formats',
            'categories'
        ));
    }

    // ── METTRE À JOUR ──
    public function update(Request $request, Panel $panel)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'commune_id' => 'required|exists:communes,id',
            'zone_id' => 'nullable|exists:zones,id',
            'format_id' => 'required|exists:panel_formats,id',
            'category_id' => 'nullable|exists:panel_categories,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'monthly_rate' => 'nullable|numeric|min:0',
            'daily_traffic' => 'nullable|integer|min:0',
            'is_lit' => 'boolean',
            'zone_description' => 'nullable|string',
            'new_images.*' => 'nullable|image|max:35840',// 35MB max
            'delete_photos' => 'nullable|array',
            'delete_photos.*' => 'exists:panel_photos,id',
        ]);



        $panel->update([
            'name' => $request->name,
            'commune_id' => $request->commune_id,
            'zone_id' => $request->zone_id,
            'format_id' => $request->format_id,
            'category_id' => $request->category_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'monthly_rate' => $request->monthly_rate,
            'daily_traffic' => $request->daily_traffic,
            'is_lit' => $request->boolean('is_lit'),
            'nombre_faces' => $request->nombre_faces,
            'type_support' => $request->type_support,
            'orientation' => $request->orientation,
            'zone_description' => $request->zone_description,
            'adresse' => $request->adresse,
            'quartier' => $request->quartier,
            'axe_routier' => $request->axe_routier,
        ]);

        // ── Supprimer les photos cochées ──
        if ($request->filled('delete_photos')) {
            $photos = PanelPhoto::whereIn('id', $request->delete_photos)
                ->where('panel_id', $panel->id)
                ->get();
            foreach ($photos as $photo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->path);
                $photo->delete();
            }
        }

        // ── Mettre à jour l'ordre ──
        if ($request->filled('ordre')) {
            foreach ($request->ordre as $photoId => $ordre) {
                PanelPhoto::where('id', $photoId)
                    ->where('panel_id', $panel->id)
                    ->update(['ordre' => (int) $ordre]);
            }
        }

        // ── Ajouter les nouvelles images ──
        if ($request->hasFile('new_images')) {

            $manager = new ImageManager(new Driver());

            $nextOrdre = ($panel->photos()->max('ordre') ?? -1) + 1;

            foreach ($request->file('new_images') as $file) {

                $image = $manager->read($file->getPathname());

                $image->scaleDown(width: 1920);

                $filename = 'panels/' . Str::uuid() . '.jpg';

                Storage::disk('public')->put(
                    $filename,
                    $image->toJpeg(90)
                );

                PanelPhoto::create([
                    'panel_id' => $panel->id,
                    'path' => $filename,
                    'ordre' => $nextOrdre++,
                ]);
            }
        }

        AlertService::create(
            'panneau',
            'info',
            '✏️ Panneau modifié — ' . $panel->reference,
            auth()->user()->name . ' a modifié le panneau ' . $panel->reference . '.',
            $panel
        );

        return redirect()->route('admin.panels.show', $panel)
            ->with('success', 'Panneau modifié avec succès !');
    }

    // ── SUPPRIMER ──
    public function destroy(Panel $panel)
    {
        $panel->delete();
        return redirect()->route('admin.panels.index')
            ->with('success', 'Panneau supprimé !');
    }

    // ── CHANGER STATUT ──
    public function updateStatus(Request $request, Panel $panel)
    {
        $request->validate([
            'status' => 'required|in:libre,occupe,option,confirme,maintenance'
        ]);

        $panel->update(['status' => $request->status]);
        AlertService::create(
            'panneau',
            'info',
            'Statut panneau mis à jour — ' . $panel->reference,
            auth()->user()->name . ' a changé le statut du panneau ' . $panel->reference . ' en "' . $request->status . '".',
            $panel
        );

        return back()->with('success', 'Statut mis à jour !');
    }

    // ── UPLOAD PHOTO ──
    public function uploadPhoto(Request $request, Panel $panel)
    {
        $request->validate([
            'photo' => 'required|image|max:5120'
        ]);

        $path = $request->file('photo')->store('panels', 'public');

        PanelPhoto::create([
            'panel_id' => $panel->id,
            'path' => $path,
            'ordre' => $panel->photos()->count(),
        ]);

        return back()->with('success', 'Photo ajoutée !');
    }

    public function deletePhoto(Panel $panel, PanelPhoto $photo)
    {
        if ($photo->panel_id !== $panel->id) {
            abort(403);
        }

        \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return back()->with('success', 'Photo supprimée.');
    }

    // ── DISPONIBILITÉ ──
    public function availability(Request $request, Panel $panel)
    {
        $reservations = $panel->reservations()
            ->whereNotIn('status', ['refuse', 'annule'])
            ->where('end_date', '>=', now())
            ->orderBy('start_date')
            ->get();

        return view('admin.panels.availability', compact('panel', 'reservations'));
    }

    // ── CARTE GPS ──
    public function map()
    {
        $communes = Commune::orderBy('name')->get();
        return view('admin.panels.map', compact('communes'));
    }

    // ── DONNÉES CARTE JSON ──
    public function mapData(Request $request)
    {
        $query = Panel::with('commune', 'category')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->commune_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $panels = $query->get()->map(function ($panel) {
            return [
                'id' => $panel->id,
                'reference' => $panel->reference,
                'name' => $panel->name,
                'latitude' => $panel->latitude,
                'longitude' => $panel->longitude,
                'status' => $panel->status->value,
                'commune' => $panel->commune->name,
                'monthly_rate' => $panel->monthly_rate,
            ];
        });

        return response()->json($panels);
    }

    // ── EXPORT PDF FICHE ──
    public function exportPdf(Panel $panel)
    {
        $service = new PdfExportService();
        return $service->exportPanelSheet($panel);
    }

    // ── EXPORT PDF LISTE ──
    public function exportList(Request $request)
    {
        $service = new PdfExportService();
        return $service->exportPanelList($request->all());
    }

    // ── EXPORT PDF RÉSEAU ──
    public function exportNetwork()
    {
        $service = new PdfExportService();
        return $service->exportNetworkReport();
    }
}
