<?php
// app/Http/Controllers/Admin/PigeController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\User;
use App\Services\AlertService;
use App\Services\PigeService;
use App\Enums\CampaignStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;


class PigeController extends Controller
{
    public function __construct(
        protected PigeService  $pigeService,
        protected AlertService $alertService,
    ) {}

    // ══════════════════════════════════════════════════════════════
    // INDEX — liste avec filtres
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        // Vue active par défaut — exclut les piges archivées (dont la campagne
        // a été supprimée ou cleanup manuel). Les archives sont accessibles
        // via la route dédiée `admin.piges.archives`.
        $query = Pige::with([
            'panel:id,reference,name,commune_id',
            'panel.commune:id,name',
            'campaign:id,name,status',
            'technicien:id,name',
            'verificateur:id,name',
        ])->active();

        // ── Filtres "neutres" appliqués au périmètre + aux compteurs ──
        if ($request->filled('campaign_id'))   $query->where('campaign_id', $request->campaign_id);
        if ($request->filled('panel_id'))      $query->where('panel_id', $request->panel_id);
        if ($request->filled('technicien_id')) $query->where('user_id', $request->technicien_id);
        if ($request->filled('geo_check'))     $query->where('geo_check', $request->geo_check);
        if ($request->filled('date_from'))     $query->whereDate('taken_at', '>=', $request->date_from);
        if ($request->filled('date_to'))       $query->whereDate('taken_at', '<=', $request->date_to);
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn($sq) =>
                $sq->whereHas('panel', fn($p) =>
                    $p->where('reference', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%")
                )
                ->orWhereHas('campaign', fn($c) => $c->where('name', 'like', "%{$q}%"))
                ->orWhereHas('technicien', fn($u) => $u->where('name', 'like', "%{$q}%"))
            );
        }

        // ─── COMPTEURS KPI sur le périmètre AVANT filtre status ───
        // (chaque carte garde sa vraie valeur quand on en clique une)
        $countsRaw = (clone $query)
            ->setEagerLoads([])
            ->reorder()
            ->select('status', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'en_attente' => (int) ($countsRaw['en_attente'] ?? 0),
            'verifie'    => (int) ($countsRaw['verifie']    ?? 0),
            'rejete'     => (int) ($countsRaw['rejete']     ?? 0),
        ];

        // ─── Filtre status (carte cliquée) appliqué APRÈS ───
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $piges = $query->latest('taken_at')->paginate(24)->withQueryString();
        $stats['total'] = $piges->total();

        $techniciens = User::where('role', 'technique')->orderBy('name')->get(['id', 'name']);
        $campaigns   = Campaign::where('status', CampaignStatus::ACTIF->value)
            ->orderBy('name')->get(['id', 'name', 'status']);

        // Panneau pré-filtré (depuis index poses)
        $filterPanel = $request->filled('panel_id')
            ? Panel::with('commune:id,name')->find($request->panel_id)
            : null;

        // Campagne pré-filtrée
        $filterCampaign = $request->filled('campaign_id')
            ? Campaign::find($request->campaign_id)
            : null;

        return view('admin.piges.index', compact(
            'piges', 'stats', 'techniciens', 'campaigns',
            'filterPanel', 'filterCampaign'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // ARCHIVES — piges des campagnes supprimées
    //
    // Affiche les piges dont `archived_at` est NOT NULL — soit la
    // campagne associée a été soft-deleted (auto-archivage par
    // CampaignObserver::deleted), soit un cleanup manuel a été lancé.
    //
    // Conservation 100% non destructive : la pige garde sa photo, ses
    // métadonnées et son lien avec la campagne (même si celle-ci est
    // soft-deleted, on la retrouve avec withTrashed).
    // ══════════════════════════════════════════════════════════════
    public function archives(Request $request)
    {
        $query = Pige::with([
            'panel:id,reference,name,commune_id',
            'panel.commune:id,name',
            'campaign' => fn($q) => $q->withTrashed()->select('id', 'name', 'status', 'deleted_at'),
            'campaign.client' => fn($q) => $q->withTrashed()->select('id', 'name'),
            'technicien:id,name',
        ])->archived();

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn($sq) =>
                $sq->whereHas('panel', fn($p) =>
                    $p->where('reference', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%")
                )
                ->orWhereHas('campaign', fn($c) =>
                    $c->withTrashed()->where('name', 'like', "%{$q}%")
                )
            );
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }

        $piges = $query->latest('archived_at')->paginate(24)->withQueryString();

        // Compteurs simples — combien d'archivages, sur combien de campagnes distinctes
        $totalArchived = Pige::archived()->count();
        $campaignsAffected = Pige::archived()
            ->whereNotNull('campaign_id')
            ->distinct('campaign_id')
            ->count('campaign_id');

        return view('admin.piges.archives', compact(
            'piges', 'totalArchived', 'campaignsAffected'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // CREATE — formulaire upload
    // ══════════════════════════════════════════════════════════════
    public function create(Request $request)
    {
        $techniciens = User::where('role', 'technique')->orderBy('name')->get(['id', 'name']);

        $preselectedCampaign = null;
        $preselectedPanel    = null;

        if ($request->filled('campaign_id')) {
            $preselectedCampaign = Campaign::with([
                'panels:id,reference,name,commune_id',
                'panels.commune:id,name',
            ])->find($request->campaign_id);
        }
        if ($request->filled('panel_id')) {
            $preselectedPanel = Panel::with('commune:id,name')->find($request->panel_id);
        }

        return view('admin.piges.create', compact(
            'techniciens', 'preselectedCampaign', 'preselectedPanel'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // STORE — upload photo(s)
    // ══════════════════════════════════════════════════════════════
    public function store(Request $request)
    {
        $request->validate([
            'panel_id'    => 'required|exists:panels,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'photos'      => 'required|array|min:1|max:10',
            'photos.*' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:30720',
            'taken_at'    => 'nullable|date|before_or_equal:now',
            'gps_lat'     => 'nullable|numeric|between:-90,90',
            'gps_lng'     => 'nullable|numeric|between:-180,180',
            'notes'       => 'nullable|string|max:500',
        ], [
            'panel_id.required'   => 'Le panneau est obligatoire.',
            'panel_id.exists'     => 'Panneau introuvable.',
            'campaign_id.exists'  => 'Campagne introuvable.',
            'photos.required'     => 'Veuillez sélectionner au moins une photo.',
            'photos.min'          => 'Veuillez sélectionner au moins une photo.',
            'photos.max'          => 'Maximum 10 photos par upload.',
            'photos.*.image'      => 'Chaque fichier doit être une image.',
            'photos.*.mimes'      => 'Formats acceptés : JPG, PNG, WebP.',
            'photos.*.max' => 'Chaque photo ne doit pas dépasser 30 Mo.',
            'taken_at.before_or_equal' => 'La date de prise de vue ne peut pas être dans le futur.',
            'gps_lat.between'     => 'Latitude invalide (entre -90 et 90).',
            'gps_lng.between'     => 'Longitude invalide (entre -180 et 180).',
            'notes.max'           => 'Les notes ne doivent pas dépasser 500 caractères.',
        ]);

        $result = $this->pigeService->upload(
            photos:     $request->file('photos'),
            panelId:    (int) $request->panel_id,
            campaignId: $request->campaign_id ? (int) $request->campaign_id : null,
            uploader:   auth()->user(),
            meta: [
                'taken_at' => $request->taken_at,
                'gps_lat'  => $request->gps_lat,
                'gps_lng'  => $request->gps_lng,
                'notes'    => $request->notes,
            ]
        );

        if (!$result['ok']) {
            return back()->withInput()->with('error', $result['error']);
        }

        $msg = $result['count'] . ' pige(s) uploadée(s) avec succès — en attente de vérification.';

        // Alerte upload piges
        AlertService::create(
            'pige',
            'info',
            '📸 Nouvelles piges uploadées',
            auth()->user()->name . ' a uploadé ' . $result['count'] . ' pige(s) pour le panneau ' . ($request->panel_id ? '#' . $request->panel_id : ''),
            null
        );

        // Rediriger vers index filtré sur la campagne/panneau
        $redirectParams = array_filter([
            'campaign_id' => $request->campaign_id,
            'panel_id'    => $request->panel_id,
        ]);

        return redirect()->route('admin.piges.index', $redirectParams)->with('success', $msg);
    }

    public function edit(Pige $pige)
    {
        $pige->load(['panel.commune', 'campaign', 'technicien', 'verificateur']);
        $techniciens = User::orderBy('name')->get(['id', 'name']);

        $prevPige = Pige::where('id', '<', $pige->id)
            ->when($pige->campaign_id, fn($q) => $q->where('campaign_id', $pige->campaign_id))
            ->latest('id')->first();

        $nextPige = Pige::where('id', '>', $pige->id)
            ->when($pige->campaign_id, fn($q) => $q->where('campaign_id', $pige->campaign_id))
            ->oldest('id')->first();

        return view('admin.piges.edit', compact('pige', 'techniciens', 'prevPige', 'nextPige'));
    }

    public function update(Request $request, Pige $pige)
    {
        // Une pige vérifiée ne peut pas être entièrement modifiée
        // mais on autorise les métadonnées (notes, taken_at, gps)
        $isVerified = $pige->isVerifiee();
 
        $rules = [
            'taken_at' => 'nullable|date',
            'gps_lat'  => 'nullable|numeric|between:-90,90',
            'gps_lng'  => 'nullable|numeric|between:-180,180',
            'notes'    => 'nullable|string|max:1000',
            'user_id'  => 'nullable|exists:users,id',
        ];
 
        if (!$isVerified) {
            $rules['photo']            = 'nullable|image|mimes:jpg,jpeg,png,webp|max:30720';
            $rules['status']           = 'nullable|in:en_attente,verifie,rejete';
            $rules['rejection_reason'] = 'nullable|string|max:500';
        }
 
        $messages = [
            'photo.max'                => 'La photo ne doit pas dépasser 30 Mo.',
            'photo.mimes'              => 'Format accepté : JPG, PNG, WebP.',
            'gps_lat.between'          => 'Latitude invalide (entre -90 et 90).',
            'gps_lng.between'          => 'Longitude invalide (entre -180 et 180).',
            'rejection_reason.required'=> 'Le motif de rejet est obligatoire.',
            'notes.max'                => 'Les notes ne doivent pas dépasser 1000 caractères.',
        ];
 
        // Motif obligatoire si statut = rejete
        if (!$isVerified && $request->input('status') === 'rejete') {
            $rules['rejection_reason'] = 'required|string|max:500';
        }
 
        $validated = $request->validate($rules, $messages);
 
        $data = [];

        if (array_key_exists('taken_at', $validated)) $data['taken_at'] = $validated['taken_at'];
        if (array_key_exists('gps_lat',  $validated)) $data['gps_lat']  = $validated['gps_lat'];
        if (array_key_exists('gps_lng',  $validated)) $data['gps_lng']  = $validated['gps_lng'];
        if (array_key_exists('notes',    $validated)) $data['notes']    = $validated['notes'];
        if (array_key_exists('user_id',  $validated)) $data['user_id']  = $validated['user_id'];
 
        // Statut et motif (si non vérifiée)
        if (!$isVerified) {
            if ($request->filled('status')) {
                $data['status'] = $validated['status'];
                if ($validated['status'] === 'rejete') {
                    $data['rejection_reason'] = $validated['rejection_reason'];
                    if (!$pige->verified_by) {
                        $data['verified_by'] = auth()->id();
                        $data['verified_at'] = now();
                    }
                } elseif ($validated['status'] === 'verifie') {
                    $data['rejection_reason'] = null;
                    $data['verified_by']      = auth()->id();
                    $data['verified_at']      = now();
                } else {
                    $data['rejection_reason'] = null;
                    $data['verified_by']      = null;
                    $data['verified_at']      = null;
                }
            }
 
            // Remplacer la photo si une nouvelle est fournie
            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                // Supprimer l'ancienne
                if ($pige->photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($pige->photo_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($pige->photo_path);
                }
                if ($pige->photo_thumb) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($pige->photo_thumb);
                }
 
                $file     = $request->file('photo');
                $ext      = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = \Illuminate\Support\Str::uuid() . '.' . $ext;
                $folder   = 'piges/' . ($pige->campaign_id ? "campaigns/{$pige->campaign_id}" : 'libre') . "/panel_{$pige->panel_id}";
                $path     = $file->storeAs($folder, $filename, 'public');
 
                $data['photo_path']  = $path;
                $data['photo_thumb'] = null;
            }
        }
 
        $statusWasNotVerified = $pige->status !== 'verifie';
        $pige->update($data);

        \Illuminate\Support\Facades\Log::info('pige.updated', [
            'pige_id' => $pige->id,
            'by'      => auth()->id(),
            'changes' => array_keys($data),
        ]);

        // ── Notification email client si la pige vient d'être validée ──
        if ($statusWasNotVerified && ($data['status'] ?? null) === 'verifie') {
            $this->notifyClientPigeValidated($pige);
        }

        // ── Auto-géolocalisation si validation via ce formulaire (chemin
        //    alternatif à verify()). Best-effort. ──
        if ($statusWasNotVerified
            && ($data['status'] ?? null) === 'verifie'
            && $pige->gps_lat !== null && $pige->gps_lng !== null) {
            try {
                $pige->loadMissing('panel');
                if ($pige->panel) {
                    app(\App\Services\PanelGeoLocator::class)->recomputeFromPiges($pige->panel);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('pige.update.geo_autolocate_failed', [
                    'pige_id' => $pige->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('admin.piges.show', $pige)
            ->with('success', 'Pige mise à jour avec succès.');
    }

    /**
     * Notifie le client par email qu'une pige a été validée.
     * - Génère un PublicLink sécurisé (30 jours, hex 256 bits).
     * - Envoi via NotificationMailer (try/catch — ne casse jamais le flux).
     */
    protected function notifyClientPigeValidated(\App\Models\Pige $pige): void
    {
        $client = $pige->campaign?->client;
        if (!$client?->email) {
            \Illuminate\Support\Facades\Log::info('pige.notify.skipped', [
                'pige_id' => $pige->id, 'reason' => 'no_client_email',
            ]);
            return;
        }

        try {
            $link = \App\Services\PublicLinkService::create(
                target:   $pige,
                type:     'pige',
                expiresAt: now()->addDays(30),
                clientId: $client->id,
                metadata: ['campaign_id' => $pige->campaign_id, 'panel_id' => $pige->panel_id],
            );

            $mailer = app(\App\Services\NotificationMailer::class);
            $mailer->sendSilently(
                $client->email,
                new \App\Mail\PigeValidatedMail($pige->loadMissing('panel.commune','campaign.client'), $link),
                cc: null,
                context: ['pige_id' => $pige->id, 'client_id' => $client->id],
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('pige.notify.failed', [
                'pige_id' => $pige->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // SHOW — détail d'une pige
    // ══════════════════════════════════════════════════════════════
    public function show(Pige $pige)
    {
        $pige->load([
            'panel.commune', 'panel.format',
            'campaign.client',
            'technicien',
            'verificateur',
        ]);

        return view('admin.piges.show', compact('pige'));
    }

    /**
     * SM2b Phase 5 — Détail JSON d'une pige pour la modale validation A4.
     * Consommé par features/admin/pige-validate.js. Renvoie tout ce qu'il
     * faut pour rendre la modale : photo URL, photo référence panneau,
     * GPS pige + GPS panneau + distance haversine, tech, campagne.
     */
    public function detailJson(Pige $pige)
    {
        $pige->load([
            'panel.commune', 'panel.format',
            'panel.photos',
            'campaign.client',
            'technicien:id,name,whatsapp_number',
            'verificateur:id,name',
        ]);

        $photoUrl = $pige->photo_path ? asset('storage/' . $pige->photo_path) : null;
        $refPhoto = $pige->panel?->photos?->sortBy('ordre')->first();
        $refPhotoUrl = $refPhoto ? asset('storage/' . $refPhoto->path) : null;

        // Distance haversine GPS pige ↔ GPS panneau (si les 2 dispos)
        $distM = null;
        if ($pige->gps_lat && $pige->gps_lng && $pige->panel?->latitude && $pige->panel?->longitude) {
            $R = 6371000;
            $lat1 = deg2rad((float) $pige->gps_lat);
            $lat2 = deg2rad((float) $pige->panel->latitude);
            $dLat = $lat2 - $lat1;
            $dLng = deg2rad(((float) $pige->panel->longitude) - ((float) $pige->gps_lng));
            $a = sin($dLat/2)**2 + cos($lat1)*cos($lat2)*sin($dLng/2)**2;
            $distM = (int) round($R * 2 * atan2(sqrt($a), sqrt(1-$a)));
        }

        return response()->json([
            'id'         => $pige->id,
            'status'     => $pige->status,
            'taken_at'   => $pige->taken_at?->toIso8601String(),
            'photo_url'  => $photoUrl,
            'ref_photo_url' => $refPhotoUrl,
            'gps' => [
                'lat'    => $pige->gps_lat ? (float) $pige->gps_lat : null,
                'lng'    => $pige->gps_lng ? (float) $pige->gps_lng : null,
                'dist_m' => $distM,
            ],
            'panel' => [
                'id'        => $pige->panel?->id,
                'reference' => $pige->panel?->reference,
                'name'      => $pige->panel?->name,
                'commune'   => $pige->panel?->commune?->name,
            ],
            'campaign' => [
                'id'     => $pige->campaign?->id,
                'name'   => $pige->campaign?->name,
                'client' => $pige->campaign?->client?->name,
            ],
            'tech' => [
                'id'    => $pige->technicien?->id,
                'name'  => $pige->technicien?->name,
                'phone' => $pige->technicien?->whatsapp_number,
            ],
            'rejection_reason' => $pige->rejection_reason,
            'verified_at'      => $pige->verified_at?->toIso8601String(),
            'verifier_name'    => $pige->verificateur?->name,
            'verify_url'       => route('admin.piges.verify', $pige),
            'reject_url'       => route('admin.piges.reject', $pige),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // VALIDATION — page dédiée "Piges à valider" avec navigation clavier
    //
    // Affiche les piges en attente sous forme de carrousel : 1 photo
    // plein écran + boutons V/R + navigation ←/→. Le MP/admin peut
    // valider 50 piges en quelques minutes au lieu d'ouvrir chaque
    // fiche individuellement.
    //
    // Raccourcis (gérés côté JS) :
    //   V          → Valider la pige courante
    //   R          → Rejeter (modal motif)
    //   ←          → Pige précédente
    //   →          → Pige suivante
    // ══════════════════════════════════════════════════════════════
    public function validation(Request $request)
    {
        $piges = Pige::with([
            'panel:id,reference,name,commune_id,latitude,longitude',
            'panel.commune:id,name',
            'campaign:id,name,status',
            'technicien:id,name,whatsapp_number',
            'poseTask:id,scheduled_at,done_at,status',
        ])
        ->where('status', 'en_attente')
        // Exclut les piges archivées (cycle de vie pige) — sinon une pige
        // avec status legacy en_attente + archived_at NOT NULL apparaît
        // dans la file de validation et peut crasher la vue si ses
        // relations ont été nettoyées entre temps.
        ->whereNull('archived_at')
        ->when($request->filled('campaign_id'),
            fn($q) => $q->where('campaign_id', $request->campaign_id))
        ->when($request->filled('technicien_id'),
            fn($q) => $q->where('user_id', $request->technicien_id))
        ->when($request->filled('geo_check'),
            fn($q) => $q->where('geo_check', $request->geo_check))
        // Pré-tri optionnel : remonter les piges douteuses (hors-zone puis
        // à vérifier) en tête pour que le MP s'y concentre. Par défaut, FIFO
        // sur taken_at — comportement inchangé.
        ->when($request->boolean('suspect_first'),
            fn($q) => $q->orderByRaw("CASE geo_check WHEN 'out' THEN 0 WHEN 'warn' THEN 1 ELSE 2 END"))
        ->orderBy('taken_at')   // FIFO : on valide d'abord les plus anciennes
        ->limit(100)             // borne perf : 100 piges/session max
        ->get();

        $campaigns = Campaign::whereIn('status', ['actif', 'planifie', 'pause'])
            ->orderBy('name')->get(['id', 'name']);
        $techniciens = User::where('role', 'technique')->orderBy('name')->get(['id', 'name']);

        // Compteur des piges douteuses (hors-zone + à vérifier) en attente,
        // pour le bandeau de pré-tri côté vue. Filtré aussi sur les
        // non-archivées (cohérence avec la requête principale).
        $suspectCount = Pige::where('status', 'en_attente')
            ->whereNull('archived_at')
            ->whereIn('geo_check', ['out', 'warn'])
            ->count();

        return view('admin.piges.validation', compact('piges', 'campaigns', 'techniciens', 'suspectCount'));
    }

    // ══════════════════════════════════════════════════════════════
    // VERIFY — vérifier avec réponse JSON et mise à jour carte
    // ══════════════════════════════════════════════════════════════
    public function verify(Request $request, Pige $pige)
    {
        $wasNotVerified = $pige->status !== 'verifie';
        $result = $this->pigeService->verify($pige, auth()->user());

        if (!$result['ok']) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $result['error']], 422);
            }
            return back()->with('error', $result['error']);
        }

        // Notification email client (si transition vers vérifiée)
        if ($wasNotVerified) {
            $this->notifyClientPigeValidated($pige->fresh()->loadMissing('campaign.client','panel.commune'));
        }

        if ($request->wantsJson()) {
            $pige->refresh();
            return response()->json([
                'success' => true, 
                'message' => 'Pige vérifiée avec succès.',
                'data' => [
                    'id' => $pige->id,
                    'status' => $pige->status,
                    'status_label' => $pige->getStatusConfig()['label'],
                    'status_color' => '#22c55e',
                    'status_bg' => 'rgba(34,197,94,.1)',
                    'status_bd' => 'rgba(34,197,94,.3)'
                ]
            ]);
        }

        // Alerte vérification pige (uniquement pour les requêtes non AJAX)
        if (!$request->wantsJson()) {
            AlertService::create(
                'pige',
                'success',
                '✅ Pige vérifiée — ' . ($pige->panel?->reference ?? ''),
                auth()->user()->name . ' a vérifié la pige du panneau ' . ($pige->panel?->reference ?? ''),
                $pige
            );
        }

        return back()->with('success', 'Pige marquée comme vérifiée.');
    }

    // ══════════════════════════════════════════════════════════════
    // DESTROY — supprimer avec réponse JSON
    // ══════════════════════════════════════════════════════════════
    public function destroy(Pige $pige)
    {
        $result = $this->pigeService->delete($pige);

        if (!$result['ok']) {
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $result['error']], 422);
            }
            return back()->with('error', $result['error']);
        }

        // Alerte suppression pige
        AlertService::create(
            'pige',
            'danger',
            '🗑 Pige supprimée — ' . ($pige->panel?->reference ?? ''),
            auth()->user()->name . ' a supprimé une pige du panneau ' . ($pige->panel?->reference ?? ''),
            null
        );

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Pige supprimée avec succès.']);
        }

        return back()->with('success', 'Pige supprimée.');
    }

    // ══════════════════════════════════════════════════════════════
    // REJECT — rejeter avec réponse JSON et mise à jour carte
    // ══════════════════════════════════════════════════════════════
    public function reject(Request $request, Pige $pige)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ], [
            'rejection_reason.required' => 'Le motif de rejet est obligatoire.',
            'rejection_reason.max'      => 'Le motif ne doit pas dépasser 500 caractères.',
        ]);

        $result = $this->pigeService->reject($pige, auth()->user(), $request->rejection_reason);

        if (!$result['ok']) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $result['error']], 422);
            }
            return back()->with('error', $result['error']);
        }

        // Alerte instantanée
        $this->alertService->notifyPigeRejected($pige->fresh()->load('panel'), $request->rejection_reason);

        if ($request->wantsJson()) {
            // Retourner les infos pour mise à jour
            $pige->refresh();
            return response()->json([
                'success' => true, 
                'message' => 'Pige rejetée.',
                'data' => [
                    'id' => $pige->id,
                    'status' => $pige->status,
                    'rejection_reason' => $pige->rejection_reason,
                    'status_label' => $pige->getStatusConfig()['label'],
                    'status_color' => '#ef4444',
                    'status_bg' => 'rgba(239,68,68,.1)',
                    'status_bd' => 'rgba(239,68,68,.3)'
                ]
            ]);
        }

        return back()->with('success', 'Pige rejetée — le technicien a été notifié.');
    }

    // ══════════════════════════════════════════════════════════════
    // VERIFY BATCH — vérifier plusieurs piges en 1 clic
    // POST /admin/piges/verify-batch
    // ══════════════════════════════════════════════════════════════
    public function verifyBatch(Request $request): JsonResponse
    {
        $request->validate(['pige_ids' => 'required|array|min:1']);

        $pigeIds   = array_filter((array) $request->pige_ids, fn($v) => is_numeric($v));
        $piges     = Pige::whereIn('id', $pigeIds)->where('status', 'en_attente')->get();
        $count     = 0;
        $geoPanelIds = [];

        foreach ($piges as $pige) {
            // autoLocate:false → on recalcule chaque panneau UNE fois après
            // la boucle (évite N recalculs si plusieurs piges d'un même
            // panneau sont validées dans le lot).
            $result = $this->pigeService->verify($pige, auth()->user(), autoLocate: false);
            if ($result['ok']) {
                $count++;
                if ($pige->gps_lat !== null && $pige->gps_lng !== null) {
                    $geoPanelIds[] = $pige->panel_id;
                }
                $this->notifyClientPigeValidated($pige->fresh()->loadMissing('campaign.client','panel.commune'));
            }
        }

        // Auto-géolocalisation groupée (best-effort, 1 recalcul par panneau).
        $this->pigeService->recomputePanelsGeo($geoPanelIds);

        // ── Alerte d'audit pour l'action bulk ───────────────────────
        // Équivalent de l'alerte créée individuellement dans verify()
        // (cf. ligne ~500). Sans cette consolidation, le bulk n'apparait
        // pas dans le journal d'audit pige côté admin.
        if ($count > 0) {
            \App\Services\AlertService::create(
                'pige',
                'info',
                '✅ Validation groupée — ' . $count . ' pige(s)',
                auth()->user()?->name . ' a validé ' . $count . ' pige(s) photo en lot'
                    . (count($pigeIds) > $count ? ' · ' . (count($pigeIds) - $count) . ' ignorée(s) (déjà traitées)' : ''),
                null
            );
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} pige(s) vérifiée(s) avec succès.",
            'count'   => $count,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // AJAX — panneaux d'une campagne (pour le formulaire upload)
    // GET /admin/piges/campaign-panels?campaign_id=X
    // ══════════════════════════════════════════════════════════════
    public function campaignPanels(Request $request): JsonResponse
    {
        $request->validate(['campaign_id' => 'required|integer|exists:campaigns,id']);
 
        $campaign = Campaign::with([
            'panels:id,reference,name,commune_id',
            'panels.commune:id,name',
        ])->findOrFail($request->campaign_id);
 
        // Stats piges par panneau (1 requête)
        $pigesStats = Pige::where('campaign_id', $campaign->id)
            ->selectRaw('panel_id,
                COUNT(*) as total,
                SUM(status="verifie") as verifie,
                SUM(status="rejete")  as rejete,
                SUM(status="en_attente") as en_attente
            ')
            ->groupBy('panel_id')
            ->get()
            ->keyBy('panel_id');
 
        // Statuts pose par panneau (1 requête)
        $poseTasks = \App\Models\PoseTask::where('campaign_id', $campaign->id)
            ->whereIn('status', ['realisee', 'en_cours', 'planifiee'])
            ->latest()
            ->get(['panel_id', 'status', 'done_at'])
            ->keyBy('panel_id');
 
        $panels = $campaign->panels->map(fn($p) => [
            'id'            => $p->id,
            'reference'     => $p->reference,
            'name'          => $p->name,
            'commune'       => $p->commune?->name ?? '—',
            // Stats pige
            'pige_total'    => (int) ($pigesStats[$p->id]->total      ?? 0),
            'pige_verifie'  => (int) ($pigesStats[$p->id]->verifie    ?? 0),
            'pige_rejete'   => (int) ($pigesStats[$p->id]->rejete     ?? 0),
            'pige_attente'  => (int) ($pigesStats[$p->id]->en_attente ?? 0),
            // Statut pose (pour afficher "Posé" dans la liste)
            'pose_status'   => $poseTasks[$p->id]?->status ?? null,
            'pose_date'     => $poseTasks[$p->id]?->done_at?->format('d/m/Y'),
        ]);
 
        return response()->json([
            'campaign' => ['id' => $campaign->id, 'name' => $campaign->name],
            'panels'   => $panels->values(),
        ]);
    }
}