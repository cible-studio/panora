<?php
// ══════════════════════════════════════════════════════════════
// app/Http/Controllers/Client/ClientDashboardController.php
// VERSION COMPLÈTE ET CORRIGÉE
// ══════════════════════════════════════════════════════════════

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\ClientContactReceivedMail;
use App\Models\Campaign;
use App\Models\ClientMessage;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Services\AlertService;
use App\Services\PropositionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientDashboardController extends Controller
{
    public function __construct(
        protected PropositionService $propositionService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // DASHBOARD PRINCIPAL
    // ══════════════════════════════════════════════════════════════
       public function index()
    {
        $client = Auth::guard('client')->user();
        
        // Récupérer les IDs des campagnes une seule fois
        $campaignIds = $client->campaigns()->pluck('id');
        
        // Propositions (limitées)
        $propositions = $client->reservations()
            ->where('status', 'en_attente')
            ->whereNotNull('proposition_token')
            ->where('end_date', '>=', now())
            ->with(['panels' => fn($q) => $q->limit(10)])
            ->orderByDesc('proposition_sent_at')
            ->limit(5)
            ->get();
        
        // Campagnes actives
        $campagnesActives = $client->campaigns()
            ->where('status', 'actif')
            ->withCount(['panels', 'externalPanels'])
            ->orderByDesc('start_date')
            ->limit(5)
            ->get();
        
        // Stats poses (une seule requête)
        $poseStats = PoseTask::whereIn('campaign_id', $campaignIds)
            ->selectRaw("
                COUNT(CASE WHEN status = 'realisee' THEN 1 END) as realisees,
                COUNT(CASE WHEN status = 'planifiee' THEN 1 END) as planifiees
            ")->first();
        
        // Stats piges vérifiées
        $pigeStats = Pige::whereIn('campaign_id', $campaignIds)
            ->where('status', 'verifie')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(DISTINCT panel_id) as panneaux_couverts
            ")->first();
        
        // Poses récentes
        $recentPoses = PoseTask::whereIn('campaign_id', $campaignIds)
            ->where('status', 'realisee')
            ->with(['panel:id,reference,name', 'campaign:id,name'])
            ->orderByDesc('done_at')
            ->limit(5)
            ->get();
        
        // Piges récentes
        $recentPiges = Pige::whereIn('campaign_id', $campaignIds)
            ->where('status', 'verifie')
            ->with(['panel:id,reference,name', 'campaign:id,name'])
            ->orderByDesc('verified_at')
            ->limit(5)
            ->get();
        
        // Panneaux actifs (une seule requête, internes + externes)
        $activePanelsCount = $client->campaigns()
            ->where('status', 'actif')
            ->withCount(['panels', 'externalPanels'])
            ->get()
            ->sum(fn($c) => (int) $c->panels_count + (int) $c->external_panels_count);
        
        $stats = [
            'propositions_en_attente' => $client->reservations()
                ->where('status', 'en_attente')
                ->whereNotNull('proposition_token')
                ->where('end_date', '>=', now())
                ->count(),
            'campagnes_actives'  => $campagnesActives->count(),
            'campagnes_total'    => $client->campaigns()->count(),
            'panneaux_actifs'    => $activePanelsCount,
            'poses_realisees'    => (int) ($poseStats->realisees ?? 0),
            'poses_planifiees'   => (int) ($poseStats->planifiees ?? 0),
            'piges_verifiees'    => (int) ($pigeStats->total ?? 0),
            'panneaux_couverts'  => (int) ($pigeStats->panneaux_couverts ?? 0),
        ];

        // Commercial principal affiché au client : on prend en priorité
        // le commercial assigné explicitement à la dernière réservation
        // (resolveCommercialContact = commercial_user_id sinon créateur).
        // Évite d'afficher le Media Planner comme interlocuteur du client.
        $latestRes = $client->reservations()
            ->with(['user:id,name,email,whatsapp_number,role,agent_code',
                    'commercial:id,name,email,whatsapp_number,role,agent_code'])
            ->latest()
            ->first();
        $commercial = $latestRes?->resolveCommercialContact();

        // Fallback : si pas de réservation (ex: campagnes directes uniquement),
        // chercher sur la dernière campagne. PRIORITÉ au commercial assigné
        // (commercial_user_id) AVANT le créateur (user_id) — sinon le client
        // voit l'admin/MP qui a saisi la campagne au lieu de son commercial.
        if (!$commercial) {
            $lastCamp = $client->campaigns()
                ->with([
                    'commercial:id,name,email,whatsapp_number,role,agent_code',
                    'user:id,name,email,whatsapp_number,role,agent_code',
                ])
                ->latest()
                ->first();
            $commercial = $lastCamp?->commercial ?? $lastCamp?->user;
        }

        return view('client.dashboard', compact(
            'client', 'propositions', 'campagnesActives', 'stats',
            'recentPoses', 'recentPiges', 'commercial'
        ));
    }


    // ══════════════════════════════════════════════════════════════
    // CAMPAGNES — liste filtrée
    // ══════════════════════════════════════════════════════════════
    public function campagnes(Request $request)
    {
        $client = Auth::guard('client')->user();

        $query = $client->campaigns()
            ->withCount(['panels', 'externalPanels'])
            ->with('satisfactionSurvey');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $campagnes = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        // Marquage « vues » de toutes les campagnes non encore consultées
        // par ce client → décrémente le badge « N nouvelles » du menu.
        // Pattern Gmail : visiter la liste = tout marqué comme vu.
        // On filtre seulement les NULL pour ne pas écraser un timestamp
        // existant (la date de première visite reste un témoin).
        $client->campaigns()
            ->whereNull('client_first_viewed_at')
            ->update(['client_first_viewed_at' => now()]);

        if ($request->ajax()) {
            return response()->json([
                'html'  => view('client.partials.campagnes-rows', compact('campagnes'))->render(),
                'total' => $campagnes->total(),
                'pages' => $campagnes->lastPage(),
            ]);
        }

        return view('client.campagnes', compact('client', 'campagnes'));
    }

    // ══════════════════════════════════════════════════════════════
    // PROPOSITIONS — liste
    // ══════════════════════════════════════════════════════════════
    public function propositions()
    {
        $client = Auth::guard('client')->user();

        $propositions = $client->reservations()
            ->whereNotNull('proposition_token')
            ->with(['panels.photos', 'panels.commune', 'panels.format', 'externalPanels'])
            ->orderByDesc('proposition_sent_at')
            ->paginate(10);

        return view('client.propositions', compact('client', 'propositions'));
    }

    // ══════════════════════════════════════════════════════════════
    // PROPOSITION DETAIL
    // ══════════════════════════════════════════════════════════════
    public function propositionDetail(string $token)
    {
        $client = Auth::guard('client')->user();

        $reservation = $client->reservations()
            ->where('proposition_token', $token)
            ->first();

        if (!$reservation) abort(403, 'Cette proposition ne vous appartient pas.');

        try {
            $reservation = $this->propositionService->validerToken($token);
        } catch (\RuntimeException $e) {
            // Proposition expirée/traitée — afficher quand même
        }

        $reservation->load([
            'panels.photos', 'panels.commune', 'panels.format', 'panels.zone', 'panels.category',
            'externalPanels.commune', 'externalPanels.zone', 'externalPanels.format', 'externalPanels.category',
            'user:id,name,email,role,whatsapp_number',
        ]);
        $this->propositionService->marquerVue($reservation);

        $months = $this->monthsBetween($reservation->start_date, $reservation->end_date);
        $panels = $reservation->proposalPanels($months);

        $joursRestants = now()->startOfDay()->diffInDays($reservation->end_date->startOfDay(), false);

        // Durée réelle de la campagne, pour affichage cohérent partout :
        //   - $days        : nombre de jours
        //   - $monthsLabel : libellé court "0,5" ou "1" ou "2,5"
        $days = (int) abs($reservation->start_date->copy()->startOfDay()
            ->diffInDays($reservation->end_date->copy()->startOfDay()));
        $monthsLabel = rtrim(rtrim(number_format($months, 1, ',', ''), '0'), ',');

        return view('client.proposition-detail', compact(
            'reservation', 'panels', 'months', 'days', 'monthsLabel', 'joursRestants', 'token', 'client'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // CAMPAGNE DETAIL — avec poses + piges
    // ══════════════════════════════════════════════════════════════
    public function campagneDetail(Campaign $campaign)
    {
        $client = Auth::guard('client')->user();

        if ($campaign->client_id !== $client->id) abort(403, 'Accès non autorisé.');

        // Marque cette campagne comme vue si c'est la première fois.
        // Cas d'accès direct (lien email/dashboard) sans passer par la liste.
        if ($campaign->client_first_viewed_at === null) {
            $campaign->forceFill(['client_first_viewed_at' => now()])->save();
        }

        $campaign->load([
            'panels.photos',
            'panels.commune:id,name',
            'panels.format:id,name,width,height',
            // Maintenance ouverte sur chaque panneau — sert au badge
            // "🔧 retour DD/MM" côté espace client. Même donnée que admin.
            'panels.activeMaintenance',
            'externalPanels.commune:id,name',
            'externalPanels.format:id,name,width,height',
            'externalPanels.agency:id,name',
            'invoices',
            'user:id,name,email,role,whatsapp_number',
            'satisfactionSurvey',
        ]);

        $panelIds        = $campaign->panels->pluck('id');
        $externalIdsCount = $campaign->externalPanels->count();

        // Poses réalisées par panneau interne — 1 requête, keyBy panel_id.
        // NOTE : la table pose_tasks ne référence actuellement que les internes
        // (colonne panel_id). Les panneaux externes ne sont pas posés via cette
        // table — un module dédié serait nécessaire pour étendre la pose à eux.
        $poses = PoseTask::where('campaign_id', $campaign->id)
            ->where('status', 'realisee')
            ->orderByDesc('done_at')
            ->get(['panel_id', 'status', 'done_at'])
            ->keyBy('panel_id');

        // Piges vérifiées groupées par panneau interne (même limitation)
        $pigesVerif = Pige::where('campaign_id', $campaign->id)
            ->where('status', 'verifie')
            ->orderByDesc('verified_at')
            ->get(['id', 'panel_id', 'photo_path', 'photo_thumb', 'verified_at', 'gps_lat', 'gps_lng'])
            ->groupBy('panel_id');

        // KPI couverture — total inclut internes + externes pour l'affichage,
        // mais le pourcentage se calcule sur les internes (ceux qui peuvent
        // effectivement être pigés via la table piges actuelle).
        $totalPanneaux        = $panelIds->count() + $externalIdsCount;
        $totalPanneauxPiges   = $panelIds->count(); // dénominateur réel pour la pige
        $posesCount           = $poses->count();
        $pigesCount           = $pigesVerif->count();
        $coveragePercent      = $totalPanneauxPiges > 0
            ? round(($pigesCount / $totalPanneauxPiges) * 100)
            : 0;

        return view('client.campagne-detail', compact(
            'client', 'campaign',
            'poses', 'pigesVerif',
            'totalPanneaux', 'posesCount', 'pigesCount', 'coveragePercent'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // POSES — suivi terrain (client voit uniquement réalisées)
    // ══════════════════════════════════════════════════════════════
    public function poses(Request $request)
    {
        $client      = Auth::guard('client')->user();
        $campaignIds = $client->campaigns()->pluck('id');

        $query = PoseTask::whereIn('campaign_id', $campaignIds)
            ->where('status', 'realisee')
            ->with([
                'panel:id,reference,name,commune_id',
                'panel.commune:id,name',
                'campaign:id,name',
            ]);

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', (int) $request->campaign_id);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->whereHas('panel', fn($p) =>
                $p->where('reference', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
            );
        }
        if ($request->filled('date_from')) {
            $query->whereDate('done_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('done_at', '<=', $request->date_to);
        }

        $poses = $query->orderByDesc('done_at')->paginate(20)->withQueryString();

        // KPI global (toutes campagnes, tous statuts — pour contexte)
        $kpi = PoseTask::whereIn('campaign_id', $campaignIds)
            ->selectRaw("
                SUM(CASE WHEN status = 'realisee' THEN 1 ELSE 0 END) as realisees,
                SUM(CASE WHEN status = 'planifiee' THEN 1 ELSE 0 END) as planifiees,
                SUM(CASE WHEN status = 'en_cours'  THEN 1 ELSE 0 END) as en_cours
            ")->first();

        $campaigns = $client->campaigns()
            ->orderByRaw("FIELD(status, 'actif', 'termine', 'annule')")
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return view('client.poses', compact('poses', 'kpi', 'campaigns', 'client'));
    }

    // ══════════════════════════════════════════════════════════════
    // PIGES — preuves d'affichage (client voit uniquement vérifiées)
    // ══════════════════════════════════════════════════════════════
    public function piges(Request $request)
    {
        $client      = Auth::guard('client')->user();
        $campaignIds = $client->campaigns()->pluck('id');

        $query = Pige::whereIn('campaign_id', $campaignIds)
            ->where('status', 'verifie')
            ->with([
                'panel:id,reference,name,commune_id',
                'panel.commune:id,name',
                'campaign:id,name',
            ]);

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', (int) $request->campaign_id);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->whereHas('panel', fn($p) =>
                $p->where('reference', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
            );
        }
        if ($request->filled('date_from')) {
            $query->whereDate('verified_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('verified_at', '<=', $request->date_to);
        }

        $piges = $query->orderByDesc('verified_at')->paginate(24)->withQueryString();

        $kpi = Pige::whereIn('campaign_id', $campaignIds)
            ->selectRaw("
                SUM(CASE WHEN status = 'verifie' THEN 1 ELSE 0 END) as verifiees,
                COUNT(DISTINCT CASE WHEN status = 'verifie' THEN panel_id END) as panneaux_piges,
                COUNT(DISTINCT CASE WHEN status = 'verifie' THEN campaign_id END) as campagnes_avec_pige
            ")->first();

        $campaigns = $client->campaigns()
            ->orderByRaw("FIELD(status, 'actif', 'termine', 'annule')")
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return view('client.piges', compact('piges', 'kpi', 'campaigns', 'client'));
    }

    // ══════════════════════════════════════════════════════════════
    // PIGE — téléchargement individuel
    // ══════════════════════════════════════════════════════════════
    public function pigeDownload(Pige $pige)
    {
        $client      = Auth::guard('client')->user();
        $campaignIds = $client->campaigns()->pluck('id');

        if (!$campaignIds->contains($pige->campaign_id)) abort(403);

        $path = Storage::disk('public')->path($pige->photo_path);
        if (!file_exists($path)) abort(404);

        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        return response()->download($path, "pige-{$pige->id}.{$ext}");
    }

    // ══════════════════════════════════════════════════════════════
    // PIGES — téléchargement ZIP campagne
    // ══════════════════════════════════════════════════════════════
    public function pigesZip(Campaign $campaign)
    {
        $client = Auth::guard('client')->user();
        if ($campaign->client_id !== $client->id) abort(403);

        $piges = Pige::where('campaign_id', $campaign->id)
            ->where('status', 'verifie')
            ->get();

        if ($piges->isEmpty()) abort(404, 'Aucune pige disponible.');

        $zip     = new \ZipArchive();
        $tmpPath = tempnam(sys_get_temp_dir(), 'piges_') . '.zip';
        $zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($piges as $pige) {
            $filePath = Storage::disk('public')->path($pige->photo_path);
            if (file_exists($filePath)) {
                $ext      = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
                $zip->addFile($filePath, "pige-{$pige->id}.{$ext}");
            }
        }
        $zip->close();

        $zipName = 'piges-' . Str::slug($campaign->name) . '.zip';
        return response()->download($tmpPath, $zipName)->deleteFileAfterSend(true);
    }

    // ══════════════════════════════════════════════════════════════
    // CONTACT — page et envoi
    // ══════════════════════════════════════════════════════════════
    public function contact()
    {
        $client = Auth::guard('client')->user();

        $interlocutors = $client->campaigns()
            ->whereIn('status', ['actif', 'planifie'])
            ->with('user:id,name,email,role,whatsapp_number')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();

        return view('client.contact', compact('client', 'interlocutors'));
    }

    public function sendContact(Request $request)
    {
        $client = Auth::guard('client')->user();

        $data = $request->validate([
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:3000',
        ]);

        // La table clients n'a pas de colonne `company` ; le nom de société
        // est porté par `name`. Le fallback historique `?? $client->name`
        // résolvait toujours à `name` — on simplifie.
        $fromName = $client->name;

        // 1. Persistance — trace en BD pour ne JAMAIS perdre un message
        //    même si l'email est filtré spam ou raté côté mailbox équipe.
        $cm = ClientMessage::create([
            'client_id'  => $client->id,
            'from_name'  => $fromName,
            'from_email' => $client->email,
            'subject'    => $data['subject'],
            'body'       => $data['message'],
            'status'     => 'new',
        ]);

        // 2. Email — best-effort, ne doit pas bloquer le retour utilisateur.
        //    Le Mailable utilise le layout PANORA + reply-to du client +
        //    CTA vers /admin/messages/{id} pour répondre depuis l'interface.
        try {
            Mail::to(config('mail.from.address'))
                ->send(new ClientContactReceivedMail($cm));
        } catch (\Throwable $e) {
            Log::warning('client.contact.mail_failed', [
                'message_id' => $cm->id, 'error' => $e->getMessage(),
            ]);
            // On NE rollback PAS la persistance — le message reste accessible
            // dans /admin/messages pour traitement manuel.
        }

        // 3. Alerte admin — cliquable grâce à l'auto-dérivation du lien
        //    (passe $cm comme related, le service mappera vers admin.messages.show).
        AlertService::notify(
            'client_message',
            '✉️ Nouveau message client — ' . $fromName,
            Str::limit($data['subject'], 120),
            $cm
        );

        // 4. Invalide le badge sidebar (cache 60s) pour que les admins voient
        //    immédiatement le compteur +1 lors de leur prochaine navigation.
        \Illuminate\Support\Facades\Cache::forget('admin.messages.new_count');

        return back()->with('contact_success',
            'Votre message a été transmis à notre équipe (référence #' . $cm->id . '). Une réponse vous parviendra à ' . $client->email . ' sous 24 heures ouvrées.'
        );
    }

    // ══════════════════════════════════════════════════════════════
    // PROFIL
    // ══════════════════════════════════════════════════════════════
    public function profil()
    {
        return view('client.profil', ['client' => Auth::guard('client')->user()]);
    }

    public function updateProfil(Request $request)
    {
        $client = Auth::guard('client')->user();
        $data   = $request->validate([
            'phone'        => 'nullable|string|max:20',
            'address'      => 'nullable|string|max:255',
            'city'         => 'nullable|string|max:100',
            'contact_name' => 'nullable|string|max:150',
        ]);
        $client->update($data);
        return back()->with('success', 'Profil mis à jour.');
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════
    /**
     * Règle facturation CIBLE CI — alignée sur PropositionController::monthsBetween
     * et ReservationService::monthsBetween :
     *   1-15 jours résiduels  → +0.5 mois
     *   16-30 jours résiduels → +1 mois
     *   minimum facturable    → 0.5 mois
     */
    private function monthsBetween($start, $end): float
    {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();

        $totalDays = (int) $s->diffInDays($e);
        if ($totalDays <= 0) return 0.5;

        $fullMonths = (int) floor($totalDays / 30);
        $remainDays = $totalDays % 30;

        $fraction = 0;
        if ($remainDays >= 1 && $remainDays <= 15) {
            $fraction = 0.5;
        } elseif ($remainDays > 15) {
            $fraction = 1;
        }

        return max($fullMonths + $fraction, 0.5);
    }

}