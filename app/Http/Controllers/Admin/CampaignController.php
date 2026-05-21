<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Services\CampaignService;
use App\Services\AvailabilityService;
use App\Services\AlertService;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\Invoice;
use App\Models\Panel;
use App\Models\PanelFormat;
use App\Models\Reservation;
use App\Models\Zone;

use App\Enums\CampaignStatus;
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
            // RBAC : un commercial ne voit que SES campagnes (via la résa
            // source ou en fallback campaign.user_id).
            ->when(auth()->user()?->role?->value === 'commercial', function ($q) {
                $uid = auth()->id();
                $q->where(function ($qq) use ($uid) {
                    $qq->whereHas('reservation', fn($r) =>
                            $r->where('commercial_user_id', $uid)
                              ->orWhere(function ($rr) use ($uid) {
                                  $rr->whereNull('commercial_user_id')
                                     ->where('user_id', $uid);
                              })
                       )
                       ->orWhere(function ($qqq) use ($uid) {
                           // Campagnes manuelles sans réservation source : on
                           // se rabat sur le créateur de la campagne.
                           $qqq->whereDoesntHave('reservation')
                               ->where('user_id', $uid);
                       });
                });
            })
            ->when($request->search,      fn($q, $s)  => $q->where('name', 'like', "%{$s}%"))
            ->when($request->client_id,   fn($q, $id) => $q->where('client_id', $id))
            // Filtres date originaux : date_from (start) / date_to (end)
            ->when($request->date_from,   fn($q, $d)  => $q->where('start_date', '>=', $d))
            ->when($request->date_to,     fn($q, $d)  => $q->where('end_date', '<=', $d))
            // T12 : Filtre période personnalisée (start_date BETWEEN date_debut AND date_fin)
            ->when($request->date_debut,  fn($q, $d)  => $q->where('start_date', '>=', $d))
            ->when($request->date_fin,    fn($q, $d)  => $q->where('start_date', '<=', $d))
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

        $nonFactureesCount = Campaign::nonFacturees()->count();

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

        return view('admin.campaigns.index', compact(
            'campaigns', 'counts', 'nonFactureesCount', 'endingSoonCount',
            'clients', 'communes', 'zones'
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
            'externalPanels.commune:id,name',
            'externalPanels.format:id,name',
            'externalPanels.agency:id,name',
            'invoices:id,campaign_id,reference,amount_ttc',
        ]);

        $user = auth()->user();
        $canManagePanel = $user->can('managePanel', $campaign)
            && in_array($campaign->status->value, ['planifie', 'actif']);

        $can = [
            'update'       => $user->can('update', $campaign),
            'updateStatus' => $user->can('updateStatus', $campaign),
            'managePanel'  => $canManagePanel,
            'delete'       => $user->can('delete', $campaign),
        ];

        $allowed = $campaign->status->allowedTransitionsLabels();

        // Panneaux disponibles : chargés en AJAX à l'ouverture du modal (cf. méthode availablePanels())
        // pour ne pas pénaliser le rendu initial de la page.

        return view('admin.campaigns.show', compact('campaign', 'can', 'allowed'));
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
     * Endpoint JSON pour charger les panneaux disponibles à l'ouverture du modal d'ajout.
     * Évite de précharger 200 panneaux à chaque rendu de la page show.
     */
    public function availablePanels(Campaign $campaign)
    {
        $this->authorize('managePanel', $campaign);

        if (!in_array($campaign->status->value, ['planifie', 'actif'])) {
            return response()->json(['panels' => []]);
        }

        $existingIds = $campaign->panels()->pluck('panels.id')->all();

        $panels = $this->availability
            ->getAvailablePanels(
                $campaign->start_date->format('Y-m-d'),
                $campaign->end_date->format('Y-m-d'),
                $campaign->reservation_id
            )
            ->reject(fn($p) => in_array($p->id, $existingIds))
            ->take(500)
            ->map(fn($p) => [
                'id'           => $p->id,
                'reference'    => $p->reference,
                'name'         => $p->name,
                'commune'      => $p->commune?->name ?? '',
                'format'       => $p->format?->name ?? '',
                'monthly_rate' => (float) ($p->monthly_rate ?? 0),
                'is_lit'       => (bool) $p->is_lit,
            ])
            ->values();

        return response()->json([
            'panels'           => $panels,
            'campaign_months'  => $campaign->billableMonths(),
        ]);
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
            ->whereIn('role', ['admin', 'commercial', 'mediaplanner'])
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'email']);

        $preselectedReservation = null;
        if ($request->filled('reservation_id')) {
            $preselectedReservation = Reservation::with(['client', 'panels', 'externalPanels'])
                ->where('status', 'confirme')
                ->whereDoesntHave('campaign')
                ->find($request->reservation_id);
        }

        return view('admin.campaigns.create',
            compact('clients', 'reservations', 'preselectedReservation', 'commerciaux'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Campaign::class);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('campaigns')->where('client_id', $request->client_id),
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

                // Statut initial dérivé des dates
                $today = now()->startOfDay();
                $start = \Carbon\Carbon::parse($data['start_date'])->startOfDay();
                $data['status'] = $start->gt($today)
                    ? CampaignStatus::PLANIFIE->value
                    : CampaignStatus::ACTIF->value;

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

            // Email au client après création (hors transaction pour ne pas
            // bloquer si SMTP lent). Pas d'envoi si la campagne a 0 panneau
            // (rare, mais le service le gère via sendSilently).
            $mailSent = false;
            try {
                $mailSent = $this->campaignService->sendStartedMailToClient($campaign);
            } catch (\Throwable $e) {
                Log::warning('campaign.created.mail_failed', [
                    'campaign_id' => $campaign->id,
                    'error'       => $e->getMessage(),
                ]);
            }

            // Notif commercial assigné si différent du créateur
            if ($campaign->commercial_user_id && $campaign->commercial_user_id !== auth()->id()) {
                $this->notifyCommercialAssigned($campaign);
            }

            $msg = "Campagne « {$campaign->name} » créée avec succès.";
            if ($mailSent) {
                $msg .= ' Email envoyé au client.';
            } elseif ($campaign->client?->email) {
                $msg .= ' (envoi email au client échoué — vérifie les logs)';
            }

            return redirect()
                ->route('admin.campaigns.show', $campaign)
                ->with('success', $msg);

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
            ->whereIn('role', ['admin', 'commercial', 'mediaplanner'])
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'email']);
        return view('admin.campaigns.edit', compact('campaign', 'clients', 'commerciaux'));
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

        // Statut recalculé en fonction des nouvelles dates
        $data['status'] = $this->calculateStatus($newStart, $newEnd, $campaign->status);

        $oldStart       = $campaign->start_date;
        $oldEnd         = $campaign->end_date;
        $oldStatus      = $campaign->status;
        $oldCommercial  = $campaign->commercial_user_id;

        $data['updated_by'] = auth()->id();

        DB::transaction(function () use ($campaign, $data) {
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

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', $message);
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

        $data = $request->validate([
            'panel_ids'        => 'required|array|min:1|max:50',
            'panel_ids.*'      => 'required|integer|exists:panels,id',
            // Prix négocié optionnel par panneau (clé = panel_id)
            'unit_prices'      => 'nullable|array',
            'unit_prices.*'    => 'nullable|numeric|min:0',
        ]);

        if (!in_array($campaign->status->value, ['planifie', 'actif'])) {
            return back()->with('error',
                'Impossible d\'ajouter des panneaux à une campagne en pause, terminée ou annulée.');
        }

        // Filtre les prix custom (skip ceux à null/vide → fallback monthly_rate)
        $unitPrices = null;
        if (!empty($data['unit_prices'])) {
            $unitPrices = [];
            foreach ($data['unit_prices'] as $panelId => $price) {
                if ($price !== null && $price !== '') {
                    $unitPrices[(int) $panelId] = (float) $price;
                }
            }
        }

        $result = $this->campaignService->addPanels($campaign, $data['panel_ids'], $unitPrices);

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        $count = $result['added'] ?? count($data['panel_ids']);
        $posesCreated = $result['poses_created'] ?? 0;

        AlertService::create(
            'campagne',
            'info',
            '➕ Panneau ajouté — ' . $campaign->name,
            auth()->user()?->name . " a ajouté {$count} panneau(x) à la campagne \"{$campaign->name}\""
            . ($posesCreated > 0 ? " — {$posesCreated} tâche(s) de pose auto-créée(s)" : ''),
            $campaign
        );

        $msg = "{$count} panneau(x) ajouté(s). Montant recalculé.";
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

        if (!in_array($campaign->status->value, ['planifie', 'actif'])) {
            return back()->with('error', 'Impossible de modifier le prix sur une campagne en pause, terminée ou annulée.');
        }

        if (!$campaign->reservation_id) {
            return back()->with('error', 'Aucune réservation associée à cette campagne. Le prix ne peut être modifié.');
        }

        // Vérifie que le panneau est bien dans la campagne (sécurité)
        if (!$campaign->panels()->where('panels.id', $panel->id)->exists()) {
            return back()->with('error', 'Ce panneau n\'est pas dans la campagne.');
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

        // Recalcule via la SDV (somme des pivots de la résa)
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
            'action'        => 'required|in:pause,resume,cancel,delete',
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
                    case 'pause':
                        if ($statusValue !== \App\Enums\CampaignStatus::ACTIF->value) {
                            $skipped[] = $c->name . ' (pas actif)';
                            continue 2;
                        }
                        $c->update(['status' => \App\Enums\CampaignStatus::PAUSE->value]);
                        $applied++;
                        break;
                    case 'resume':
                        if ($statusValue !== \App\Enums\CampaignStatus::PAUSE->value) {
                            $skipped[] = $c->name . ' (pas en pause)';
                            continue 2;
                        }
                        $c->update(['status' => \App\Enums\CampaignStatus::ACTIF->value]);
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
        } else {
            $campaign->update([
                'status'     => $newStatus->value,
                'updated_by' => auth()->id(),
            ]);
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

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', "Statut mis à jour : {$newStatus->label()}.");
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

        $filename = 'campagnes-' . now()->format('Ymd-His') . '.xlsx';

        Log::info('campaigns.export.excel', [
            'filters' => $filters,
            'user_id' => auth()->id(),
        ]);

        return Excel::download(new CampaignsExport($filters), $filename);
    }

    // ══════════════════════════════════════════════════════════════
    // EXPORT PDF — liste filtrée, format A4 paysage
    // ══════════════════════════════════════════════════════════════
    public function exportPdf(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);

        $query = Campaign::with(['client:id,name', 'user:id,name'])
            ->withCount('panels')
            ->when($request->search,      fn($q, $s)  => $q->where('name', 'like', "%{$s}%"))
            ->when($request->client_id,   fn($q, $id) => $q->where('client_id', $id))
            ->when($request->status,      fn($q, $s)  => $q->where('status', $s))
            ->when($request->date_debut,  fn($q, $d)  => $q->where('start_date', '>=', $d))
            ->when($request->date_fin,    fn($q, $d)  => $q->where('start_date', '<=', $d))
            ->when($request->date_from,   fn($q, $d)  => $q->where('start_date', '>=', $d))
            ->when($request->date_to,     fn($q, $d)  => $q->where('end_date', '<=', $d))
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
