<?php

use Illuminate\Support\Facades\Route;

// ── Dev A ─────────────────────────────────────────────────────────
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PanelController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\PoseController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\RapportController;
use App\Http\Controllers\Admin\PigeController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\InvoiceController;

use App\Http\Controllers\Settings\ZoneController;
use App\Http\Controllers\Settings\CommuneController;
use App\Http\Controllers\Settings\PanelFormatController;
use App\Http\Controllers\Settings\PanelCategoryController;
use App\Http\Controllers\Settings\SettingsController;

// ── Dev B ─────────────────────────────────────────────────────────
use App\Http\Controllers\Admin\PropositionController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ExternalAgencyController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\CampaignController;

use App\Http\Controllers\Client\ClientAuthController;
use App\Http\Controllers\Client\ClientDashboardController;

// ══════════════════════════════════════════════════════════════════
// ROUTES PUBLIQUES (sans auth)
// ══════════════════════════════════════════════════════════════════

// ── Propositions publiques (lien email client) ────────────────────
Route::prefix('proposition')->name('proposition.')->group(function () {
    Route::get('/{token}',             [PropositionController::class, 'show'])     ->name('show');
    Route::post('/{token}/confirmer',  [PropositionController::class, 'confirmer'])->name('confirmer')->middleware('throttle:5,1');
    Route::post('/{token}/refuser',    [PropositionController::class, 'refuser'])  ->name('refuser') ->middleware('throttle:5,1');
});

// ── Espace client ─────────────────────────────────────────────────
Route::prefix('client')->name('client.')->group(function () {

    // Auth (public)
    Route::get('/login',  [ClientAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [ClientAuthController::class, 'login'])    ->name('login.post')->middleware('throttle:5,1');
    Route::post('/logout',[ClientAuthController::class, 'logout'])   ->name('logout');

    // Routes protégées client
    Route::middleware([
        \App\Http\Middleware\EnsureClientIsAuthenticated::class,
        \App\Http\Middleware\ForceClientPasswordChange::class,
    ])->group(function () {

        Route::get('/dashboard',                    [ClientDashboardController::class, 'index'])           ->name('dashboard');
        Route::get('/propositions',                 [ClientDashboardController::class, 'propositions'])    ->name('propositions');
        Route::get('/propositions/{token}',         [ClientDashboardController::class, 'propositionDetail'])->name('proposition.detail');
        Route::get('/campagnes',                    [ClientDashboardController::class, 'campagnes'])       ->name('campagnes');
        Route::get('/campagnes/{campaign}',         [ClientDashboardController::class, 'campagneDetail'])  ->name('campagne.detail');
        Route::get('/profil',                       [ClientDashboardController::class, 'profil'])          ->name('profil');
        Route::patch('/profil',                     [ClientDashboardController::class, 'updateProfil'])    ->name('profil.update');

        // Changement mot de passe (sans ForceClientPasswordChange pour éviter boucle)
        Route::get('/password/change',  [ClientAuthController::class, 'showChangePassword'])
            ->name('password.change')
            ->withoutMiddleware(\App\Http\Middleware\ForceClientPasswordChange::class);
        Route::post('/password/change', [ClientAuthController::class, 'updatePassword'])
            ->name('password.update')
            ->withoutMiddleware(\App\Http\Middleware\ForceClientPasswordChange::class);
    });
});

// ══════════════════════════════════════════════════════════════════
// ROUTES ADMIN (auth requise)
// ══════════════════════════════════════════════════════════════════

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin,commercial,mediaplanner,technique'])
    ->group(function () {

    // ════════════════════════════════════════════════════
    // DASHBOARD
    // ════════════════════════════════════════════════════

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ════════════════════════════════════════════════════
    // DEV A — PANNEAUX & INFRASTRUCTURE
    // ════════════════════════════════════════════════════

    // ── Panneaux ──────────────────────────────────────────────────
    Route::resource('panels', PanelController::class);
    Route::post('panels/{panel}/status',             [PanelController::class, 'updateStatus'])      ->name('panels.status');
    Route::get('panels/{panel}/availability',        [PanelController::class, 'availability'])      ->name('panels.availability');
    Route::post('panels/{panel}/photos',             [PanelController::class, 'uploadPhoto'])       ->name('panels.photos');
    Route::delete('panels/{panel}/photos/{photo}',  [PanelController::class, 'deletePhoto'])        ->name('panels.photos.delete');
    Route::get('panels/{panel}/pdf',                [PanelController::class, 'exportPdf'])          ->name('panels.pdf');
    Route::get('panels/export/list',                [PanelController::class, 'exportList'])         ->name('panels.export.list');
    Route::get('panels/export/network',             [PanelController::class, 'exportNetwork'])      ->name('panels.export.network');
    Route::get('panels/export/excel',               [PanelController::class, 'exportExcel'])        ->name('panels.export.excel');
    Route::get('panels/export/network-excel',       [PanelController::class, 'exportNetworkExcel']) ->name('panels.export.network-excel');

    // ── Carte & Heatmap ───────────────────────────────────────────
    Route::get('map',       [PanelController::class, 'map'])     ->name('map');
    Route::get('map/data',  [PanelController::class, 'mapData']) ->name('map.data');

    // ── Pose OOH ──────────────────────────────────────────────────
    Route::resource('pose-tasks', PoseController::class);
    Route::post('pose-tasks/{task}/complete', [PoseController::class, 'markComplete'])->name('pose.complete');

    // ── Maintenance ───────────────────────────────────────────────
    Route::resource('maintenances', MaintenanceController::class);
    Route::post('maintenances/{maintenance}/resolve', [MaintenanceController::class, 'resolve'])->name('maintenances.resolve');

    // ── Alertes ───────────────────────────────────────────────────
    Route::get('alerts',                  [AlertController::class, 'index'])       ->name('alerts.index');
    Route::post('alerts/read-all',        [AlertController::class, 'markAllRead']) ->name('alerts.read-all');
    Route::post('alerts/{alert}/read',    [AlertController::class, 'markRead'])    ->name('alerts.read');
    Route::delete('alerts/{alert}',       [AlertController::class, 'destroy'])     ->name('alerts.destroy');

    // ── Piges Photos ──────────────────────────────────────────────
    // ⚠️ Routes spécifiques AVANT {pige} pour éviter les conflits
    Route::get('piges',              [PigeController::class, 'index'])    ->name('piges.index');
    Route::post('piges/upload',      [PigeController::class, 'upload'])   ->name('piges.upload');
    Route::get('piges/export/pdf',   [PigeController::class, 'exportPdf'])->name('piges.export.pdf');
    Route::get('piges/{pige}',       [PigeController::class, 'show'])     ->name('piges.show');
    Route::post('piges/{pige}/verify',  [PigeController::class, 'verify'])  ->name('piges.verify');
    Route::post('piges/{pige}/reject',  [PigeController::class, 'reject'])  ->name('piges.reject');
    Route::delete('piges/{pige}',       [PigeController::class, 'destroy']) ->name('piges.destroy');

    // ── Taxes Communes ────────────────────────────────────────────
    // ⚠️ Routes spécifiques AVANT resource
    Route::get('taxes/export/pdf',    [TaxController::class, 'exportPdf']) ->name('taxes.export.pdf');
    Route::resource('taxes', TaxController::class);
    Route::patch('taxes/{tax}/pay',   [TaxController::class, 'markPaid'])  ->name('taxes.pay');

    // ── Facturation ───────────────────────────────────────────────
    // ⚠️ Routes spécifiques AVANT resource
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'exportPdf'])->name('invoices.pdf');
    Route::resource('invoices', InvoiceController::class);
    Route::patch('invoices/{invoice}/send', [InvoiceController::class, 'markSent'])->name('invoices.send');
    Route::patch('invoices/{invoice}/pay',  [InvoiceController::class, 'markPaid']) ->name('invoices.pay');

    // ── Paramètres (admin uniquement) ─────────────────────────────
    Route::middleware('role:admin')
        ->prefix('settings')
        ->name('settings.')
        ->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::resource('zones',      ZoneController::class);
            Route::resource('communes',   CommuneController::class);
            Route::resource('formats',    PanelFormatController::class);
            Route::resource('categories', PanelCategoryController::class);
        });

    // ── Utilisateurs (admin uniquement) ───────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class);
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle');
        Route::get('audit-logs',                  [UserController::class, 'auditLogs'])    ->name('audit.logs');
    });

    // ── Rapports & Analyses ───────────────────────────────────────
    Route::prefix('rapports')->name('rapports.')->group(function () {
        Route::get('/',     [RapportController::class, 'index'])->name('index');
        Route::get('/ajax', [RapportController::class, 'ajax']) ->name('ajax');
    });

    // ════════════════════════════════════════════════════
    // DEV B — RÉSERVATIONS, CAMPAGNES, CLIENTS
    // ════════════════════════════════════════════════════

    // ── Clients ───────────────────────────────────────────────────
    // ⚠️ quick-store AVANT resource pour ne pas être capturé par {client}
    Route::post('clients/quick-store', [ClientController::class, 'storeQuick'])->name('clients.quick-store');
    Route::resource('clients', ClientController::class);
    Route::get('clients/{client}/data',        [ClientController::class, 'getClientData'])  ->name('clients.data')->middleware('throttle:60,1');
    Route::post('clients/{client}/account',    [ClientController::class, 'createAccount'])  ->name('clients.account.create');
    Route::post('clients/{client}/account/reset', [ClientController::class, 'resetPassword'])->name('clients.account.reset');
    Route::delete('clients/{client}/account', [ClientController::class, 'revokeAccount'])   ->name('clients.account.revoke');

    // ── Régies externes ───────────────────────────────────────────
    Route::resource('external-agencies', ExternalAgencyController::class)->except(['create', 'edit']);
    Route::post('external-agencies/{externalAgency}/panels',              [ExternalAgencyController::class, 'storePanel'])  ->name('external-agencies.panels.store');
    Route::put('external-agencies/{externalAgency}/panels/{panel}',       [ExternalAgencyController::class, 'updatePanel'])  ->name('external-agencies.panels.update');
    Route::delete('external-agencies/{externalAgency}/panels/{panel}',    [ExternalAgencyController::class, 'destroyPanel']) ->name('external-agencies.panels.destroy');

    // ── Disponibilités & Réservations ─────────────────────────────
    // ⚠️ RÈGLE CRITIQUE : routes spécifiques TOUJOURS avant Route::resource()

    // Prix panneaux dans réservation
    Route::patch('reservations/{reservation}/panels/{panel}/price',       [ReservationController::class, 'updatePanelPrice']) ->name('reservations.panels.price');
    Route::post('reservations/{reservation}/panels/{panel}/price/reset',  [ReservationController::class, 'resetPanelPrice'])  ->name('reservations.panels.price.reset');

    // Disponibilités
    Route::get('disponibilites',            [ReservationController::class, 'disponibilites'])      ->name('reservations.disponibilites');
    Route::post('disponibilites/confirmer', [ReservationController::class, 'confirmerSelection'])  ->name('reservations.confirmer-selection');
    Route::get('disponibilites/panneaux',   [ReservationController::class, 'panneauxAjax'])        ->name('reservations.disponibilites.panneaux')->middleware('throttle:120,1');
    Route::get('disponibilites/export',     [ReservationController::class, 'exportDisponibilites'])->name('disponibilites.export');
    Route::post('disponibilites/pdf-images',[ReservationController::class, 'pdfImages'])           ->name('reservations.disponibilites.pdf-images');
    Route::post('disponibilites/pdf-liste', [ReservationController::class, 'pdfListe'])            ->name('reservations.disponibilites.pdf-liste');

    // Réservations spécifiques avant resource
    Route::get('reservations/available-panels', [ReservationController::class, 'availablePanels'])->name('reservations.available-panels')->middleware('throttle:60,1');
    Route::post('reservations/mark-seen',        [ReservationController::class, 'markSeen'])       ->name('reservations.mark-seen');

    // CRUD Réservations
    Route::resource('reservations', ReservationController::class)->except(['create', 'store']);
    Route::patch('reservations/{reservation}/status',  [ReservationController::class, 'updateStatus'])->name('reservations.update-status');
    Route::patch('reservations/{reservation}/annuler', [ReservationController::class, 'annuler'])     ->name('reservations.annuler');

    // Propositions admin (envoi email)
    Route::post('reservations/{reservation}/proposition/envoyer',      [ReservationController::class, 'envoyerProposition'])    ->name('reservations.proposition.envoyer');
    Route::post('reservations/{reservation}/proposition/reinitialiser', [ReservationController::class, 'reinitialiserProposition'])->name('reservations.proposition.reinitialiser');

    // ── Campagnes ─────────────────────────────────────────────────
    Route::resource('campaigns', CampaignController::class);
    Route::patch('campaigns/{campaign}/status',                         [CampaignController::class, 'updateStatus'])      ->name('campaigns.update-status');
    Route::patch('campaigns/{campaign}/prolonger',                      [CampaignController::class, 'prolonger'])         ->name('campaigns.prolonger');
    Route::post('campaigns/{campaign}/panels',                          [CampaignController::class, 'addPanel'])          ->name('campaigns.panels.add');
    Route::delete('campaigns/{campaign}/panels/{panel}',               [CampaignController::class, 'removePanel'])       ->name('campaigns.panels.remove');
    Route::delete('campaigns/{campaign}/external-panels/{externalPanel}', [CampaignController::class, 'removeExternalPanel'])->name('campaigns.external-panels.remove');

    // ── Propositions (admin) ──────────────────────────────────────
    Route::resource('propositions', PropositionController::class);
    Route::patch('propositions/{proposition}/status', [PropositionController::class, 'updateStatus'])->name('propositions.update-status');
    Route::get('propositions/{proposition}/pdf',      [PropositionController::class, 'exportPdf'])   ->name('propositions.pdf');

}); // ← FIN du groupe admin
