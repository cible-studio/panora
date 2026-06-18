<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoseTeam;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gestion des équipes de pose — M2 Performance Technicien.
 *
 * Routes :
 *   GET    /admin/teams                         → liste
 *   GET    /admin/teams/create                  → form création
 *   POST   /admin/teams                         → enregistrement
 *   GET    /admin/teams/{team}/edit             → form édition
 *   PUT    /admin/teams/{team}                  → mise à jour
 *   DELETE /admin/teams/{team}                  → soft delete + détache membres
 *   POST   /admin/teams/{team}/members          → ajouter techs membres
 *   DELETE /admin/teams/{team}/members/{user}   → retirer un membre
 *
 * RBAC : admin + mediaplanner uniquement.
 */
class PoseTeamController extends Controller
{
    public function index(Request $request)
    {
        $teams = PoseTeam::with(['leader:id,name', 'activeMembers:id,name,pose_team_id,agent_code'])
            ->orderBy('name')
            ->get();

        return view('admin.teams.index', compact('teams'));
    }

    public function create()
    {
        $techniciens   = User::techniciens()->whereNull('pose_team_id')->get(['id', 'name', 'agent_code']);
        $eligibleLeaders = User::techniciens()
            ->whereDoesntHave('teamLeading')
            ->get(['id', 'name', 'agent_code']);
        $colors        = PoseTeam::COLOR_PALETTE;

        return view('admin.teams.create', compact('techniciens', 'eligibleLeaders', 'colors'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|min:2|max:80|unique:pose_teams,name',
            'color_slug'     => 'required|string|in:' . implode(',', array_keys(PoseTeam::COLOR_PALETTE)),
            'leader_user_id' => 'nullable|integer|exists:users,id',
            'description'    => 'nullable|string|max:500',
            'member_ids'     => 'nullable|array',
            'member_ids.*'   => 'integer|exists:users,id',
        ]);

        // Garde-fou B Q2 — vérif amont leader pour message explicite
        if (!empty($data['leader_user_id'])) {
            $alreadyLeader = PoseTeam::where('leader_user_id', $data['leader_user_id'])->first();
            if ($alreadyLeader) {
                return back()->withInput()->with('error',
                    "🚫 Ce technicien est déjà leader de l'équipe « {$alreadyLeader->name} ». Réassigne-le ou choisis un autre leader.");
            }
        }

        $team = PoseTeam::create([
            'name'           => $data['name'],
            'color_slug'     => $data['color_slug'],
            'leader_user_id' => $data['leader_user_id'] ?? null,
            'description'    => $data['description'] ?? null,
            'is_active'      => true,
        ]);

        // Membres optionnels (sync via pose_team_id)
        if (!empty($data['member_ids'])) {
            User::whereIn('id', $data['member_ids'])->update(['pose_team_id' => $team->id]);
        }
        // Le leader est aussi membre s'il n'est pas déjà rattaché ailleurs
        if (!empty($data['leader_user_id'])) {
            User::where('id', $data['leader_user_id'])
                ->whereNull('pose_team_id')
                ->update(['pose_team_id' => $team->id]);
        }

        Log::info('pose_team.created', ['id' => $team->id, 'name' => $team->name, 'by' => $request->user()?->name]);
        return $this->redirectBack($request, "✅ Équipe « {$team->name} » créée.");
    }

    public function edit(PoseTeam $team)
    {
        $team->load('members:id,name,pose_team_id,agent_code', 'leader:id,name');
        $techniciensLibres = User::techniciens()
            ->whereNull('pose_team_id')
            ->get(['id', 'name', 'agent_code']);
        $eligibleLeaders = User::techniciens()
            ->where(function ($q) use ($team) {
                $q->whereDoesntHave('teamLeading')
                  ->orWhere('id', $team->leader_user_id);
            })
            ->get(['id', 'name', 'agent_code']);
        $colors = PoseTeam::COLOR_PALETTE;

        return view('admin.teams.edit', compact('team', 'techniciensLibres', 'eligibleLeaders', 'colors'));
    }

    public function update(Request $request, PoseTeam $team)
    {
        $data = $request->validate([
            'name'           => 'required|string|min:2|max:80|unique:pose_teams,name,' . $team->id,
            'color_slug'     => 'required|string|in:' . implode(',', array_keys(PoseTeam::COLOR_PALETTE)),
            'leader_user_id' => 'nullable|integer|exists:users,id',
            'description'    => 'nullable|string|max:500',
            'is_active'      => 'nullable|boolean',
        ]);

        // Garde-fou B Q2
        if (!empty($data['leader_user_id'])) {
            $alreadyLeader = PoseTeam::where('leader_user_id', $data['leader_user_id'])
                ->where('id', '!=', $team->id)
                ->first();
            if ($alreadyLeader) {
                return back()->withInput()->with('error',
                    "🚫 Ce technicien est déjà leader de l'équipe « {$alreadyLeader->name} ». Choisis un autre leader.");
            }
        }

        $team->update([
            'name'           => $data['name'],
            'color_slug'     => $data['color_slug'],
            'leader_user_id' => $data['leader_user_id'] ?? null,
            'description'    => $data['description'] ?? null,
            'is_active'      => $request->boolean('is_active', $team->is_active),
        ]);

        return $this->redirectBack($request, "✅ Équipe « {$team->name} » mise à jour.");
    }

    /**
     * Redirection "smart back" — résout la destination depuis le champ
     * caché `back` du form (whitelist stricte côté contrôleur — pas de
     * open-redirect). Fallback : teams.index.
     * Cf. resources/views/admin/teams/create.blade.php pour la liste des
     * sources autorisées.
     */
    protected function redirectBack(Request $request, string $flash)
    {
        // ⚠ Whitelist alignée avec resources/views/admin/partials/_smart_back.blade.php
        //   (single source of truth). Mettre à jour les 2 endroits ensemble.
        $backMap = [
            'finance'                => 'admin.finance.index',
            'finance.relances'       => 'admin.finance.relances',
            'rapports'               => 'admin.rapports.index',
            'performance.commercial' => 'admin.performance.commercial.index',
            'performance.tech'       => 'admin.performance.tech.index',
            'performance.team'       => 'admin.performance.team.index',
            'teams'                  => 'admin.teams.index',
            'posetasks'              => 'admin.pose-tasks.index',
            'posetasks.techniciens'  => 'admin.pose-tasks.techniciens.index',
            'clients'                => 'admin.clients.index',
            'campaigns'              => 'admin.campaigns.index',
            'invoices'               => 'admin.invoices.index',
            'panels'                 => 'admin.panels.index',
            'propositions'           => 'admin.propositions.index',
            'reservations'           => 'admin.reservations.index',
            'maintenances'           => 'admin.maintenances.index',
            'piges'                  => 'admin.piges.index',
            'signalements'           => 'admin.signalements.index',
            'sla'                    => 'admin.sla.retards.index',
        ];
        $key = (string) $request->input('back', '');
        $route = $backMap[$key] ?? 'admin.teams.index';
        return redirect()->route($route)->with('success', $flash);
    }

    /** Soft delete : détache les membres + softDelete équipe. */
    public function destroy(PoseTeam $team)
    {
        $membersCount = $team->members()->count();

        // Détache les membres (pose_team_id = NULL)
        User::where('pose_team_id', $team->id)->update(['pose_team_id' => null]);

        // SoftDelete équipe
        $team->delete();

        Log::info('pose_team.softDeleted', ['id' => $team->id, 'name' => $team->name, 'detached_members' => $membersCount]);
        return redirect()->route('admin.teams.index')
            ->with('success', "✅ Équipe « {$team->name} » supprimée. {$membersCount} membre(s) détaché(s). Consultation historique préservée.");
    }

    /** POST /admin/teams/{team}/members — ajouter des techs à l'équipe. */
    public function addMembers(Request $request, PoseTeam $team)
    {
        $data = $request->validate([
            'user_ids'   => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $count = User::whereIn('id', $data['user_ids'])
            ->where('role', \App\Enums\UserRole::TECHNIQUE->value)
            ->update(['pose_team_id' => $team->id]);

        return back()->with('success', "✅ {$count} technicien(s) ajouté(s) à l'équipe.");
    }

    /** DELETE /admin/teams/{team}/members/{user} — retirer un membre. */
    public function removeMember(Request $request, PoseTeam $team, User $user)
    {
        if ($user->pose_team_id !== $team->id) {
            return back()->with('error', '🚫 Ce technicien n\'est pas membre de cette équipe.');
        }
        $user->forceFill(['pose_team_id' => null])->save();
        return back()->with('success', "✅ {$user->name} retiré de l'équipe.");
    }
}
