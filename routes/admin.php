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
// ── Routes PUBLIQUES Pose OOH (accès technicien sans auth) ─────────
// Throttle strict pour éviter l'abus en cas de fuite du lien.
// ── ANCIENS LIENS /pose/{token} → REDIRECT vers /pige/{token} ──────
// La spec Évolution 3 demande un lien unique au format /pige/{token}.
// Tout ancien lien WhatsApp encore dans la poche du technicien est
// automatiquement redirigé vers la nouvelle URL — pas de rupture.
//
// Note : pour les POST (update/done/photo), Laravel ne supporte pas
// proprement le redirect 307/308 avec préservation du body. Le tech
// rechargera la page (GET → redirect 301 → nouvelle URL) et soumettra
// vers les nouvelles routes côté JS. Les noms 'pose.public.*' sont
// préservés comme alias des nouveaux noms pour ne pas casser le code
// qui appelle route('pose.public.show', ...) en attendant la migration.
Route::redirect('/pose/{token}', '/pige/{token}', 301)
    ->where('token', '[A-Za-z0-9]{32}');

// Alias des noms de routes pour code legacy (PoseTask::publicUrl utilise
// pose.public.show — gardé fonctionnel via le nom de route ci-dessous).
Route::prefix('pose')->name('pose.public.')->group(function () {
    Route::get('/{token}', fn($token) => redirect("/pige/{$token}", 301))
        ->name('show')
        ->where('token', '[A-Za-z0-9]{32}');
});

// ── Route PUBLIQUE Satisfaction client (T9) ─────────────────────────
// Accès direct via token 64 chars sans authentification.
// Locale forcée 'fr' (Lot 12.1).
Route::prefix('satisfaction')->middleware(['throttle:10,1', \App\Http\Middleware\SetFrenchLocale::class])->group(function () {
    Route::get('/{token}',  [\App\Http\Controllers\SatisfactionController::class, 'show'])
        ->name('satisfaction.show');
    Route::post('/{token}', [\App\Http\Controllers\SatisfactionController::class, 'submit'])
        ->name('satisfaction.submit');
});

// ── Route PUBLIQUE Pige campagne (Lot 5) ────────────────────────────
// Le commercial génère un token sur la fiche campagne et le partage au
// technicien terrain. Throttle plus large (60 req/min) car upload de
// photos sur plusieurs panneaux en suivant — éviter le throttle agressif.
// Locale forcée 'fr' (Lot 12.1) — public client.
// ── LIEN UNIQUE TECHNICIEN : /pige/{token} ─────────────────────────
// Un seul point d'entrée pour le tech terrain (cf. Évolution 3 du spec).
// Le token résout vers :
//   - PoseTask (32 chars)  → interface unique intervention/panneau
//   - Campaign (48 chars)  → interface multi-panneaux campagne (legacy)
// Toutes les sous-actions vivent sous /pige/{token}/... — l'ancien
// préfixe /pose/{token}/... est conservé pour rétrocompat mais redirige.
Route::prefix('pige')->middleware(['throttle:60,1', \App\Http\Middleware\SetFrenchLocale::class])->group(function () {

    // Dispatcher GET — PublicPigeController::show essaie d'abord
    // pose_task, fallback campaign.
    Route::get('/{token}',           [\App\Http\Controllers\PublicPigeController::class, 'show'])
        ->name('pige.public.show');

    // ─── Sous-routes intervention/panneau (PoseTask) ────────────────
    Route::post('/{token}/update',       [\App\Http\Controllers\PoseTaskPublicController::class, 'update'])
        ->name('pige.public.intervention.update');
    Route::post('/{token}/done',         [\App\Http\Controllers\PoseTaskPublicController::class, 'markDone'])
        ->name('pige.public.intervention.done');
    Route::post('/{token}/photo',        [\App\Http\Controllers\PoseTaskPublicController::class, 'uploadPhoto'])
        ->name('pige.public.intervention.photo')
        ->middleware('throttle:30,1');
    Route::delete('/{token}/photo/{pigeId}', [\App\Http\Controllers\PoseTaskPublicController::class, 'deletePhoto'])
        ->name('pige.public.intervention.photo.delete')
        ->whereNumber('pigeId');
    Route::post('/{token}/status',           [\App\Http\Controllers\PoseTaskPublicController::class, 'setStatus'])
        ->name('pige.public.intervention.status');
    Route::post('/{token}/photo/{pigeId}/replace', [\App\Http\Controllers\PoseTaskPublicController::class, 'replacePhoto'])
        ->name('pige.public.intervention.photo.replace')
        ->middleware('throttle:30,1')
        ->whereNumber('pigeId');

    // ─── Sous-routes multi-panneaux campagne (legacy) ────────────────
    Route::post('/{token}/upload',   [\App\Http\Controllers\PublicPigeController::class, 'upload'])
        ->name('pige.public.upload');
    // Lot 9.3 — Validation "Pose effectuée" par panneau depuis la page publique
    Route::post('/{token}/posed',    [\App\Http\Controllers\PublicPigeController::class, 'markPosed'])
        ->name('pige.public.posed');
});

Route::prefix('proposition')->name('proposition.')->middleware(\App\Http\Middleware\SetFrenchLocale::class)->group(function () {

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
// Lot 12.1 — locale forcée à 'fr' sur tout le scope client (auth +
// dashboard) pour que validation/auth.failed/mot de passe oublié
// soient en français même si APP_LOCALE est 'en' côté env.
Route::prefix('client')->name('client.')->middleware(\App\Http\Middleware\SetFrenchLocale::class)->group(function () {
    // Auth
    Route::get('/login', [ClientAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [ClientAuthController::class, 'login'])
        ->name('login.post')
        ->middleware('throttle:20,1');
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
        Route::get('/piges/{pige}/download', [ClientDashboardController::class, 'pigeDownload'])->name('pige.download');
        Route::get('/campagnes/{campaign}/piges/download-zip', [ClientDashboardController::class, 'pigesZip'])->name('campagne.piges.zip');

        // Profil
        Route::get('/profil', [ClientDashboardController::class, 'profil'])->name('profil');
        Route::patch('/profil', [ClientDashboardController::class, 'updateProfil'])->name('profil.update');

        // Contact
        Route::get('/contact',  [ClientDashboardController::class, 'contact'])->name('contact');
        Route::post('/contact', [ClientDashboardController::class, 'sendContact'])->name('contact.send');

        // Équipe (multi-utilisateurs)
        Route::get('/equipe',                   [\App\Http\Controllers\Client\ClientUserController::class, 'index'])  ->name('equipe');
        Route::post('/equipe',                  [\App\Http\Controllers\Client\ClientUserController::class, 'store'])  ->name('equipe.store');
        Route::patch('/equipe/{clientUser}',    [\App\Http\Controllers\Client\ClientUserController::class, 'update']) ->name('equipe.update');
        Route::delete('/equipe/{clientUser}',   [\App\Http\Controllers\Client\ClientUserController::class, 'destroy'])->name('equipe.destroy');

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
            // Polling progression temps réel (vue admin index)
            Route::get('progress',         [PoseController::class, 'progress'])       ->name('progress');
            // Actions groupées (sélection multiple)
            Route::post('bulk-update',     [PoseController::class, 'bulkUpdate'])     ->name('bulk-update');

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

        // ── Renvoyer / envoyer manuellement la notification WhatsApp ──
        Route::post('pose-tasks/{poseTask}/notify', [PoseController::class, 'notifyWhatsApp'])
            ->name('pose-tasks.notify');

        // Maintenance
        // Endpoints AJAX recherche panneaux + création rapide technicien
        // (à placer AVANT Route::resource sinon /search-panels matche {maintenance})
        Route::get ('maintenances/search-panels',  [MaintenanceController::class, 'searchPanels'])->name('maintenances.search-panels');
        Route::post('maintenances/quick-tech',     [MaintenanceController::class, 'quickCreateTechnician'])->name('maintenances.quick-tech');
        Route::resource('maintenances', MaintenanceController::class);
        Route::post('maintenances/{maintenance}/resolve', [MaintenanceController::class, 'resolve'])->name('maintenances.resolve');
        Route::post('maintenances/{maintenance}/reopen',  [MaintenanceController::class, 'reopen'])->name('maintenances.reopen');

        // ── Alertes ───────────────────────────────────────────────
        Route::prefix('alerts')->name('alerts.')->group(function () {
            Route::get('/',                       [AlertController::class, 'index'])     ->name('index');
            Route::post('read-all',               [AlertController::class, 'markAllRead'])->name('read-all');
            Route::post('clear-read',             [AlertController::class, 'clearRead'])  ->name('clear-read');
            Route::get('summary',                 [AlertController::class, 'summary'])    ->name('summary');
            Route::post('{alert}/read',           [AlertController::class, 'markRead'])   ->name('read');
            Route::post('{alert}/archive',        [AlertController::class, 'archive'])    ->name('archive');
            Route::delete('{alert}',              [AlertController::class, 'destroy'])    ->name('destroy');
        });

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
        Route::get('taxes/auto/preview',  [TaxController::class, 'previewAuto'])->name('taxes.auto.preview');
        Route::post('taxes/auto/generate', [TaxController::class, 'generateAuto'])->name('taxes.auto.generate');
        Route::get ('taxes/calcul',        [TaxController::class, 'calcul'])       ->name('taxes.calcul');
        Route::get ('taxes/details',       [TaxController::class, 'details'])      ->name('taxes.details');
        Route::get ('taxes/details/pdf',   [TaxController::class, 'detailsPdf'])   ->name('taxes.details.pdf');
        Route::get ('taxes/details/excel', [TaxController::class, 'detailsExcel']) ->name('taxes.details.excel');
        Route::post('taxes/payments',      [TaxController::class, 'recordPayment'])->name('taxes.payments.record');
        Route::get ('taxes/historique',    [TaxController::class, 'historique'])   ->name('taxes.historique');
        Route::resource('taxes', TaxController::class);
        Route::patch('taxes/{tax}/pay', [TaxController::class, 'markPaid'])->name('taxes.pay');
        Route::get('taxes/export/pdf', [TaxController::class, 'exportPdf'])->name('taxes.export.pdf');

        // ── Facturation (admin uniquement) ───────────────────────
        Route::middleware('role:admin')->group(function () {
            Route::get('invoices/export/pdf',   [InvoiceController::class, 'exportListPdf'])->name('invoices.export.pdf');
            Route::get('invoices/export/excel', [InvoiceController::class, 'exportListExcel'])->name('invoices.export.excel');
            Route::resource('invoices', InvoiceController::class);
            Route::patch('invoices/{invoice}/send',         [InvoiceController::class, 'markSent'])->name('invoices.send');
            Route::patch('invoices/{invoice}/pay',          [InvoiceController::class, 'markPaid'])->name('invoices.pay');
            Route::patch('invoices/{invoice}/cancel',       [InvoiceController::class, 'markCancelled'])->name('invoices.cancel');
            Route::patch('invoices/{invoice}/revert-draft', [InvoiceController::class, 'revertDraft'])->name('invoices.revert-draft');
            Route::get('invoices/{invoice}/pdf',            [InvoiceController::class, 'exportPdf'])->name('invoices.pdf');
        });

        // ════════════════════════════════════════════════
        // DEV B
        // ════════════════════════════════════════════════
        // Clients
        Route::post('clients/quick-store', [ClientController::class, 'storeQuick'])
            ->name('clients.quick-store');
        // Import Excel (avant les routes paramétriques pour éviter conflit)
        Route::get('clients/import/template', [ClientController::class, 'importTemplate'])
            ->name('clients.import.template');
        Route::post('clients/import',         [ClientController::class, 'import'])
            ->name('clients.import');
        // Exports liste clients (5.2) — placés AVANT /clients/{client} pour
        // ne pas être avalés par le route model binding.
        Route::get('clients/export/csv', [ClientController::class, 'exportCsv'])
            ->name('clients.export.csv');
        Route::get('clients/export/pdf', [ClientController::class, 'exportPdf'])
            ->name('clients.export.pdf');
        Route::get('clients/export/excel', [ClientController::class, 'exportExcel'])
            ->name('clients.export.excel');
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

        // ── Multi-interlocuteurs (T4) ─────────────────────────────
        Route::post  ('clients/{client}/contacts',                [\App\Http\Controllers\Admin\ClientContactController::class, 'store'])     ->name('clients.contacts.store');
        Route::put   ('clients/{client}/contacts/{contact}',      [\App\Http\Controllers\Admin\ClientContactController::class, 'update'])    ->name('clients.contacts.update');
        Route::delete('clients/{client}/contacts/{contact}',      [\App\Http\Controllers\Admin\ClientContactController::class, 'destroy'])   ->name('clients.contacts.destroy');
        Route::patch ('clients/{client}/contacts/{contact}/primary', [\App\Http\Controllers\Admin\ClientContactController::class, 'setPrimary'])->name('clients.contacts.primary');

        // Régies externes
        Route::resource('external-agencies', ExternalAgencyController::class)->except(['create', 'edit']);

        Route::post('external-agencies/{externalAgency}/panels', [ExternalAgencyController::class, 'storePanel'])
            ->name('external-agencies.panels.store');
        Route::put('external-agencies/{externalAgency}/panels/{panel}', [ExternalAgencyController::class, 'updatePanel'])
            ->name('external-agencies.panels.update');
        Route::delete('external-agencies/{externalAgency}/panels/{panel}', [ExternalAgencyController::class, 'destroyPanel'])
            ->name('external-agencies.panels.destroy');

        // Exports régie externe (PDF images / PDF liste / Excel)
        Route::post('external-agencies/{externalAgency}/exports/pdf-images', [ExternalAgencyController::class, 'pdfImages'])
            ->name('external-agencies.exports.pdf-images');
        Route::post('external-agencies/{externalAgency}/exports/pdf-liste', [ExternalAgencyController::class, 'pdfListe'])
            ->name('external-agencies.exports.pdf-liste');
        Route::post('external-agencies/{externalAgency}/exports/excel', [ExternalAgencyController::class, 'exportExcel'])
            ->name('external-agencies.exports.excel');

        // ══════════════════════════════════════════════════════════
        // ⚠️ RÈGLE IMPORTANTE : routes GET spécifiques AVANT resource
        // ══════════════════════════════════════════════════════════

        // Prix panneaux dans une réservation (internes + externes)
        Route::patch('reservations/{reservation}/panels/{panel}/price',
            [ReservationController::class, 'updatePanelPrice'])->name('reservations.panels.price');
        Route::post('reservations/{reservation}/panels/{panel}/price/reset',
            [ReservationController::class, 'resetPanelPrice'])->name('reservations.panels.price.reset');

        Route::patch('reservations/{reservation}/external-panels/{panel}/price',
            [ReservationController::class, 'updateExternalPanelPrice'])->name('reservations.external-panels.price');
        Route::post('reservations/{reservation}/external-panels/{panel}/price/reset',
            [ReservationController::class, 'resetExternalPanelPrice'])->name('reservations.external-panels.price.reset');

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
        Route::post('disponibilites/export-excel', [ReservationController::class, 'exportExcel'])
            ->name('reservations.disponibilites.export-excel');


        // Réservations
        Route::get('reservations/available-panels', [ReservationController::class, 'availablePanels'])
            ->name('reservations.available-panels')
            ->middleware('throttle:60,1');
        Route::post('reservations/mark-seen', [ReservationController::class, 'markSeen'])->name('reservations.mark-seen');

        // Ajout de panneaux à une réservation existante (utilisé dans la modale d'ajout de panneaux)
        Route::post('reservations/{reservation}/panels/add', [ReservationController::class, 'addPanel']) ->name('reservations.panels.add');

        // CRUD Réservations (en dernier pour ne pas capturer les routes spécifiques)
        // JSON — liste des panneaux d'une réservation (modale "Voir les panneaux" depuis l'index)
        Route::get('reservations/{reservation}/panels-list', [ReservationController::class, 'getPanels'])
            ->name('reservations.panels.list');

        // JSON — snapshot du statut courant (polling 10s côté admin pour
        // refléter en temps réel les actions client : confirmation,
        // refus, vue de la proposition…). Endpoint léger.
        Route::get('reservations/{reservation}/status-snapshot', [ReservationController::class, 'statusSnapshot'])
            ->name('reservations.status-snapshot');

        Route::resource('reservations', ReservationController::class)->except(['create', 'store']);
        Route::patch('reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])->name('reservations.update-status');
        Route::patch('reservations/{reservation}/annuler', [ReservationController::class, 'annuler'])->name('reservations.annuler');

        // Propositions (admin)
        // MP : soumet la proposition au commercial pour envoi.
        Route::post(
            'reservations/{reservation}/proposition/soumettre',
            [PropositionController::class, 'submitProposition']
        )
            ->name('reservations.proposition.soumettre');

        Route::post(
            'reservations/{reservation}/proposition/envoyer',
            [PropositionController::class, 'envoyerProposition']
        )
            ->name('reservations.proposition.envoyer');

        // Renvoyer = même action, le contrôleur regénère token+slug si nécessaire.
        // Le JS du partial proposition-actions.blade.php utilise cette URL quand
        // l'admin clique "Renvoyer la proposition".
        Route::post(
            'reservations/{reservation}/proposition/renvoyer',
            [PropositionController::class, 'envoyerProposition']
        )
            ->name('reservations.proposition.renvoyer');

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

        // Campagnes — exports (avant le resource pour éviter conflit /campaigns/{id})
        Route::get('campaigns/export/excel', [CampaignController::class, 'exportExcel'])
            ->name('campaigns.export.excel');
        Route::get('campaigns/export/pdf',   [CampaignController::class, 'exportPdf'])
            ->name('campaigns.export.pdf');

        // Campagnes
        Route::resource('campaigns', CampaignController::class);
        Route::patch('campaigns/{campaign}/status', [CampaignController::class, 'updateStatus'])->name('campaigns.update-status');
        Route::post ('campaigns/{campaign}/activate', [CampaignController::class, 'activate'])->name('campaigns.activate');
        Route::patch('campaigns/{campaign}/billing-quick', [CampaignController::class, 'billingQuick'])->name('campaigns.billing-quick');
        Route::patch('campaigns/{campaign}/prolonger', [CampaignController::class, 'prolonger'])->name('campaigns.prolonger');
        // Lot 5 — Lien pige public (token partageable)
        Route::post  ('campaigns/{campaign}/pige-token', [CampaignController::class, 'generatePigeToken'])->name('campaigns.pige-token.generate');
        Route::delete('campaigns/{campaign}/pige-token', [CampaignController::class, 'revokePigeToken'])  ->name('campaigns.pige-token.revoke');
        Route::post('campaigns/{campaign}/panels', [CampaignController::class, 'addPanel'])->name('campaigns.panels.add');
        Route::delete('campaigns/{campaign}/panels/{panel}', [CampaignController::class, 'removePanel'])->name('campaigns.panels.remove');
        // Endpoints AJAX (rafraîchissement progression + chargement panneaux disponibles)
        Route::get('campaigns/{campaign}/progress', [CampaignController::class, 'progress'])->name('campaigns.progress');
        Route::get('campaigns/{campaign}/available-panels', [CampaignController::class, 'availablePanels'])->name('campaigns.available-panels');

        // Gestion panneaux externes d'une campagne
        Route::delete('campaigns/{campaign}/external-panels/{externalPanel}', [CampaignController::class, 'removeExternalPanel'])
            ->name('campaigns.external-panels.remove');

        // ── Taxes Communes ────────────────────────────────────────
        Route::get('taxes/auto/preview',  [TaxController::class, 'previewAuto'])->name('taxes.auto.preview');
        Route::post('taxes/auto/generate', [TaxController::class, 'generateAuto'])->name('taxes.auto.generate');
        Route::get ('taxes/calcul',        [TaxController::class, 'calcul'])       ->name('taxes.calcul');
        Route::get ('taxes/details',       [TaxController::class, 'details'])      ->name('taxes.details');
        Route::get ('taxes/details/pdf',   [TaxController::class, 'detailsPdf'])   ->name('taxes.details.pdf');
        Route::get ('taxes/details/excel', [TaxController::class, 'detailsExcel']) ->name('taxes.details.excel');
        Route::post('taxes/payments',      [TaxController::class, 'recordPayment'])->name('taxes.payments.record');
        Route::get ('taxes/historique',    [TaxController::class, 'historique'])   ->name('taxes.historique');
        Route::resource('taxes', TaxController::class);
        Route::patch('taxes/{tax}/pay', [TaxController::class, 'markPaid'])->name('taxes.pay');
        Route::get('taxes/export/pdf', [TaxController::class, 'exportPdf'])->name('taxes.export.pdf');

        // ── Facturation (admin uniquement) ───────────────────────
        Route::middleware('role:admin')->group(function () {
            Route::get('invoices/export/pdf',   [InvoiceController::class, 'exportListPdf'])->name('invoices.export.pdf');
            Route::get('invoices/export/excel', [InvoiceController::class, 'exportListExcel'])->name('invoices.export.excel');
            Route::resource('invoices', InvoiceController::class);
            Route::patch('invoices/{invoice}/send',         [InvoiceController::class, 'markSent'])->name('invoices.send');
            Route::patch('invoices/{invoice}/pay',          [InvoiceController::class, 'markPaid'])->name('invoices.pay');
            Route::patch('invoices/{invoice}/cancel',       [InvoiceController::class, 'markCancelled'])->name('invoices.cancel');
            Route::patch('invoices/{invoice}/revert-draft', [InvoiceController::class, 'revertDraft'])->name('invoices.revert-draft');
            Route::get('invoices/{invoice}/pdf',            [InvoiceController::class, 'exportPdf'])->name('invoices.pdf');
        });

        Route::get('/rapports', [RapportController::class, 'index'])->name('rapports.index');
        Route::get('/rapports/ajax', [RapportController::class, 'ajax'])->name('rapports.ajax');
        Route::get('/rapports/annulations', [RapportController::class, 'annulations'])->name('rapports.annulations');
        Route::get('/rapports/communes/{commune}/detail', [RapportController::class, 'communeDetail'])
            ->name('rapports.communes.detail');
        Route::get('/rapports/taxes', [RapportController::class, 'taxes'])->name('rapports.taxes');

    });


