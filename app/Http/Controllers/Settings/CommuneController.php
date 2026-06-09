<?php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use Illuminate\Http\Request;

class CommuneController extends Controller
{
    public function create()
    {
        return view('settings.communes.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'city'     => 'nullable|string|max:100',
            'region'   => 'nullable|string|max:100',
            'odp_rate' => 'nullable|numeric|min:0',
            'tm_rate'  => 'nullable|numeric|min:0',
        ]);

        $data['odp_rate'] = $data['odp_rate'] ?? 0;
        $data['tm_rate']  = $data['tm_rate']  ?? 0;

        Commune::create($data);

        return redirect()->route('admin.settings.index')
            ->with('success', 'Commune créée avec succès !');
    }

    public function update(Request $request, Commune $commune)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'city'     => 'nullable|string|max:100',
            'region'   => 'nullable|string|max:100',
            'odp_rate' => 'nullable|numeric|min:0',
            'tm_rate'  => 'nullable|numeric|min:0',
        ]);

        $data['odp_rate'] = $data['odp_rate'] ?? 0;
        $data['tm_rate']  = $data['tm_rate']  ?? 0;

        $commune->update($data);

        // L'observer CommuneObserver historise automatiquement les
        // changements de tarif — on précise juste ça à l'admin si un
        // tarif a effectivement bougé.
        $changed = array_intersect_key($commune->getChanges(), array_flip(['odp_rate', 'tm_rate']));
        $msg = 'Commune modifiée avec succès !';
        if (!empty($changed)) {
            $msg .= ' Tarifs historisés (les calculs rétroactifs gardent les anciennes valeurs).';
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->route('admin.settings.index')
            ->with('success', $msg);
    }

    public function destroy(Commune $commune)
    {
        $commune->delete();
        return redirect()->route('admin.settings.index')
            ->with('success', 'Commune supprimée !');
    }

    // ══════════════════════════════════════════════════════════════
    // TARIFS COMMUNAUX (ODP/TM) — gestion dédiée admin
    //
    // Vue d'ensemble du référentiel tarifaire utilisé pour la
    // facturation FNE. L'admin peut :
    //   - voir le tarif courant de chaque commune (ODP + TM)
    //   - consulter l'historique tarifaire complet (effective_from/to)
    //   - programmer un nouveau tarif pour une année future
    //     (effective_from 1er janvier N+1, par exemple) sans toucher
    //     au tarif courant. Le système l'utilisera automatiquement
    //     dès la date d'effet, via Commune::ratesAt().
    // ══════════════════════════════════════════════════════════════
    public function tariffs()
    {
        $communes = Commune::query()
            ->orderBy('name')
            ->withCount(['rateHistory'])
            ->get(['id', 'name', 'odp_rate', 'tm_rate', 'updated_at']);

        $today = now()->toDateString();

        // Pour chaque commune, on dérive :
        //   - last_change : date du dernier changement de tarif
        //   - next_change : tarif programmé futur (effective_from > today)
        $communes->each(function ($c) use ($today) {
            $last = $c->rateHistory()
                ->orderByDesc('effective_from')->first();
            $c->last_change_date = $last?->effective_from;
            $c->last_change_notes = $last?->notes;

            $future = $c->rateHistory()
                ->where('effective_from', '>', $today)
                ->orderBy('effective_from')->first();
            $c->next_change = $future ? [
                'date'      => $future->effective_from->format('d/m/Y'),
                'odp_rate'  => (float) $future->odp_rate,
                'tm_rate'   => (float) $future->tm_rate,
            ] : null;
        });

        return view('admin.settings.communes-tariffs', compact('communes'));
    }

    /**
     * Historique tarifaire d'une commune (JSON pour modal AJAX).
     * Renvoie toutes les lignes triées par effective_from desc.
     */
    public function tariffHistory(Commune $commune)
    {
        $history = $commune->rateHistory()
            ->with('createdBy:id,name')
            ->orderByDesc('effective_from')
            ->get(['id', 'commune_id', 'odp_rate', 'tm_rate',
                   'effective_from', 'effective_to', 'notes', 'created_by', 'created_at']);

        return response()->json([
            'commune' => ['id' => $commune->id, 'name' => $commune->name],
            'history' => $history->map(fn($h) => [
                'id'              => $h->id,
                'odp_rate'        => (float) $h->odp_rate,
                'tm_rate'         => (float) $h->tm_rate,
                'effective_from'  => $h->effective_from?->format('Y-m-d'),
                'effective_from_h'=> $h->effective_from?->format('d/m/Y'),
                'effective_to'    => $h->effective_to?->format('Y-m-d'),
                'effective_to_h'  => $h->effective_to?->format('d/m/Y'),
                'is_current'      => $h->effective_to === null
                                     || $h->effective_to->isFuture(),
                'is_future'       => $h->effective_from?->isFuture() ?? false,
                'notes'           => $h->notes,
                'created_by'      => $h->createdBy?->name,
                'created_at'      => $h->created_at?->format('d/m/Y H:i'),
            ]),
        ]);
    }

    /**
     * Programme un nouveau tarif pour une date d'effet future
     * (ex: 1er janvier 2026 pour préparer l'année à l'avance).
     *
     * Crée une ligne dans commune_tax_rate_history sans toucher
     * communes.odp_rate / tm_rate (qui restent le tarif COURANT).
     * Quand effective_from sera atteint, Commune::ratesAt() prendra
     * automatiquement le nouveau tarif.
     */
    public function scheduleTariff(Request $request, Commune $commune)
    {
        $data = $request->validate([
            'effective_from' => 'required|date|after:today',
            'odp_rate'       => 'required|numeric|min:0',
            'tm_rate'        => 'required|numeric|min:0',
            'notes'          => 'nullable|string|max:500',
        ], [
            'effective_from.after' => 'La date d\'effet doit être dans le futur. Pour modifier le tarif courant, édite directement la commune.',
        ]);

        // Idempotence : si une ligne existe déjà avec la même
        // effective_from, on la met à jour au lieu de dupliquer.
        $existing = \App\Models\CommuneTaxRateHistory::where('commune_id', $commune->id)
            ->whereDate('effective_from', $data['effective_from'])
            ->first();

        $payload = [
            'commune_id'     => $commune->id,
            'odp_rate'       => $data['odp_rate'],
            'tm_rate'        => $data['tm_rate'],
            'effective_from' => $data['effective_from'],
            'effective_to'   => null,
            'created_by'     => auth()->id(),
            'notes'          => $data['notes'] ?? '[Manuel] Tarif programmé par admin',
        ];

        if ($existing) {
            $existing->update($payload);
            $msg = "Tarif programmé mis à jour pour {$commune->name} (effectif {$data['effective_from']}).";
        } else {
            \App\Models\CommuneTaxRateHistory::create($payload);
            $msg = "Tarif programmé pour {$commune->name} à partir du " . \Carbon\Carbon::parse($data['effective_from'])->format('d/m/Y') . '.';
        }

        return back()->with('success', '✅ ' . $msg);
    }

    /**
     * Supprime une ligne d'historique tarifaire FUTURE (programmation
     * encore non effective). On ne permet pas de supprimer une ligne
     * historique passée — c'est un journal fiscal, modifications
     * tracées ailleurs.
     */
    public function deleteTariffEntry(Commune $commune, \App\Models\CommuneTaxRateHistory $entry)
    {
        if ($entry->commune_id !== $commune->id) abort(404);

        if (!$entry->effective_from || !$entry->effective_from->isFuture()) {
            return back()->with('error',
                'Seuls les tarifs FUTURS peuvent être supprimés. Les tarifs passés font partie du journal fiscal.'
            );
        }

        $entry->delete();
        return back()->with('success', 'Tarif programmé supprimé.');
    }
}
