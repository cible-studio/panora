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
            // Panneaux éclairés (2026-11-18) : vide = même tarif.
            'odp_rate_eclaire' => 'nullable|numeric|min:0',
            'tm_rate_eclaire'  => 'nullable|numeric|min:0',
        ]);

        $data['odp_rate'] = $data['odp_rate'] ?? 0;
        $data['tm_rate']  = $data['tm_rate']  ?? 0;

        $commune = Commune::create($data);

        return $this->redirectToSettingsRecord($commune, 'communes')
            ->with('success', 'Commune « ' . $commune->name . ' » créée avec succès !');
    }

    public function update(Request $request, Commune $commune)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'city'     => 'nullable|string|max:100',
            'region'   => 'nullable|string|max:100',
            'odp_rate' => 'nullable|numeric|min:0',
            'tm_rate'  => 'nullable|numeric|min:0',
            // Panneaux éclairés (2026-11-18) : vide = même tarif. Le
            // formulaire qui ne les envoie pas ne les efface pas.
            'odp_rate_eclaire' => 'sometimes|nullable|numeric|min:0',
            'tm_rate_eclaire'  => 'sometimes|nullable|numeric|min:0',
        ]);

        $data['odp_rate'] = $data['odp_rate'] ?? 0;
        $data['tm_rate']  = $data['tm_rate']  ?? 0;

        $commune->update($data);

        // L'observer CommuneObserver historise automatiquement les
        // changements de tarif — on précise juste ça à l'admin si un
        // tarif a effectivement bougé.
        $changed = array_intersect_key($commune->getChanges(), array_flip(\App\Observers\CommuneObserver::COLONNES_TARIF));
        $msg = 'Commune modifiée avec succès !';
        if (!empty($changed)) {
            $msg .= ' Tarifs historisés (les calculs rétroactifs gardent les anciennes valeurs).';
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return $this->redirectToSettingsRecord($commune, 'communes')
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
            ->get(['id', 'name', 'odp_rate', 'tm_rate', 'odp_rate_eclaire', 'tm_rate_eclaire', 'updated_at']);

        $today = now()->toDateString();

        // Pour chaque commune, on dérive :
        //   - last_change : date du dernier changement de tarif
        //   - next_change : tarif programmé futur (effective_from > today)
        $communes->each(function ($c) use ($today) {
            // 2026-11-27 (point 6 validé) — Tarif EN VIGUEUR aujourd'hui (même
            // source que les calculs), et non la colonne de la fiche, qui ne
            // suit pas un tarif programmé quand sa date arrive.
            $c->tarif_courant = $c->tarifsEnVigueur($today);

            // Dernier changement : le passé seulement (avant : un tarif
            // programmé affichait sa date future ici).
            $last = $c->rateHistory()
                ->whereDate('effective_from', '<=', $today)
                ->orderByDesc('effective_from')->first();
            $c->last_change_date = $last?->effective_from;
            $c->last_change_notes = $last?->notes;
            $c->last_change_origine = $last && $last->effective_from?->toDateString() === \App\Observers\CommuneObserver::DEBUT_SANS_HISTORIQUE;

            $future = $c->rateHistory()
                ->where('effective_from', '>', $today)
                ->orderBy('effective_from')->first();
            $c->next_change = $future ? [
                'date'      => $future->effective_from->format('d/m/Y'),
                'odp_rate'  => (float) $future->odp_rate,
                'tm_rate'   => (float) $future->tm_rate,
                'odp_rate_eclaire' => $future->odp_rate_eclaire,
                'tm_rate_eclaire'  => $future->tm_rate_eclaire,
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
            ->get(['id', 'commune_id', 'odp_rate', 'tm_rate', 'odp_rate_eclaire', 'tm_rate_eclaire',
                   'effective_from', 'effective_to', 'notes', 'created_by', 'created_at']);

        return response()->json([
            'commune' => ['id' => $commune->id, 'name' => $commune->name],
            'history' => $history->map(fn($h) => [
                'id'              => $h->id,
                'odp_rate'        => (float) $h->odp_rate,
                'tm_rate'         => (float) $h->tm_rate,
                'odp_rate_eclaire'=> $h->odp_rate_eclaire,
                'tm_rate_eclaire' => $h->tm_rate_eclaire,
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
     * Enregistre un tarif à une date d'effet (écran « Tarifs communes »).
     *
     * 2026-11-18 (demande de l'utilisateur) : la date peut être PASSÉE —
     * les nouveaux tarifs d'Adjamé et de Port-Bouët valent au 01/01/2026 —
     * avec un motif obligatoire. Les factures émises ne bougent jamais
     * (taux figés sur leurs lignes). Toute la logique d'historique est
     * dans App\Services\Taxes\TarifsCommunes.
     */
    public function scheduleTariff(Request $request, Commune $commune)
    {
        $data = $request->validate([
            'effective_from'   => 'required|date|after_or_equal:2000-01-01',
            'odp_rate'         => 'required|numeric|min:0',
            'tm_rate'          => 'required|numeric|min:0',
            'odp_rate_eclaire' => 'nullable|numeric|min:0',
            'tm_rate_eclaire'  => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string|max:500',
        ]);

        $effet = \Carbon\Carbon::parse($data['effective_from']);
        $passe = $effet->lt(today());
        if ($passe && trim((string) ($data['notes'] ?? '')) === '') {
            return back()->withInput()->with('error',
                'Date d\'effet passée : indiquez le motif (ex. « tarif confirmé par la mairie le … »). '
                . 'Les taxes seront recalculées depuis cette date ; les factures déjà émises ne changent pas.');
        }

        app(\App\Services\Taxes\TarifsCommunes::class)->enregistrer($commune, $effet, [
            'odp'         => (int) $data['odp_rate'],
            'tm'          => (int) $data['tm_rate'],
            'odp_eclaire' => $data['odp_rate_eclaire'] ?? null,
            'tm_eclaire'  => $data['tm_rate_eclaire'] ?? null,
        ], $data['notes'] ?? null, $request->user());

        return back()->with('success', '✅ Tarif de ' . $commune->name . ' enregistré à partir du ' . $effet->format('d/m/Y') . '.'
            . ($passe ? ' Les taxes sont recalculées depuis cette date ; les factures déjà émises ne changent pas.' : ''));
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

        // 2026-11-27 (point 7) — La période précédente reprend jusqu'où allait
        // le tarif supprimé : TarifsCommunes l'avait refermée la veille.
        \Illuminate\Support\Facades\DB::transaction(function () use ($commune, $entry) {
            \App\Models\CommuneTaxRateHistory::where('commune_id', $commune->id)
                ->whereKeyNot($entry->getKey())
                ->whereDate('effective_to', $entry->effective_from->copy()->subDay()->toDateString())
                ->update(['effective_to' => $entry->effective_to?->toDateString()]);
            $entry->delete();
        });

        return back()->with('success', 'Tarif programmé du ' . $entry->effective_from->format('d/m/Y')
            . ' supprimé pour ' . $commune->name . '.');
    }
}
