<?php

use Illuminate\Support\Facades\Route;

// ── Dev A ──────────────────────────────────────────────────────────
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
use App\Http\Controllers\Admin\PropositionController;
use App\Http\Controllers\Admin\PigeController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\InvoiceController;
// ── Dev B ──────────────────────────────────────────────────────────
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ExternalAgencyController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\CampaignController;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin,commercial,mediaplanner,technique'])
    ->group(function () {

        // ══════════════════════════════════════════════════════════
        // DEV A
        // ══════════════════════════════════════════════════════════

        // ── Dashboard ─────────────────────────────────────────────
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        // ── Panneaux ──────────────────────────────────────────────
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

        // ── Carte / Heatmap ───────────────────────────────────────
        Route::get('map', [PanelController::class, 'map'])
            ->name('map');
        Route::get('map/data', [PanelController::class, 'mapData'])
            ->name('map.data');

        // ── Pose OOH ──────────────────────────────────────────────
        Route::resource('pose-tasks', PoseController::class);
        Route::post('pose-tasks/{task}/complete', [PoseController::class, 'markComplete'])
            ->name('pose.complete');

        // ── Maintenance ───────────────────────────────────────────
        Route::resource('maintenances', MaintenanceController::class);
        Route::post('maintenances/{maintenance}/resolve', [MaintenanceController::class, 'resolve'])
            ->name('maintenances.resolve');

        // ── Alertes ───────────────────────────────────────────────
        Route::get('alerts', [AlertController::class, 'index'])
            ->name('alerts.index');
        Route::post('alerts/read-all', [AlertController::class, 'markAllRead'])
            ->name('alerts.read-all');
        Route::post('alerts/{alert}/read', [AlertController::class, 'markRead'])
            ->name('alerts.read');
        Route::delete('alerts/{alert}', [AlertController::class, 'destroy'])
            ->name('alerts.destroy');

        // ── Paramètres (admin uniquement) ─────────────────────────
        Route::middleware('role:admin')
            ->prefix('settings')
            ->name('settings.')
            ->group(function () {
            Route::resource('zones', ZoneController::class);
            Route::resource('communes', CommuneController::class);
            Route::resource('formats', PanelFormatController::class);
            Route::resource('categories', PanelCategoryController::class);
        });

        // ── Utilisateurs (admin uniquement) ───────────────────────
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

        // ── Clients ───────────────────────────────────────────────
        Route::resource('clients', ClientController::class);

        // ── Régies externes ───────────────────────────────────────
        Route::resource('external-agencies', ExternalAgencyController::class)
            ->except(['create', 'edit']);
        Route::post(
            'external-agencies/{externalAgency}/panels',
            [ExternalAgencyController::class, 'storePanel']
        )
            ->name('external-agencies.panels.store');
        Route::put(
            'external-agencies/{externalAgency}/panels/{panel}',
            [ExternalAgencyController::class, 'updatePanel']
        )
            ->name('external-agencies.panels.update');
        Route::delete(
            'external-agencies/{externalAgency}/panels/{panel}',
            [ExternalAgencyController::class, 'destroyPanel']
        )
            ->name('external-agencies.panels.destroy');

        // ── Disponibilités ⚠️ AVANT resource reservations ─────────
        Route::get(
            'disponibilites',
            [ReservationController::class, 'disponibilites']
        )
            ->name('reservations.disponibilites');
        Route::post(
            'disponibilites/confirmer',
            [ReservationController::class, 'confirmerSelection']
        )
            ->name('reservations.confirmer-selection');

        // ── Réservations API AJAX ⚠️ AVANT resource ───────────────
        Route::get(
            'reservations/available-panels',
            [ReservationController::class, 'availablePanels']
        )
            ->name('reservations.available-panels')
            ->middleware('throttle:60,1');
        Route::post(
            'reservations/mark-seen',
            [ReservationController::class, 'markSeen']
        )
            ->name('reservations.mark-seen');

        // ── Réservations CRUD ─────────────────────────────────────
        Route::resource('reservations', ReservationController::class)
            ->except(['create', 'store']);
        Route::patch(
            'reservations/{reservation}/status',
            [ReservationController::class, 'updateStatus']
        )
            ->name('reservations.update-status');
        Route::patch(
            'reservations/{reservation}/annuler',
            [ReservationController::class, 'annuler']
        )
            ->name('reservations.annuler');

        // ── Campagnes ─────────────────────────────────────────────
        Route::resource('campaigns', CampaignController::class);
        Route::patch(
            'campaigns/{campaign}/status',
            [CampaignController::class, 'updateStatus']
        )
            ->name('campaigns.update-status');
        Route::patch(
            'campaigns/{campaign}/prolonger',
            [CampaignController::class, 'prolonger']
        )
            ->name('campaigns.prolonger');
        Route::post(
            'campaigns/{campaign}/panels',
            [CampaignController::class, 'addPanel']
        )
            ->name('campaigns.panels.add');
        Route::delete(
            'campaigns/{campaign}/panels/{panel}',
            [CampaignController::class, 'removePanel']
        )
            ->name('campaigns.panels.remove');

        // ── Propositions ──────────────────────────────── Dev A ───

        Route::resource('propositions', PropositionController::class);
        Route::patch(
            'propositions/{proposition}/status',
            [PropositionController::class, 'updateStatus']
        )
            ->name('propositions.update-status');
        Route::get(
            'propositions/{proposition}/pdf',
            [PropositionController::class, 'exportPdf']
        )
            ->name('propositions.pdf');

        // ── Piges Photos ──────────────────────────────── Dev A ───

        Route::get('piges', [PigeController::class, 'index'])
            ->name('piges.index');
        Route::post('piges/upload', [PigeController::class, 'upload'])
            ->name('piges.upload');
        Route::get('piges/{pige}', [PigeController::class, 'show'])
            ->name('piges.show');
        Route::post('piges/{pige}/verify', [PigeController::class, 'verify'])
            ->name('piges.verify');
        Route::delete('piges/{pige}', [PigeController::class, 'destroy'])
            ->name('piges.destroy');
        Route::get('piges/export/pdf', [PigeController::class, 'exportPdf'])
            ->name('piges.export.pdf');

        // ── Taxes Communes ────────────────────────────── Dev A ───


        Route::resource('taxes', TaxController::class);
        Route::patch('taxes/{tax}/pay', [TaxController::class, 'markPaid'])
            ->name('taxes.pay');
        Route::get('taxes/export/pdf', [TaxController::class, 'exportPdf'])
            ->name('taxes.export.pdf');


        // ── Facturation ───────────────────────────────── Dev A ───

        Route::resource('invoices', InvoiceController::class);
        Route::patch('invoices/{invoice}/send', [InvoiceController::class, 'markSent'])
            ->name('invoices.send');
        Route::patch('invoices/{invoice}/pay', [InvoiceController::class, 'markPaid'])
            ->name('invoices.pay');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'exportPdf'])
            ->name('invoices.pdf');

    });
