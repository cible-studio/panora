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
        Route::post('alerts/read-all', [AlertController::class, 'markAllRead'])
            ->name('alerts.read-all');
        Route::delete('alerts/{alert}', [AlertController::class, 'destroy'])
            ->name('alerts.destroy');

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
    });
