<?php

namespace App\Policies;

use App\Models\PoseTaskAction;
use App\Models\User;

/**
 * Policy pour les actions PoseTaskAction.
 *
 * Politique RBAC SLA (mission M3) :
 *   - admin        : tout
 *   - mediaplanner : peut consulter + amender les motifs a posteriori
 *   - commercial   : aucun accès (ce n'est pas sa donnée métier)
 *   - technique    : peut créer (signalement terrain) mais pas amender ni consulter l'analyse
 */
class PoseTaskActionPolicy
{
    /** Consulter la liste analytique SLA + détail. */
    public function view(User $user, PoseTaskAction $action): bool
    {
        return $this->isAdminOrMediaplanner($user);
    }

    /** Idem pour les vues d'index. */
    public function viewAny(User $user): bool
    {
        return $this->isAdminOrMediaplanner($user);
    }

    /** Modifier le motif a posteriori (= créer une PoseTaskAction motif_modified). */
    public function amend(User $user, PoseTaskAction $action): bool
    {
        // On ne peut amender qu'un signalement, pas un autre type d'action.
        if ($action->action !== PoseTaskAction::ACTION_PROBLEM_REPORTED) {
            return false;
        }
        return $this->isAdminOrMediaplanner($user);
    }

    /** Alias Laravel canonique. */
    public function update(User $user, PoseTaskAction $action): bool
    {
        return $this->amend($user, $action);
    }

    protected function isAdminOrMediaplanner(User $user): bool
    {
        $role = $user->role?->value;
        return in_array($role, ['admin', 'mediaplanner'], true);
    }
}
