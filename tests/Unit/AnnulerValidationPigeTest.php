<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Annuler la validation d'une pige (2026-11-17).
 *
 * ═══ LE BLOCAGE ═══
 * Une pige validée par erreur était un cul-de-sac complet. Quatre portes,
 * toutes fermées par la même règle, pour tout le monde — admin et MP
 * compris, qui avaient pourtant les routes :
 *
 *   PigeService::delete()      → « Impossible de supprimer une pige déjà vérifiée. »
 *   PigeService::reject()      → « Impossible de rejeter une pige déjà vérifiée. »
 *   PigeController::update()   → le statut n'est modifiable que si la pige
 *                                n'est pas vérifiée
 *
 * Conséquence terrain : la tâche de pose restait « terminée » et
 * disparaissait de la liste du technicien. Personne ne pouvait la faire
 * revenir.
 *
 * ═══ LA RÈGLE (validée le 2026-11-17) ═══
 * La pige repasse en REJETÉE avec un motif obligatoire, la pose se
 * rouvre, le technicien est prévenu. Admin ET Media Planner : c'est le
 * MP qui valide au quotidien, donc lui qui s'aperçoit de l'erreur.
 *
 * ⚠ Ce que l'annulation ne rattrape pas : la validation a envoyé un
 * e-mail au client. Le motif sert de trace pour expliquer l'écart.
 *
 * Tests sur le code source, sans base de données : ils tournent sur le
 * poste à base vide. Le comportement réel se vérifie en production
 * (docs/A_TESTER.md § 34).
 */
class AnnulerValidationPigeTest extends TestCase
{
    private function src(string $chemin): string
    {
        $absolu = __DIR__ . '/../../' . $chemin;
        $this->assertFileExists($absolu, "Fichier attendu absent : {$chemin}");

        return (string) file_get_contents($absolu);
    }

    private function service(): string
    {
        return $this->src('app/Services/PigeService.php');
    }

    // ══════════════════════════════════════════════════════════════
    // LA SORTIE DE SECOURS
    // ══════════════════════════════════════════════════════════════

    public function test_une_validation_peut_etre_annulee(): void
    {
        $this->assertStringContainsString(
            'public function annulerValidation(Pige $pige, User $auteur, string $motif): array',
            $this->service(),
            "Une pige validée par erreur doit avoir une sortie : sans elle, "
            . "la tâche de pose reste terminée et quitte la liste du technicien."
        );
    }

    /**
     * L'annulation ne doit s'appliquer qu'à une pige RÉELLEMENT validée :
     * sur une pige en attente, c'est le rejet ordinaire qui s'applique.
     */
    public function test_l_annulation_ne_vise_que_les_piges_validees(): void
    {
        $src = $this->service();

        $this->assertMatchesRegularExpression(
            '/annulerValidation\([^)]*\): array\s*\{\s*if \(!\$pige->isVerifiee\(\)\)/s',
            $src,
            "annulerValidation() doit refuser une pige qui n'est pas validée."
        );

        // Verrou optimiste : deux MP qui cliquent en même temps ne doivent
        // pas produire deux annulations.
        $this->assertStringContainsString(
            "->where('status', 'verifie')",
            $src,
            "L'écriture doit être conditionnée au statut attendu, sinon deux "
            . "clics simultanés passent tous les deux."
        );
    }

    /**
     * Un motif vide rendrait la trace inutile — et c'est la seule chose
     * qui restera pour expliquer au client un e-mail déjà parti.
     */
    public function test_le_motif_est_obligatoire(): void
    {
        $this->assertStringContainsString(
            "'motif' => 'required|string|max:500'",
            $this->src('app/Http/Controllers/Admin/PigeController.php'),
            "Le motif d'annulation doit être obligatoire."
        );

        $this->assertStringContainsString(
            "trim(\$motif) === ''",
            $this->service(),
            "Le service doit refuser un motif vide, même si le formulaire "
            . "est contourné."
        );
    }

    // ══════════════════════════════════════════════════════════════
    // LA POSE REVIENT CHEZ LE TECHNICIEN
    // ══════════════════════════════════════════════════════════════

    /**
     * C'est le but de toute la fonction : que la tâche réapparaisse.
     * Et la logique ne doit exister qu'à UN endroit — deux copies
     * finiraient par diverger, l'une rouvrant la pose et l'autre non.
     */
    public function test_la_reouverture_de_la_pose_est_centralisee(): void
    {
        $src = $this->service();

        $this->assertStringContainsString(
            'private function rouvrirPoseApresRefus(Pige $pige, string $reason): void',
            $src,
            "La réouverture de la pose doit vivre dans une méthode unique."
        );

        // Appelée par le rejet ordinaire ET par l'annulation.
        $this->assertSame(
            2,
            substr_count($src, '$this->rouvrirPoseApresRefus('),
            "reject() et annulerValidation() doivent tous deux passer par "
            . "la méthode centralisée."
        );

        // Et la pose redescend bien de « terminée » à « en cours ».
        $this->assertStringContainsString(
            'PoseTaskStatus::IN_PROGRESS->value',
            $src,
            "La tâche doit repasser « en cours » pour revenir dans la liste "
            . "du technicien."
        );
    }

    /**
     * Le panneau avait pu être géolocalisé à partir de cette pige : on
     * refait le calcul sans elle, sinon il garde une position établie sur
     * une photo qu'on vient de retirer.
     */
    public function test_le_gps_du_panneau_est_recalcule(): void
    {
        $this->assertMatchesRegularExpression(
            '/annulerValidation.*?PanelGeoLocator::class.*?recomputeFromPiges/s',
            $this->service(),
            "Annuler une validation doit relancer le calcul GPS du panneau."
        );
    }

    // ══════════════════════════════════════════════════════════════
    // ACCÈS ET TRAÇABILITÉ
    // ══════════════════════════════════════════════════════════════

    /**
     * La route doit exister dans le groupe admin + mediaplanner : le MP
     * valide au quotidien, c'est lui qui s'aperçoit de l'erreur.
     */
    public function test_la_route_est_ouverte_a_l_admin_et_au_media_planner(): void
    {
        $routes = $this->src('routes/admin.php');

        $this->assertStringContainsString(
            "Route::post('{pige}/annuler-validation', [PigeController::class, 'annulerValidation'])",
            $routes,
            "La route d'annulation doit exister."
        );

        // Elle doit être dans le même groupe que verify/reject, protégé
        // par role:admin,mediaplanner.
        // strrpos : un ancien groupe de routes piges subsiste en
        // commentaire plus haut dans le fichier — c'est le dernier, le
        // bloc réellement actif, qu'on inspecte.
        $bloc = substr(
            $routes,
            (int) strrpos($routes, "Route::prefix('piges')->name('piges.')"),
            3000
        );
        $this->assertStringContainsString(
            "role:admin,mediaplanner",
            $bloc,
            "Le groupe des piges doit rester réservé à l'admin et au MP."
        );
        $this->assertStringContainsString(
            'annuler-validation',
            $bloc,
            "L'annulation doit vivre dans ce groupe, pas ailleurs."
        );
    }

    /**
     * Revenir sur une preuve déjà envoyée au client n'est pas anodin :
     * l'équipe doit le voir passer, et le log doit le garder.
     */
    public function test_l_annulation_est_tracee(): void
    {
        $this->assertStringContainsString(
            "Log::warning('pige.validation_annulee'",
            $this->service(),
            "L'annulation doit être journalisée en warning, pas en info : "
            . "c'est un retour en arrière sur une preuve diffusée."
        );

        $this->assertStringContainsString(
            'Validation annulée — ',
            $this->src('app/Http/Controllers/Admin/PigeController.php'),
            "Une alerte doit prévenir l'équipe."
        );
    }

    /**
     * L'écran doit proposer la sortie là où l'on constate l'erreur, et
     * dire ce qui ne se rattrape pas.
     */
    public function test_l_ecran_propose_la_sortie_et_annonce_sa_limite(): void
    {
        $vue = $this->src('resources/views/admin/piges/index.blade.php');

        $this->assertStringContainsString(
            'PigeActions.showAnnulerModal(',
            $vue,
            "La carte d'une pige validée doit porter le bouton d'annulation."
        );

        $this->assertStringContainsString(
            "l'e-mail envoyé au client à la validation ne peut pas être repris",
            mb_strtolower($vue),
            "La fenêtre doit dire que l'e-mail client ne se reprend pas : "
            . "c'est la conséquence que l'utilisateur doit connaître avant "
            . "de cliquer."
        );
    }
}
