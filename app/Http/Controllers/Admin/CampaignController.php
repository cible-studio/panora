<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Services\CampaignService;
use App\Services\AvailabilityService;
use App\Services\AlertService;
use App\Services\CampaignAmountConsistency;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\ExternalPanel;
use App\Models\Invoice;
use App\Models\Panel;
use App\Models\PanelFormat;
use App\Models\Reservation;
use App\Models\Zone;

use App\Enums\CampaignStatus;
use App\Enums\PanelStatus;
use App\Exports\CampaignsExport;
use App\Support\PdfAssets;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class CampaignController extends Controller
{
    use PdfAssets;

    public function __construct(
        protected CampaignService     $campaignService,
        protected AvailabilityService $availability
    ) {}

    // ══════════════════════════════════════════════════════════════
    // INDEX
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);

        // Filtres "neutres" appliqués au périmètre + au calcul des compteurs.
        // Le filtre status est traité APRÈS le clone — sinon cliquer "Planifiées"
        // fait tomber les autres cartes (Actif/Pause/Terminées/Annulées) à 0.
        $query = Campaign::with([
                'client',
                'user',
                'invoices' => fn($q) => $q->select(['id','campaign_id','status','amount_ttc','paid_at','reference'])->latest(),
            ])
            ->withCount(['panels', 'externalPanels', 'invoices'])
            // RBAC : un commercial voit SES campagnes — couvre 4 cas :
            //  1) Campagne directement assignée à lui (campaign.commercial_user_id)
            //     — cas typique : admin/MP attribue une campagne au commercial
            //  2) Via la résa source : reservation.commercial_user_id == uid
            //  3) Résa sans commercial : reservation.user_id == uid (créateur)
            //  4) Campagne manuelle sans résa : campaign.user_id == uid
            ->when(auth()->user()?->role?->value === 'commercial', function ($q) {
                $uid = auth()->id();
                $q->where(function ($qq) use ($uid) {
                    $qq->where('commercial_user_id', $uid)
                       ->orWhereHas('reservation', fn($r) =>
                            $r->where('commercial_user_id', $uid)
                              ->orWhere(function ($rr) use ($uid) {
                                  $rr->whereNull('commercial_user_id')
                                     ->where('user_id', $uid);
                              })
                       )
                       ->orWhere(function ($qqq) use ($uid) {
                           $qqq->whereDoesntHave('reservation')
                               ->where('user_id', $uid);
                       });
                });
            })
            ->when($request->search,      fn($q, $s)  => $q->where('name', 'like', "%{$s}%"))
            ->when($request->client_id,   fn($q, $id) => $q->where('client_id', $id))
            // Filtre commercial assigné — admin/MP/comptable peuvent vouloir
            // voir les campagnes d'un commercial précis (suivi portefeuille).
            // Réutilise la même logique d'appartenance que le scope commercial
            // ci-dessus pour rester cohérent : assigné direct, via résa, ou
            // créateur de la campagne/résa sans commercial explicite.
            ->when($request->commercial_user_id, function ($q, $uid) {
                $uid = (int) $uid;
                $q->where(function ($qq) use ($uid) {
                    $qq->where('commercial_user_id', $uid)
                       ->orWhereHas('reservation', fn($r) =>
                            $r->where('commercial_user_id', $uid)
                              ->orWhere(function ($rr) use ($uid) {
                                  $rr->whereNull('commercial_user_id')
                                     ->where('user_id', $uid);
                              })
                       )
                       ->orWhere(function ($qqq) use ($uid) {
                           $qqq->whereDoesntHave('reservation')
                               ->where('user_id', $uid);
                       });
                });
            })
            // Filtres date originaux : date_from (start) / date_to (end)
            ->when($request->date_from,   fn($q, $d)  => $q->where('start_date', '>=', $d))
            ->when($request->date_to,     fn($q, $d)  => $q->where('end_date', '<=', $d))
            // T12 : Filtre période personnalisée (start_date BETWEEN date_debut AND date_fin)
            ->when($request->date_debut,  fn($q, $d)  => $q->where('start_date', '>=', $d))
            ->when($request->date_fin,    fn($q, $d)  => $q->where('start_date', '<=', $d))
            // 2026-07-15 (feedback patronne) : drill-down depuis les cards
            // KPI de perf commerciale — filtre par DATE DE CRÉATION plutôt
            // que par période de campagne. Sert au clic "Nouvelles
            // campagnes créées" de /admin/performance/commerciaux.
            ->when($request->created_from, fn($q, $d) => $q->whereDate('campaigns.created_at', '>=', $d))
            ->when($request->created_to,   fn($q, $d) => $q->whereDate('campaigns.created_at', '<=', $d))
            ->when($request->non_facturee, fn($q)     => $q->nonFacturees())
            ->when($request->commune_id,  fn($q, $id) => $q->whereHas('panels', fn($p) => $p->where('commune_id', $id)))
            ->when($request->zone_id,     fn($q, $id) => $q->whereHas('panels', fn($p) => $p->where('zone_id', $id)))
            ->orderByDesc('created_at');

        // ─── COMPTEURS KPI sur le périmètre AVANT filtre status ───
        // Permet à chaque carte de garder sa vraie valeur quand on en clique
        // une (sinon les autres tombent à 0).
        $countsRaw = (clone $query)
            ->setEagerLoads([])
            ->reorder()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'planifie' => (int) ($countsRaw['planifie'] ?? 0),
            'actif'    => (int) ($countsRaw['actif']    ?? 0),
            'pause'    => (int) ($countsRaw['pause']    ?? 0),
            'termine'  => (int) ($countsRaw['termine']  ?? 0),
            'annule'   => (int) ($countsRaw['annule']   ?? 0),
        ];

        // ─── Filtre status (carte cliquée OU select) appliqué APRÈS ───
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $campaigns = $query->paginate(20)->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'html'       => view('admin.campaigns.partials.table-rows', compact('campaigns'))->render(),
                'pagination' => $campaigns->links('pagination::bootstrap-4')->render(),
                'stats'      => [
                    'total'  => $campaigns->total(),
                    'counts' => $counts, // 5.x : pour rafraîchir les KPI cards en AJAX
                ],
            ]);
        }

        // Scope commercial : même règle que la liste principale, sinon
        // l'admin voyait "12 non facturées" mais le commercial voyait
        // toujours le compteur global de l'entreprise au lieu de SES
        // campagnes non facturées uniquement.
        $isCommercial = auth()->user()?->role?->value === 'commercial';
        $uid = (int) (auth()->id() ?? 0);
        $nonFactureesCount = Campaign::nonFacturees()
            ->when($isCommercial, fn($q) => $q->forCommercialUser($uid))
            ->count();

        // ── Compteur "ending soon" — UNIQUEMENT les campagnes qui ne
        // sont PAS déjà visibles dans la page courante ──────────────
        // Sans ce filtre, on affichait un bandeau redondant "1 campagne
        // se termine dans X jours" alors que cette même campagne avait
        // déjà son badge ⚠️ "Dans X jour(s)" sur sa carte juste en
        // dessous. Désormais on ne signale que les campagnes "à risque
        // d'être loupées" (cachées par filtres ou pagination).
        $endingSoonIds = Campaign::endingSoon(14)->pluck('id')->all();
        $visibleIds    = $campaigns->pluck('id')->all();
        $endingSoonCount = count(array_diff($endingSoonIds, $visibleIds));

        $clients  = Client::orderBy('name')->get(['id', 'name']);
        $communes = Commune::orderBy('name')->get(['id', 'name']);
        $zones    = Zone::orderBy('name')->get(['id', 'name']);

        // Liste des commerciaux assignables pour le filtre. On inclut
        // admin (qui peut être titulaire) + commercial. Le filtre n'est
        // utile que pour les rôles non-commerciaux (le commercial ne
        // voit déjà que ses propres campagnes via le scope plus haut).
        $commerciaux = \App\Models\User::query()
            ->whereIn('role', ['admin', 'commercial'])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view('admin.campaigns.index', compact(
            'campaigns', 'counts', 'nonFactureesCount', 'endingSoonCount',
            'clients', 'communes', 'zones', 'commerciaux'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // SHOW
    // ══════════════════════════════════════════════════════════════
    public function show(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        // Synchroniser le statut avec les dates (idempotent, gratuit si déjà à jour)
        $campaign->syncStatusWithDates();

        $campaign->load([
            'client', 'user', 'updatedBy',
            'reservation:id,reference,status,start_date,end_date',
            'panels.commune:id,name',
            'panels.format:id,name',
            // Maintenance ouverte la plus récente sur chaque panneau — sert
            // à afficher un badge "🔧 retour le DD/MM" dans la vue.
            'panels.activeMaintenance',
            'externalPanels.commune:id,name',
            'externalPanels.format:id,name',
            'externalPanels.agency:id,name',
            'invoices:id,campaign_id,reference,amount_ttc',
        ]);

        $user = auth()->user();
        // 'termine' inclus pour la correction de l'historique (campagnes
        // importées dont les dates sont passées). 'annule' reste exclu.
        $canManagePanel = $user->can('managePanel', $campaign)
            && in_array($campaign->status->value, ['planifie', 'actif', 'termine']);

        $can = [
            'update'       => $user->can('update', $campaign),
            'updateStatus' => $user->can('updateStatus', $campaign),
            'managePanel'  => $canManagePanel,
            'delete'       => $user->can('delete', $campaign),
        ];

        $allowed = $campaign->status->allowedTransitionsLabels();

        // Liste des commerciaux assignables — utilisée par le modal de
        // correction (nom + commercial) sur les campagnes terminées.
        $commerciaux = \App\Models\User::query()
            ->whereIn('role', ['admin', 'commercial'])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        // Panneaux disponibles : chargés en AJAX à l'ouverture du modal (cf. méthode availablePanels())
        // pour ne pas pénaliser le rendu initial de la page.

        // Cohérence facturation campagne vs résa (best-effort) — le bandeau
        // reste visible tant que l'écart existe, pas seulement au moment de
        // l'action. Sinon l'admin oublie au prochain chargement de la fiche.
        $amountConsistency = null;
        try {
            $amountConsistency = app(CampaignAmountConsistency::class)->check($campaign);
        } catch (\Throwable $e) {
            Log::warning('campaign.show.amount_check_failed', [
                'campaign_id' => $campaign->id,
                'error'       => $e->getMessage(),
            ]);
        }

        return view('admin.campaigns.show', compact('campaign', 'can', 'allowed', 'commerciaux', 'amountConsistency'));
    }

    /**
     * Active manuellement une campagne PLANIFIEE (ou redémarre depuis PAUSE).
     * Garde : au moins 1 panneau obligatoire. Mail au client envoyé
     * automatiquement à la première activation (PLANIFIE → ACTIF).
     */
    public function activate(Campaign $campaign)
    {
        $this->authorize('updateStatus', $campaign);

        $result = $this->campaignService->activate($campaign);

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        $msg = '✅ Campagne activée.';
        if (!empty($result['mail_sent'])) {
            $msg .= ' Email d\'annonce envoyé au client.';
        }
        return back()->with('success', $msg);
    }

    /**
     * Notifier manuellement le client des modifications apportées à la
     * campagne (panneaux ajoutés/retirés, prix négocié, etc.). Renvoie
     * le mail "récap" basé sur l'état actuel de la campagne.
     *
     * Action déclenchée par un bouton sur la fiche campagne — utile
     * lorsque le commercial veut formaliser des changements importants
     * envers le client après la première activation.
     */
    public function notifyClient(Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        if (!in_array($campaign->status->value, ['planifie', 'actif', 'pause'])) {
            return back()->with('error',
                'Le client ne peut être notifié que sur une campagne planifiée, active ou en pause.');
        }

        $panelsCount = $campaign->panels()->count() + $campaign->externalPanels()->count();
        if ($panelsCount === 0) {
            return back()->with('error',
                'Ajoutez au moins un panneau avant de notifier le client.');
        }

        if (!$campaign->client?->email && $campaign->client?->contacts()->whereNotNull('email')->doesntExist()) {
            return back()->with('error',
                'Aucune adresse email connue pour ce client (ni client.email ni interlocuteurs).');
        }

        try {
            $sent = $this->campaignService->sendStartedMailToClient($campaign->fresh());
        } catch (\Throwable $e) {
            Log::warning('campaign.notify_client.failed', [
                'campaign_id' => $campaign->id,
                'error'       => $e->getMessage(),
            ]);
            return back()->with('error', 'Échec de l\'envoi du mail au client — vérifiez les logs.');
        }

        if (!$sent) {
            return back()->with('error', 'Le mail n\'a pas pu être envoyé (vérifiez les logs).');
        }

        Log::info('campaign.notify_client', [
            'campaign_id' => $campaign->id,
            'user_id'     => auth()->id(),
        ]);

        return back()->with('success', '✅ Récap envoyé au client (panneaux et montants actuels).');
    }

    /**
     * Page dédiée aux poses OOH d'UNE campagne — vue simplifiée sans
     * KPI/filtres globaux. Permet de gérer le terrain d'une campagne
     * spécifique sans le bruit de la page Gestion Pose OOH globale.
     */
    public function poses(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $poseTasks = \App\Models\PoseTask::where('campaign_id', $campaign->id)
            ->with([
                'panel:id,reference,name,commune_id',
                'panel.commune:id,name',
                'campaign' => fn($q) => $q->withTrashed()->select('id', 'name', 'status', 'deleted_at'),
                'technicien:id,name,whatsapp_number',
            ])
            ->withCount([
                'piges as pige_count',
                'piges as pige_verifie_count' => fn($q) => $q->where('status', 'verifie'),
            ])
            ->leftJoin('panels', 'panels.id', '=', 'pose_tasks.panel_id')
            ->select('pose_tasks.*')
            ->orderBy('panels.reference')
            ->orderByDesc('pose_tasks.scheduled_at')
            ->paginate(50)->withQueryString();

        // Compteurs spécifiques à cette campagne uniquement
        $stats = [
            'total'     => \App\Models\PoseTask::where('campaign_id', $campaign->id)->count(),
            'planifiee' => \App\Models\PoseTask::where('campaign_id', $campaign->id)->where('status', 'planifiee')->count(),
            'en_cours'  => \App\Models\PoseTask::where('campaign_id', $campaign->id)->where('status', 'en_cours')->count(),
            'realisee'  => \App\Models\PoseTask::where('campaign_id', $campaign->id)->where('status', 'realisee')->count(),
            'annulee'   => \App\Models\PoseTask::where('campaign_id', $campaign->id)->where('status', 'annulee')->count(),
        ];

        return view('admin.campaigns.poses', compact('campaign', 'poseTasks', 'stats'));
    }

    /**
     * Endpoint JSON léger pour rafraîchir la progression sans recharger la page.
     * Appelé par le JS toutes les 60 secondes sur la page show.
     */
    public function progress(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        // Sync silencieuse — si end_date est passée, le statut bascule en TERMINE
        $changed = $campaign->syncStatusWithDates();

        return response()->json([
            'pct'         => $campaign->progressPercent(),
            'days_left'   => $campaign->daysRemaining(),
            'human_time'  => $campaign->humanTimeRemaining(),
            'status'      => $campaign->status->value,
            'status_label'=> $campaign->status->label(),
            'ending_soon' => $campaign->isEndingSoon(),
            'is_running'  => $campaign->status === CampaignStatus::ACTIF,
            'reload'      => $changed, // Frontend recharge la page si statut a changé
            'server_time' => now()->toIso8601String(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // FACTURATION RAPIDE (inline depuis la liste)
    // ══════════════════════════════════════════════════════════════
    public function billingQuick(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $data = $request->validate([
            'status'     => 'required|in:brouillon,envoyee,payee,annulee',
            'paid_at'    => 'nullable|date',
            'amount_ttc' => 'nullable|numeric|min:0',
        ]);

        $invoice = $campaign->invoices()->latest()->first();
        $previousStatus = $invoice?->status;
        $isCreation = !$invoice;

        if ($isCreation) {
            $year = (int) date('Y');
            $seq  = Invoice::whereYear('created_at', $year)->count() + 1;
            $ref  = 'FAC-' . $year . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);

            $amount = isset($data['amount_ttc']) ? (float) $data['amount_ttc'] : (float) $campaign->total_amount;

            $invoice = Invoice::create([
                'reference'   => $ref,
                'client_id'   => $campaign->client_id,
                'campaign_id' => $campaign->id,
                'created_by'  => auth()->id(),
                'amount'      => $amount,
                'tva'         => 0,
                'amount_ttc'  => $amount,
                'issued_at'   => today(),
                'status'      => $data['status'],
                'paid_at'     => $data['status'] === 'payee'
                                    ? ($data['paid_at'] ?? today()->toDateString())
                                    : null,
            ]);
        } else {
            $update = ['status' => $data['status']];
            $update['paid_at'] = $data['status'] === 'payee'
                ? ($data['paid_at'] ?? today()->toDateString())
                : null;
            if (isset($data['amount_ttc'])) {
                $update['amount']     = (float) $data['amount_ttc'];
                $update['amount_ttc'] = (float) $data['amount_ttc'];
            }
            $invoice->update($update);
        }

        // ── Alerte in-app + message UI ──────────────────────────────
        // Crée une alerte (bell icon) seulement si le statut a changé,
        // sinon on évite le bruit pour les ajustements de montant.
        $statusChanged = $isCreation || $previousStatus !== $invoice->status;
        $clientName    = $campaign->client?->name ?? 'Client';

        if ($statusChanged) {
            $alertCode = $isCreation
                ? 'facture_creee'
                : match ($invoice->status) {
                    'envoyee' => 'facture_envoyee',
                    'payee'   => 'facture_payee',
                    'annulee' => 'facture_annulee',
                    default   => 'facture_creee', // retour brouillon
                };

            \App\Services\AlertService::notify(
                $alertCode,
                sprintf('Facture %s — %s', $invoice->reference, $clientName),
                sprintf(
                    'Facture %s pour la campagne « %s » : statut %s · montant %s FCFA.',
                    $invoice->reference,
                    $campaign->name,
                    $invoice->status,
                    number_format((float) $invoice->amount_ttc, 0, ',', ' ')
                ),
                $invoice
            );
        }

        $cfg = [
            'brouillon' => ['icon' => '📝', 'label' => 'Brouillon', 'color' => '#6b7280'],
            'envoyee'   => ['icon' => '📤', 'label' => 'Envoyée',   'color' => '#3b82f6'],
            'payee'     => ['icon' => '✅', 'label' => 'Payée',     'color' => '#22c55e'],
            'annulee'   => ['icon' => '🚫', 'label' => 'Annulée',   'color' => '#ef4444'],
        ][$invoice->status] ?? ['icon' => '', 'label' => $invoice->status, 'color' => ''];

        // Message contextualisé pour le toast côté UI
        $message = match (true) {
            $isCreation                                  => "Facture {$invoice->reference} créée ({$cfg['label']}).",
            $statusChanged && $invoice->status === 'payee'   => "Facture {$invoice->reference} marquée comme payée. ✅",
            $statusChanged && $invoice->status === 'envoyee' => "Facture {$invoice->reference} envoyée. 📤",
            $statusChanged && $invoice->status === 'annulee' => "Facture {$invoice->reference} annulée.",
            $statusChanged                                   => "Statut de la facture {$invoice->reference} mis à jour.",
            default                                          => "Facture {$invoice->reference} mise à jour.",
        };

        // Re-render la ligne campagne avec les mêmes eager-loads que index()
        // pour que le frontend puisse remplacer la <tr> en place — pas
        // besoin de fetchData() qui rejoue tout le listing avec un spinner.
        $campaign->load([
            'client', 'user',
            'invoices' => fn($q) => $q->select(['id','campaign_id','status','amount_ttc','paid_at','reference'])->latest(),
        ]);
        $campaign->loadCount(['panels', 'externalPanels', 'invoices']);
        $rowHtml = view('admin.campaigns.partials.row', ['campaign' => $campaign])->render();

        return response()->json([
            'ok'              => true,
            'status'          => $invoice->status,
            'previous_status' => $previousStatus,
            'status_changed'  => $statusChanged,
            'is_creation'     => $isCreation,
            'message'         => $message,
            'label'           => $cfg['label'],
            'icon'            => $cfg['icon'],
            'color'           => $cfg['color'],
            'paid_at'         => $invoice->paid_at?->format('d/m/Y'),
            'amount'          => number_format((float) $invoice->amount_ttc, 0, ',', ' '),
            'ref'             => $invoice->reference,
            'invoice_id'      => $invoice->id,
            'invoice_url'     => route('admin.invoices.show', $invoice),
            'count'           => $campaign->invoices()->count(),
            'row_html'        => $rowHtml,
            'campaign_id'     => $campaign->id,
        ]);
    }

    /**
     * Endpoint JSON pour charger les panneaux candidats à l'ajout à une campagne.
     *
     * Stratégie « tout le parc visible » (alignée sur ReservationController) :
     *   - on liste TOUS les panneaux du parc actif (internes + externes des
     *     régies partenaires actives), excluant ceux déjà attachés à la
     *     campagne ;
     *   - chaque panneau est annoté avec `available` / `release_date` /
     *     `blocking_status` / `occupations` ;
     *   - la sélection se fait côté UI uniquement sur ceux `available=true`.
     *
     * Avantage : l'utilisateur voit le parc en entier et comprend pourquoi
     * tel panneau est indisponible, plutôt qu'un sec « 0 panneau libre »
     * sans contexte.
     */
    public function availablePanels(Campaign $campaign)
    {
        $this->authorize('managePanel', $campaign);

        $startDate = $campaign->start_date->format('Y-m-d');
        $endDate   = $campaign->end_date->format('Y-m-d');

        // Si la campagne n'est plus modifiable (annulée), on coupe tout de
        // suite : pas la peine de calculer la dispo, l'UI affichera un
        // message dédié via reason. 'termine' est autorisé pour la
        // correction de l'historique (anciennes campagnes importées).
        if (!in_array($campaign->status->value, ['planifie', 'actif', 'pause', 'termine'])) {
            return response()->json([
                'panels'          => [],
                'reason'          => 'campaign_status_not_modifiable',
                'campaign_status' => $campaign->status->value,
                'period'          => ['start' => $startDate, 'end' => $endDate],
                'totals'          => ['internal' => 0, 'external' => 0],
                'counts'          => ['internal_available' => 0, 'external_available' => 0],
                'campaign_months' => $campaign->billableMonths(),
            ]);
        }

        // ─── PANNEAUX INTERNES ──────────────────────────────────────
        $existingIds = $campaign->panels()->pluck('panels.id')->all();

        $internalPanels = Panel::with(['commune:id,name', 'format:id,name,width,height', 'zone:id,name'])
            ->whereNull('deleted_at')
            ->where('status', '!=', PanelStatus::MAINTENANCE->value)
            ->whereNotIn('id', $existingIds)
            ->orderBy('reference')
            ->get();

        $internalAvail = $this->availability->getPanelAvailabilityData(
            $internalPanels->pluck('id')->all(),
            $startDate,
            $endDate,
            $campaign->reservation_id,
            true,                  // includeCampaignBlockings : critique pour
                                   // ne pas afficher comme « libre » un panneau
                                   // engagé dans une autre campagne directe.
            $campaign->id          // excludeCampaignId : la campagne courante
                                   // ne doit pas se bloquer elle-même (de
                                   // toute façon ses panneaux sont déjà
                                   // exclus par $existingIds, mais on est
                                   // explicite et défensif).
        );

        $internal = $internalPanels->map(function ($p) use ($internalAvail) {
            $a = $internalAvail->get($p->id, ['available' => true, 'release_date' => null, 'blocking_status' => null, 'occupations' => []]);
            return [
                'id'              => $p->id,
                'source'          => 'internal',
                'reference'       => $p->reference,
                'name'            => $p->name,
                'commune'         => $p->commune?->name ?? '',
                'format'          => $p->format?->name ?? '',
                'monthly_rate'    => (float) ($p->monthly_rate ?? 0),
                'is_lit'          => (bool) $p->is_lit,
                'agency_name'     => null,
                'available'       => (bool) $a['available'],
                'release_date'    => self::formatReleaseLabel($a['release_date'] ?? null),
                'blocking_status' => $a['blocking_status'] ?? null,
                'occupations'     => $a['occupations'] ?? [],
            ];
        })->values();

        // ─── PANNEAUX EXTERNES (régies partenaires) ─────────────────
        $existingExtIds = $campaign->externalPanels()->pluck('external_panels.id')->all();

        $externalPanels = ExternalPanel::with([
                'commune:id,name',
                'format:id,name,width,height',
                'agency:id,name',
            ])
            ->whereHas('agency', fn($q) => $q->where('is_active', true)->whereNull('deleted_at'))
            ->where(fn($q) => $q->whereNull('availability_status')->orWhere('availability_status', '!=', 'maintenance'))
            ->whereNotIn('id', $existingExtIds)
            ->orderBy('code_panneau')
            ->get();

        $extBookings = $this->availability->getExternalPanelBookingMap(
            $externalPanels->pluck('id')->all(),
            $startDate,
            $endDate,
            $campaign->reservation_id
        );

        $external = $externalPanels->map(function ($p) use ($extBookings) {
            $b = $extBookings->get($p->id);
            $hasConfirmed = (bool) ($b->has_confirmed ?? false);
            $hasOption    = (bool) ($b->has_option    ?? false);
            $blocking     = $hasConfirmed ? 'confirme' : ($hasOption ? 'en_attente' : null);
            $releaseRaw   = $b->release_date ?? null;

            return [
                'id'              => 'ext_' . $p->id,
                'source'          => 'external',
                'reference'       => $p->code_panneau,
                'name'            => $p->designation,
                'commune'         => $p->commune?->name ?? '',
                'format'          => $p->format?->name ?? '',
                'monthly_rate'    => (float) ($p->monthly_rate ?? 0),
                'is_lit'          => (bool) ($p->is_lit ?? false),
                'agency_name'     => $p->agency?->name,
                'available'       => !$hasConfirmed,         // option = bookable (sera tranché lors de la confirmation), confirme = bloqué.
                'release_date'    => self::formatReleaseLabel($releaseRaw),
                'blocking_status' => $blocking,
                'occupations'     => [],
            ];
        })->values();

        $internalAvailableCount = $internal->where('available', true)->count();
        $externalAvailableCount = $external->where('available', true)->count();

        Log::info('campaign.available_panels.fetched', [
            'campaign_id'         => $campaign->id,
            'period'              => "{$startDate} → {$endDate}",
            'reservation_id'      => $campaign->reservation_id,
            'internal_total'      => $internal->count(),
            'internal_available'  => $internalAvailableCount,
            'internal_attached'   => count($existingIds),
            'external_total'      => $external->count(),
            'external_available'  => $externalAvailableCount,
            'external_attached'   => count($existingExtIds),
        ]);

        return response()->json([
            'panels'          => $internal->concat($external)->values(),
            'period'          => ['start' => $startDate, 'end' => $endDate],
            'totals'          => [
                'internal' => $internal->count(),
                'external' => $external->count(),
            ],
            'counts'          => [
                'internal_available' => $internalAvailableCount,
                'external_available' => $externalAvailableCount,
            ],
            'campaign_months' => $campaign->billableMonths(),
        ]);
    }

    /**
     * Formate une date de libération en libellé humain (« Libre maintenant »
     * / « Libre demain » / « Libre le DD/MM/YYYY (Nj) »). Cohérent avec la
     * vue Reservation pour ne pas multiplier les formats côté front.
     */
    private static function formatReleaseLabel($raw): ?string
    {
        if (!$raw) return null;
        $rd = \Carbon\Carbon::parse($raw);
        $daysLeft = (int) now()->startOfDay()->diffInDays($rd->startOfDay(), false);
        if ($daysLeft <= 0)  return 'Libre maintenant';
        if ($daysLeft === 1) return 'Libre demain';
        return 'Libre le ' . $rd->format('d/m/Y') . " ({$daysLeft}j)";
    }

    // ══════════════════════════════════════════════════════════════
    // CREATE / STORE
    // ══════════════════════════════════════════════════════════════
    public function create(Request $request)
    {
        $this->authorize('create', Campaign::class);

        $clients      = Client::orderBy('name')->get();
        $reservations = Reservation::with(['client', 'panels', 'externalPanels'])
            ->where('status', 'confirme')
            ->whereDoesntHave('campaign')
            ->get();

        // Commerciaux disponibles pour assignation (rôles commercial + admin + MP).
        // L'admin peut assigner à n'importe quel commercial/MP.
        $commerciaux = \App\Models\User::query()
            ->whereIn('role', ['admin', 'commercial'])
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'email']);

        $preselectedReservation = null;
        if ($request->filled('reservation_id')) {
            $preselectedReservation = Reservation::with(['client', 'panels', 'externalPanels'])
                ->where('status', 'confirme')
                ->whereDoesntHave('campaign')
                ->find($request->reservation_id);
        }

        // Préselection depuis une campagne existante (action "Renouveler /
        // Dupliquer") : on passe les valeurs au formulaire via old() en
        // les flashant dans la session une seule fois.
        $duplicateFrom = null;
        if ($request->filled('from')) {
            $duplicateFrom = Campaign::with(['panels:id', 'externalPanels:id'])
                ->find((int) $request->from);
            if ($duplicateFrom) {
                $request->session()->flashInput([
                    'name'               => $duplicateFrom->name . ' (renouvelée)',
                    'client_id'          => $duplicateFrom->client_id,
                    'commercial_user_id' => $duplicateFrom->commercial_user_id,
                    'notes'              => $duplicateFrom->notes,
                ]);
            }
        }

        return view('admin.campaigns.create',
            compact('clients', 'reservations', 'preselectedReservation', 'commerciaux', 'duplicateFrom'));
    }

    /**
     * Dupliquer une campagne existante : crée une nouvelle campagne en
     * PLANIFIE avec les mêmes panneaux, prix négociés et commercial.
     *
     * Dates :
     *   - Si l'utilisateur saisit start_date + end_date dans le modal,
     *     on les utilise telles quelles.
     *   - Sinon (cas "renouvellement express"), défaut = ancienne fin + 1 j
     *     pendant la même durée.
     *
     * IMPORTANT : le paramètre s'appelle $campaign (pas $source) — la
     * route déclare {campaign}, donc Laravel a besoin du même nom de
     * paramètre dans la méthode pour résoudre le route-model-binding.
     * Sinon Laravel passe un Campaign vide → erreurs nulles partout.
     */
    public function duplicate(Request $request, Campaign $campaign)
    {
        $this->authorize('create', Campaign::class);
        $this->authorize('view', $campaign);

        $data = $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after:start_date',
        ]);

        $source   = $campaign;
        $duration = (int) max(1, $source->start_date->diffInDays($source->end_date));

        $newStart = !empty($data['start_date'])
            ? \Carbon\Carbon::parse($data['start_date'])
            : $source->end_date->copy()->addDay();
        $newEnd = !empty($data['end_date'])
            ? \Carbon\Carbon::parse($data['end_date'])
            : $newStart->copy()->addDays($duration);

        try {
            $newCampaign = DB::transaction(function () use ($source, $newStart, $newEnd) {
                $baseName = $source->name . ' (renouvelée)';
                $name = $baseName;
                $i = 2;
                while (Campaign::where('client_id', $source->client_id)
                    ->where('name', $name)
                    ->whereNull('deleted_at')
                    ->exists()
                ) {
                    $name = $baseName . ' ' . $i;
                    $i++;
                }

                $newCampaign = Campaign::create([
                    'name'               => $name,
                    'client_id'          => $source->client_id,
                    'commercial_user_id' => $source->commercial_user_id,
                    'user_id'            => auth()->id(),
                    'start_date'         => $newStart->format('Y-m-d'),
                    'end_date'           => $newEnd->format('Y-m-d'),
                    'status'             => CampaignStatus::PLANIFIE->value,
                    'notes'              => $source->notes,
                    'total_amount'       => 0,
                ]);

                // Réattache les panneaux internes via le service (qui crée
                // la résa technique + recalcule + applique les conflits).
                $internalIds = $source->panels()->pluck('panels.id')->all();
                if (!empty($internalIds)) {
                    $this->campaignService->addPanels($newCampaign, $internalIds);
                }

                $externalIds = $source->externalPanels()->pluck('external_panels.id')->all();
                if (!empty($externalIds)) {
                    $this->campaignService->addExternalPanels($newCampaign->fresh(), $externalIds);
                }

                Log::info('campaign.duplicated', [
                    'source_id' => $source->id,
                    'new_id'    => $newCampaign->id,
                    'user_id'   => auth()->id(),
                ]);

                return $newCampaign;
            });

            AlertService::create(
                'campagne', 'info',
                '🔁 Campagne dupliquée — ' . $newCampaign->name,
                auth()->user()?->name . " a dupliqué la campagne #{$source->id} ({$source->name}) → #{$newCampaign->id}",
                $newCampaign
            );

            return redirect()
                ->route('admin.campaigns.show', $newCampaign)
                ->with('success', "✅ Campagne dupliquée avec mêmes panneaux + commercial. Ajustez si besoin puis cliquez « ▶ Démarrer la campagne ».");

        } catch (\Throwable $e) {
            Log::error('campaign.duplicate.failed', [
                'source_id' => $campaign->id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Échec de la duplication : ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        $this->authorize('create', Campaign::class);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                // Campaign use SoftDeletes — on doit explicitement exclure
                // les rows soft-deleted sinon l'utilisateur ne peut plus
                // créer une campagne avec le nom d'une campagne supprimée.
                Rule::unique('campaigns')
                    ->where('client_id', $request->client_id)
                    ->whereNull('deleted_at'),
            ],
            'client_id'          => 'required|exists:clients,id',
            'reservation_id'     => 'nullable|exists:reservations,id',
            'commercial_user_id' => 'nullable|exists:users,id',
            'start_date'         => 'required|date',
            'end_date'           => 'required|date|after:start_date',
            'notes'              => 'nullable|string|max:2000',
        ]);

        $client = Client::withTrashed()->findOrFail($data['client_id']);
        if ($client->trashed()) {
            return back()->withInput()->with('error',
                'Impossible de créer une campagne pour un client supprimé.');
        }

        try {
            $campaign = DB::transaction(function () use ($data) {
                $data['user_id'] = auth()->id();

                // Si pas de commercial assigné explicitement, on hérite du
                // commercial de la résa source. Sinon le créateur devient
                // par défaut le commercial référent.
                if (empty($data['commercial_user_id']) && !empty($data['reservation_id'])) {
                    $r = Reservation::find($data['reservation_id']);
                    $data['commercial_user_id'] = $r?->commercial_user_id ?: $r?->user_id;
                }
                if (empty($data['commercial_user_id'])) {
                    $data['commercial_user_id'] = auth()->id();
                }

                // ── Statut initial ────────────────────────────────────
                // Règle UX nouvelle (workflow campagne directe) :
                //
                // 1. Si la campagne provient d'une réservation, on connaît
                //    déjà tous les panneaux + prix → statut dérivé des dates
                //    comme avant (ACTIF si déjà commencée, PLANIFIE sinon)
                //    et mail client envoyé en aval.
                //
                // 2. Si la campagne est créée EN DIRECT (sans réservation),
                //    on n'a encore aucun panneau ni prix → on force PLANIFIE
                //    quel que soit start_date. L'utilisateur ajoute ensuite
                //    les panneaux + prix négocié + commercial, PUIS clique
                //    "▶ Démarrer la campagne" pour passer ACTIF + envoyer
                //    le mail client avec les vraies infos.
                //
                // Sans cette règle, une campagne directe créée le jour J
                // partait en ACTIF immédiatement → mail client envoyé avec
                // 0 panneau → faux montant chez le client.
                $isDirect = empty($data['reservation_id']);
                if ($isDirect) {
                    $data['status'] = CampaignStatus::PLANIFIE->value;
                } else {
                    $today = now()->startOfDay();
                    $start = \Carbon\Carbon::parse($data['start_date'])->startOfDay();
                    $data['status'] = $start->gt($today)
                        ? CampaignStatus::PLANIFIE->value
                        : CampaignStatus::ACTIF->value;
                }

                $reservation = null;
                if (!empty($data['reservation_id'])) {
                    $reservation = Reservation::with(['panels', 'externalPanels'])->findOrFail($data['reservation_id']);

                    if ($reservation->campaign()->exists()) {
                        throw new \Exception('Cette réservation est déjà liée à une campagne.');
                    }
                    if ($reservation->client_id !== (int) $data['client_id']) {
                        throw new \Exception('Le client ne correspond pas à celui de la réservation.');
                    }

                    $data['total_panels'] = $reservation->panels->count() + $reservation->externalPanels->count();
                    $data['total_amount'] = $reservation->total_amount;
                    $data['start_date']   ??= $reservation->start_date;
                    $data['end_date']     ??= $reservation->end_date;
                }

                $campaign = Campaign::create($data);

                $internalIds = [];
                $externalIds = [];
                if ($reservation !== null) {
                    $internalIds = $reservation->panels->pluck('id')->all();
                    if (!empty($internalIds)) {
                        $campaign->panels()->sync($internalIds);
                    }

                    // Attache aussi les externes — pivot campaign_panels avec
                    // type='externe' (cf. ReservationController::store).
                    $externalIds = $reservation->externalPanels->pluck('id')->all();
                    if (!empty($externalIds)) {
                        $rows = array_map(fn($id) => [
                            'campaign_id'       => $campaign->id,
                            'external_panel_id' => $id,
                            'type'              => 'externe',
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ], $externalIds);
                        DB::table('campaign_panels')->insert($rows);
                    }
                }

                // Lot 9.1 — Auto-création tâches de pose après sync panneaux
                $campaign->ensurePoseTasksAutoCreated();

                // ⚠ Bug fix : si la campagne démarre ACTIF (start_date <=
                // aujourd'hui), les panneaux hérités de la résa étaient
                // restés à CONFIRMÉ dans l'inventaire — la campagne diffusait
                // mais "Disponibilité" affichait CONFIRMÉ. On propage les
                // statuts aux panneaux dès la création pour aligner
                // immédiatement l'inventaire sur la réalité terrain.
                // Best-effort (try/catch) : un fail ici ne doit pas annuler
                // la création — le user a déjà sa campagne, on ne fait que
                // resynchroniser un état dérivé.
                try {
                    if (!empty($internalIds)) {
                        $this->availability->syncPanelStatuses($internalIds);
                    }
                    if (!empty($externalIds)) {
                        $this->availability->syncExternalPanelStatuses($externalIds);
                    }
                } catch (\Throwable $e) {
                    Log::warning('campaign.created.panel_sync_failed', [
                        'campaign_id' => $campaign->id,
                        'error'       => $e->getMessage(),
                    ]);
                }

                Log::info('campaign.created', [
                    'campaign_id'      => $campaign->id,
                    'user_id'          => auth()->id(),
                    'client_id'        => $campaign->client_id,
                    'with_reservation' => $reservation !== null,
                    'status'           => $campaign->status->value,
                ]);

                AlertService::create(
                    'campagne',
                    'info',
                    '🚀 Campagne créée — ' . $campaign->name,
                    auth()->user()?->name . ' a créé la campagne "' . $campaign->name . '"'
                        . ($reservation ? ' depuis la réservation ' . $reservation->reference : ''),
                    $campaign
                );

                return $campaign;
            });

            // ── Mail client à la création ─────────────────────────────
            // Envoyé UNIQUEMENT pour les campagnes créées depuis une
            // réservation (panneaux et prix déjà connus). Pour les
            // campagnes directes, on attend l'action explicite
            // "▶ Démarrer la campagne" sur la fiche show — ce qui garantit
            // que le mail part avec les vrais panneaux/prix.
            $mailSent = false;
            $isDirectCreation = empty($data['reservation_id']);
            if (!$isDirectCreation) {
                try {
                    $mailSent = $this->campaignService->sendStartedMailToClient($campaign);
                } catch (\Throwable $e) {
                    Log::warning('campaign.created.mail_failed', [
                        'campaign_id' => $campaign->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            // Notif commercial assigné si différent du créateur
            if ($campaign->commercial_user_id && $campaign->commercial_user_id !== auth()->id()) {
                $this->notifyCommercialAssigned($campaign);
            }

            $msg = "Campagne « {$campaign->name} » créée avec succès.";
            if ($isDirectCreation) {
                $msg .= ' Ajoutez les panneaux et le prix négocié, puis cliquez sur « ▶ Démarrer la campagne » pour la lancer.';
            } elseif ($mailSent) {
                $msg .= ' Email envoyé au client.';
            } elseif ($campaign->client?->email) {
                $msg .= ' (envoi email au client échoué — vérifie les logs)';
            }

            $redirect = redirect()
                ->route('admin.campaigns.show', $campaign)
                ->with('success', $msg);

            // Avertit l'admin si les dates de campagne aboutissent à un
            // montant différent de celui facturé sur la résa (ex: campagne
            // 03/06→21/08 = 3 mois → 270k, mais résa 07/06→25/07 = 2 mois
            // → 180k figés). Sans ce signal, la divergence reste invisible.
            try {
                $check = app(CampaignAmountConsistency::class)->check($campaign->fresh());
                if ($check && !$check['matches']) {
                    $redirect = $redirect->with(
                        'warning',
                        app(CampaignAmountConsistency::class)->humanMessage($check)
                    );
                    Log::info('campaign.created.amount_drift', [
                        'campaign_id' => $campaign->id,
                        'expected'    => $check['expected'],
                        'stored'      => $check['stored'],
                        'diff'        => $check['diff'],
                        'source'      => $check['source'],
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('campaign.created.amount_check_failed', [
                    'campaign_id' => $campaign->id,
                    'error'       => $e->getMessage(),
                ]);
            }

            return $redirect;

        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════
    // EDIT / UPDATE
    // ══════════════════════════════════════════════════════════════
    public function edit(Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $clients = Client::orderBy('name')->get();
        $commerciaux = \App\Models\User::query()
            ->whereIn('role', ['admin', 'commercial'])
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'email']);
        return view('admin.campaigns.edit', compact('campaign', 'clients', 'commerciaux'));
    }

    /**
     * Correction ciblée d'une campagne — NOM + COMMERCIAL assigné. Marche
     * AUSSI sur les campagnes terminées (correction d'historique : anciennes
     * campagnes importées dont le commercial conditionne le Top Commercial).
     * Volontairement limité à name + commercial_user_id : ne touche ni aux
     * dates ni au client (pas de re-check dispo, zéro risque sur l'historique).
     * Utilise managePanel (MP + admin, bloque annulé) plutôt que update()
     * qui bloque les campagnes terminées.
     */
    public function rename(Request $request, Campaign $campaign)
    {
        $this->authorize('managePanel', $campaign);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('campaigns', 'name')
                    ->where('client_id', $campaign->client_id)
                    ->whereNull('deleted_at')
                    ->ignore($campaign->id),
            ],
            'commercial_user_id' => 'nullable|exists:users,id',
        ], [
            'name.required'           => 'Le nom de la campagne est obligatoire.',
            'name.unique'             => 'Une campagne porte déjà ce nom pour ce client.',
            'name.max'                => 'Le nom ne doit pas dépasser 150 caractères.',
            'commercial_user_id.exists' => 'Le commercial sélectionné est invalide.',
        ]);

        $oldName       = $campaign->name;
        $oldCommercial = $campaign->commercial_user_id;

        $campaign->update([
            'name'               => $data['name'],
            'commercial_user_id' => $data['commercial_user_id'] ?: null,
        ]);

        \Illuminate\Support\Facades\Log::info('campaign.corrected', [
            'campaign_id'    => $campaign->id,
            'old_name'       => $oldName,
            'new_name'       => $data['name'],
            'old_commercial' => $oldCommercial,
            'new_commercial' => $campaign->commercial_user_id,
            'user_id'        => auth()->id(),
        ]);

        return back()->with('success', "Campagne mise à jour (nom + commercial).");
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        // Garde-fou : seules les campagnes PLANIFIEE / ACTIVE sont modifiables
        if (in_array($campaign->status->value, ['termine', 'annule'])) {
            return back()->withInput()->with('error',
                "❌ Une campagne « {$campaign->status->label()} » ne peut pas être modifiée. " .
                "Seules les campagnes Planifiées ou Actives sont modifiables."
            );
        }

        $rules = [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('campaigns', 'name')
                    ->where('client_id', $request->client_id)
                    ->whereNull('deleted_at')
                    ->ignore($campaign->id),
            ],
            'client_id'          => 'required|exists:clients,id',
            'commercial_user_id' => 'nullable|exists:users,id',
            'end_date'           => 'required|date|after:start_date',
            'notes'              => 'nullable|string|max:2000',
        ];

        // start_date : verrouillée pour campagne ACTIVE, modifiable pour PLANIFIEE
        $rules['start_date'] = $campaign->status === CampaignStatus::ACTIF
            ? 'nullable|date'
            : 'required|date';

        $data = $request->validate($rules);

        if ($campaign->status === CampaignStatus::ACTIF && empty($data['start_date'])) {
            $data['start_date'] = $campaign->start_date->format('Y-m-d');
        }

        $today    = now()->startOfDay();
        $newStart = \Carbon\Carbon::parse($data['start_date'])->startOfDay();
        $newEnd   = \Carbon\Carbon::parse($data['end_date'])->startOfDay();

        if ($newEnd->lte($newStart)) {
            return back()->withInput()->with('error',
                '❌ La date de fin doit être postérieure à la date de début.');
        }

        // Garde-fou durée maximale : 36 mois (cohérence Reservation)
        if (abs($newStart->diffInMonths($newEnd)) > 36) {
            return back()->withInput()->with('error',
                '❌ La durée maximale d\'une campagne est de 36 mois.');
        }

        // Verrou date début pour campagne active
        if ($campaign->status === CampaignStatus::ACTIF
            && !$campaign->start_date->isSameDay($newStart)) {
            return back()->withInput()->with('error',
                '❌ Une campagne active ne peut pas voir sa date de début modifiée. ' .
                'La campagne a déjà commencé le ' . $campaign->start_date->format('d/m/Y') . '.');
        }

        // ⚠ Garde anti-saisie incohérente : une campagne PLANIFIEE ne peut
        // pas avoir une start_date dans le passé. Cela créait des campagnes
        // "planifiées" déjà en retard, qui ne s'activaient pas automatiquement
        // au bon jour (cron sync regarde start_date <= today mais le statut
        // resté planifie ne déclenche aucune logique terrain).
        if ($campaign->status === CampaignStatus::PLANIFIE
            && $newStart->lt($today)) {
            return back()->withInput()->with('error',
                '❌ Une campagne planifiée ne peut pas avoir une date de début dans le passé. ' .
                'Choisis aujourd\'hui (' . $today->format('d/m/Y') . ') ou une date future. ' .
                'Pour saisir une campagne déjà commencée, crée-la directement en statut Actif.'
            );
        }

        // Statut recalculé en fonction des nouvelles dates
        $data['status'] = $this->calculateStatus($newStart, $newEnd, $campaign->status);

        $oldStart       = $campaign->start_date;
        $oldEnd         = $campaign->end_date;
        $oldStatus      = $campaign->status;
        $oldCommercial  = $campaign->commercial_user_id;

        $data['updated_by'] = auth()->id();

        DB::transaction(function () use ($campaign, $data, $oldStatus) {
            $campaign->update($data);

            // Si dates changées et qu'il y a une réservation liée, on doit
            // au minimum aligner end_date pour la cohérence facturation
            if ($campaign->reservation && $campaign->wasChanged(['start_date', 'end_date'])) {
                $campaign->reservation->updateWithoutObservers([
                    'start_date' => $campaign->start_date->format('Y-m-d'),
                    'end_date'   => $campaign->end_date->format('Y-m-d'),
                ]);
            }

            // Recalcul du montant si la durée a changé
            if ($campaign->wasChanged(['start_date', 'end_date'])) {
                $this->campaignService->recalculateCampaignAmount($campaign->fresh());
            }

            // ⚠ Bug fix : calculateStatus() peut faire passer la campagne
            // d'un statut à l'autre (ex: PLANIFIE → ACTIF si start_date
            // ramenée à today). Sans le sync, les panneaux restent à
            // CONFIRME alors que la campagne diffuse. On propage la
            // transition de statut aux panneaux pour cohérence inventaire.
            // ⚠ Étendu aux changements de dates même sans changement de
            // statut : si la résa initiale avait CONFIRMÉ le panneau et
            // que les nouvelles dates de campagne placent today dans la
            // fenêtre, le panneau doit basculer EN_AFFICHAGE — sync
            // idempotent, donc safe d'appeler à chaque update significatif.
            if ($campaign->wasChanged(['status', 'start_date', 'end_date'])) {
                try {
                    $panelIds = $campaign->panels()->pluck('panels.id')->all();
                    $extIds   = $campaign->externalPanels()->pluck('external_panels.id')->all();
                    if (!empty($panelIds)) {
                        app(\App\Services\AvailabilityService::class)->syncPanelStatuses($panelIds);
                    }
                    if (!empty($extIds)) {
                        app(\App\Services\AvailabilityService::class)->syncExternalPanelStatuses($extIds);
                    }
                    Log::info('campaign.update.status_changed', [
                        'campaign_id' => $campaign->id,
                        'old_status'  => $oldStatus->value,
                        'new_status'  => $campaign->status->value,
                        'panels_synced' => count($panelIds) + count($extIds),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('campaign.update.sync_failed', [
                        'campaign_id' => $campaign->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        });

        // Notif commercial si nouvellement assigné (et différent du créateur)
        $newCommercial = $campaign->commercial_user_id;
        if ($newCommercial && $newCommercial !== $oldCommercial && $newCommercial !== auth()->id()) {
            $this->notifyCommercialAssigned($campaign->fresh());
        }

        Log::info('campaign.updated', [
            'campaign_id' => $campaign->id,
            'user_id'     => auth()->id(),
            'changes'     => [
                'start_date' => ['old' => $oldStart->format('Y-m-d'), 'new' => $campaign->start_date->format('Y-m-d')],
                'end_date'   => ['old' => $oldEnd->format('Y-m-d'),   'new' => $campaign->end_date->format('Y-m-d')],
                'status'     => ['old' => $oldStatus->value,           'new' => $campaign->status->value],
            ],
        ]);

        AlertService::create(
            'campagne',
            'info',
            '✏️ Campagne modifiée — ' . $campaign->name,
            auth()->user()?->name . ' a modifié la campagne "' . $campaign->name . '"',
            $campaign
        );

        $message = "✅ Campagne « {$campaign->name} » mise à jour avec succès.";
        if ($oldStatus === CampaignStatus::PLANIFIE && $campaign->status === CampaignStatus::ACTIF) {
            $message .= " La campagne est maintenant active.";
        } elseif ($campaign->status === CampaignStatus::TERMINE && $newEnd->lt($today)) {
            $message .= " La campagne a été automatiquement marquée comme terminée.";
        }

        $redirect = redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', $message);

        // Idem store() : signaler les écarts entre montant stocké et montant
        // attendu pour la nouvelle période. Pas de recalcul auto — l'admin
        // décide (ajuster la résa, accepter l'écart, ou overrider le total).
        try {
            $check = app(CampaignAmountConsistency::class)->check($campaign->fresh());
            if ($check && !$check['matches']) {
                $redirect = $redirect->with(
                    'warning',
                    app(CampaignAmountConsistency::class)->humanMessage($check)
                );
                Log::info('campaign.updated.amount_drift', [
                    'campaign_id' => $campaign->id,
                    'expected'    => $check['expected'],
                    'stored'      => $check['stored'],
                    'diff'        => $check['diff'],
                    'source'      => $check['source'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('campaign.updated.amount_check_failed', [
                'campaign_id' => $campaign->id,
                'error'       => $e->getMessage(),
            ]);
        }

        return $redirect;
    }

    /** Calcule le statut cible d'une campagne à partir de ses nouvelles dates */
    protected function calculateStatus(\Carbon\Carbon $start, \Carbon\Carbon $end, CampaignStatus $current): string
    {
        $today = now()->startOfDay();

        if ($end->lte($today))   return CampaignStatus::TERMINE->value;
        if ($start->gt($today))  return CampaignStatus::PLANIFIE->value;
        // PAUSE est préservée (suspension manuelle, pas dérivée des dates).
        if ($current === CampaignStatus::PAUSE) return CampaignStatus::PAUSE->value;
        return CampaignStatus::ACTIF->value;
    }

    // ══════════════════════════════════════════════════════════════
    // ADD PANEL
    // ══════════════════════════════════════════════════════════════
    public function addPanel(Request $request, Campaign $campaign)
    {
        $this->authorize('managePanel', $campaign);

        // Le payload peut désormais contenir des IDs internes (entiers) et
        // des IDs externes au format "ext_<n>" (string) — l'utilisateur peut
        // donc cocher dans la même modale des panneaux des deux origines.
        $data = $request->validate([
            'panel_ids'      => 'required|array|min:1',
            'panel_ids.*'    => 'required',
            // Prix négocié optionnel par panneau (clé = panel_id ou "ext_<n>")
            'unit_prices'    => 'nullable|array',
            'unit_prices.*'  => 'nullable|numeric|min:0',
        ]);

        if (!in_array($campaign->status->value, ['planifie', 'actif', 'termine'])) {
            return back()->with('error',
                'Impossible d\'ajouter des panneaux à une campagne en pause ou annulée.');
        }

        // ─── Séparation internes / externes ──────────────────────────
        $internalIds       = [];
        $externalIds       = [];
        $internalPrices    = [];
        $externalPrices    = [];
        foreach ($data['panel_ids'] as $raw) {
            $rawStr = (string) $raw;
            if (str_starts_with($rawStr, 'ext_')) {
                $extId = (int) substr($rawStr, 4);
                if ($extId > 0) {
                    $externalIds[] = $extId;
                    if (!empty($data['unit_prices'][$rawStr]) && is_numeric($data['unit_prices'][$rawStr])) {
                        $externalPrices[$extId] = (float) $data['unit_prices'][$rawStr];
                    }
                }
            } else {
                $intId = (int) $rawStr;
                if ($intId > 0) {
                    $internalIds[] = $intId;
                    if (!empty($data['unit_prices'][$intId]) && is_numeric($data['unit_prices'][$intId])) {
                        $internalPrices[$intId] = (float) $data['unit_prices'][$intId];
                    } elseif (!empty($data['unit_prices'][(string) $intId]) && is_numeric($data['unit_prices'][(string) $intId])) {
                        $internalPrices[$intId] = (float) $data['unit_prices'][(string) $intId];
                    }
                }
            }
        }

        // Validation existence (à la main, plus simple que des règles dynamiques)
        if (!empty($internalIds)) {
            $found = Panel::whereIn('id', $internalIds)->pluck('id')->all();
            $missing = array_diff($internalIds, $found);
            if (!empty($missing)) {
                return back()->with('error', 'Panneau(x) interne(s) inconnu(s) : ' . implode(', ', $missing));
            }
        }
        if (!empty($externalIds)) {
            $foundExt = ExternalPanel::whereIn('id', $externalIds)->pluck('id')->all();
            $missingExt = array_diff($externalIds, $foundExt);
            if (!empty($missingExt)) {
                return back()->with('error', 'Panneau(x) externe(s) inconnu(s) : ' . implode(', ', $missingExt));
            }
        }

        $totalAdded   = 0;
        $posesCreated = 0;
        $messages     = [];

        // ─── Internes ────────────────────────────────────────────────
        if (!empty($internalIds)) {
            $result = $this->campaignService->addPanels(
                $campaign,
                $internalIds,
                !empty($internalPrices) ? $internalPrices : null
            );
            if (!$result['ok']) {
                return back()->with('error', $result['error']);
            }
            $totalAdded   += $result['added'] ?? count($internalIds);
            $posesCreated += $result['poses_created'] ?? 0;
        }

        // ─── Externes ────────────────────────────────────────────────
        if (!empty($externalIds)) {
            $result = $this->campaignService->addExternalPanels(
                $campaign->fresh(),
                $externalIds,
                !empty($externalPrices) ? $externalPrices : null
            );
            if (!$result['ok']) {
                $prefix = $totalAdded > 0
                    ? "Partiellement appliqué : {$totalAdded} panneau(x) interne(s) ajouté(s), mais "
                    : '';
                return back()->with('error', $prefix . $result['error']);
            }
            $totalAdded += $result['added'] ?? count($externalIds);
        }

        AlertService::create(
            'campagne',
            'info',
            '➕ Panneau ajouté — ' . $campaign->name,
            auth()->user()?->name . " a ajouté {$totalAdded} panneau(x) à la campagne \"{$campaign->name}\""
            . ($posesCreated > 0 ? " — {$posesCreated} tâche(s) de pose auto-créée(s)" : ''),
            $campaign
        );

        $msg = "{$totalAdded} panneau(x) ajouté(s). Montant recalculé.";
        if ($posesCreated > 0) {
            $msg .= " {$posesCreated} pose(s) auto-créée(s).";
        }
        return back()->with('success', $msg);
    }

    // ══════════════════════════════════════════════════════════════
    // UPDATE PANEL PRICE — Modifier le prix d'un panneau déjà attaché
    // (cas typique : prix négocié après ajout, ou correction)
    // ══════════════════════════════════════════════════════════════
    public function updatePanelPrice(Request $request, Campaign $campaign, Panel $panel)
    {
        $this->authorize('managePanel', $campaign);

        $data = $request->validate([
            'unit_price' => 'required|numeric|min:0',
        ]);

        if (!in_array($campaign->status->value, ['planifie', 'actif', 'termine'])) {
            $isAjax = $request->expectsJson() || $request->ajax();
            $msg = 'Impossible de modifier le prix sur une campagne en pause ou annulée.';
            return $isAjax
                ? response()->json(['ok' => false, 'error' => $msg], 422)
                : back()->with('error', $msg);
        }

        // Vérifie que le panneau est bien dans la campagne (sécurité).
        // Les campagnes directes ont une "réservation technique" créée
        // automatiquement au premier addPanels() — le pivot
        // reservation_panels existe donc toujours dès qu'un panneau est lié.
        if (!$campaign->panels()->where('panels.id', $panel->id)->exists()) {
            $isAjax = $request->expectsJson() || $request->ajax();
            $msg = 'Ce panneau n\'est pas dans la campagne.';
            return $isAjax
                ? response()->json(['ok' => false, 'error' => $msg], 404)
                : back()->with('error', $msg);
        }

        if (!$campaign->reservation_id) {
            // Cas théoriquement impossible (addPanels crée toujours une
            // résa technique). Filet de sécurité.
            $isAjax = $request->expectsJson() || $request->ajax();
            $msg = 'État incohérent — la campagne a un panneau sans réservation pivot. Contactez le support.';
            return $isAjax
                ? response()->json(['ok' => false, 'error' => $msg], 500)
                : back()->with('error', $msg);
        }

        $months = $campaign->billableMonths();
        $unit   = (float) $data['unit_price'];

        \DB::table('reservation_panels')
            ->where('reservation_id', $campaign->reservation_id)
            ->where('panel_id', $panel->id)
            ->update([
                'unit_price'  => $unit,
                'total_price' => round($unit * $months, 2),
                'updated_at'  => now(),
            ]);

        $this->campaignService->recalculateCampaignAmount($campaign->fresh());

        Log::info('campaign.panel_price_updated', [
            'campaign_id' => $campaign->id,
            'panel_id'    => $panel->id,
            'unit_price'  => $unit,
            'user_id'     => auth()->id(),
        ]);

        AlertService::create(
            'campagne', 'info',
            '💰 Prix modifié — ' . $campaign->name,
            auth()->user()?->name . " a modifié le prix du panneau {$panel->reference} à " . number_format($unit, 0, ',', ' ') . " FCFA/mois",
            $campaign
        );

        // Réponse adaptée au type d'appel (AJAX inline edit / form classique)
        if ($request->expectsJson() || $request->ajax()) {
            $campaign->refresh();
            return response()->json([
                'ok'             => true,
                'unit_price'     => $unit,
                'total_period'   => round($unit * $months, 2),
                'campaign_total' => (float) $campaign->total_amount,
                'message'        => 'Prix mis à jour.',
            ]);
        }

        return back()->with('success', 'Prix mis à jour. Total campagne recalculé.');
    }

    public function resetPanelPrice(Campaign $campaign, Panel $panel)
    {
        $this->authorize('managePanel', $campaign);

        if (!in_array($campaign->status->value, ['planifie', 'actif'])) {
            return back()->with('error', 'Campagne non modifiable.');
        }

        if (!$campaign->reservation_id) {
            return back()->with('error', 'Aucune réservation associée.');
        }

        $months = $campaign->billableMonths();
        $unit   = (float) ($panel->monthly_rate ?? 0);

        \DB::table('reservation_panels')
            ->where('reservation_id', $campaign->reservation_id)
            ->where('panel_id', $panel->id)
            ->update([
                'unit_price'  => $unit,
                'total_price' => round($unit * $months, 2),
                'updated_at'  => now(),
            ]);

        $this->campaignService->recalculateCampaignAmount($campaign->fresh());

        return back()->with('success', 'Prix remis au tarif catalogue (' . number_format($unit, 0, ',', ' ') . ' FCFA/mois).');
    }

    // ══════════════════════════════════════════════════════════════
    // REMOVE PANEL
    // ══════════════════════════════════════════════════════════════
    public function removePanel(Campaign $campaign, Panel $panel)
    {
        $this->authorize('managePanel', $campaign);

        $result = $this->campaignService->removePanel($campaign, $panel);

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        AlertService::create(
            'campagne',
            'warning',
            '➖ Panneau retiré — ' . $campaign->name,
            auth()->user()?->name . " a retiré le panneau {$panel->reference} de la campagne \"{$campaign->name}\"",
            $campaign
        );

        $msg = "Panneau {$panel->reference} retiré.";

        if (isset($result['warning'])) {
            return redirect()
                ->route('admin.campaigns.index')
                ->with('warning', $msg . ' ⚠️ ' . $result['warning']);
        }

        return back()->with('success', $msg . ' Montant recalculé.');
    }

    // ══════════════════════════════════════════════════════════════
    // OVERRIDE MONTANT TOTAL (remise globale / négociation forfaitaire)
    //
    // Permet à un commercial de fixer un montant total différent de la
    // somme calculée (panel.unit_price × mois). Cas typiques :
    //   - Remise globale négociée avec le client
    //   - Forfait packagé (panneaux + production + média)
    //
    // Ne touche pas aux prix individuels — c'est juste un override du
    // total final affiché et facturé. Si l'utilisateur ajoute/retire un
    // panneau ensuite, le total est recalculé depuis les prix unitaires
    // (l'override est perdu). On le signale dans l'UI.
    // ══════════════════════════════════════════════════════════════
    public function updateTotal(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $data = $request->validate([
            'total_amount' => 'required|numeric|min:0',
        ]);

        if (in_array($campaign->status->value, ['termine', 'annule'])) {
            $isAjax = $request->expectsJson() || $request->ajax();
            $msg = 'Impossible de modifier le montant d\'une campagne terminée ou annulée.';
            return $isAjax
                ? response()->json(['ok' => false, 'error' => $msg], 422)
                : back()->with('error', $msg);
        }

        $oldTotal = (float) $campaign->total_amount;
        $newTotal = round((float) $data['total_amount'], 2);

        $campaign->update([
            'total_amount'                  => $newTotal,
            'total_amount_overridden_at'    => now(),
            'total_amount_overridden_by_id' => auth()->id(),
        ]);

        // Synchronise la réservation liée (réelle ou technique) — sinon
        // la fiche réservation affiche encore l'ancien montant calculé
        // depuis les prix unitaires. On utilise updateQuietly() pour
        // éviter de re-déclencher ReservationObserver::updated (boucle).
        if ($campaign->reservation_id) {
            \App\Models\Reservation::where('id', $campaign->reservation_id)
                ->update(['total_amount' => $newTotal]);
        }

        Log::info('campaign.total_overridden', [
            'campaign_id' => $campaign->id,
            'old_total'   => $oldTotal,
            'new_total'   => $newTotal,
            'user_id'     => auth()->id(),
        ]);

        AlertService::create(
            'campagne', 'info',
            '💰 Montant total ajusté — ' . $campaign->name,
            auth()->user()?->name . ' a modifié le total de '
                . number_format($oldTotal, 0, ',', ' ') . ' → '
                . number_format($newTotal, 0, ',', ' ') . ' FCFA',
            $campaign
        );

        $userName = auth()->user()?->name ?? '';
        $whenIso  = now()->toIso8601String();
        $whenFmt  = now()->format('d/m/Y à H:i');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'                     => true,
                'total_amount'           => $newTotal,
                'total_amount_formatted' => number_format($newTotal, 0, ',', ' '),
                'overridden_by'          => $userName,
                'overridden_at'          => $whenIso,
                'overridden_at_formatted'=> $whenFmt,
                'message'                => '✅ Montant négocié enregistré : ' . number_format($newTotal, 0, ',', ' ') . ' FCFA.',
            ]);
        }

        return back()->with('success', '✅ Montant négocié enregistré.');
    }

    // ══════════════════════════════════════════════════════════════
    // REMOVE EXTERNAL PANEL — détache un panneau régie partenaire
    // ══════════════════════════════════════════════════════════════
    public function removeExternalPanel(Campaign $campaign, ExternalPanel $externalPanel)
    {
        $this->authorize('managePanel', $campaign);

        $result = $this->campaignService->removeExternalPanel($campaign, $externalPanel);

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        AlertService::create(
            'campagne',
            'warning',
            '➖ Panneau externe retiré — ' . $campaign->name,
            auth()->user()?->name . " a retiré le panneau externe {$externalPanel->code_panneau} de la campagne \"{$campaign->name}\"",
            $campaign
        );

        return back()->with('success', "Panneau externe {$externalPanel->code_panneau} retiré. Montant recalculé.");
    }

    // ══════════════════════════════════════════════════════════════
    // BULK ACTION — actions groupées sur plusieurs campagnes
    //
    // Actions :
    //   - 'pause'  : ACTIF → PAUSE (chaque campagne)
    //   - 'resume' : PAUSE → ACTIF
    //   - 'cancel' : → ANNULE (déclenche le flow CampaignService::cancel
    //                 qui libère panneaux + pose-tasks)
    //   - 'delete' : suppression définitive (via CampaignService::delete
    //                 qui a déjà ses gardes : pas de piges, etc.)
    //
    // Gardes : on traite chaque campagne avec ses contrôles, on skip
    // silencieusement celles qui ne sont pas éligibles. Une seule
    // alerte consolidée à la fin (pas N alertes spam).
    // ══════════════════════════════════════════════════════════════
    public function bulkAction(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'action'        => 'required|in:start,pause,resume,cancel,delete',
            'ids'           => 'required|array|min:1|max:200',
            'ids.*'         => 'integer|exists:campaigns,id',
            'cancel_reason' => 'nullable|string|max:500',
        ]);

        $campaigns = Campaign::whereIn('id', $data['ids'])->get();
        $applied = 0;
        $skipped = [];

        foreach ($campaigns as $c) {
            $statusValue = is_object($c->status) ? $c->status->value : $c->status;
            try {
                switch ($data['action']) {
                    case 'start':
                        // PLANIFIE → ACTIF (« Démarrer »). Passe par
                        // CampaignService::activate() pour la garde « min 1
                        // panneau » + l'envoi du mail client (transition
                        // depuis PLANIFIE, contrairement à resume).
                        if ($statusValue !== \App\Enums\CampaignStatus::PLANIFIE->value) {
                            $skipped[] = $c->name . ' (pas planifiée)';
                            continue 2;
                        }
                        $result = $this->campaignService->activate($c);
                        if (!$result['ok']) {
                            $skipped[] = $c->name . ' (' . ($result['error'] ?? 'démarrage refusé') . ')';
                            continue 2;
                        }
                        $applied++;
                        break;
                    case 'pause':
                        if ($statusValue !== \App\Enums\CampaignStatus::ACTIF->value) {
                            $skipped[] = $c->name . ' (pas actif)';
                            continue 2;
                        }
                        // Update direct OK : l'observer Campaign capture
                        // wasChanged('status') et crée l'alerte associée.
                        // Pas de logique métier complexe en pause.
                        $c->update([
                            'status'     => \App\Enums\CampaignStatus::PAUSE->value,
                            'updated_by' => auth()->id(),
                        ]);
                        $applied++;
                        break;
                    case 'resume':
                        if ($statusValue !== \App\Enums\CampaignStatus::PAUSE->value) {
                            $skipped[] = $c->name . ' (pas en pause)';
                            continue 2;
                        }
                        // PAUSE → ACTIF passe par CampaignService::activate()
                        // pour bénéficier de la garde "min 1 panneau" (sinon
                        // on peut reprendre une campagne vidée de ses
                        // panneaux). Le service ne ré-envoie pas le mail
                        // client (transition depuis PAUSE, pas PLANIFIE).
                        $result = $this->campaignService->activate($c);
                        if (!$result['ok']) {
                            $skipped[] = $c->name . ' (' . ($result['error'] ?? 'reprise refusée') . ')';
                            continue 2;
                        }
                        $applied++;
                        break;
                    case 'cancel':
                        if (in_array($statusValue, [
                            \App\Enums\CampaignStatus::ANNULE->value,
                            \App\Enums\CampaignStatus::TERMINE->value,
                        ], true)) {
                            $skipped[] = $c->name . ' (statut terminal)';
                            continue 2;
                        }
                        $this->campaignService->cancel(
                            $c,
                            $data['cancel_reason'] ?? 'Annulation groupée',
                        );
                        $applied++;
                        break;
                    case 'delete':
                        $result = $this->campaignService->delete($c);
                        if (!$result['ok']) {
                            $skipped[] = $c->name . ' (' . ($result['error'] ?? 'erreur') . ')';
                            continue 2;
                        }
                        $applied++;
                        break;
                }
            } catch (\Throwable $e) {
                Log::warning('campaign.bulk.skipped', [
                    'id' => $c->id, 'action' => $data['action'], 'err' => $e->getMessage(),
                ]);
                $skipped[] = $c->name . ' (erreur)';
            }
        }

        AlertService::create(
            'campagne',
            'warning',
            '⚡ Action groupée — ' . $applied . ' campagne(s)',
            auth()->user()?->name . ' a effectué : ' . $data['action'] . ' sur ' . $applied . ' campagne(s).'
                . (!empty($skipped) ? ' Ignorées : ' . count($skipped) . '.' : ''),
            null
        );

        $verbs = [
            'start'  => 'démarrée(s)',
            'pause'  => 'mise(s) en pause',
            'resume' => 'réactivée(s)',
            'cancel' => 'annulée(s)',
            'delete' => 'supprimée(s)',
        ];
        $msg = "{$applied} campagne(s) " . ($verbs[$data['action']] ?? '') . '.';
        if (!empty($skipped)) {
            $msg .= ' ' . count($skipped) . ' ignorée(s) : ' . implode(', ', array_slice($skipped, 0, 5))
                  . (count($skipped) > 5 ? '…' : '');
        }
        return redirect()->route('admin.campaigns.index')->with('success', $msg);
    }

    // ══════════════════════════════════════════════════════════════
    // DESTROY
    // ══════════════════════════════════════════════════════════════
    public function destroy(Campaign $campaign)
    {
        $this->authorize('delete', $campaign);

        $name   = $campaign->name;
        $result = $this->campaignService->delete($campaign);

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        AlertService::create(
            'campagne',
            'danger',
            '🗑 Campagne supprimée — ' . $name,
            auth()->user()?->name . " a supprimé la campagne \"{$name}\"",
            null
        );

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Campagne supprimée définitivement.');
    }

    // ══════════════════════════════════════════════════════════════
    // UPDATE STATUS
    // ══════════════════════════════════════════════════════════════
    public function updateStatus(Request $request, Campaign $campaign)
    {
        $this->authorize('updateStatus', $campaign);

        $request->validate([
            'status' => ['required', Rule::enum(CampaignStatus::class)],
        ]);

        $newStatus = CampaignStatus::from($request->status);

        if (!$campaign->status->canTransitionTo($newStatus)) {
            return back()->with('error',
                "Transition interdite : {$campaign->status->label()} → {$newStatus->label()}.");
        }

        $userName = auth()->user()?->name;

        if ($newStatus === CampaignStatus::ANNULE) {
            $reasonKey   = $request->input('cancellation_reason', '');
            $cancelNotes = $request->input('cancellation_notes', '');
            $reasonLabels = [
                'budget'     => 'Budget insuffisant',
                'zone'       => 'Zone non pertinente',
                'strategie'  => 'Changement de stratégie',
                'report'     => 'Report de campagne',
                'concurrent' => 'Choix concurrent',
                'autre'      => 'Autre',
            ];
            $reasonLabel = $reasonLabels[$reasonKey] ?? '';
            $label = "Annulation manuelle par {$userName}" . ($reasonLabel ? " — {$reasonLabel}" : "");
            if ($cancelNotes) $label .= " : {$cancelNotes}";

            $this->campaignService->cancel($campaign, $label, $reasonKey ?: null, $cancelNotes ?: null);
            $alertLevel = 'danger';
            $alertIcon  = '🚫';
            $alertVerb  = 'a annulé';
        } elseif ($newStatus === CampaignStatus::TERMINE) {
            $this->campaignService->terminate($campaign, "Clôture manuelle par {$userName}");
            $alertLevel = 'info';
            $alertIcon  = '✅';
            $alertVerb  = 'a clôturé';
        } elseif ($newStatus === CampaignStatus::ACTIF) {
            // ── PLANIFIE → ACTIF ou PAUSE → ACTIF ─────────────────────
            // On route à travers CampaignService::activate() pour appliquer
            // les gardes (≥ 1 panneau) ET envoyer le mail client à la
            // première activation. Auparavant on faisait juste un
            // ->update(['status' => 'actif']) qui sautait ces deux étapes.
            $result = $this->campaignService->activate($campaign);
            if (!$result['ok']) {
                return back()->with('error', $result['error']);
            }
            $mailSent = !empty($result['mail_sent']);
            $alertLevel = 'info';
            $alertIcon  = '▶️';
            $alertVerb  = 'a activé';
        } else {
            // ⚠️ Anti double-booking : toute transition vers un statut BLOQUANT
            // (planifie / pause) doit re-valider que les panneaux de la
            // campagne ne sont pas déjà engagés ailleurs. Évite la résurrection
            // d'une campagne TERMINE dont l'ajout de panneaux bypass le check.
            if (in_array($newStatus->value, ['planifie', 'pause'], true)) {
                if ($err = $this->campaignService->detectConflictsOnCurrentPanels($campaign)) {
                    return back()->with('error', $err);
                }
            }
            $campaign->update([
                'status'     => $newStatus->value,
                'updated_by' => auth()->id(),
            ]);

            // Propagation aux panneaux : ACTIF → PAUSE doit faire passer
            // les panneaux d'OCCUPE à CONFIRME (le client a payé, la résa
            // est toujours active, mais plus de diffusion terrain). Sans
            // cette sync les panneaux restaient en OCCUPE en pause —
            // incohérent avec la légende admin "En affichage".
            try {
                $panelIds = $campaign->panels()->pluck('panels.id')->all();
                $extIds   = $campaign->externalPanels()->pluck('external_panels.id')->all();
                if (!empty($panelIds)) {
                    app(\App\Services\AvailabilityService::class)->syncPanelStatuses($panelIds);
                }
                if (!empty($extIds)) {
                    app(\App\Services\AvailabilityService::class)->syncExternalPanelStatuses($extIds);
                }
            } catch (\Throwable $e) {
                Log::warning('campaign.status_update.sync_failed', [
                    'campaign_id' => $campaign->id,
                    'new_status'  => $newStatus->value,
                    'error'       => $e->getMessage(),
                ]);
            }

            $alertLevel = 'info';
            $alertIcon  = '🔄';
            $alertVerb  = 'a changé le statut de';
        }

        AlertService::create(
            'campagne',
            $alertLevel,
            "{$alertIcon} Campagne — {$campaign->name}",
            "{$userName} {$alertVerb} la campagne \"{$campaign->name}\" → {$newStatus->label()}",
            $campaign
        );

        $msg = "Statut mis à jour : {$newStatus->label()}.";
        if (isset($mailSent) && $mailSent) {
            $msg .= ' 📧 Mail d\'annonce envoyé au client.';
        }

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', $msg);
    }

    // ══════════════════════════════════════════════════════════════
    // LIEN PIGE PUBLIC (token partageable au technicien)
    // ══════════════════════════════════════════════════════════════
    /**
     * Génère ou ré-utilise un token unique pour la campagne. Le commercial
     * partage le lien (WhatsApp/SMS/QR) au technicien terrain pour qu'il
     * uploade les photos via une page publique sans login.
     */
    public function generatePigeToken(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        if ($campaign->status->value === 'annule') {
            return back()->with('error', 'Impossible de générer un lien pour une campagne annulée.');
        }

        // Si un token existe déjà on le réutilise (idempotent) — l'admin
        // peut explicitement le réinitialiser via revokePigeToken si besoin.
        if (empty($campaign->pige_token)) {
            $campaign->update([
                'pige_token'            => \Illuminate\Support\Str::random(48),
                'pige_token_created_at' => now(),
            ]);
        }

        return back()->with('success', 'Lien pige actif. Partagez-le au technicien.');
    }

    /**
     * Révoque le token actuel — l'ancien lien ne fonctionne plus.
     * Un nouveau token sera généré au prochain appel à generate.
     */
    public function revokePigeToken(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $campaign->update([
            'pige_token'            => null,
            'pige_token_created_at' => null,
        ]);

        return back()->with('success', 'Lien pige révoqué. L\'ancien lien ne fonctionne plus.');
    }

    // ══════════════════════════════════════════════════════════════
    // PROLONGER
    // ══════════════════════════════════════════════════════════════
    public function prolonger(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        if ($campaign->status === CampaignStatus::ANNULE) {
            return back()->with('error', 'Impossible de prolonger une campagne annulée.');
        }

        $request->validate([
            'new_end_date' => [
                'required', 'date',
                'after:' . $campaign->end_date->format('Y-m-d'),
            ],
        ]);

        $newEndDate = \Carbon\Carbon::parse($request->new_end_date)->startOfDay();

        // Garde-fou durée maximale 36 mois
        if (abs($campaign->start_date->copy()->startOfDay()->diffInMonths($newEndDate)) > 36) {
            return back()->with('error',
                '❌ La durée totale dépasserait 36 mois (limite régie).');
        }

        // Vérifier conflits sur la période étendue
        $panelIds  = $campaign->panels->pluck('id')->toArray();
        $conflicts = $this->availability->getUnavailablePanelIds(
            $panelIds,
            $campaign->end_date->format('Y-m-d'),
            $newEndDate->format('Y-m-d'),
            $campaign->reservation_id
        );

        if (!empty($conflicts)) {
            $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
            return back()->with('error',
                "Impossible de prolonger — conflits sur la nouvelle période : {$refs}");
        }

        $oldEnd = $campaign->end_date->format('d/m/Y');
        $newEnd = $newEndDate->format('d/m/Y');

        DB::transaction(function () use ($campaign, $newEndDate) {
            $campaign->update([
                'end_date'   => $newEndDate->format('Y-m-d'),
                'status'     => CampaignStatus::ACTIF->value,
                'updated_by' => auth()->id(),
            ]);

            if ($campaign->reservation) {
                $campaign->reservation->updateWithoutObservers([
                    'end_date' => $newEndDate->format('Y-m-d'),
                ]);
            }

            $this->campaignService->recalculateCampaignAmount($campaign->fresh());
        });

        Log::info('campaign.prolonged', [
            'campaign_id'  => $campaign->id,
            'old_end_date' => $oldEnd,
            'new_end_date' => $newEnd,
            'user_id'      => auth()->id(),
        ]);

        AlertService::create(
            'campagne',
            'info',
            '📅 Campagne prolongée — ' . $campaign->name,
            auth()->user()?->name . " a prolongé la campagne \"{$campaign->name}\" jusqu'au {$newEnd}",
            $campaign
        );

        return back()->with('success', "Campagne prolongée jusqu'au {$newEnd}. Montant recalculé.");
    }

    // ══════════════════════════════════════════════════════════════
    // EXPORT EXCEL — applique les mêmes filtres que l'index
    // ══════════════════════════════════════════════════════════════
    public function exportExcel(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);

        $filters = $request->only([
            'search', 'status', 'client_id',
            'date_debut', 'date_fin', 'date_from', 'date_to',
        ]);

        // Export d'une sélection précise (cases cochées dans l'index).
        // Prioritaire sur les filtres : si ids[] est fourni, on exporte
        // exactement ces campagnes-là, indépendamment des filtres actifs.
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        if (!empty($ids)) {
            $filters = ['ids' => $ids];
        }

        $filename = 'campagnes-' . now()->format('Ymd-His') . '.xlsx';

        Log::info('campaigns.export.excel', [
            'filters' => $filters,
            'user_id' => auth()->id(),
        ]);

        return Excel::download(new CampaignsExport($filters), $filename);
    }

    // ══════════════════════════════════════════════════════════════
    /**
     * 2026-06-22 — Fiche de pose PDF d'UNE campagne.
     *
     * À transmettre aux équipes terrain : nom campagne + client + période
     * + liste des panneaux avec photo, commune, format, date de pose
     * prévue (depuis PoseTask) et technicien/équipe assigné.
     *
     * Pose en retard = scheduled_at < PoseTask::lateThreshold() (today - LATE_GRACE_DAYS).
     */
    public function fichePosePdf(Campaign $campaign, Request $request)
    {
        $this->authorize('view', $campaign);

        // 2026-06-25 — Mode "list" (compact, sans photos, format paysage)
        // ou "cards" (par défaut, avec photo de chaque panneau).
        // Permet d'imprimer une liste rapide pour les équipes terrain
        // ou un dossier détaillé selon le besoin.
        $mode = $request->input('mode', 'cards') === 'list' ? 'list' : 'cards';

        $relations = [
            'client:id,name',
            'panels' => fn ($q) => $q->orderBy('reference'),
            'panels.commune:id,name',
            'panels.format:id,name',
            'poseTasks' => fn ($q) => $q->with('technicien:id,name'),
        ];
        // On ne charge les photos que pour le mode cartes (gain perf en mode liste).
        if ($mode === 'cards') {
            $relations['panels.photos'] = fn ($q) => $q->orderBy('ordre');
        }
        $campaign->load($relations);

        // Map panel_id → la PoseTask la plus récente (1 panneau peut avoir
        // plusieurs poses si re-planifiée). On garde la plus récente non
        // annulée pour afficher le technicien/équipe assigné.
        $poseByPanel = $campaign->poseTasks
            ->sortByDesc('scheduled_at')
            ->groupBy('panel_id')
            ->map(fn ($group) => $group->reject(fn ($pt) => $pt->status === 'annulee')->first() ?? $group->first());

        $viewName = $mode === 'list'
            ? 'admin.campaigns.fiche-pose-pdf-liste'
            : 'admin.campaigns.fiche-pose-pdf';
        $paperOrientation = $mode === 'list' ? 'landscape' : 'portrait';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($viewName, [
            'campaign'    => $campaign,
            'panels'      => $campaign->panels,
            'poseByPanel' => $poseByPanel,
            'user'        => $request->user(),
        ])->setPaper('a4', $paperOrientation);

        $slug = \Illuminate\Support\Str::slug($campaign->name);
        $suffix = $mode === 'list' ? '-liste' : '';
        return $pdf->download('fiche-pose' . $suffix . '-' . $slug . '-' . now()->format('Ymd') . '.pdf');
    }

    // EXPORT PDF — liste filtrée, format A4 paysage
    // ══════════════════════════════════════════════════════════════
    public function exportPdf(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);

        // Export d'une sélection précise (cases cochées) — prioritaire sur
        // les filtres : si ids[] est fourni, on exporte exactement ces
        // campagnes, sinon on applique les filtres de l'index.
        $selectedIds = array_filter(array_map('intval', (array) $request->input('ids', [])));

        $query = Campaign::with(['client:id,name', 'user:id,name'])
            ->withCount('panels')
            ->when(!empty($selectedIds), fn($q) => $q->whereIn('id', $selectedIds))
            ->when(empty($selectedIds), fn($q) => $q
                ->when($request->search,      fn($q, $s)  => $q->where('name', 'like', "%{$s}%"))
                ->when($request->client_id,   fn($q, $id) => $q->where('client_id', $id))
                ->when($request->status,      fn($q, $s)  => $q->where('status', $s))
                ->when($request->date_debut,  fn($q, $d)  => $q->where('start_date', '>=', $d))
                ->when($request->date_fin,    fn($q, $d)  => $q->where('start_date', '<=', $d))
                ->when($request->date_from,   fn($q, $d)  => $q->where('start_date', '>=', $d))
                ->when($request->date_to,     fn($q, $d)  => $q->where('end_date', '<=', $d))
            )
            ->orderByDesc('created_at')
            ->limit(2000); // garde-fou perf : pas plus de 2000 lignes par PDF

        $campaigns = $query->get();
        $logoSrc   = $this->getLogoPdf();
        $generated = now()->format('d/m/Y à H:i');
        $totalAmount = (float) $campaigns->sum('total_amount');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.campaigns.pdf.list', compact(
            'campaigns', 'logoSrc', 'generated', 'totalAmount'
        ))->setPaper('a4', 'landscape')->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'DejaVu Sans',
            'dpi'                  => 96,
        ]);

        Log::info('campaigns.export.pdf', [
            'count'   => $campaigns->count(),
            'user_id' => auth()->id(),
        ]);

        return $pdf->download('campagnes-' . now()->format('Ymd-His') . '.pdf');
    }

    /**
     * Envoie au commercial nouvellement assigné une notif email
     * (lien direct vers la fiche campagne). Non-bloquant.
     */
    protected function notifyCommercialAssigned(Campaign $campaign): void
    {
        $commercial = $campaign->commercial;
        if (!$commercial?->email) {
            Log::info('campaign.assign.skipped', [
                'campaign_id' => $campaign->id,
                'reason'      => 'no_email',
            ]);
            return;
        }

        try {
            app(\App\Services\NotificationMailer::class)->sendSilently(
                $commercial->email,
                new \App\Mail\CampaignAssignedMail($campaign, auth()->user()),
                cc: null,
                context: [
                    'campaign_id'        => $campaign->id,
                    'commercial_user_id' => $commercial->id,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('campaign.assign.mail_failed', [
                'campaign_id' => $campaign->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
