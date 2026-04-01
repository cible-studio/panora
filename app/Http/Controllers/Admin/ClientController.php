<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{

    public function index(Request $request)
    {
        $query = Client::withCount(['campaigns', 'reservations'])
            ->with(['campaigns' => function($q) {
                $q->whereIn('status', ['actif', 'pose']);
            }]);

        // Filtres
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('ncc', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
                ->orWhere('contact_name', 'like', "%{$request->search}%")
                ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }
        if ($request->sector) {
            $query->where('sector', $request->sector);
        }

        // Tri
        $sort = $request->sort ?? 'name';
        $query->orderBy($sort, $sort === 'name' ? 'asc' : 'desc');

        // Statistiques
        $stats = [
            'total' => Client::count(),
            'actifs' => Client::whereHas('campaigns', function($q) {
                $q->whereIn('status', ['actif', 'pose']);
            })->count(),
            'ca_total' => Client::with('campaigns')->get()->sum(function($client) {
                return $client->campaigns->sum('total_amount');
            }),
        ];

        $clients = $query->paginate(20)->withQueryString();
        $sectors = Client::SECTORS;

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.clients.partials.table-rows', compact('clients'))->render(),
                'pagination' => $clients->links()->render(),
                'total' => $clients->total(),
            ]);
        }

        return view('admin.clients.index', compact('clients', 'stats', 'sectors'));
    }

    public function create()
    {
        $sectors = Client::SECTORS;
        return view('admin.clients.create', compact('sectors'));
    }

    public function store(StoreClientRequest $request)
    {
        $client = Client::create($request->validated());

        Log::info('client.created', [
            'client_id' => $client->id,
            'ncc'       => $client->ncc,
            'user_id'   => auth()->id(),
        ]);

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', "Client {$client->name} créé avec succès. NCC : {$client->ncc}");
    }

    public function show(Client $client)
    {
        $client->load([
            'reservations' => fn($q) => $q->latest()->limit(5),
            'campaigns'    => fn($q) => $q->latest()->limit(8),
            'invoices'     => fn($q) => $q->latest()->limit(5),
        ]);

        // Montant total facturé — requête directe sans N+1
        $totalFacture = $client->invoices()->sum('amount_ttc');

        $sectors = Client::SECTORS;

        // ── Inventaire panneaux du client ──────────────────────────
        // Panneaux via réservations
        $panneauxReservations = \App\Models\ReservationPanel::with([
                'panel.commune', 'panel.format', 'reservation'
            ])
            ->whereHas('reservation', fn($q) => $q->where('client_id', $client->id))
            ->get()
            ->map(fn($rp) => [
                'panel'      => $rp->panel,
                'source'     => 'reservation',
                'reference_source' => $rp->reservation->reference ?? '—',
                'source_id'  => $rp->reservation->id,
                'start_date' => $rp->reservation->start_date,
                'end_date'   => $rp->reservation->end_date,
                'status'     => $rp->reservation->status->value ?? 'inconnu',
                'status_label' => $rp->reservation->status->label() ?? '—',
            ]);

        // Panneaux via campagnes
        $panneauxCampagnes = \App\Models\CampaignPanel::with([
                'panel.commune', 'panel.format', 'campaign'
            ])
            ->where('type', 'interne')
            ->whereHas('campaign', fn($q) => $q->where('client_id', $client->id))
            ->get()
            ->map(fn($cp) => [
                'panel'      => $cp->panel,
                'source'     => 'campaign',
                'reference_source' => $cp->campaign->name ?? '—',
                'source_id'  => $cp->campaign->id,
                'start_date' => $cp->campaign->start_date,
                'end_date'   => $cp->campaign->end_date,
                'status'     => $cp->campaign->status->value ?? 'inconnu',
                'status_label' => $cp->campaign->status->label() ?? '—',
            ]);

        // Fusionner + dédoublonner par panel_id (priorité campagne > reservation)
        $panneauxClient = $panneauxCampagnes->concat($panneauxReservations)
            ->unique(fn($item) => $item['panel']?->id . '-' . $item['source_id'])
            ->filter(fn($item) => $item['panel'] !== null)
            ->sortBy('panel.reference')
            ->values();

        return view('admin.clients.show', compact(
            'client', 'totalFacture', 'sectors', 'panneauxClient'
        ));
    }

    public function edit(Client $client)
    {
        $sectors = Client::SECTORS;
        return view('admin.clients.edit', compact('client', 'sectors'));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $client->update($request->validated());

        Log::info('client.updated', [
            'client_id' => $client->id,
            'user_id'   => auth()->id(),
        ]);

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Client mis à jour avec succès.');
    }

    public function destroy(Client $client)
    {
        // Bloquer suppression si campagnes actives
        if ($client->hasActiveCampaigns()) {
            return back()->with('error',
                'Impossible de supprimer ce client : il a des campagnes actives en cours.'
            );
        }

        $name = $client->name;
        $client->delete();

        Log::info('client.deleted', [
            'client_id'   => $client->id,
            'client_name' => $name,
            'user_id'     => auth()->id(),
        ]);

        return redirect()
            ->route('admin.clients.index')
            ->with('success', "Client {$name} supprimé.");
    }
}
