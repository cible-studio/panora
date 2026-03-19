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

Route::prefix('admin')
     ->name('admin.')
     ->middleware(['auth', 'role:admin,commercial,mediaplanner,technique'])
     ->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
         ->name('dashboard');

    // Panneaux
    Route::resource('panels', PanelController::class);
    Route::post('panels/{panel}/status', [PanelController::class, 'updateStatus'])
         ->name('panels.status');
    Route::get('panels/{panel}/availability', [PanelController::class, 'availability'])
         ->name('panels.availability');
    Route::post('panels/{panel}/photos', [PanelController::class, 'uploadPhoto'])
         ->name('panels.photos');
    Route::get('map', [PanelController::class, 'map'])
         ->name('map');
    Route::get('map/data', [PanelController::class, 'mapData'])
         ->name('map.data');

    // Pose OOH
    Route::resource('pose-tasks', PoseController::class);
    Route::post('pose-tasks/{task}/complete', [PoseController::class, 'markComplete'])
         ->name('pose.complete');

    // Maintenance
    Route::resource('maintenances', MaintenanceController::class);
    Route::post('maintenances/{maintenance}/resolve', [MaintenanceController::class, 'resolve'])
         ->name('maintenances.resolve');

    // Alertes
    Route::get('alerts', [AlertController::class, 'index'])
         ->name('alerts.index');
    Route::post('alerts/{alert}/read', [AlertController::class, 'markRead'])
         ->name('alerts.read');

    // Paramètres — admin seulement
    Route::middleware('role:admin')
         ->prefix('settings')
         ->name('settings.')
         ->group(function () {
            Route::resource('zones', ZoneController::class);
            Route::resource('communes', CommuneController::class);
            Route::resource('formats', PanelFormatController::class);
            Route::resource('categories', PanelCategoryController::class);
    });

    // Utilisateurs — admin seulement
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class);
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
             ->name('users.toggle');
        Route::get('audit-logs', [UserController::class, 'auditLogs'])
             ->name('audit.logs');
    });


    // ── Clients ──────────────────────────────── Dev B ───
    Route::resource('clients', \App\Http\Controllers\Admin\ClientController::class);

    // ── Régies externes ──────────────────────── Dev B ───
    Route::resource('external-agencies', \App\Http\Controllers\Admin\ExternalAgencyController::class)
        ->except(['create', 'edit']);
    Route::post('external-agencies/{externalAgency}/panels',
        [\App\Http\Controllers\Admin\ExternalAgencyController::class, 'storePanel'])
        ->name('external-agencies.panels.store');
    Route::put('external-agencies/{externalAgency}/panels/{panel}',
        [\App\Http\Controllers\Admin\ExternalAgencyController::class, 'updatePanel'])
        ->name('external-agencies.panels.update');
    Route::delete('external-agencies/{externalAgency}/panels/{panel}',
        [\App\Http\Controllers\Admin\ExternalAgencyController::class, 'destroyPanel'])
        ->name('external-agencies.panels.destroy');

    // ── Disponibilités ───────────────────────── Dev B ───
    Route::get('disponibilites',
        [\App\Http\Controllers\Admin\ReservationController::class, 'disponibilites'])
        ->name('reservations.disponibilites');
    Route::post('disponibilites/confirmer',
        [\App\Http\Controllers\Admin\ReservationController::class, 'confirmerSelection'])
        ->name('reservations.confirmer-selection');

    // ── Réservations ─────────────────────────── Dev B ───
    Route::get('reservations/available-panels',
        [\App\Http\Controllers\Admin\ReservationController::class, 'availablePanels'])
        ->name('reservations.available-panels')
        ->middleware('throttle:60,1');
    Route::resource('reservations', \App\Http\Controllers\Admin\ReservationController::class)
        ->except(['create', 'store']);
    Route::patch('reservations/{reservation}/status',
        [\App\Http\Controllers\Admin\ReservationController::class, 'updateStatus'])
        ->name('reservations.update-status');
    Route::patch('reservations/{reservation}/annuler',
        [\App\Http\Controllers\Admin\ReservationController::class, 'annuler'])
        ->name('reservations.annuler');
    Route::post('reservations/mark-seen',
        [\App\Http\Controllers\Admin\ReservationController::class, 'markSeen'])
        ->name('reservations.mark-seen');

    // ── Campagnes ────────────────────────────── Dev B ───
    Route::resource('campaigns', \App\Http\Controllers\Admin\CampaignController::class);
    Route::patch('campaigns/{campaign}/status',
        [\App\Http\Controllers\Admin\CampaignController::class, 'updateStatus'])
        ->name('campaigns.update-status');
    Route::post('campaigns/{campaign}/panels',
        [\App\Http\Controllers\Admin\CampaignController::class, 'addPanel'])
        ->name('campaigns.panels.add');
    Route::delete('campaigns/{campaign}/panels/{panel}',
        [\App\Http\Controllers\Admin\CampaignController::class, 'removePanel'])
        ->name('campaigns.panels.remove');

    Route::patch('campaigns/{campaign}/prolonger',
    [\App\Http\Controllers\Admin\CampaignController::class, 'prolonger'])
    ->name('campaigns.prolonger');



});
