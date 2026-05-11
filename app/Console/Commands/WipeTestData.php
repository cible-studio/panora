<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vide les données opérationnelles de test, en gardant la structure et les
 * référentiels métier (users, communes, zones, formats, catégories, taxes,
 * agences externes, rôles/permissions).
 *
 * Usage :
 *   php artisan panora:wipe-test-data --dry-run
 *   php artisan panora:wipe-test-data --force
 *
 * IMPORTANT : faire un mysqldump AVANT toute exécution en production.
 */
class WipeTestData extends Command
{
    protected $signature   = 'panora:wipe-test-data
        {--dry-run : Affiche les comptages sans rien supprimer}
        {--force   : Ne demande pas confirmation}';
    protected $description = 'Vide les données de test (panneaux, réservations, campagnes, alertes, ...) en préservant users + référentiels.';

    /**
     * Ordre de vidage : du plus dépendant vers le moins dépendant.
     * Permet d'éviter les références orphelines même sans FK contrainte.
     */
    private const TABLES_TO_WIPE = [
        // Logs & alertes (références aux opérations)
        'alerts',
        'audit_logs',
        'satisfaction_surveys',

        // Facturation
        'invoices',

        // Propositions / négociations
        'propositions',

        // Pige terrain
        'piges',

        // Tâches & maintenance (réfèrent panels)
        'pose_tasks',
        'maintenances',
        'panel_photos',

        // Pivots commerciaux
        'reservation_panels',
        'campaign_panels',

        // Opérations commerciales
        'reservations',
        'campaigns',

        // Comptes clients (utilisateurs login + interlocuteurs)
        'client_users',
        'client_contacts',

        // Panneaux (sera re-rempli via PanelImportSeeder)
        'panels',
        'external_panels',

        // Queues & sessions (nettoyage hygiène)
        'jobs',
        'failed_jobs',
        'job_batches',
        'sessions',
        'cache',
        'cache_locks',
        'password_reset_tokens',
    ];

    /**
     * Conservées : users, roles, permissions, model_has_roles,
     * communes, zones, panel_categories, panel_formats, taxes,
     * commune_tax_payments, external_agencies, clients, migrations.
     */
    private const TABLES_TO_KEEP = [
        'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        'communes', 'zones', 'panel_categories', 'panel_formats',
        'taxes', 'commune_tax_payments',
        'external_agencies', 'clients',
        'migrations',
    ];

    public function handle(): int
    {
        $dry   = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('═══════════════════════════════════════════════');
        $this->info('  WIPE DES DONNÉES DE TEST — Panora');
        $this->info('═══════════════════════════════════════════════');

        // Comptages avant
        $countsBefore = [];
        foreach (self::TABLES_TO_WIPE as $t) {
            if (!Schema::hasTable($t)) {
                continue;
            }
            $countsBefore[$t] = DB::table($t)->count();
        }

        // Affichage
        $this->line('');
        $this->line('  TABLES À VIDER :');
        $total = 0;
        foreach ($countsBefore as $t => $c) {
            $this->line(sprintf('    %-26s %6d ligne(s)%s', $t, $c, $c > 0 ? ' ← à supprimer' : ''));
            $total += $c;
        }
        $this->line('  ────────────────────────────────');
        $this->line(sprintf('  Total à supprimer : <fg=red>%d ligne(s)</>', $total));

        $this->line('');
        $this->line('  TABLES CONSERVÉES :');
        foreach (self::TABLES_TO_KEEP as $t) {
            if (Schema::hasTable($t)) {
                $c = DB::table($t)->count();
                $this->line(sprintf('    %-26s %6d ligne(s) (préservées)', $t, $c));
            }
        }

        if ($dry) {
            $this->warn('');
            $this->warn('  ▶ MODE DRY-RUN : aucune suppression effectuée.');
            return self::SUCCESS;
        }

        if (!$force) {
            $this->warn('');
            if (!$this->confirm('  ⚠  Confirmer la suppression de ' . $total . ' lignes ?', false)) {
                $this->line('  Annulé.');
                return self::SUCCESS;
            }
        }

        // ── Wipe ──
        $this->line('');
        $this->info('  Suppression en cours...');

        // Désactive FK (utile pour InnoDB ; no-op pour MyISAM mais ne casse rien)
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach (self::TABLES_TO_WIPE as $t) {
                if (!Schema::hasTable($t)) {
                    continue;
                }
                DB::table($t)->truncate();
                $this->line('    ✓ ' . $t . ' vidée');
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->info('');
        $this->info('  ✅ Wipe terminé.');
        $this->info('');
        $this->info('  Prochaine étape :');
        $this->info('    php artisan db:seed --class=PanelImportSeeder --force');
        $this->info('    php artisan cache:clear');

        return self::SUCCESS;
    }
}
