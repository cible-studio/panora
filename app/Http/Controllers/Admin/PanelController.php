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
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\PdfExportService;

use App\Models\Reservation;
use App\Models\ReservationPanel;
use App\Models\Client;
use App\Enums\ReservationStatus;
use Illuminate\Support\Facades\DB;


class PanelController extends Controller
{
    // ── LISTE ──
    public function index(Request $request)
    {
        $query = Panel::with('commune', 'zone', 'format', 'category', 'photos');

        // Filtres
        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->commune_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('reference', 'like', '%' . $request->search . '%')
                    ->orWhere('name', 'like', '%' . $request->search . '%');
            });
        }

        $panels = $query->latest()->paginate(15)->withQueryString();
        $communes = Commune::orderBy('name')->get();
        $categories = PanelCategory::orderBy('name')->get();

        // Stats
        $totalPanneaux = Panel::count();
        $panneauxLibres = Panel::where('status', 'libre')->count();
        $panneauxOccupes = Panel::whereIn('status', ['occupe', 'option', 'confirme'])->count();
        $enMaintenance = Panel::where('status', 'maintenance')->count();

        return view('admin.panels.index', compact(
            'panels',
            'communes',
            'categories',
            'totalPanneaux',
            'panneauxLibres',
            'panneauxOccupes',
            'enMaintenance'
        ));
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
            foreach ($request->file('photos') as $index => $photo) {
                $path = $photo->store('panels', 'public');
                PanelPhoto::create([
                    'panel_id' => $panel->id,
                    'path' => $path,
                    'ordre' => $index,
                ]);
            }
        }

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

        return view('admin.panels.show', compact('panel'));
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
        ]);

        $panel->update([
            ...$request->except('_token', '_method'),
            'is_lit' => $request->boolean('is_lit'),
        ]);

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


    public function quickDetails(Panel $panel)
    {
        $now = now()->startOfDay();
        
        // Occupation en cours
        $current = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->join('clients', 'clients.id', '=', 'reservations.client_id')
            ->where('reservation_panels.panel_id', $panel->id)
            ->where('reservations.start_date', '<=', $now)
            ->where('reservations.end_date', '>=', $now)
            ->whereIn('reservations.status', ['en_attente', 'confirme'])
            ->select('clients.name as client_name', 'reservations.start_date', 'reservations.end_date', 'reservations.status')
            ->first();
        
        // Prochaine occupation
        $next = null;
        if (!$current) {
            $next = DB::table('reservation_panels')
                ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
                ->join('clients', 'clients.id', '=', 'reservations.client_id')
                ->where('reservation_panels.panel_id', $panel->id)
                ->where('reservations.start_date', '>', $now)
                ->whereIn('reservations.status', ['en_attente', 'confirme'])
                ->orderBy('reservations.start_date')
                ->select('clients.name as client_name', 'reservations.start_date', 'reservations.end_date')
                ->first();
        }
        
        return response()->json([
            'current_occupation' => $current ? [
                'client_name' => $current->client_name,
                'start_date' => $current->start_date,
                'end_date' => $current->end_date,
                'status' => $current->status === 'confirme' ? 'confirme' : 'option'
            ] : null,
            'next_occupation' => $next ? [
                'client_name' => $next->client_name,
                'start_date' => $next->start_date,
                'end_date' => $next->end_date,
            ] : null,
        ]);
    }

}
