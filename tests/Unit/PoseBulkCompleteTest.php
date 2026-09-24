<?php

namespace Tests\Unit;

use App\Services\PoseService;
use PHPUnit\Framework\TestCase;

/**
 * Validation groupée des poses (2026-09-24).
 *
 * Demande MP : sur une campagne de 50+ panneaux, valider les poses une
 * par une était intenable. `PoseService::bulkUpdate()` refuse le statut
 * « réalisée » à dessein — on ne contourne pas ce garde-fou, on applique
 * les mêmes contrôles en lot via `bulkComplete()`.
 *
 * Règle validée par écrit le 2026-09-24 : **une pose sans pige photo
 * n'est pas validable en groupé.** L'écran « Poses oubliées » est la
 * seule exception (requirePige: false), c'est son objet même.
 *
 * Tests purs (pas de BDD) : ils verrouillent les garde-fous d'entrée et
 * la structure de la réponse. Les scénarios avec panneaux/campagnes
 * réels relèvent des tests Feature, qui exigent MySQL (cf.
 * docs/TECHNICAL_DEBT.md — migrations non SQLite-portables).
 */
class PoseBulkCompleteTest extends TestCase
{
    private function service(): PoseService
    {
        return new PoseService();
    }

    private function acteur(): \App\Models\User
    {
        // Pas de persistance : bulkComplete ne lit que ->id sur l'acteur
        // avant d'atteindre la base, et les cas testés ici s'arrêtent
        // aux garde-fous d'entrée.
        $u = new \App\Models\User();
        $u->id = 1;

        return $u;
    }

    public function test_une_selection_vide_est_refusee(): void
    {
        $r = $this->service()->bulkComplete([], $this->acteur());

        $this->assertFalse($r['ok']);
        $this->assertSame(0, $r['completed']);
        $this->assertStringContainsString('Aucune pose', $r['error']);
    }

    public function test_les_ids_vides_ou_nuls_sont_ignores(): void
    {
        // '0', null et '' ne doivent pas passer pour des identifiants.
        $r = $this->service()->bulkComplete(['0', null, '', 0], $this->acteur());

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Aucune pose', $r['error']);
    }

    public function test_une_date_illisible_est_refusee(): void
    {
        $r = $this->service()->bulkComplete([1], $this->acteur(), 'pas-une-date');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Date de réalisation invalide', $r['error']);
    }

    public function test_une_date_future_est_refusee(): void
    {
        // Une pose ne peut pas avoir été faite demain.
        $r = $this->service()->bulkComplete([1], $this->acteur(), now()->addDays(3)->toDateString());

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('futur', $r['error']);
    }

    public function test_la_reponse_porte_toujours_les_memes_cles(): void
    {
        // Le front lit completed / skipped / skipped_details pour son
        // récapitulatif : ces clés doivent exister même en cas d'échec.
        $r = $this->service()->bulkComplete([], $this->acteur());

        foreach (['ok', 'completed', 'skipped', 'skipped_details'] as $cle) {
            $this->assertArrayHasKey($cle, $r, "Clé « {$cle} » absente de la réponse.");
        }
        $this->assertIsArray($r['skipped_details']);
    }

    // ── Canaris statiques ──────────────────────────────────────────

    public function test_bulk_update_refuse_toujours_le_statut_realisee(): void
    {
        // Le garde-fou historique doit rester : la validation groupée
        // passe par bulkComplete(), qui contrôle la pige, PAS par un
        // change_status en masse qui court-circuiterait tout.
        $source = file_get_contents(__DIR__ . '/../../app/Services/PoseService.php');

        $this->assertStringNotContainsString(
            'PoseTaskStatus::COMPLETED->value,
                    PoseTaskStatus::IN_PROGRESS->value,',
            $source,
            'RÉGRESSION : COMPLETED ne doit pas être ajouté aux statuts ' .
            'autorisés de bulkUpdate(change_status).'
        );
    }

    public function test_l_ecran_poses_oubliees_ne_fait_plus_d_update_de_masse(): void
    {
        // Avant 2026-09-24, bulkCompleteOubliees() faisait un update()
        // brut qui contournait la garde campagne, la traçabilité et le
        // log. Il doit passer par le service.
        $source = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Admin/PoseController.php');

        $this->assertStringContainsString(
            'requirePige: false',
            $source,
            'RÉGRESSION : « Poses oubliées » doit appeler bulkComplete() ' .
            'avec requirePige: false, pas un update() de masse.'
        );
        $this->assertStringContainsString(
            'requirePige: true',
            $source,
            'RÉGRESSION : la liste principale doit exiger la pige photo.'
        );
    }

    public function test_la_completion_passe_par_une_seule_transition_detat(): void
    {
        // complete() (unitaire) et bulkComplete() (groupé) doivent partager
        // applyCompletion() : sinon la traçabilité completed_by_user_id
        // diverge entre les deux chemins, et l'espace technicien n'affiche
        // plus « fait par … (bureau) ».
        $source = file_get_contents(__DIR__ . '/../../app/Services/PoseService.php');

        $this->assertStringContainsString(
            'private function applyCompletion(',
            $source,
            'applyCompletion() est la source unique de la transition ' .
            '« pose réalisée ».'
        );
        $this->assertSame(
            2,
            substr_count($source, '$this->applyCompletion('),
            'applyCompletion() doit être appelée exactement 2 fois : ' .
            'par complete() et par bulkComplete().'
        );
        $this->assertStringContainsString(
            "\$payload['completed_by_user_id'] = \$actor->id;",
            $source,
            'La traçabilité de l\'auteur doit rester dans applyCompletion().'
        );
        $this->assertStringContainsString(
            "\$payload['completed_source']",
            $source,
            'L\'origine (tech / bureau) doit être renseignée à la clôture.'
        );
    }
}
