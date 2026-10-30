<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Les deux migrations TX-9 (2026-07-29) vivent sur les deux branches depuis
 * le passage du module Devis sur main (2026-10-30) : la production a joué
 * celle d'invoice_lines, le staging celle d'invoice_lines + quote_lines.
 * Jouer l'autre après ne doit jamais échouer (sinon `migrate --force` bloque
 * le démarrage de la production).
 */
class MigrationsTx9JumellesTest extends TestCase
{
    private const MAIN = 'database/migrations/2026_07_29_100000_add_campaign_dates_to_invoice_lines.php';
    private const DEVELOP = 'database/migrations/2026_07_29_100000_add_campaign_dates_to_invoice_and_quote_lines.php';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['invoice_lines', 'quote_lines'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id(); $t->decimal('duree_mois', 8, 2)->nullable();
            });
        }
    }

    private function jouer(string $fichier): void
    {
        (require base_path($fichier))->up();
    }

    public function test_production_puis_module_devis(): void
    {
        $this->jouer(self::MAIN);
        $this->assertFalse(Schema::hasColumn('quote_lines', 'campaign_start'));

        $this->jouer(self::DEVELOP); // ne doit pas lever « Duplicate column »

        foreach (['invoice_lines', 'quote_lines'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'campaign_start'), $table);
            $this->assertTrue(Schema::hasColumn($table, 'campaign_end'), $table);
        }
    }

    public function test_staging_puis_migration_de_main(): void
    {
        $this->jouer(self::DEVELOP);
        $this->jouer(self::MAIN);

        $this->assertTrue(Schema::hasColumn('invoice_lines', 'campaign_end'));
        $this->assertTrue(Schema::hasColumn('quote_lines', 'campaign_end'));
    }
}
