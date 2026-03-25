<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PanelController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\PoseController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Settings\ZoneController;
use App\Http\Controllers\Settings\CommuneController;
use App\Http\Controllers\Settings\PanelFormatController;
use App\Http\Controllers\Settings\PanelCategoryController;

// ── Dev B ─────────────────────────────────────────────────────────
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ExternalAgencyController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\CampaignController;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin,commercial,mediaplanner,technique'])
    ->group(function () {

        // ── Dashboard ─────────────────────────────────── Dev A ───
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        // ── Panneaux ──────────────────────────────────── Dev A ───
        Route::resource('panels', PanelController::class);
        Route::post('panels/{panel}/status', [PanelController::class, 'updateStatus'])
            ->name('panels.status');
        Route::get('panels/{panel}/availability', [PanelController::class, 'availability'])
            ->name('panels.availability');
        Route::post('panels/{panel}/photos', [PanelController::class, 'uploadPhoto'])
            ->name('panels.photos');
        Route::get('panels/{panel}/pdf', [PanelController::class, 'exportPdf'])
            ->name('panels.pdf');
        Route::get('panels/export/list', [PanelController::class, 'exportList'])
            ->name('panels.export.list');
        Route::get('panels/export/network', [PanelController::class, 'exportNetwork'])
            ->name('panels.export.network');

        // ── Carte / Heatmap ───────────────────────────── Dev A ───
        Route::get('map', [PanelController::class, 'map'])
            ->name('map');
        Route::get('map/data', [PanelController::class, 'mapData'])
            ->name('map.data');

        // ── Pose OOH ─────────────────────────────────── Dev A ───
        Route::resource('pose-tasks', PoseController::class);
        Route::post('pose-tasks/{task}/complete', [PoseController::class, 'markComplete'])
            ->name('pose.complete');

        // ── Maintenance ───────────────────────────────── Dev A ───
        Route::resource('maintenances', MaintenanceController::class);
        Route::post('maintenances/{maintenance}/resolve', [MaintenanceController::class, 'resolve'])
            ->name('maintenances.resolve');

        // ── Alertes ───────────────────────────────────── Dev A ───
        Route::get('alerts', [AlertController::class, 'index'])
            ->name('alerts.index');
        Route::post('alerts/{alert}/read', [AlertController::class, 'markRead'])
            ->name('alerts.read');
        Route::post('alerts/read-all', [AlertController::class, 'markAllRead'])
            ->name('alerts.read-all');
        Route::delete('alerts/{alert}', [AlertController::class, 'destroy'])
            ->name('alerts.destroy');

        // ── Paramètres (admin uniquement) ─────────────── Dev A ───
        Route::middleware('role:admin')
            ->prefix('settings')
            ->name('settings.')
            ->group(function () {
                Route::resource('zones', ZoneController::class);
                Route::resource('communes', CommuneController::class);
                Route::resource('formats', PanelFormatController::class);
                Route::resource('categories', PanelCategoryController::class);
            });

        // ── Utilisateurs (admin uniquement) ───────────── Dev A ───
        Route::middleware('role:admin')->group(function () {
            Route::resource('users', UserController::class);
            Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
                ->name('users.toggle');
            Route::get('audit-logs', [UserController::class, 'auditLogs'])
                ->name('audit.logs');
        });

        // ══════════════════════════════════════════════════════════
        // DEV B
        // ══════════════════════════════════════════════════════════

        // ── Clients ───────────────────────────────────── Dev B ───
        Route::resource('clients', ClientController::class);

        // ── Régies externes ───────────────────────────── Dev B ───
        Route::resource('external-agencies', ExternalAgencyController::class)
            ->except(['create', 'edit']);
        Route::post('external-agencies/{externalAgency}/panels',
            [ExternalAgencyController::class, 'storePanel'])
            ->name('external-agencies.panels.store');
        Route::put('external-agencies/{externalAgency}/panels/{panel}',
            [ExternalAgencyController::class, 'updatePanel'])
            ->name('external-agencies.panels.update');
        Route::delete('external-agencies/{externalAgency}/panels/{panel}',
            [ExternalAgencyController::class, 'destroyPanel'])
            ->name('external-agencies.panels.destroy');

        // ══════════════════════════════════════════════════════════
        // ⚠️ RÈGLE CRITIQUE : toutes les routes GET spécifiques
        // DOIVENT être déclarées AVANT Route::resource()
        // sinon Laravel capte /reservations/xxx comme {reservation}
        // ══════════════════════════════════════════════════════════

        // ── Disponibilités ────────────── ⚠️ AVANT resource ───────
        Route::get('disponibilites',
            [ReservationController::class, 'disponibilites'])
            ->name('reservations.disponibilites');
        Route::post('disponibilites/confirmer',
            [ReservationController::class, 'confirmerSelection'])
            ->name('reservations.confirmer-selection');

        // ── AJAX disponibilités (grille panneaux) ── ⚠️ AVANT ─────
        // Utilisé par disponibilites.blade.php pour charger les cartes panneaux
        Route::get('disponibilites/panneaux',
            [ReservationController::class, 'panneauxAjax'])
            ->name('disponibilites.panneaux')
            ->middleware('throttle:120,1');

        // ── AJAX réservations edit ──────────────── ⚠️ AVANT ──────
        // Utilisé par edit.blade.php pour charger les panneaux dispo sur période
        Route::get('reservations/available-panels',
            [ReservationController::class, 'availablePanels'])
            ->name('reservations.available-panels')
            ->middleware('throttle:60,1');

        // ── mark-seen ────────────────────────────── ⚠️ AVANT ─────
        Route::post('reservations/mark-seen',
            [ReservationController::class, 'markSeen'])
            ->name('reservations.mark-seen');

        // ── Réservations CRUD ── ⚠️ EN DERNIER (capte {reservation}) ──
        Route::resource('reservations', ReservationController::class)
            ->except(['create', 'store']);
        Route::patch('reservations/{reservation}/status',
            [ReservationController::class, 'updateStatus'])
            ->name('reservations.update-status');
        Route::patch('reservations/{reservation}/annuler',
            [ReservationController::class, 'annuler'])
            ->name('reservations.annuler');

        // ── Campagnes ─────────────────────────────────── Dev B ───
        Route::resource('campaigns', CampaignController::class);
        Route::patch('campaigns/{campaign}/status',
            [CampaignController::class, 'updateStatus'])
            ->name('campaigns.update-status');
        Route::patch('campaigns/{campaign}/prolonger',
            [CampaignController::class, 'prolonger'])
            ->name('campaigns.prolonger');
        Route::post('campaigns/{campaign}/panels',
            [CampaignController::class, 'addPanel'])
            ->name('campaigns.panels.add');
        Route::delete('campaigns/{campaign}/panels/{panel}',
            [CampaignController::class, 'removePanel'])
            ->name('campaigns.panels.remove');

    });