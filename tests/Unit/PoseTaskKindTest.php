<?php

namespace Tests\Unit;

use App\Enums\PoseTaskKind;
use Tests\TestCase;

/**
 * Tests unité de l'enum PoseTaskKind (ajout 2026-08-04).
 *
 * Le service PoseService::createRechange lui-même nécessite MySQL
 * (transactions + modèle), donc testé en feature test à part. Ici on
 * couvre juste le comportement de l'enum qui pilote le workflow.
 */
class PoseTaskKindTest extends TestCase
{
    public function test_kinds_available(): void
    {
        $cases = PoseTaskKind::cases();
        $this->assertCount(3, $cases, 'On doit avoir 3 kinds : initial, rechange, retouche.');
        $this->assertSame('initial',  PoseTaskKind::INITIAL->value);
        $this->assertSame('rechange', PoseTaskKind::RECHANGE->value);
        $this->assertSame('retouche', PoseTaskKind::RETOUCHE->value);
    }

    public function test_labels_are_french(): void
    {
        $this->assertSame('Pose initiale',   PoseTaskKind::INITIAL->label());
        $this->assertSame('Rechange affiche', PoseTaskKind::RECHANGE->label());
        $this->assertSame('Retouche',        PoseTaskKind::RETOUCHE->label());
    }

    public function test_only_rechange_supersedes_previous(): void
    {
        // Règle métier clé : seul le RECHANGE marque l'ancienne pose
        // comme "remplacée" (replaced_at = now). La retouche laisse
        // l'ancienne affiche en place (juste réparation).
        $this->assertFalse(PoseTaskKind::INITIAL->supersedesPrevious());
        $this->assertTrue(PoseTaskKind::RECHANGE->supersedesPrevious());
        $this->assertFalse(PoseTaskKind::RETOUCHE->supersedesPrevious());
    }

    public function test_tryfrom_fallback_to_initial_via_helper(): void
    {
        // Si la BDD contient NULL ou une valeur inconnue, on doit
        // retomber sur INITIAL — cf. PoseTask::kind() qui utilise
        // ce mécanisme pour rester safe même sur les vieilles poses
        // sans pose_kind renseigné.
        $this->assertNull(PoseTaskKind::tryFrom('inconnu'));
        $this->assertNull(PoseTaskKind::tryFrom(''));
        $this->assertSame(PoseTaskKind::INITIAL, PoseTaskKind::tryFrom('initial'));
    }

    public function test_icons_and_colors_defined_for_all_kinds(): void
    {
        foreach (PoseTaskKind::cases() as $kind) {
            $this->assertNotEmpty($kind->icon(), "Icon manquant pour {$kind->value}");
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $kind->color(),
                "Color hex mal formée pour {$kind->value}");
        }
    }

    public function test_labels_helper_returns_associative_array(): void
    {
        $labels = PoseTaskKind::labels();
        $this->assertSame([
            'initial'  => 'Pose initiale',
            'rechange' => 'Rechange affiche',
            'retouche' => 'Retouche',
        ], $labels);
    }
}
