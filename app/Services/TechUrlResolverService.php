<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\PoseTask;

/**
 * TechUrlResolverService — résout un token `/pige/{token}` vers l'URL
 * canonique `/tech/{user_token}/poses?focus=task_{id}`.
 *
 * 🚨 SHADOW SERVICE — créé en Phase 1 de la sous-mission de refonte
 * (SM1 — refactor pur). Le branchement effectif (redirect 301 depuis
 * PublicPigeController::show) viendra UNIQUEMENT quand le nouveau
 * dashboard tech saura accepter le paramètre `?focus=task_X` (Sous-
 * mission 2 — révélation progressive). En SM1, ce service existe
 * mais reste hors-circuit.
 *
 * Logique :
 *   - token 32 chars → cherche PoseTask::where('public_token', $token)
 *     → si trouvé ET task->assigned_user_id ET user->tech_public_token
 *       → return route('tech.space', user_token) . '?focus=task_' . task_id
 *     → sinon : null (laisse l'appelant retomber sur la vue legacy)
 *   - token 48 chars → cherche Campaign::where('pige_token', $token)
 *     → retourne null pour l'instant (mode campagne legacy conservé —
 *       unification en SM3 prévue)
 *   - sinon : null (404 côté appelant)
 *
 * Critères de redirection (TOUS doivent être vrais) :
 *   - PoseTask existe via le public_token
 *   - PoseTask.assigned_user_id n'est PAS null (sinon le tech n'a pas
 *     de compte → on garde la vue legacy qui supporte le tech_name_self)
 *   - User correspondant a tech_public_token non null + is_active=true
 *     (sinon ensureTechPublicToken() à la volée + check actif)
 */
class TechUrlResolverService
{
    /**
     * Résout le token public d'une PoseTask vers l'URL canonique
     * tech.space. Renvoie null si la résolution n'est pas possible
     * (la vue legacy doit prendre le relais).
     */
    public function resolve(string $token): ?string
    {
        $len = strlen($token);

        if ($len === 32 && preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            return $this->resolvePoseTaskToken($token);
        }

        if ($len === 48 && preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            return $this->resolveCampaignToken($token);
        }

        return null;
    }

    /**
     * 32 chars = pose_tasks.public_token. Si la pose a un tech assigné
     * avec un token public, on redirige vers son dashboard avec focus.
     */
    protected function resolvePoseTaskToken(string $token): ?string
    {
        $task = PoseTask::query()
            ->where('public_token', $token)
            ->whereNotNull('assigned_user_id')
            ->with('technicien:id,tech_public_token,is_active')
            ->first(['id', 'assigned_user_id', 'public_token']);

        if (!$task || !$task->technicien) {
            return null;
        }

        $tech = $task->technicien;
        if (!$tech->is_active) {
            return null;
        }

        // ensureTechPublicToken n'est PAS appelé ici en SM1 (shadow) :
        // on lit seulement le token existant pour éviter les write-on-read
        // surprises. En SM2 (au branchement effectif), on pourra le
        // créer à la volée si manquant — décision à valider à ce moment.
        if (empty($tech->tech_public_token)) {
            return null;
        }

        return route('tech.space', ['token' => $tech->tech_public_token])
             . '?focus=task_' . $task->id;
    }

    /**
     * 48 chars = campaigns.pige_token (mode multi-panneaux campagne).
     * Pas d'unification en SM1 — la page legacy public.pige-collect
     * supporte des fonctionnalités spécifiques (multi-panneaux, sans
     * assigned_user_id, tech_name_self) qui demandent une refonte
     * dédiée (planifiée en SM3).
     */
    protected function resolveCampaignToken(string $token): ?string
    {
        // Vérification d'existence uniquement (pas de redirect).
        // Retourner null signale à l'appelant : "garde la vue legacy".
        $exists = Campaign::where('pige_token', $token)->exists();
        return $exists ? null : null;
    }

    /**
     * Helper test-friendly : si le token résout, indique vers quelle
     * route + quels paramètres, sans formatter l'URL. Utile pour les
     * tests unitaires qui ne veulent pas dépendre du config app.url.
     *
     * @return array{route:string, params:array}|null
     */
    public function describe(string $token): ?array
    {
        $url = $this->resolve($token);
        if ($url === null) return null;

        // Extraction grossière pour le diagnostic — pas pour la prod.
        // Format attendu : /tech/{user_token}/poses?focus=task_X
        if (preg_match('#/tech/([A-Za-z0-9]{32})/poses\?focus=task_(\d+)#', $url, $m)) {
            return [
                'route'  => 'tech.space',
                'params' => ['token' => $m[1], 'focus' => 'task_' . $m[2]],
            ];
        }

        return null;
    }
}
