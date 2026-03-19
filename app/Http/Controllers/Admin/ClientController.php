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
        $query = Client::query()
            ->withCount('campaigns')
            ->withCount(['campaigns as active_campaigns_count' => fn($q) =>
                $q->where('status', 'actif')
            ])
            ->withCount('reservations')
            ->when($request->search, fn($q) =>
                $q->search($request->search)
            )
            ->when($request->sector, fn($q, $sector) =>
                $q->where('sector', $sector)
            );

        // Tri
        $sort      = $request->get('sort', 'name');
        $direction = $request->get('direction', 'asc');
        $allowed   = ['name', 'created_at', 'campaigns_count'];

        if (in_array($sort, $allowed)) {
            $query->orderBy($sort, $direction === 'desc' ? 'desc' : 'asc');
        }

        $clients = $query->paginate(20)->withQueryString();
        $sectors = Client::SECTORS;

        // Stats globales — 1 requête agrégée
        $stats = [
            'total'  => Client::count(),
            'actifs' => Client::whereHas('campaigns', fn($q) => $q->where('status', 'actif'))->count(),
        ];

        return view('admin.clients.index', compact('clients', 'sectors', 'stats'));
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

        return view('admin.clients.show', compact('client', 'totalFacture', 'sectors'));
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