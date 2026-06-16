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
use App\Services\PanelReferenceGenerator;

class PanelController extends Controller
{
    // ── LISTE ──
    public function index(Request $request)
    {
        $source = $request->input('source', 'all');
        $showOccupants = false;

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
            // Eager loading optimisé : on ne charge que la photo principale (ordre=0/1)
            // pour éviter de tirer toutes les photos sur l'index (réduit drastiquement
            // la taille du payload et le N+1 photos).
            $showOccupants = $source === 'occupes'
                          || in_array($request->status, ['occupe', 'option', 'confirme']);

            $eagerLoad = [
                'commune:id,name',
                'zone:id,name',
                'format:id,name,width,height',
                'category:id,name',
                'photos' => fn($q) => $q->orderBy('ordre')->limit(1),
            ];
            if ($showOccupants) {
                $eagerLoad['campaigns'] = fn($q) => $q
                    ->whereNotIn('campaigns.status', ['annule', 'termine'])
                    ->with('client:id,name');
            }

            $query = Panel::with($eagerLoad);

            if ($source === 'occupes') {
                $query->whereIn('status', ['occupe', 'option', 'confirme']);
            }

            // 🔍 RECHERCHE EXACTE SUR MOT ENTIER, INSENSIBLE AUX ACCENTS
            // Exemple : "ABG" trouve "ABG-002" mais pas "CABG-001"
            // "port bouet" trouve "Port Bouët" (les accents sont normalisés
            // des deux côtés via unaccentSql/stripAccents).
           if ($request->filled('search')) {
                $search = $this->stripAccents(trim($request->search));
                $escapedSearch = preg_quote($search, '/');
                $pattern = '(^|[^a-zA-Z0-9])' . $escapedSearch . '([^a-zA-Z0-9]|$)';

                $refExpr      = $this->unaccentSql('reference');
                $nameExpr     = $this->unaccentSql('name');
                $quartierExpr = $this->unaccentSql('quartier');
                $adresseExpr  = $this->unaccentSql('adresse');
                $communeExpr  = $this->unaccentSql('name');

                $query->where(function ($q) use ($pattern, $refExpr, $nameExpr, $quartierExpr, $adresseExpr, $communeExpr) {
                    $q->whereRaw("$refExpr REGEXP ?", [$pattern])
                      ->orWhereRaw("$nameExpr REGEXP ?", [$pattern])
                      ->orWhereRaw("$quartierExpr REGEXP ?", [$pattern])
                      ->orWhereRaw("$adresseExpr REGEXP ?", [$pattern])
                      ->orWhereHas('commune', function($c) use ($pattern, $communeExpr) {
                          $c->whereRaw("$communeExpr REGEXP ?", [$pattern]);
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
            if ($request->filled('format_id')) {
                $query->where('format_id', $request->format_id);
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
            $search = $this->stripAccents(trim($request->search));
            $escapedSearch = preg_quote($search, '/');
            $pattern = '(^|[^a-zA-Z0-9])' . $escapedSearch . '([^a-zA-Z0-9]|$)';

            $codeExpr = $this->unaccentSql('code_panneau');
            $desigExpr = $this->unaccentSql('designation');

            $externalQuery->where(function ($q) use ($pattern, $codeExpr, $desigExpr) {
                $q->whereRaw("$codeExpr REGEXP ?", [$pattern])
                  ->orWhereRaw("$desigExpr REGEXP ?", [$pattern]);
            });
        }
        if ($request->filled('commune_id')) {
            $externalQuery->where('commune_id', $request->commune_id);
        }
        if ($request->filled('zone_id')) {
            $externalQuery->where('zone_id', $request->zone_id);
        }
        if ($request->filled('format_id')) {
            $externalQuery->where('format_id', $request->format_id);
        }

        $externalPanels = $externalQuery->get();
        $totalExternes = \App\Models\ExternalPanel::count();

        // ═══════════════════════════════════════════════════════════════
        // RÉPONSE AJAX
        // ═══════════════════════════════════════════════════════════════
        if ($request->ajax() || $request->input('ajax')) {
            $html = view('admin.panels.partials.table-rows', compact('panels', 'source', 'externalPanels', 'request', 'showOccupants'))->render();
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
        // Formats triés par surface croissante (du plus petit au plus grand).
        // Évite l'ordre alphabétique qui donne 10m² avant 2m².
        $formats = PanelFormat::orderBy('surface')->orderBy('width')->orderBy('height')->get();
        $clients = \App\Models\Client::orderBy('name')->get(['id', 'name']);

        return view('admin.panels.index', compact(
            'panels',
            'communes',
            'zones',
            'categories',
            'formats',
            'clients',
            'totalPanneaux',
            'panneauxLibres',
            'panneauxOccupes',
            'enMaintenance',
            'externalPanels',
            'totalExternes',
            'source',
            'showOccupants'
        ));
    }

    private function getStatsHtml($source, $panels, $externalPanels)
    {
        if ($source === 'externe') {
            return '🏢 Panneaux Régies externes (' . $externalPanels->count() . ')';
        }
        if ($source === 'occupes') {
            return '🔴 Panneaux occupés (' . $panels->total() . ')';
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

    // ── APERÇU LIVE DE LA RÉFÉRENCE (AJAX) ──
    //
    // Endpoint appelé par la vue create / edit pour afficher la
    // référence calculée sans recharger la page. Retourne aussi des
    // flags pour signaler à l'UI si un code commune/catégorie a dû
    // être dérivé (= invitation à le saisir manuellement dans
    // /admin/communes ou /admin/panel-categories).
    public function generateReference(Request $request, PanelReferenceGenerator $refGen)
    {
        $request->validate([
            'commune_id'  => 'required|exists:communes,id',
            'category_id' => 'nullable|exists:panel_categories,id',
            'face'        => 'nullable|in:A,B,C,D',
            'exclude_id'  => 'nullable|integer',
        ]);

        $commune  = Commune::find($request->commune_id);
        $category = $request->category_id
            ? PanelCategory::find($request->category_id)
            : null;

        $preview = $refGen->preview(
            $commune,
            $category,
            $request->face,
            $request->exclude_id ? (int) $request->exclude_id : null,
        );

        return response()->json($preview);
    }

    // ── SAUVEGARDER ──
    public function store(Request $request, PanelReferenceGenerator $refGen)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'reference' => 'nullable|string|max:32|unique:panels,reference',
            'photos.*' => 'nullable|image|max:35840',// 35MB max
            'commune_id' => 'required|exists:communes,id',
            'zone_id' => 'nullable|exists:zones,id',
            'format_id' => 'required|exists:panel_formats,id',
            'category_id' => 'nullable|exists:panel_categories,id',
            'face' => 'nullable|in:A,B,C,D',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'monthly_rate' => 'nullable|numeric|min:0',
            'daily_traffic' => 'nullable|integer|min:0',
            'is_lit' => 'boolean',
            'is_vip' => 'boolean',
            'zone_description' => 'nullable|string',
        ]);

        // Référence :
        //   - Si l'utilisateur a saisi une valeur manuellement, on l'utilise.
        //   - Sinon, on la génère selon le pattern {COMMUNE}{CAT}-{NN}{FACE}.
        $reference = $request->filled('reference')
            ? strtoupper(trim($request->reference))
            : $refGen->generate(
                Commune::findOrFail($request->commune_id),
                $request->category_id ? PanelCategory::find($request->category_id) : null,
                $request->face,
            );

        // Double-vérification anti-doublon (race condition possible si
        // 2 créations simultanées) : si la ref proposée est déjà prise,
        // on régénère.
        if (!$refGen->isAvailable($reference)) {
            $reference = $refGen->generate(
                Commune::findOrFail($request->commune_id),
                $request->category_id ? PanelCategory::find($request->category_id) : null,
                $request->face,
            );
        }

        // Coordonnées saisies à la main → gps_source='manual' (protégé contre
        // l'écrasement par l'auto-géoloc des piges, cf. PanelGeoLocator).
        $manualGps = $request->filled('latitude') && $request->filled('longitude');

        $panel = Panel::create([
            ...$request->except(['_token', 'face', 'reference', 'gps_source']),
            'reference' => $reference,
            'status' => PanelStatus::LIBRE,
            'created_by' => auth()->id(),
            'is_lit' => $request->boolean('is_lit'),
            'is_vip' => $request->boolean('is_vip'),
            'gps_source' => $manualGps ? 'manual' : null,
            'gps_computed_at' => $manualGps ? now() : null,
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

        // Maintenance ouverte (signalee/en_cours) — pour afficher la carte
        // d'alerte avec lien direct vers la fiche panne. Si plusieurs, on
        // prend la plus récente (improbable mais défensif).
        $activeMaintenance = \App\Models\Maintenance::where('panel_id', $panel->id)
            ->whereIn('statut', \App\Models\Maintenance::STATUTS_OUVERTS)
            ->with('technicien:id,name,whatsapp_number')
            ->latest('id')
            ->first();

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

        return view('admin.panels.show', compact('panel', 'occupants', 'activeMaintenance'));
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
            'reference' => [
                'required',
                'string',
                'max:32',
                \Illuminate\Validation\Rule::unique('panels', 'reference')->ignore($panel->id),
            ],
            'commune_id' => 'required|exists:communes,id',
            'zone_id' => 'nullable|exists:zones,id',
            'format_id' => 'required|exists:panel_formats,id',
            'category_id' => 'nullable|exists:panel_categories,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'monthly_rate' => 'nullable|numeric|min:0',
            'daily_traffic' => 'nullable|integer|min:0',
            'is_lit' => 'boolean',
            'is_vip' => 'boolean',
            'zone_description' => 'nullable|string',
            'new_images.*' => 'nullable|image|max:35840',// 35MB max
            'delete_photos' => 'nullable|array',
            'delete_photos.*' => 'exists:panel_photos,id',
        ]);



        // ── Provenance GPS ──
        // On ne marque 'manual' que si l'admin a réellement SAISI/MODIFIÉ des
        // coordonnées (sinon ouvrir le formulaire et resauvegarder figerait
        // une position auto-géolocalisée par piges). Coords vidées → reset à
        // null (l'auto-géoloc pourra repeupler).
        $hasCoords  = $request->filled('latitude') && $request->filled('longitude');
        $gpsSource  = $panel->gps_source;
        $gpsComputed = $panel->gps_computed_at;
        if (!$hasCoords) {
            $gpsSource   = null;
            $gpsComputed = null;
        } else {
            $coordsChanged = $panel->latitude === null || $panel->longitude === null
                || abs((float) $panel->latitude  - (float) $request->latitude)  > 1e-7
                || abs((float) $panel->longitude - (float) $request->longitude) > 1e-7;
            if ($coordsChanged) {
                $gpsSource   = 'manual';
                $gpsComputed = now();
            }
        }

        $panel->update([
            'name' => $request->name,
            'reference' => strtoupper(trim($request->reference)),
            'commune_id' => $request->commune_id,
            'zone_id' => $request->zone_id,
            'format_id' => $request->format_id,
            'category_id' => $request->category_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'gps_source' => $gpsSource,
            'gps_computed_at' => $gpsComputed,
            'monthly_rate' => $request->monthly_rate,
            'daily_traffic' => $request->daily_traffic,
            'is_lit' => $request->boolean('is_lit'),
            'is_vip' => $request->boolean('is_vip'),
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
        $panelRef = $panel->reference;
        
        $panel->delete();
        
        // Alerte suppression panneau
        AlertService::create(
            'panneau',
            'danger',
            '🗑 Panneau supprimé — ' . $panelRef,
            auth()->user()->name . ' a supprimé le panneau ' . $panelRef . '.',
            null
        );
        
        return redirect()->route('admin.panels.index')
            ->with('success', 'Panneau supprimé !');
    }

    // ── CHANGER STATUT ──
    public function updateStatus(Request $request, Panel $panel)
    {
        // Seuls libre et maintenance sont manuels — les autres (option,
        // confirme, occupe) sont calculés par AvailabilityService à partir
        // des réservations. Permettre leur saisie manuelle créait des
        // incohérences avec le planning commercial.
        $request->validate([
            'status' => 'required|in:libre,maintenance'
        ], [
            'status.in' => "Seuls les statuts 'libre' et 'maintenance' sont modifiables manuellement. Les autres sont dérivés des réservations.",
        ]);

        $previousStatus = $panel->status->value;
        $panel->update(['status' => $request->status]);

        // Si on revient à 'libre' alors qu'une réservation active existe,
        // AvailabilityService réajuste vers le bon statut (option / confirme).
        if ($request->status === 'libre') {
            try {
                app(\App\Services\AvailabilityService::class)->syncPanelStatuses([$panel->id]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('panel.updateStatus.sync_failed', [
                    'panel_id' => $panel->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        AlertService::create(
            'panneau',
            'info',
            'Statut panneau mis à jour — ' . $panel->reference,
            auth()->user()->name . ' a changé le statut du panneau ' . $panel->reference . ' : ' . $previousStatus . ' → ' . $request->status,
            $panel
        );

        if ($request->expectsJson() || $request->ajax()) {
            $statusCfg = match ($panel->status->value) {
                'libre'       => ['label' => 'Libre',       'class' => 'badge-green',  'color' => '#22c55e'],
                'option'      => ['label' => 'Option',      'class' => 'badge-orange', 'color' => '#f59e0b'],
                'confirme'    => ['label' => 'Confirmé',    'class' => 'badge-blue',   'color' => '#3b82f6'],
                'occupe'      => ['label' => 'Occupé',      'class' => 'badge-purple', 'color' => '#a855f7'],
                'maintenance' => ['label' => 'Maintenance', 'class' => 'badge-red',    'color' => '#ef4444'],
                default       => ['label' => $panel->status->value, 'class' => '', 'color' => '#6b7280'],
            };

            return response()->json([
                'ok'              => true,
                'message'         => "Statut mis à jour : {$statusCfg['label']}.",
                'status'          => $panel->status->value,
                'previous_status' => $previousStatus,
                'label'           => $statusCfg['label'],
                'class'           => $statusCfg['class'],
                'color'           => $statusCfg['color'],
            ]);
        }

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

    // ══════════════════════════════════════════════════════════════
    // GPS — Saisie manuelle des coordonnées manquantes (mission user)
    //
    // Page bulk edit qui liste tous les panneaux SANS lat/lng et permet
    // de saisir lat/lng à la volée. Tri par commune pour faire à la
    // chaîne. Le gps_source est posé à 'manual' à l'enregistrement.
    // ══════════════════════════════════════════════════════════════
    public function gpsMissing(Request $request)
    {
        $communes = Commune::orderBy('name')->get(['id', 'name']);

        $query = Panel::with('commune:id,name', 'format:id,name')
            ->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            })
            ->whereNull('deleted_at');

        if ($request->filled('commune_id')) {
            $query->where('commune_id', (int) $request->commune_id);
        }
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('name',    'like', "%{$search}%");
            });
        }

        $panels = $query->orderBy('commune_id')->orderBy('reference')->paginate(50)->withQueryString();

        $stats = [
            'total'    => Panel::count(),
            'with_gps' => Panel::whereNotNull('latitude')->whereNotNull('longitude')->count(),
        ];
        $stats['missing'] = $stats['total'] - $stats['with_gps'];

        return view('admin.panels.gps-missing', compact('panels', 'communes', 'stats'));
    }

    public function gpsBulkUpdate(Request $request)
    {
        $data = $request->validate([
            'gps'           => 'required|array|min:1',
            'gps.*.id'      => 'required|integer|exists:panels,id',
            // Côte d'Ivoire : ~4°-11° N, -8°-(-2)° W. On valide largement
            // pour permettre les communes périphériques sans bloquer.
            'gps.*.lat'     => 'nullable|numeric|between:3.5,11.5',
            'gps.*.lng'     => 'nullable|numeric|between:-9,-1.5',
        ], [
            'gps.*.lat.between' => 'Latitude hors zone Côte d\'Ivoire (3,5 → 11,5°N attendus).',
            'gps.*.lng.between' => 'Longitude hors zone Côte d\'Ivoire (-9 → -1,5°W attendus).',
        ]);

        $updated = 0;
        $skipped = 0;

        foreach ($data['gps'] as $row) {
            $lat = $row['lat'] ?? null;
            $lng = $row['lng'] ?? null;
            // Les 2 doivent être fournis pour valider
            if ($lat === null || $lng === null || $lat === '' || $lng === '') {
                $skipped++;
                continue;
            }

            Panel::where('id', (int) $row['id'])->update([
                'latitude'         => (float) $lat,
                'longitude'        => (float) $lng,
                'gps_source'       => 'manual',
                'gps_computed_at'  => now(),
            ]);
            $updated++;
        }

        $msg = "✅ {$updated} panneau(x) géolocalisé(s).";
        if ($skipped > 0) $msg .= " ({$skipped} ligne(s) ignorée(s) — lat/lng incomplètes)";

        return back()->with('success', $msg);
    }

    // ── CARTE GPS ──
    public function map()
    {
        $communes = Commune::orderBy('name')->get();

        // Couverture géoloc du réseau (pour le bandeau de la carte).
        $geoCoverage = [
            'total'      => Panel::count(),
            'with_gps'   => Panel::whereNotNull('latitude')->whereNotNull('longitude')->count(),
            'manual'     => Panel::where('gps_source', 'manual')->count(),
            'confirmed'  => Panel::where('gps_source', 'pige_confirmed')->count(),
            'provisional'=> Panel::where('gps_source', 'pige_provisional')->count(),
            'dispersion' => Panel::where('gps_dispersion_flag', true)->count(),
        ];
        $geoCoverage['missing'] = $geoCoverage['total'] - $geoCoverage['with_gps'];

        return view('admin.panels.map', compact('communes', 'geoCoverage'));
    }

    // ── DONNÉES CARTE JSON ──
    public function mapData(Request $request)
    {
        $query = Panel::with('commune', 'category', 'format')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->commune_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        // Filtre provenance GPS : manual | pige_confirmed | pige_provisional |
        // unknown (legacy, coords présentes mais source NULL).
        if ($request->filled('gps_source')) {
            $request->gps_source === 'unknown'
                ? $query->whereNull('gps_source')
                : $query->where('gps_source', $request->gps_source);
        }

        $panels = $query->get()->map(function ($panel) {
            // Surface en m² calculée si possible — sinon fallback sur format->name.
            $surface = null;
            if ($panel->format?->width && $panel->format?->height) {
                $surface = (float) $panel->format->width * (float) $panel->format->height;
            }
            return [
                'id' => $panel->id,
                'reference' => $panel->reference,
                'name' => $panel->name,
                'latitude' => $panel->latitude,
                'longitude' => $panel->longitude,
                'status' => $panel->status->value,
                'commune' => $panel->commune->name,
                'monthly_rate' => $panel->monthly_rate,
                'category' => $panel->category?->name,
                'format' => $panel->format?->name,
                'surface' => $surface,
                'gps_source' => $panel->gps_source ?? 'unknown',
                'gps_dispersion' => (bool) $panel->gps_dispersion_flag,
            ];
        });

        // Réponse : tableau plat (forme historique inchangée — la vue
        // panels/map consomme directement le tableau). Les nouveaux champs
        // gps_source / gps_dispersion sont purement additifs.
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

    // ══════════════════════════════════════════════════════════════
    // HELPERS — Recherche insensible aux accents
    // ══════════════════════════════════════════════════════════════

    /**
     * Retire les accents et passe en minuscules — pour normaliser le terme
     * de recherche côté PHP avant de construire la regex.
     */
    private function stripAccents(string $s): string
    {
        return strtr(mb_strtolower($s, 'UTF-8'), [
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
            'ç'=>'c',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ñ'=>'n',
            'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
            'ý'=>'y','ÿ'=>'y',
        ]);
    }

    /**
     * Construit une expression SQL qui retire les accents d'une colonne
     * (chaîne REPLACE imbriquées + LOWER). Portable sur MySQL/MariaDB
     * sans dépendre de la collation configurée — la recherche fonctionne
     * que la base soit en utf8mb4_unicode_ci, _general_ci ou _bin.
     */
    private function unaccentSql(string $column): string
    {
        $map = [
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
            'ç'=>'c',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ñ'=>'n',
            'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
            'ý'=>'y','ÿ'=>'y',
        ];
        $expr = "LOWER($column)";
        foreach ($map as $from => $to) {
            $expr = "REPLACE($expr, '$from', '$to')";
        }
        return $expr;
    }
}
