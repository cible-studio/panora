<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Page admin de backfill manuel : attribuer un commercial aux campagnes
 * existantes qui n'en ont pas (campaigns.commercial_user_id IS NULL).
 *
 * Décision B (Sous-mission 0) : pas de migration auto en masse — le MP
 * décide cas par cas. Boutton « Assigner par défaut au créateur » pour
 * les cas évidents (créateur = commercial → autoassign sécurisé).
 *
 * RBAC : admin only.
 */
class CommercialAttributionController extends Controller
{
    /** GET /admin/migration/commercial-attribution */
    public function index(Request $request)
    {
        if ($request->user()->role?->value !== 'admin') {
            abort(403, 'Réservé aux administrateurs.');
        }

        $campaigns = Campaign::query()
            ->whereNull('commercial_user_id')
            ->whereNull('deleted_at')
            ->with(['client:id,name', 'user:id,name,role'])
            ->orderByDesc('created_at')
            ->paginate(25);

        // MAJ 2026-06-17 : on autorise l'attribution à un admin/MP/commercial
        // (un admin peut amener des clients et gérer la campagne lui-même).
        // Le dropdown affiche le rôle entre parenthèses pour clarté.
        $commerciaux = User::query()
            ->whereIn('role', [
                \App\Enums\UserRole::ADMIN->value,
                \App\Enums\UserRole::MEDIAPLANNER->value,
                \App\Enums\UserRole::COMMERCIAL->value,
            ])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'agent_code', 'role']);

        $unattributedCount = Campaign::whereNull('commercial_user_id')->whereNull('deleted_at')->count();
        $totalCount        = Campaign::whereNull('deleted_at')->count();

        return view('admin.migration.commercial-attribution', compact(
            'campaigns', 'commerciaux', 'unattributedCount', 'totalCount'
        ));
    }

    /** POST /admin/migration/commercial-attribution/{campaign} */
    public function assign(Request $request, Campaign $campaign)
    {
        if ($request->user()->role?->value !== 'admin') {
            abort(403);
        }

        $data = $request->validate([
            'commercial_user_id' => 'required|integer|exists:users,id',
            'use_creator'        => 'nullable|boolean',
        ]);

        // Si use_creator=true → on force le commercial = créateur de la campagne
        // (Campaign.user_id), uniquement si ce créateur est lui-même commercial.
        if ($request->boolean('use_creator')) {
            $creator = $campaign->user; // = créateur via campaigns.user_id
            if (!$creator || !in_array($creator->role?->value, ['admin','mediaplanner','commercial'], true)) {
                return back()->with('error', '🚫 Le créateur de cette campagne n\'a pas un rôle compatible (admin/MP/commercial requis).');
            }
            $data['commercial_user_id'] = $creator->id;
        }

        $target = User::find($data['commercial_user_id']);
        if (!$target || !in_array($target->role?->value, ['admin','mediaplanner','commercial'], true)) {
            return back()->with('error', '🚫 Cet utilisateur n\'a pas un rôle compatible (admin/MP/commercial requis).');
        }

        $campaign->forceFill(['commercial_user_id' => $target->id])->save();

        Log::info('campaign.commercial_attribution.assigned', [
            'campaign_id' => $campaign->id,
            'campaign'    => $campaign->name,
            'commercial'  => $target->name,
            'by'          => $request->user()->name,
        ]);

        return back()->with('success', "✅ Campagne « {$campaign->name} » attribuée à {$target->name}.");
    }
}
