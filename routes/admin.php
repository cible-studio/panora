<?php

use App\Http\Controllers\Admin\RapportController;
use Illuminate\Support\Facades\Route;

// ── Dev A ─────────────────────────────────────────────
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
use App\Http\Controllers\Settings\SettingsController;

use App\Http\Controllers\Admin\PropositionController;
use App\Http\Controllers\Admin\PigeController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\InvoiceController;

// ── Dev B ─────────────────────────────────────────────
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ExternalAgencyController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\CampaignController;

use App\Http\Controllers\Client\ClientAuthController;
use App\Http\Controllers\Client\ClientDashboardController;

// ══════════════════════════════════════════════════════════════
// ROUTES PUBLIQUES (sans auth)
// ══════════════════════════════════════════════════════════════

// ── Routes propositions publiques ──────────────────────────────
Route::prefix('proposition')->name('proposition.')->group(function () {

    // Ancienne URL (token 64 chars) — rétrocompatibilité
    Route::get('/{token}', function ($token) {
        $reservation = \App\Models\Reservation::where('proposition_token', $token)
            ->whereNotNull('proposition_slug')
            ->first();
        if ($reservation) {
            return redirect()->route('proposition.show', [
                $reservation->reference,
                $reservation->proposition_slug,
            ], 301);
        }
        abort(404, 'Proposition introuvable.');
    })->name('show.legacy');

    // Nouvelle URL lisible + sécurisée
    Route::get('/{reference}/{slug}', [PropositionController::class, 'showPublic'])
        ->name('show');

    Route::post('/{reference}/{slug}/confirmer', [PropositionController::class, 'confirmer'])
        ->name('confirmer')
        ->middleware('throttle:5,1');

    Route::post('/{reference}/{slug}/refuser', [PropositionController::class, 'refuser'])
        ->name('refuser')
        ->middleware('throttle:5,1');

    Route::delete('/{reference}/{slug}/panneau/{panelId}', [PropositionController::class, 'retirerPanneau'])
        ->name('retirer-panneau')
        ->middleware('throttle:10,1');
});

// ── Routes espace client (sans auth) ──────────────────────────
Route::prefix('client')->name('client.')->group(function () {
    // Auth
    Route::get('/login', [ClientAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [ClientAuthController::class, 'login'])
        ->name('login.post')
        ->middleware('throttle:5,1');
    Route::post('/logout', [ClientAuthController::class, 'logout'])->name('logout');

    // Routes protégées
    Route::middleware([
        \App\Http\Middleware\EnsureClientIsAuthenticated::class,
        \App\Http\Middleware\ForceClientPasswordChange::class,
    ])->group(function () {
        // Dashboard
        Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');

        // Propositions
        Route::get('/propositions', [ClientDashboardController::class, 'propositions'])->name('propositions');
        Route::get('/propositions/{token}', [ClientDashboardController::class, 'propositionDetail'])->name('proposition.detail');

        // Campagnes
        Route::get('/campagnes', [ClientDashboardController::class, 'campagnes'])->name('campagnes');
        Route::get('/campagnes/{campaign}', [ClientDashboardController::class, 'campagneDetail'])->name('campagne.detail');

        //Poses & Piges
        Route::get('/poses',  [ClientDashboardController::class, 'poses']) ->name('poses');
        Route::get('/piges',  [ClientDashboardController::class, 'piges']) ->name('piges');

        // Profil
        Route::get('/profil', [ClientDashboardController::class, 'profil'])->name('profil');
        Route::patch('/profil', [ClientDashboardController::class, 'updateProfil'])->name('profil.update');

        // Changement mot de passe (sans middleware ForceClientPasswordChange)
        Route::get('/password/change', [ClientAuthController::class, 'showChangePassword'])
            ->name('password.change')
            ->withoutMiddleware(\App\Http\Middleware\ForceClientPasswordChange::class);
        Route::post('/password/change', [ClientAuthController::class, 'updatePassword'])
            ->name('password.update')
            ->withoutMiddleware(\App\Http\Middleware\ForceClientPasswordChange::class);
    });
});

// ══════════════════════════════════════════════════════════════
// ROUTES ADMIN (auth requise)
// ══════════════════════════════════════════════════════════════

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin,commercial,mediaplanner,technique'])
    ->group(function () {

        // ════════════════════════════════════════════════
        // DEV A
        // ════════════════════════════════════════════════

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── Panneaux ──────────────────────────────────────────────
        Route::resource('panels', PanelController::class);
        Route::post('panels/{panel}/status', [PanelController::class, 'updateStatus'])
            ->name('panels.status');
        Route::get('panels/{panel}/availability', [PanelController::class, 'availability'])
            ->name('panels.availability');
        Route::post('panels/{panel}/photos', [PanelController::class, 'uploadPhoto'])
            ->name('panels.photos');
        Route::delete('panels/{panel}/photos/{photo}', [PanelController::class, 'deletePhoto'])
            ->name('panels.photos.delete');
        Route::get('panels/{panel}/pdf', [PanelController::class, 'exportPdf'])
            ->name('panels.pdf');
        Route::get('panels/export/list', [PanelController::class, 'exportList'])
            ->name('panels.export.list');
        Route::get('panels/export/network', [PanelController::class, 'exportNetwork'])
            ->name('panels.export.network');
        Route::get('panels/export/excel', [PanelController::class, 'exportExcel'])
            ->name('panels.export.excel');
        Route::get('panels/export/network-excel', [PanelController::class, 'exportNetworkExcel'])
            ->name('panels.export.network-excel');

        // ── Carte / Heatmap ───────────────────────────────────────
        Route::get('map', [PanelController::class, 'map'])
            ->name('map');
        Route::get('map/data', [PanelController::class, 'mapData'])
            ->name('map.data');

        // ── Pose OOH ──────────────────────────────────────────────────
        // ⚠️ Routes AJAX spécifiques AVANT resource pour éviter conflits
        Route::prefix('pose-tasks')->name('pose-tasks.')->group(function () {
            // ── AJAX endpoints (avant les routes paramétriques) ──────
            Route::get('search-campaigns', [PoseController::class, 'searchCampaigns'])->name('search-campaigns');
            Route::get('campaign-panels',  [PoseController::class, 'campaignPanels']) ->name('campaign-panels');
            Route::get('search-panels',    [PoseController::class, 'searchPanels'])   ->name('search-panels');

            // ── CRUD standard ─────────────────────────────────────────
            Route::get('/',         [PoseController::class, 'index'])  ->name('index');
            Route::get('/create',   [PoseController::class, 'create']) ->name('create');
            Route::post('/',        [PoseController::class, 'store'])  ->name('store');
            Route::get('/{poseTask}',      [PoseController::class, 'show'])->name('show');
            Route::get('/{poseTask}/edit', [PoseController::class, 'edit'])->name('edit');
            Route::put('/{poseTask}',      [PoseController::class, 'update'])->name('update');
            Route::delete('/{poseTask}',   [PoseController::class, 'destroy'])->name('destroy');
        });
        
        // ── Alias pour markComplete (rétrocompatibilité) ──────────────
        Route::post('pose-tasks/{poseTask}/complete', [PoseController::class, 'markComplete'])
            ->name('pose.complete');        

        // Maintenance
        Route::resource('maintenances', MaintenanceController::class);
        Route::post('maintenances/{maintenance}/resolve', [MaintenanceController::class, 'resolve'])->name('maintenances.resolve');

        // ── Alertes ───────────────────────────────────────────────
        Route::get('alerts', [AlertController::class, 'index'])
            ->name('alerts.index');
        Route::post('alerts/read-all', [AlertController::class, 'markAllRead'])
            ->name('alerts.read-all');
        Route::post('alerts/{alert}/read', [AlertController::class, 'markRead'])
            ->name('alerts.read');
        Route::delete('alerts/{alert}', [AlertController::class, 'destroy'])
            ->name('alerts.destroy');

        // Paramètres (admin uniquement)
        Route::middleware('role:admin')
            ->prefix('settings')
            ->name('settings.')
            ->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::resource('zones', ZoneController::class);
            Route::resource('communes', CommuneController::class);
            Route::resource('formats', PanelFormatController::class);
            Route::resource('categories', PanelCategoryController::class);
        });

        // Utilisateurs (admin uniquement)
        Route::middleware('role:admin')->group(function () {
            Route::resource('users', UserController::class);
            Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle');
            Route::get('audit-logs', [UserController::class, 'auditLogs'])->name('audit.logs');
        });

        // ── Piges Photos ──────────────────────────────────────────
        // Route::prefix('piges')->name('piges.')->group(function () {
        //     Route::get('/',                        [PigeController::class, 'index'])           ->name('index');
        //     Route::post('/upload',                 [PigeController::class, 'upload'])          ->name('upload');
        //     Route::get('/export-pdf',              [PigeController::class, 'exportPdf'])       ->name('export-pdf');
        //     Route::get('/context',                 [PigeController::class, 'context'])         ->name('context');
        //     Route::get('/panels-by-campaign',      [PigeController::class, 'panelsByCampaign'])->name('panels-by-campaign'); // ← NOUVEAU
        //     Route::get('/campagne/{campaign}',     [PigeController::class, 'byCampaign'])      ->name('by-campaign');
        //     // Routes avec {pige} EN DERNIER
        //     Route::get('/{pige}',                  [PigeController::class, 'show'])            ->name('show');
        //     Route::post('/{pige}/verify',          [PigeController::class, 'verify'])          ->name('verify');
        //     Route::post('/{pige}/reject',          [PigeController::class, 'reject'])          ->name('reject');
        //     Route::delete('/{pige}',              [PigeController::class, 'destroy'])          ->name('destroy');
        // });

        // ══════════════════════════════════════════════════════════════
        // ROUTES PIGES — CRUD + actions spécifiques
        // ══════════════════════════════════════════════════════════════
        
        Route::prefix('piges')->name('piges.')->group(function () {
        
            // ── AJAX (avant les routes paramétriques) ──────────────────
            Route::get('campaign-panels', [PigeController::class, 'campaignPanels'])->name('campaign-panels');
            Route::post('verify-batch',   [PigeController::class, 'verifyBatch'])   ->name('verify-batch');
        
            // ── Actions sur une pige ───────────────────────────────────
            Route::post('{pige}/verify', [PigeController::class, 'verify']) ->name('verify');
            Route::post('{pige}/reject', [PigeController::class, 'reject']) ->name('reject');
        
            // ── CRUD standard ──────────────────────────────────────────
            Route::get('/',           [PigeController::class, 'index'])  ->name('index');
            Route::get('/create',     [PigeController::class, 'create']) ->name('create');
            Route::post('/',          [PigeController::class, 'store'])  ->name('store');
            Route::get('/{pige}',     [PigeController::class, 'show'])   ->name('show');
            Route::get('/{pige}/edit',  [PigeController::class, 'edit'])   ->name('edit');
            Route::put('/{pige}',       [PigeController::class, 'update']) ->name('update');
            Route::delete('/{pige}',  [PigeController::class, 'destroy'])->name('destroy');
        });

        // ── Taxes Communes ────────────────────────────────────────
        Route::resource('taxes', TaxController::class);
        Route::patch('taxes/{tax}/pay', [TaxController::class, 'markPaid'])->name('taxes.pay');
        Route::get('taxes/export/pdf', [TaxController::class, 'exportPdf'])->name('taxes.export.pdf');

        // ── Facturation ───────────────────────────────────────────
        Route::resource('invoices', InvoiceController::class);
        Route::patch('invoices/{invoice}/send', [InvoiceController::class, 'markSent'])->name('invoices.send');
        Route::patch('invoices/{invoice}/pay', [InvoiceController::class, 'markPaid'])->name('invoices.pay');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'exportPdf'])->name('invoices.pdf');

        // ════════════════════════════════════════════════
        // DEV B
        // ════════════════════════════════════════════════
        // Clients
        Route::post('clients/quick-store', [ClientController::class, 'storeQuick'])
            ->name('clients.quick-store');
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');

        Route::get('clients/{client}/data', [ClientController::class, 'getClientData'])
            ->name('admin.clients.data')
            ->middleware('throttle:60,1');
        Route::post('clients/{client}/account', [ClientController::class, 'createAccount'])->name('clients.account.create');
        Route::post('clients/{client}/account/reset', [ClientController::class, 'resetPassword'])->name('clients.account.reset');
        Route::delete('clients/{client}/account', [ClientController::class, 'revokeAccount'])->name('clients.account.revoke');

        // Régies externes
        Route::resource('external-agencies', ExternalAgencyController::class)->except(['create', 'edit']);

        Route::post('external-agencies/{externalAgency}/panels', [ExternalAgencyController::class, 'storePanel'])
            ->name('external-agencies.panels.store');
        Route::put('external-agencies/{externalAgency}/panels/{panel}', [ExternalAgencyController::class, 'updatePanel'])
            ->name('external-agencies.panels.update');
        Route::delete('external-agencies/{externalAgency}/panels/{panel}', [ExternalAgencyController::class, 'destroyPanel'])
            ->name('external-agencies.panels.destroy');

        // ══════════════════════════════════════════════════════════
        // ⚠️ RÈGLE IMPORTANTE : routes GET spécifiques AVANT resource
        // ══════════════════════════════════════════════════════════

        // Prix panneaux dans une réservation
        Route::patch(
            'reservations/{reservation}/panels/{panel}/price',
            [ReservationController::class, 'updatePanelPrice']
        )
            ->name('reservations.panels.price');

        Route::post(
            'reservations/{reservation}/panels/{panel}/price/reset',
            [ReservationController::class, 'resetPanelPrice']
        )
            ->name('reservations.panels.price.reset');

        // Disponibilités
        Route::get('disponibilites', [ReservationController::class, 'disponibilites'])->name('reservations.disponibilites');
        Route::post('disponibilites/confirmer', [ReservationController::class, 'confirmerSelection'])->name('reservations.confirmer-selection');

        // Route AJAX pour récupérer les panneaux disponibles d'une campagne (utilisée dans la modale de création de réservation)
        Route::get('disponibilites/panneaux', [ReservationController::class, 'panneauxAjax'])
            ->name('reservations.disponibilites.panneaux')
            // Throttling pour éviter les abus sur cette route qui peut être appelée fréquemment lors de la création de réservations
            ->middleware('throttle:120,1');

        // Exports PDF disponibilités
        Route::get('disponibilites/export', [ReservationController::class, 'exportDisponibilites'])->name('disponibilites.export');
        Route::post('disponibilites/pdf-images', [ReservationController::class, 'pdfImages'])
            ->name('reservations.disponibilites.pdf-images');
        Route::post('disponibilites/pdf-liste', [ReservationController::class, 'pdfListe'])
            ->name('reservations.disponibilites.pdf-liste');


        // Réservations
        Route::get('reservations/available-panels', [ReservationController::class, 'availablePanels'])
            ->name('reservations.available-panels')
            ->middleware('throttle:60,1');
        Route::post('reservations/mark-seen', [ReservationController::class, 'markSeen'])->name('reservations.mark-seen');

        // CRUD Réservations (en dernier pour ne pas capturer les routes spécifiques)
        Route::resource('reservations', ReservationController::class)->except(['create', 'store']);
        Route::patch('reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])->name('reservations.update-status');
        Route::patch('reservations/{reservation}/annuler', [ReservationController::class, 'annuler'])->name('reservations.annuler');

        // Propositions (admin)
        Route::post(
            'reservations/{reservation}/proposition/envoyer',
            [PropositionController::class, 'envoyerProposition']
        )
            ->name('reservations.proposition.envoyer');

        Route::post(
            'reservations/{reservation}/proposition/reinitialiser',
            [PropositionController::class, 'reinitialiserProposition']
        )
            ->name('reservations.proposition.reinitialiser');

        // ── CRUD Propositions admin ─────────────────────────────────────
        Route::get('propositions', [PropositionController::class, 'index'])
            ->name('propositions.index');
        Route::get('propositions/{proposition}', [PropositionController::class, 'show'])
            ->name('propositions.show');
        Route::patch('propositions/{proposition}/status', [PropositionController::class, 'updateStatus'])
            ->name('propositions.update-status');
        Route::get('propositions/{proposition}/pdf', [PropositionController::class, 'exportPdf'])
            ->name('propositions.pdf');

        // Campagnes
        Route::resource('campaigns', CampaignController::class);
        Route::patch('campaigns/{campaign}/status', [CampaignController::class, 'updateStatus'])->name('campaigns.update-status');
        Route::patch('campaigns/{campaign}/prolonger', [CampaignController::class, 'prolonger'])->name('campaigns.prolonger');
        Route::post('campaigns/{campaign}/panels', [CampaignController::class, 'addPanel'])->name('campaigns.panels.add');
        Route::delete('campaigns/{campaign}/panels/{panel}', [CampaignController::class, 'removePanel'])->name('campaigns.panels.remove');

        // Gestion panneaux externes d'une campagne
        Route::delete('campaigns/{campaign}/external-panels/{externalPanel}', [CampaignController::class, 'removeExternalPanel'])
            ->name('campaigns.external-panels.remove');

        // ── Taxes Communes ────────────────────────────────────────
        Route::resource('taxes', TaxController::class);
        Route::patch('taxes/{tax}/pay', [TaxController::class, 'markPaid'])->name('taxes.pay');
        Route::get('taxes/export/pdf', [TaxController::class, 'exportPdf'])->name('taxes.export.pdf');

        // ── Facturation ───────────────────────────────────────────
        Route::resource('invoices', InvoiceController::class);
        Route::patch('invoices/{invoice}/send', [InvoiceController::class, 'markSent'])->name('invoices.send');
        Route::patch('invoices/{invoice}/pay', [InvoiceController::class, 'markPaid'])->name('invoices.pay');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'exportPdf'])->name('invoices.pdf');

        Route::get('/rapports', [RapportController::class, 'index'])->name('rapports.index');
        Route::get('/rapports/ajax', [RapportController::class, 'ajax'])->name('rapports.ajax');

    });


