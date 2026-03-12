<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;

class ClientController extends Controller
{
    // Liste des clients avec recherche et pagination
    public function index()
    {
        $clients = Client::query()
            ->withCount('campaigns')
            ->withCount(['campaigns as active_panels_count' => function ($q) {
                $q->where('status', 'actif');
            }])
            ->withSum('invoices', 'amount_ttc')
            ->when(request('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->when(request('sector'), function ($query, $sector) {
                $query->where('sector', $sector);
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $sectors = Client::whereNotNull('sector')
            ->distinct()
            ->pluck('sector')
            ->sort()
            ->values();

        return view('admin.clients.index', compact('clients', 'sectors'));
    }

    // Formulaire de création
    public function create()
    {
        $sectors = Client::whereNotNull('sector')
            ->distinct()
            ->pluck('sector')
            ->sort()
            ->values();

        return view('admin.clients.create', compact('sectors'));
    }

    // Enregistrement d'un nouveau client
    public function store(StoreClientRequest $request)
    {
        Client::create($request->validated());

        return redirect()
            ->route('admin.clients.index')
            ->with('success', 'Client créé avec succès.');
    }

    // Détail d'un client
    public function show(Client $client)
    {
        $client->load(['reservations', 'campaigns', 'invoices']);

        return view('admin.clients.show', compact('client'));
    }

    // Formulaire d'édition
    public function edit(Client $client)
    {
        $sectors = Client::whereNotNull('sector')
            ->distinct()
            ->pluck('sector')
            ->sort()
            ->values();

        return view('admin.clients.edit', compact('client', 'sectors'));
    }

    // Mise à jour d'un client
    public function update(UpdateClientRequest $request, Client $client)
    {
        $client->update($request->validated());

        return redirect()
            ->route('admin.clients.index')
            ->with('success', 'Client mis à jour avec succès.');
    }

    // Suppression (soft delete)
    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()
            ->route('admin.clients.index')
            ->with('success', 'Client supprimé avec succès.');
    }
}