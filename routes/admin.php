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
    // Signalement terrain (panneau cassé / accès bloqué / mauvaise adresse…)
    Route::post('/{token}/report',           [\App\Http\Controllers\PoseTaskPublicController::class, 'reportProblem'])
        ->name('pige.public.intervention.report')
        ->middleware('throttle:10,1');
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

// ── ESPACE TECHNICIEN PERSONNEL : /tech/{token}/poses ──────────────
// URL stable par technicien (1 token permanent dans users.tech_public_token).
// Affiche UNIQUEMENT les poses assignées à ce tech, toutes campagnes
// confondues, groupées par date. Remplace l'usage du lien
// /pige/{campaign.pige_token} pour les envois WhatsApp en batch
// (qui mélangeait les panneaux d'autres techs sur la même campagne).
Route::prefix('tech')->middleware(['throttle:60,1', \App\Http\Middleware\SetFrenchLocale::class])->group(function () {
    Route::get('/{token}/poses', [\App\Http\Controllers\TechSpaceController::class, 'show'])
        ->name('tech.space');

    // Historique piges du tech — statuts (en_attente / verifie / rejete)
    // + photo + raison de rejet visible direct. Cliqué depuis le chip
    // "📸 Mes piges" du header tech-space.
    Route::get('/{token}/piges', [\App\Http\Controllers\TechSpaceController::class, 'piges'])
        ->name('tech.space.piges');

    // Heartbeat JSON tech : KPI dashboard live (polling 20s côté JS).
    Route::get('/{token}/heartbeat', [\App\Http\Controllers\TechSpaceController::class, 'heartbeat'])
        ->name('tech.space.heartbeat');

    // SM2c B3 — Notifications du tech (drawer + badge).
    Route::get('/{token}/notifications', [\App\Http\Controllers\TechSpaceController::class, 'notifications'])
        ->name('tech.space.notifications');
    Route::post('/{token}/notifications/mark-read', [\App\Http\Controllers\TechSpaceController::class, 'notificationsMarkRead'])
        ->name('tech.space.notifications.mark-read');

    // Recherche AJAX scalable : source pour Select2 (paginé, full-text sur
    // référence/nom/commune/campagne/client). Permet au tech de trouver
    // une pose précise même si elle n'est pas dans le SSR initial (cap 200).
    Route::get('/{token}/poses/search', [\App\Http\Controllers\TechSpaceController::class, 'searchPoses'])
        ->name('tech.space.search');

    // Feuille de route imprimable / PDF — toutes les poses du tech sur
    // une page, groupées par commune, optimisée pour A4. Filet offline.
    Route::get('/{token}/poses/route-sheet', [\App\Http\Controllers\TechSpaceController::class, 'routeSheet'])
        ->name('tech.space.route-sheet');

    // Vue Carte (Leaflet + cluster) — markers colorés par statut, popups
    // avec actions (Y aller, Voir la pose). Indispensable au-delà de 30
    // poses pour visualiser la densité et planifier la tournée.
    Route::get('/{token}/poses/map', [\App\Http\Controllers\TechSpaceController::class, 'map'])
        ->name('tech.space.map');

    // TSP nearest-neighbor : prend la position du tech (lat/lng) et
    // retourne l'ordre optimal de tournée (greedy) pour minimiser le
    // trajet total. JSON pur, consommé par les vues liste et map.
    Route::get('/{token}/poses/optimize', [\App\Http\Controllers\TechSpaceController::class, 'optimizeTour'])
        ->name('tech.space.optimize');

    Route::post('/{token}/poses/{task}/status', [\App\Http\Controllers\TechSpaceController::class, 'updateStatus'])
        ->whereNumber('task')
        ->name('tech.space.status');

    // 2026-07-06 (feedback patronne) : le tech peut renseigner sa progression
    // 0/25/50/75/100 % pendant qu'il pose, sans passer par un statut. L'admin
    // voit la progression temps réel sur la fiche pose + le pilotage.
    Route::post('/{token}/poses/{task}/progress', [\App\Http\Controllers\TechSpaceController::class, 'updateProgress'])
        ->whereNumber('task')
        ->middleware('throttle:20,1')
        ->name('tech.space.progress');

    Route::post('/{token}/poses/{task}/photo', [\App\Http\Controllers\TechSpaceController::class, 'uploadPhoto'])
        ->whereNumber('task')
        ->middleware('throttle:30,1')
        ->name('tech.space.photo');

    // Signalement terrain en 1 tap (panneau cassé / accès bloqué…)
    Route::post('/{token}/poses/{task}/report', [\App\Http\Controllers\TechSpaceController::class, 'report'])
        ->whereNumber('task')
        ->middleware('throttle:10,1')
        ->name('tech.space.report');
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

    // Demande client de décalage de dates (négociation aller-retour).
    // La proposition reste EN_ATTENTE, l'admin reçoit une alerte + email
    // et peut accepter (les nouvelles dates remplacent les actuelles) ou
    // refuser (la demande est effacée et le client est notifié).
    Route::post('/{reference}/{slug}/demander-changement-dates', [PropositionController::class, 'demanderChangementDates'])
        ->name('demander-changement-dates')
        ->middleware('throttle:5,1');

    // Annulation de la demande client (retour aux dates initiales).
    // Indispensable pour ne pas piéger le client qui change d'avis :
    // sans ça, il restait bloqué tant que CIBLE n'avait pas répondu.
    Route::post('/{reference}/{slug}/annuler-demande-changement-dates', [PropositionController::class, 'annulerDemandeChangementDates'])
        ->name('annuler-demande-changement-dates')
        ->middleware('throttle:5,1');

    Route::delete('/{reference}/{slug}/panneau/{panelId}', [PropositionController::class, 'retirerPanneau'])
        ->name('retirer-panneau')
        ->middleware('throttle:10,1');

    // Retrait groupé (multi-select) — un seul appel pour N panneaux.
    // Évite de lancer N appels DELETE en boucle côté navigateur et
    // garantit l'idempotence du recalcul de total côté serveur.
    Route::post('/{reference}/{slug}/panneaux/bulk-delete', [PropositionController::class, 'bulkRetirerPanneaux'])
        ->name('bulk-retirer-panneaux')
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

// ══════════════════════════════════════════════════════════════════════
// ROUTES ADMIN — Matrice d'accès par rôle (cf. docs/ROLES_VALIDES.md)
// ──────────────────────────────────────────────────────────────────────
// • Le rôle `technique` est exclu du web admin : le technicien n'a
//   QUE l'accès mobile via /pige/{token} (lien WhatsApp public).
// • 3 rôles staff connectables : admin / commercial / mediaplanner.
// • Restrictions par module via middleware imbriqué role:... ci-dessous.
// ══════════════════════════════════════════════════════════════════════
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin,commercial,mediaplanner,comptable'])
    ->group(function () {

        // ════════════════════════════════════════════════
        // DEV A
        // ════════════════════════════════════════════════

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // SM2b Lot 1.2 + 1.4 — Dashboard admin live (polling 20s côté front).
        // JSON-only. Réservé admin + mediaplanner (le commercial n'a pas
        // vocation à piloter les techs terrain en live).
        Route::middleware('role:admin,mediaplanner')->group(function () {
            Route::get('/dashboard/live', [\App\Http\Controllers\Admin\AdminLiveDashboardController::class, 'live'])
                ->name('dashboard.live');
            // SM2b Phase 2 — Page Blade A1 "Pilotage terrain".
            Route::get('/pilotage', function () {
                return view('admin.dashboard.live');
            })->name('pilotage');
            // SM2b Phase 3 — Fiche tech A2 (Blade live).
            Route::get('/pilotage/tech/{user}', function (\App\Models\User $user) {
                abort_if($user->role?->value !== 'technique', 404);
                return view('admin.dashboard.tech-live', ['tech' => $user]);
            })->name('pilotage.tech');
            // SM2b Phase 4 — Carte live A3.
            Route::get('/pilotage/map', function () {
                return view('admin.dashboard.map-live');
            })->name('pilotage.map');
            // SM2b Phase 6 — Vue équipe A5.
            Route::get('/pilotage/team/{poseTeam}', function (\App\Models\PoseTeam $poseTeam) {
                $poseTeam->load('members');
                return view('admin.dashboard.team-live', ['team' => $poseTeam]);
            })->name('pilotage.team');
            Route::get('/tech/{user}/timeline', [\App\Http\Controllers\Admin\AdminLiveDashboardController::class, 'techTimeline'])
                ->name('tech.timeline');
            Route::get('/map/live', [\App\Http\Controllers\Admin\AdminLiveDashboardController::class, 'mapLive'])
                ->name('map.live');
        });

        // ── Panneaux ────────────────────────────────────────────────
        // Lecture + exports = tous les staff (admin/commercial/MP).
        // Création / modification / suppression / photos = admin + MP.
        // Changement de statut (libre / maintenance) = admin + MP.
        Route::get('panels', [PanelController::class, 'index'])->name('panels.index');
        // Aperçu de référence — utilisé par les vues create/edit pour
        // générer un aperçu live de la référence sans recharger la page.
        Route::get('panels/generate-reference', [PanelController::class, 'generateReference'])
            ->name('panels.generate-reference');
        Route::get('panels/{panel}', [PanelController::class, 'show'])
            ->whereNumber('panel')->name('panels.show');
        Route::get('panels/{panel}/availability', [PanelController::class, 'availability'])
            ->whereNumber('panel')->name('panels.availability');
        Route::get('panels/{panel}/pdf', [PanelController::class, 'exportPdf'])
            ->whereNumber('panel')->name('panels.pdf');
        Route::get('panels/export/list', [PanelController::class, 'exportList'])
            ->name('panels.export.list');
        Route::get('panels/export/network', [PanelController::class, 'exportNetwork'])
            ->name('panels.export.network');
        Route::get('panels/export/excel', [PanelController::class, 'exportExcel'])
            ->name('panels.export.excel');
        Route::get('panels/export/network-excel', [PanelController::class, 'exportNetworkExcel'])
            ->name('panels.export.network-excel');

        // Changement statut panneau = admin + MP
        Route::post('panels/{panel}/status', [PanelController::class, 'updateStatus'])
            ->middleware('role:admin,mediaplanner')
            ->whereNumber('panel')->name('panels.status');

        // Création / modif / suppression / photos = admin + MP
        Route::middleware('role:admin,mediaplanner')->group(function () {
            // Saisie rapide des coordonnées GPS manquantes — page de bulk
            // edit avec grille (lat + lng par panneau). Permet de renseigner
            // les 360+ panneaux qui n'ont pas encore leurs coordonnées.
            Route::get('panels/gps/missing',  [PanelController::class, 'gpsMissing'])->name('panels.gps.missing');
            Route::post('panels/gps/missing', [PanelController::class, 'gpsBulkUpdate'])->name('panels.gps.update');

            Route::get('panels/create', [PanelController::class, 'create'])->name('panels.create');
            Route::post('panels', [PanelController::class, 'store'])->name('panels.store');
            Route::get('panels/{panel}/edit', [PanelController::class, 'edit'])
                ->whereNumber('panel')->name('panels.edit');
            Route::put('panels/{panel}', [PanelController::class, 'update'])
                ->whereNumber('panel')->name('panels.update');
            Route::patch('panels/{panel}', [PanelController::class, 'update'])
                ->whereNumber('panel')->name('panels.update.patch');
            Route::delete('panels/{panel}', [PanelController::class, 'destroy'])
                ->whereNumber('panel')->name('panels.destroy');
            Route::post('panels/{panel}/photos', [PanelController::class, 'uploadPhoto'])
                ->whereNumber('panel')->name('panels.photos');
            Route::delete('panels/{panel}/photos/{photo}', [PanelController::class, 'deletePhoto'])
                ->whereNumber('panel')->name('panels.photos.delete');
        });

        // ── Carte / Heatmap ───────────────────────────────────────
        Route::get('map', [PanelController::class, 'map'])
            ->name('map');
        Route::get('map/data', [PanelController::class, 'mapData'])
            ->name('map.data');

        // ── Import GPS depuis Excel/CSV (admin + MP) ──────────────
        // Formulaire d'upload + traitement. Admin et MP peuvent importer
        // car ils gèrent tous deux le parc (cohérent avec édition panneau).
        Route::middleware('role:admin,mediaplanner')->group(function () {
            Route::get('panels/import-gps', [PanelController::class, 'importGpsForm'])
                ->name('panels.import-gps.form');
            Route::post('panels/import-gps', [PanelController::class, 'importGps'])
                ->name('panels.import-gps');
        });

        // ── Pose OOH ─────────────────────────────── (admin + MP only) ──
        // Le commercial n'a aucun accès au suivi terrain (matrice POSES).
        // ⚠️ Routes AJAX spécifiques AVANT resource pour éviter conflits
        Route::prefix('pose-tasks')->name('pose-tasks.')
            ->middleware('role:admin,mediaplanner')
            ->group(function () {
            // ── AJAX endpoints (avant les routes paramétriques) ──────
            Route::get('search-campaigns', [PoseController::class, 'searchCampaigns'])->name('search-campaigns');
            Route::get('campaign-panels',  [PoseController::class, 'campaignPanels']) ->name('campaign-panels');
            Route::get('search-panels',    [PoseController::class, 'searchPanels'])   ->name('search-panels');
            // Polling progression temps réel (vue admin index)
            Route::get('progress',         [PoseController::class, 'progress'])       ->name('progress');
            // Actions groupées (sélection multiple)
            Route::post('bulk-update',     [PoseController::class, 'bulkUpdate'])     ->name('bulk-update');
            // (2026-06-26) Écran "Poses oubliées" — liste les poses non finalisées
            // dont la date prévue est dépassée, avec bulk "Marquer réalisées".
            // Utile quand le MP a oublié de saisir des poses faites sur le terrain.
            Route::get('oubliees',                [PoseController::class, 'oubliees'])             ->name('oubliees');
            Route::post('bulk-complete-oubliees', [PoseController::class, 'bulkCompleteOubliees']) ->name('bulk-complete-oubliees');
            // Carte GPS des poses (markers colorés par statut)
            Route::get('map',              [PoseController::class, 'map'])            ->name('map');
            Route::get('map-data',         [PoseController::class, 'mapData'])        ->name('map.data');
            // Route 'sla' (admin.pose-tasks.sla) retirée le 2026-06-17 —
            // contenu redondant avec Performance commerciale + Décapages + SLA & Retards.
            // Calendrier hebdomadaire par technicien (planning visuel)
            Route::get('calendar',         [PoseController::class, 'calendar'])       ->name('calendar');
            // Suggestion intelligente d'un tech (zone + charge + perf)
            Route::get('suggest-tech',     [PoseController::class, 'suggestTech'])    ->name('suggest-tech');

            // ── Sous-module TECHNICIENS (déplacé hors de /admin/users) ─
            // Admin + MP gèrent les techniciens directement ici car c'est
            // leur domaine métier. Aucun mot de passe (les techs accèdent
            // via /tech/{token}/poses, pas via /login).
            Route::prefix('techniciens')->name('techniciens.')->group(function () {
                Route::get('/',                   [\App\Http\Controllers\Admin\TechnicienController::class, 'index'])  ->name('index');
                Route::get('/create',             [\App\Http\Controllers\Admin\TechnicienController::class, 'create']) ->name('create');
                Route::post('/',                  [\App\Http\Controllers\Admin\TechnicienController::class, 'store'])  ->name('store');
                Route::get('/{technicien}/edit',  [\App\Http\Controllers\Admin\TechnicienController::class, 'edit'])   ->name('edit');
                Route::put('/{technicien}',       [\App\Http\Controllers\Admin\TechnicienController::class, 'update']) ->name('update');
                Route::delete('/{technicien}',    [\App\Http\Controllers\Admin\TechnicienController::class, 'destroy'])->name('destroy');
                Route::post('/{technicien}/regenerate-token', [\App\Http\Controllers\Admin\TechnicienController::class, 'regenerateToken'])->name('regenerate-token');
                Route::post('/{technicien}/toggle-active',    [\App\Http\Controllers\Admin\TechnicienController::class, 'toggleActive'])   ->name('toggle-active');
            });

            // ── CRUD standard ─────────────────────────────────────────
            Route::get('/',         [PoseController::class, 'index'])  ->name('index');
            Route::get('/create',   [PoseController::class, 'create']) ->name('create');
            Route::post('/',        [PoseController::class, 'store'])  ->name('store');
            Route::get('/{poseTask}',      [PoseController::class, 'show'])->name('show');
            Route::get('/{poseTask}/edit', [PoseController::class, 'edit'])->name('edit');
            Route::put('/{poseTask}',      [PoseController::class, 'update'])->name('update');
            Route::delete('/{poseTask}',   [PoseController::class, 'destroy'])->name('destroy');
        });
        
        // ── Pose : alias + notify (admin + MP only) ───────────────────
        Route::middleware('role:admin,mediaplanner')->group(function () {
            // Alias pour markComplete (rétrocompatibilité)
            Route::post('pose-tasks/{poseTask}/complete', [PoseController::class, 'markComplete'])
                ->name('pose.complete');

            // Renvoyer / envoyer manuellement la notification WhatsApp
            Route::post('pose-tasks/{poseTask}/notify', [PoseController::class, 'notifyWhatsApp'])
                ->name('pose-tasks.notify');
        });

        // ── Maintenance ──────────────────────── (admin + MP only) ──
        // Endpoints AJAX recherche panneaux + création rapide technicien
        // (à placer AVANT Route::resource sinon /search-panels matche {maintenance})
        Route::middleware('role:admin,mediaplanner')->group(function () {
            Route::get ('maintenances/search-panels',  [MaintenanceController::class, 'searchPanels'])->name('maintenances.search-panels');
            Route::post('maintenances/quick-tech',     [MaintenanceController::class, 'quickCreateTechnician'])->name('maintenances.quick-tech');
            Route::resource('maintenances', MaintenanceController::class);
            Route::post('maintenances/{maintenance}/resolve', [MaintenanceController::class, 'resolve'])->name('maintenances.resolve');
            Route::post('maintenances/{maintenance}/reopen',  [MaintenanceController::class, 'reopen'])->name('maintenances.reopen');
        });

        // ── Messages clients (formulaire « Contacter la régie ») ──
        Route::prefix('messages')->name('messages.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Admin\ClientMessageController::class, 'index'])  ->name('index');
            Route::get('{message}',          [\App\Http\Controllers\Admin\ClientMessageController::class, 'show'])   ->name('show');
            Route::post('{message}/reply',   [\App\Http\Controllers\Admin\ClientMessageController::class, 'reply'])  ->name('reply');
            Route::post('{message}/archive', [\App\Http\Controllers\Admin\ClientMessageController::class, 'archive'])->name('archive');
            Route::delete('{message}',       [\App\Http\Controllers\Admin\ClientMessageController::class, 'destroy'])->name('destroy');
        });

        // ── Alertes ───────────────────────────────────────────────
        Route::prefix('alerts')->name('alerts.')->group(function () {
            Route::get('/',                       [AlertController::class, 'index'])     ->name('index');
            Route::post('read-all',               [AlertController::class, 'markAllRead'])->name('read-all');
            Route::post('clear-read',             [AlertController::class, 'clearRead'])  ->name('clear-read');
            // Actions groupées : 'mark-read', 'delete', 'archive'
            Route::post('bulk',                   [AlertController::class, 'bulkAction']) ->name('bulk');
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
            // Pages dédiées index/edit retirées — toute la gestion passe par
            // /admin/settings (vue d'ensemble + modals quick-edit).
            // On garde uniquement create/store/update/destroy.
            Route::resource('zones',      ZoneController::class)->only(['create', 'store', 'update', 'destroy']);
            Route::resource('communes',   CommuneController::class)->only(['create', 'store', 'update', 'destroy']);

            // Tarifs communaux (ODP/TM) — gestion dédiée admin :
            //   - vue d'ensemble + historique + tarif programmé futur.
            //   - L'observer CommuneObserver historise auto les changements
            //     du tarif courant (édition commune). Cette page complète
            //     en exposant l'historique et la programmation future.
            Route::get('communes/tariffs',
                [CommuneController::class, 'tariffs'])->name('communes.tariffs');
            Route::get('communes/{commune}/tariffs/history',
                [CommuneController::class, 'tariffHistory'])->name('communes.tariffs.history');
            Route::post('communes/{commune}/tariffs/schedule',
                [CommuneController::class, 'scheduleTariff'])->name('communes.tariffs.schedule');
            Route::delete('communes/{commune}/tariffs/{entry}',
                [CommuneController::class, 'deleteTariffEntry'])->name('communes.tariffs.delete');
            Route::resource('formats',    PanelFormatController::class)->only(['create', 'store', 'update', 'destroy']);
            Route::resource('categories', PanelCategoryController::class)->only(['create', 'store', 'update', 'destroy']);
        });

        // Utilisateurs (admin uniquement)
        Route::middleware('role:admin')->group(function () {
            Route::resource('users', UserController::class);
            Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle');
            // Actions groupées : 'activate', 'deactivate'.
            // GARDE-FOU : ne peut JAMAIS désactiver le dernier admin actif
            // ni se désactiver soi-même.
            Route::post('users/bulk', [UserController::class, 'bulkAction'])->name('users.bulk');
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
        // ROUTES PIGES — CRUD + actions spécifiques (admin + MP only)
        // Le commercial peut télécharger les ZIP de ses campagnes mais
        // pas accéder à la validation / au CRUD des piges.
        // ══════════════════════════════════════════════════════════════

        Route::prefix('piges')->name('piges.')
            ->middleware('role:admin,mediaplanner')
            ->group(function () {

            // ── AJAX (avant les routes paramétriques) ──────────────────
            Route::get('campaign-panels', [PigeController::class, 'campaignPanels'])->name('campaign-panels');
            Route::post('verify-batch',   [PigeController::class, 'verifyBatch'])   ->name('verify-batch');

            // Validation rapide : page dédiée avec navigation clavier V/R/←/→
            Route::get('validation', [PigeController::class, 'validation'])->name('validation');

            // Archives : piges des campagnes supprimées (auto-archivées
            // par CampaignObserver::deleted ou via panora:cleanup).
            // Conservation non destructive pour valeur légale + facturation.
            Route::get('archives', [PigeController::class, 'archives'])->name('archives');
        
            // ── Actions sur une pige ───────────────────────────────────
            Route::post('{pige}/verify', [PigeController::class, 'verify']) ->name('verify');
            Route::post('{pige}/reject', [PigeController::class, 'reject']) ->name('reject');
            // SM2b Phase 5 — Détail JSON pour modale validation A4.
            Route::get('{pige}/detail-json', [PigeController::class, 'detailJson'])->name('detail-json');
        
            // ── CRUD standard ──────────────────────────────────────────
            Route::get('/',           [PigeController::class, 'index'])  ->name('index');
            Route::get('/create',     [PigeController::class, 'create']) ->name('create');
            Route::post('/',          [PigeController::class, 'store'])  ->name('store');
            Route::get('/{pige}',     [PigeController::class, 'show'])   ->name('show');
            Route::get('/{pige}/edit',  [PigeController::class, 'edit'])   ->name('edit');
            Route::put('/{pige}',       [PigeController::class, 'update']) ->name('update');
            Route::delete('/{pige}',  [PigeController::class, 'destroy'])->name('destroy');
        });

        // ── Taxes Communes ───────────── (admin + MP + comptable) ──
        // Voir / exporter les rapports / enregistrer les paiements taxes
        // = admin + MP + comptable. Modifier les tarifs communaux reste
        // réservé à l'admin (matrice TAXES). Le comptable saisit les
        // règlements TM/ODP comme les autres encaissements.
        Route::middleware('role:admin,mediaplanner,comptable')->group(function () {
            Route::get('taxes/auto/preview',  [TaxController::class, 'previewAuto'])->name('taxes.auto.preview');
            Route::post('taxes/auto/generate', [TaxController::class, 'generateAuto'])->name('taxes.auto.generate');
            Route::get ('taxes/calcul',        [TaxController::class, 'calcul'])       ->name('taxes.calcul');
            Route::get ('taxes/details',       [TaxController::class, 'details'])      ->name('taxes.details');
            Route::get ('taxes/details/pdf',   [TaxController::class, 'detailsPdf'])   ->name('taxes.details.pdf');
            Route::get ('taxes/details/excel', [TaxController::class, 'detailsExcel']) ->name('taxes.details.excel');
            Route::post('taxes/payments',      [TaxController::class, 'recordPayment'])->name('taxes.payments.record');
            Route::get ('taxes/historique',    [TaxController::class, 'historique'])   ->name('taxes.historique');
            // LOT 3 (cahier 2026-06-19) — Historique paiements détaillé d'une
            // commune (1 ligne / versement avec cumul + solde après).
            // Placée AVANT Route::resource pour éviter la collision sur
            // {tax} qui matcherait le mot "commune".
            Route::get ('taxes/commune/{commune}/payments-history',
                [TaxController::class, 'communePaymentsHistory'])
                ->name('taxes.commune.payments-history');
            // LOT 2 (cahier 2026-06-19) — Fiche commune détaillée (suivi mensuel
            // + cumul trimestriel + cumul annuel).
            Route::get ('taxes/commune/{commune}',
                [TaxController::class, 'showCommune'])
                ->name('taxes.commune.show');
            Route::resource('taxes', TaxController::class);
            Route::patch('taxes/{tax}/pay', [TaxController::class, 'markPaid'])->name('taxes.pay');
            Route::get('taxes/export/pdf', [TaxController::class, 'exportPdf'])->name('taxes.export.pdf');
        });

        // ── Facturation ───────────────────────────────────────────────
        // Lecture (index/show/pdf/exports liste) : tous staff (le commercial
        // voit ses siennes via filtrage InvoiceController). Création /
        // modification / suppression / changement statut = admin uniquement.
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/export/pdf',   [InvoiceController::class, 'exportListPdf'])->name('invoices.export.pdf');
        Route::get('invoices/export/excel', [InvoiceController::class, 'exportListExcel'])->name('invoices.export.excel');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])
            ->whereNumber('invoice')->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'exportPdf'])
            ->whereNumber('invoice')->name('invoices.pdf');
        // Phase 7 cahier §13 — Consultation des audits (timeline +
        // diff JSON avant/après) sur une facture.
        Route::get('invoices/{invoice}/audit', [InvoiceController::class, 'auditTimeline'])
            ->whereNumber('invoice')->name('invoices.audit');

        // ⚠ Bloc admin+comptable : actions facturation accessibles au comptable
        // (saisie versements, transitions de statut, échéancier, lookups).
        // Les actions hard (create/update/destroy/cancel/lock/unlock) sont
        // gardées par InvoicePolicy qui retourne false sauf pour admin.
        Route::middleware('role:admin,comptable')->group(function () {
            // ── Lookups JSON pour le formulaire facture ─────────────
            // Servent au formulaire create/edit pour auto-remplir le client
            // quand on choisit une campagne (et inversement filtrer les
            // campagnes par client) + récupérer le montant HT déjà connu.
            Route::get('invoices/lookup/client/{client}/campaigns', [InvoiceController::class, 'lookupClientCampaigns'])
                ->whereNumber('client')->name('invoices.lookup.client-campaigns');
            Route::get('invoices/lookup/campaign/{campaign}', [InvoiceController::class, 'lookupCampaignInfo'])
                ->whereNumber('campaign')->name('invoices.lookup.campaign-info');

            // Recherche AJAX panneaux (Select2) — pré-remplit la ligne
            // avec commune + m² + PU dès qu'un panneau est sélectionné.
            Route::get('invoices/lookup/panels', [InvoiceController::class, 'lookupPanels'])
                ->name('invoices.lookup.panels');

            Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
            Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');

            // Génération auto FNE depuis une campagne (utilise
            // InvoiceFromCampaignBuilder : récupère panneaux internes
            // + externes, résout ODP/TM historisés, calcule agrégats).
            Route::post('invoices/from-campaign/{campaign}', [InvoiceController::class, 'fromCampaign'])
                ->whereNumber('campaign')->name('invoices.from-campaign');
            Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])
                ->whereNumber('invoice')->name('invoices.edit');
            Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])
                ->whereNumber('invoice')->name('invoices.update');
            Route::patch('invoices/{invoice}', [InvoiceController::class, 'update'])
                ->whereNumber('invoice')->name('invoices.update.patch');
            Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])
                ->whereNumber('invoice')->name('invoices.destroy');
            Route::patch('invoices/{invoice}/send',         [InvoiceController::class, 'markSent'])->name('invoices.send');
            Route::patch('invoices/{invoice}/pay',          [InvoiceController::class, 'markPaid'])->name('invoices.pay');
            // Solde manuel sans saisie des versements (migration / facture
            // historique réglée hors plateforme). Raison obligatoire,
            // traçabilité assurée par audit InvoicePayment + statusHistory.
            Route::post ('invoices/{invoice}/mark-paid-manual', [InvoiceController::class, 'markPaidManual'])->name('invoices.mark-paid-manual');
            Route::patch('invoices/{invoice}/cancel',       [InvoiceController::class, 'markCancelled'])->name('invoices.cancel');
            Route::patch('invoices/{invoice}/revert-draft', [InvoiceController::class, 'revertDraft'])->name('invoices.revert-draft');
            // Phase 8A cahier §3 — actions manuelles complètes du cycle
            // de vie (brouillon → générée → validée → envoyée, + litige).
            Route::patch('invoices/{invoice}/mark-generated', [InvoiceController::class, 'markGenerated'])->name('invoices.generated');
            Route::patch('invoices/{invoice}/mark-validated', [InvoiceController::class, 'markValidated'])->name('invoices.validated');
            Route::patch('invoices/{invoice}/mark-litige',    [InvoiceController::class, 'markLitige'])->name('invoices.litige');

            // Verrouillage facture — auto à l'envoi, déverrouillage explicite
            // par admin si correction nécessaire (action tracée en logs).
            Route::patch('invoices/{invoice}/lock',   [InvoiceController::class, 'lock'])->name('invoices.lock');
            Route::patch('invoices/{invoice}/unlock', [InvoiceController::class, 'unlock'])->name('invoices.unlock');

            // Versements (acompte, mensualités, solde) — N paiements / facture.
            // Le statut de la facture est dérivé de la somme des versements.
            Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'addPayment'])
                ->name('invoices.payments.add');
            Route::delete('invoices/{invoice}/payments/{payment}', [InvoiceController::class, 'removePayment'])
                ->name('invoices.payments.remove');
            // Phase 2 : téléchargement pièce justificative versement
            // (cahier §4 : "moyen de paiement, pièce jointe, note")
            Route::get('invoices/{invoice}/payments/{payment}/attachment',
                [InvoiceController::class, 'downloadPaymentAttachment'])
                ->name('invoices.payments.attachment');

            // Échéancier prévisionnel (acompte / solde / mensualités).
            // Le rapprochement avec les versements réels est manuel via
            // markScheduleEntryPaid pour rester simple. Le statut paiement
            // dérivé reste basé sur invoice_payments (source unique).
            Route::post('invoices/{invoice}/schedule', [InvoiceController::class, 'generateSchedule'])
                ->name('invoices.schedule.generate');
            Route::delete('invoices/{invoice}/schedule', [InvoiceController::class, 'deleteSchedule'])
                ->name('invoices.schedule.delete');
            Route::patch('invoices/{invoice}/schedule/{schedule}/paid', [InvoiceController::class, 'markScheduleEntryPaid'])
                ->name('invoices.schedule.pay');
            Route::patch('invoices/{invoice}/schedule/{schedule}/unpaid', [InvoiceController::class, 'unmarkScheduleEntryPaid'])
                ->name('invoices.schedule.unpay');
        });

        // ════════════════════════════════════════════════
        // DEV B
        // ════════════════════════════════════════════════
        // ── Clients ─────────────────────────────────────────────────
        // Lecture (index + show + data) = tous staff (admin/commercial/MP).
        // Création / modification / import / interlocuteurs = admin + commercial + MP.
        //   → Le MP a besoin de créer des clients depuis l'onglet Pose
        //     (campagne sans client préalable, pige terrain, etc.).
        // Suppression unitaire et groupée = admin uniquement (RGPD / sécurité).
        // Création de compte client (espace /client) = admin + commercial
        //   uniquement (action sensible : génération MDP + envoi email).
        Route::post('clients/quick-store', [ClientController::class, 'storeQuick'])
            ->middleware('role:admin,commercial,mediaplanner,comptable')
            ->name('clients.quick-store');
        // Suppression groupée (sélection multiple) — admin uniquement
        Route::post('clients/bulk-destroy', [ClientController::class, 'bulkDestroy'])
            ->middleware('role:admin')
            ->name('clients.bulk-destroy');
        // Import Excel — admin + commercial + MP
        Route::get('clients/import/template', [ClientController::class, 'importTemplate'])
            ->name('clients.import.template');
        Route::post('clients/import',         [ClientController::class, 'import'])
            ->middleware('role:admin,commercial,mediaplanner')
            ->name('clients.import');
        // Outil d'urgence (2026-06-26) — vider tous les caches en prod.
        // Utile quand un déploiement passe (commit présent sur le serveur)
        // mais que les fichiers PHP modifiés restent en cache OPcache.
        // Appel direct : GET /admin/system/clear-cache (admin uniquement).
        Route::get('system/clear-cache', function () {
            $msgs = [];
            \Artisan::call('view:clear');   $msgs[] = 'Vues compilées';
            \Artisan::call('cache:clear');  $msgs[] = 'Cache app';
            \Artisan::call('config:clear'); $msgs[] = 'Config';
            \Artisan::call('route:clear');  $msgs[] = 'Routes';
            if (function_exists('opcache_reset') && opcache_reset()) {
                $msgs[] = 'OPcache ✓';
            } else {
                $msgs[] = 'OPcache indisponible';
            }
            return redirect()->route('admin.dashboard')
                ->with('success', 'Caches vidés : ' . implode(' · ', $msgs));
        })->middleware('role:admin')->name('system.clear-cache');

        // Outil one-shot — corriger les clients "PERSONNE / ENTREPRISE"
        Route::get('clients/fix-import-names',  [ClientController::class, 'fixImportNamesPreview'])
            ->middleware('role:admin')
            ->name('clients.fix-import-names.preview');
        Route::post('clients/fix-import-names', [ClientController::class, 'fixImportNamesApply'])
            ->middleware('role:admin')
            ->name('clients.fix-import-names.apply');
        // Outil one-shot — effacer les NCC auto-générés (CLT-YYYY-NNNN)
        Route::get('clients/clear-auto-ncc',  [ClientController::class, 'clearAutoNccPreview'])
            ->middleware('role:admin')
            ->name('clients.clear-auto-ncc.preview');
        Route::post('clients/clear-auto-ncc', [ClientController::class, 'clearAutoNccApply'])
            ->middleware('role:admin')
            ->name('clients.clear-auto-ncc.apply');
        // Exports liste clients (5.2) — placés AVANT /clients/{client} pour
        // ne pas être avalés par le route model binding.
        Route::get('clients/export/csv', [ClientController::class, 'exportCsv'])
            ->name('clients.export.csv');
        Route::get('clients/export/pdf', [ClientController::class, 'exportPdf'])
            ->name('clients.export.pdf');
        Route::get('clients/export/excel', [ClientController::class, 'exportExcel'])
            ->name('clients.export.excel');
        // Lecture clients : tous les staff
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])
            ->middleware('role:admin,commercial,mediaplanner,comptable')->name('clients.create');
        Route::get('clients/{client}/edit', [ClientController::class, 'edit'])
            ->middleware('role:admin,commercial,mediaplanner,comptable')->name('clients.edit');
        Route::post('clients', [ClientController::class, 'store'])
            ->middleware('role:admin,commercial,mediaplanner,comptable')->name('clients.store');
        Route::put('clients/{client}', [ClientController::class, 'update'])
            ->middleware('role:admin,commercial,mediaplanner,comptable')->name('clients.update');
        Route::delete('clients/{client}', [ClientController::class, 'destroy'])
            ->middleware('role:admin')->name('clients.destroy');
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');

        Route::get('clients/{client}/data', [ClientController::class, 'getClientData'])
            ->name('admin.clients.data')
            ->middleware('throttle:60,1');

        // Comptes client (espace /client) : action sensible
        // (génération MDP + envoi email) → admin + commercial uniquement,
        // pas le MP (qui peut créer le client mais pas son compte d'accès).
        Route::middleware('role:admin,commercial')->group(function () {
            Route::post('clients/{client}/account', [ClientController::class, 'createAccount'])->name('clients.account.create');
            Route::post('clients/{client}/account/reset', [ClientController::class, 'resetPassword'])->name('clients.account.reset');
            Route::delete('clients/{client}/account', [ClientController::class, 'revokeAccount'])->name('clients.account.revoke');
        });

        // Multi-interlocuteurs (T4) — édition = admin + commercial + MP
        // (cohérent avec édition client : qui peut éditer la fiche peut
        // éditer les interlocuteurs).
        Route::middleware('role:admin,commercial,mediaplanner')->group(function () {
            Route::post  ('clients/{client}/contacts',                [\App\Http\Controllers\Admin\ClientContactController::class, 'store'])     ->name('clients.contacts.store');
            Route::put   ('clients/{client}/contacts/{contact}',      [\App\Http\Controllers\Admin\ClientContactController::class, 'update'])    ->name('clients.contacts.update');
            Route::delete('clients/{client}/contacts/{contact}',      [\App\Http\Controllers\Admin\ClientContactController::class, 'destroy'])   ->name('clients.contacts.destroy');
            Route::patch ('clients/{client}/contacts/{contact}/primary', [\App\Http\Controllers\Admin\ClientContactController::class, 'setPrimary'])->name('clients.contacts.primary');
        });

        // ── Régies externes ─────────────────────────────────────────
        // Lecture (index + show) = tous staff. Création / modification /
        // suppression d'une régie ou de ses panneaux = admin + media planner
        // (le MP a besoin de gérer les régies partenaires pour planifier les
        // campagnes — création/édition de panneaux externes faisant partie
        // de son périmètre opérationnel).
        Route::get('external-agencies', [ExternalAgencyController::class, 'index'])->name('external-agencies.index');
        Route::get('external-agencies/{external_agency}', [ExternalAgencyController::class, 'show'])->name('external-agencies.show');

        // Exports : lecture étendue (admin + MP qui planifie)
        Route::middleware('role:admin,mediaplanner')->group(function () {
            Route::post('external-agencies/{externalAgency}/exports/pdf-images', [ExternalAgencyController::class, 'pdfImages'])
                ->name('external-agencies.exports.pdf-images');
            Route::post('external-agencies/{externalAgency}/exports/pdf-liste', [ExternalAgencyController::class, 'pdfListe'])
                ->name('external-agencies.exports.pdf-liste');
            Route::post('external-agencies/{externalAgency}/exports/excel', [ExternalAgencyController::class, 'exportExcel'])
                ->name('external-agencies.exports.excel');
        });

        // Création / modification / suppression = admin + media planner
        Route::middleware('role:admin,mediaplanner')->group(function () {
            Route::post('external-agencies', [ExternalAgencyController::class, 'store'])->name('external-agencies.store');
            Route::put('external-agencies/{external_agency}', [ExternalAgencyController::class, 'update'])->name('external-agencies.update');
            Route::patch('external-agencies/{external_agency}', [ExternalAgencyController::class, 'update'])->name('external-agencies.update.patch');
            Route::delete('external-agencies/{external_agency}', [ExternalAgencyController::class, 'destroy'])->name('external-agencies.destroy');

            Route::post('external-agencies/{externalAgency}/panels', [ExternalAgencyController::class, 'storePanel'])
                ->name('external-agencies.panels.store');
            Route::put('external-agencies/{externalAgency}/panels/{panel}', [ExternalAgencyController::class, 'updatePanel'])
                ->name('external-agencies.panels.update');
            Route::delete('external-agencies/{externalAgency}/panels/{panel}', [ExternalAgencyController::class, 'destroyPanel'])
                ->name('external-agencies.panels.destroy');
        });

        // ══════════════════════════════════════════════════════════
        // ⚠️ RÈGLE IMPORTANTE : routes GET spécifiques AVANT resource
        // ══════════════════════════════════════════════════════════

        // Prix panneaux dans une réservation (internes + externes) :
        // admin + MP + commercial. Le commercial est limité à SES résas
        // via la policy update() (vérifie commercial_user_id).
        Route::middleware('role:admin,mediaplanner,commercial')->group(function () {
            Route::patch('reservations/{reservation}/panels/{panel}/price',
                [ReservationController::class, 'updatePanelPrice'])->name('reservations.panels.price');
            Route::post('reservations/{reservation}/panels/{panel}/price/reset',
                [ReservationController::class, 'resetPanelPrice'])->name('reservations.panels.price.reset');

            Route::patch('reservations/{reservation}/external-panels/{panel}/price',
                [ReservationController::class, 'updateExternalPanelPrice'])->name('reservations.external-panels.price');
            Route::post('reservations/{reservation}/external-panels/{panel}/price/reset',
                [ReservationController::class, 'resetExternalPanelPrice'])->name('reservations.external-panels.price.reset');
        });

        // ── Page admin "Conflits détectés" (double-bookings BD) ──
        // Liste les panneaux engagés sur 2+ entités chevauchantes.
        // Visible admin + MP, mais SEUL l'admin peut trancher (resolve).
        Route::get('conflicts', [\App\Http\Controllers\Admin\ConflictsController::class, 'index'])
            ->middleware('role:admin,mediaplanner')
            ->name('conflicts.index');
        Route::post('conflicts/resolve', [\App\Http\Controllers\Admin\ConflictsController::class, 'resolve'])
            ->middleware('role:admin')
            ->name('conflicts.resolve');

        // ── Page admin "Signalements terrain" (problèmes signalés par tech) ──
        // Liste, mise en maintenance (statut panneau MAINTENANCE) ou dismiss.
        Route::get('signalements', [\App\Http\Controllers\Admin\SignalementsController::class, 'index'])
            ->middleware('role:admin,mediaplanner')
            ->name('signalements.index');
        // Heartbeat JSON — appelé en polling par toutes les pages admin
        // (badge sidebar live + toast/son sur nouveau signal). Léger (count
        // + 3 derniers), pas de throttle strict mais staff seulement.
        Route::get('signalements/heartbeat', [\App\Http\Controllers\Admin\SignalementsController::class, 'heartbeat'])
            ->middleware('role:admin,mediaplanner,commercial')
            ->name('signalements.heartbeat');
        Route::post('signalements/{action}/maintenance', [\App\Http\Controllers\Admin\SignalementsController::class, 'resolveToMaintenance'])
            ->middleware('role:admin,mediaplanner')
            ->whereNumber('action')
            ->name('signalements.maintenance');
        Route::post('signalements/{action}/dismiss', [\App\Http\Controllers\Admin\SignalementsController::class, 'dismiss'])
            ->middleware('role:admin,mediaplanner')
            ->whereNumber('action')
            ->name('signalements.dismiss');

        // ── M3 SLA enrichi : page analytique + édition motif a posteriori ──
        // Policy PoseTaskActionPolicy (admin/mediaplanner only — commercial/technique → 403).
        Route::get('sla/retards/export/pdf', [\App\Http\Controllers\Admin\SlaDelaysController::class, 'exportPdf'])
            ->middleware('role:admin,mediaplanner')
            ->name('sla.retards.export.pdf');
        // 2026-06-19 — Fusion : l'ancienne page /admin/sla/retards a été fusionnée
        // dans /admin/signalements (onglet "📊 Analyse"). On conserve la route
        // pour ne casser aucun lien existant (bookmarks, smart_back?back=sla,
        // PDFs déjà imprimés…) — elle redirige avec les filtres préservés.
        Route::get('sla/retards', function (\Illuminate\Http\Request $request) {
            $params = array_merge(['view' => 'analyse'],
                $request->only(['from','to','motif','zone','commune_id','client_id']));
            return redirect()->route('admin.signalements.index', $params);
        })
            ->middleware('role:admin,mediaplanner')
            ->name('sla.retards.index');
        // Édition motif a posteriori — crée action='motif_modified' SANS écraser l'original.
        Route::put('sla/retards/{action}/motif', [\App\Http\Controllers\Admin\SlaDelaysController::class, 'updateMotif'])
            ->middleware('role:admin,mediaplanner')
            ->whereNumber('action')
            ->name('sla.retards.motif.update');

        // ── M1 Performance Commerciale (mission 2026-06-17) ──
        // Routes ouvertes admin + mediaplanner + commercial. Le service force
        // commercialId = auth()->id() pour le rôle commercial (pas de fuite
        // cross-comm via URL forgé /performance/commerciaux/{autre-id}).
        Route::middleware('role:admin,mediaplanner,commercial')->group(function () {
            Route::get('performance/commerciaux',
                [\App\Http\Controllers\Admin\CommercialPerformanceController::class, 'index']
            )->name('performance.commercial.index');
            Route::get('performance/commerciaux/me',
                [\App\Http\Controllers\Admin\CommercialPerformanceController::class, 'me']
            )->name('performance.commercial.me');
            Route::get('performance/commerciaux/export/pdf',
                [\App\Http\Controllers\Admin\CommercialPerformanceController::class, 'exportPdf']
            )->name('performance.commercial.export.pdf');
            Route::get('performance/techniciens/export/pdf',
                [\App\Http\Controllers\Admin\TechnicianPerformanceController::class, 'exportPdf']
            )->name('performance.tech.export.pdf');
            Route::get('performance/equipes/export/pdf',
                [\App\Http\Controllers\Admin\TeamPerformanceController::class, 'exportPdf']
            )->name('performance.team.export.pdf');
            Route::get('performance/commerciaux/{user}',
                [\App\Http\Controllers\Admin\CommercialPerformanceController::class, 'show']
            )->whereNumber('user')->name('performance.commercial.show');
        });

        // Page de backfill (admin only) — assigner commercial_user_id aux
        // campagnes historiques case par case (Décision B, pas de migration auto).
        Route::middleware('role:admin')->group(function () {
            Route::get('migration/commercial-attribution',
                [\App\Http\Controllers\Admin\CommercialAttributionController::class, 'index']
            )->name('migration.commercial-attribution');
            Route::post('migration/commercial-attribution/{campaign}',
                [\App\Http\Controllers\Admin\CommercialAttributionController::class, 'assign']
            )->whereNumber('campaign')->name('migration.commercial-attribution.assign');
        });

        // ── M2 Performance Technicien : gestion des équipes (admin/MP) ──
        Route::middleware('role:admin,mediaplanner')->group(function () {
            Route::resource('teams', \App\Http\Controllers\Admin\PoseTeamController::class)
                ->parameters(['teams' => 'team'])
                ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
            Route::post('teams/{team}/members',
                [\App\Http\Controllers\Admin\PoseTeamController::class, 'addMembers']
            )->whereNumber('team')->name('teams.members.add');
            Route::delete('teams/{team}/members/{user}',
                [\App\Http\Controllers\Admin\PoseTeamController::class, 'removeMember']
            )->whereNumber('team')->whereNumber('user')->name('teams.members.remove');
        });

        // ── M2 Performance Technicien — pages perf (admin/MP/technique) ──
        // Le service force le scope au self pour technique.
        Route::middleware('role:admin,mediaplanner,technique')->group(function () {
            Route::get('performance/techniciens',
                [\App\Http\Controllers\Admin\TechnicianPerformanceController::class, 'index']
            )->name('performance.tech.index');
            Route::get('performance/techniciens/me',
                [\App\Http\Controllers\Admin\TechnicianPerformanceController::class, 'me']
            )->name('performance.tech.me');
            Route::get('performance/techniciens/{user}',
                [\App\Http\Controllers\Admin\TechnicianPerformanceController::class, 'show']
            )->whereNumber('user')->name('performance.tech.show');
        });

        // ── M2 Performance Équipe — admin/MP only ──
        Route::middleware('role:admin,mediaplanner')->group(function () {
            Route::get('performance/equipes',
                [\App\Http\Controllers\Admin\TeamPerformanceController::class, 'index']
            )->name('performance.team.index');
            Route::get('performance/equipes/{team}',
                [\App\Http\Controllers\Admin\TeamPerformanceController::class, 'show']
            )->whereNumber('team')->name('performance.team.show');
        });

        // ── Disponibilités ─────────────────── (admin + MP + commercial) ──
        // Le commercial peut maintenant créer des réservations depuis l'espace
        // disponibilités (matrice DISPONIBILITÉS étendue). La policy
        // ReservationPolicy::create couvre l'autorisation fine.
        Route::middleware('role:admin,mediaplanner,commercial')->group(function () {
            Route::get('disponibilites', [ReservationController::class, 'disponibilites'])->name('reservations.disponibilites');
            Route::post('disponibilites/confirmer', [ReservationController::class, 'confirmerSelection'])->name('reservations.confirmer-selection');

            // Route AJAX pour récupérer les panneaux disponibles d'une campagne
            Route::get('disponibilites/panneaux', [ReservationController::class, 'panneauxAjax'])
                ->name('reservations.disponibilites.panneaux')
                ->middleware('throttle:120,1');

            // Exports PDF disponibilités
            Route::get('disponibilites/export', [ReservationController::class, 'exportDisponibilites'])->name('disponibilites.export');
            Route::post('disponibilites/pdf-images', [ReservationController::class, 'pdfImages'])
                ->name('reservations.disponibilites.pdf-images');
            Route::post('disponibilites/pdf-liste', [ReservationController::class, 'pdfListe'])
                ->name('reservations.disponibilites.pdf-liste');
            Route::post('disponibilites/export-excel', [ReservationController::class, 'exportExcel'])
                ->name('reservations.disponibilites.export-excel');
        });


        // ── Réservations ─────────────────────────────────────────────
        // Lecture (index + show + JSON) = tous staff. Le commercial voit
        // ses propres dossiers via Policy ReservationPolicy::view().
        // Modifications (prix, ajout panneaux, status, annuler) = admin + MP.
        Route::get('reservations/available-panels', [ReservationController::class, 'availablePanels'])
            ->name('reservations.available-panels')
            ->middleware('throttle:60,1');
        Route::post('reservations/mark-seen', [ReservationController::class, 'markSeen'])->name('reservations.mark-seen');

        // Ajout/retrait de panneaux dans une réservation. Ouvert au commercial
        // pour qu'il puisse compléter ses propres résas (qu'il reçoit par
        // mail) ; la policy update() limite à SES résas via commercial_user_id.
        Route::middleware('role:admin,mediaplanner,commercial')->group(function () {
            Route::post('reservations/{reservation}/panels/add', [ReservationController::class, 'addPanel']) ->name('reservations.panels.add');
            Route::delete('reservations/{reservation}/panels/{panel}', [ReservationController::class, 'removePanel'])->name('reservations.panels.remove');
        });

        // CRUD Réservations (en dernier pour ne pas capturer les routes spécifiques)
        // JSON — liste des panneaux d'une réservation (modale "Voir les panneaux" depuis l'index)
        Route::get('reservations/{reservation}/panels-list', [ReservationController::class, 'getPanels'])
            ->name('reservations.panels.list');

        // JSON — snapshot du statut courant (polling 10s côté admin).
        Route::get('reservations/{reservation}/status-snapshot', [ReservationController::class, 'statusSnapshot'])
            ->name('reservations.status-snapshot');

        // Raccourci "Créer la facture" depuis la fiche réservation
        // (2026-06-26) — aiguille vers /admin/invoices/create avec campaign_id
        // et client_id préremplis. Voir ReservationController::createInvoice().
        Route::get('reservations/{reservation}/create-invoice', [ReservationController::class, 'createInvoice'])
            ->whereNumber('reservation')
            ->name('reservations.create-invoice');

        // Lecture résa (index + show) reste ouverte aux 3 rôles staff.
        // Update/destroy ne sont pas exposés par la resource (sauf via verbes
        // dédiés ci-dessous), donc pas besoin de scinder ici.
        Route::resource('reservations', ReservationController::class)->except(['create', 'store']);

        // Changement de statut / annulation = admin + MP uniquement
        Route::middleware('role:admin,mediaplanner')->group(function () {
            Route::patch('reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])->name('reservations.update-status');
            Route::patch('reservations/{reservation}/annuler', [ReservationController::class, 'annuler'])->name('reservations.annuler');

            // Actions groupées sur les réservations (admin + MP).
            // Action acceptées : 'cancel' (annulation en masse), 'delete'
            // (suppression — bloque si campagne active).
            Route::post('reservations/bulk', [ReservationController::class, 'bulkAction'])
                ->name('reservations.bulk');
        });

        // Arbitrage sur une demande de décalage de dates initiée par le
        // client depuis sa page de proposition (accept / refuse / contre-
        // proposition). Ouvert au commercial pour qu'il puisse arbitrer ses
        // propres résas reçues par mail ; la policy update() limite à SES
        // résas via commercial_user_id, et le controller appelle authorize().
        Route::middleware('role:admin,mediaplanner,commercial')->group(function () {
            Route::post('reservations/{reservation}/date-change/accept', [ReservationController::class, 'acceptDateChange'])
                ->name('reservations.date-change.accept');
            Route::post('reservations/{reservation}/date-change/refuse', [ReservationController::class, 'refuseDateChange'])
                ->name('reservations.date-change.refuse');
            Route::post('reservations/{reservation}/date-change/counter', [ReservationController::class, 'counterDateChange'])
                ->name('reservations.date-change.counter');
        });

        // ── API interne — Liste des commerciaux pour la modale "Soumettre" ──
        // Retourne id+name+email des users role=admin/commercial actifs.
        Route::get('api/commercials', function () {
            return \App\Models\User::query()
                ->whereIn('role', ['admin', 'commercial'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        })->middleware('role:admin,mediaplanner')->name('api.commercials');

        // ── Propositions (workflow MP → Commercial → Client) ──────────
        // MP soumet la proposition au commercial pour envoi (matrice).
        Route::middleware('role:admin,mediaplanner')->group(function () {
            Route::post(
                'reservations/{reservation}/proposition/soumettre',
                [PropositionController::class, 'submitProposition']
            )->name('reservations.proposition.soumettre');

            Route::post(
                'reservations/{reservation}/proposition/reinitialiser',
                [PropositionController::class, 'reinitialiserProposition']
            )->name('reservations.proposition.reinitialiser');
        });

        // Envoi / renvoi au client = admin + commercial uniquement (matrice).
        Route::middleware('role:admin,commercial')->group(function () {
            Route::post(
                'reservations/{reservation}/proposition/envoyer',
                [PropositionController::class, 'envoyerProposition']
            )->name('reservations.proposition.envoyer');

            Route::post(
                'reservations/{reservation}/proposition/renvoyer',
                [PropositionController::class, 'envoyerProposition']
            )->name('reservations.proposition.renvoyer');
        });

        // ── CRUD Propositions admin (lecture libre, modif = admin+MP) ──
        Route::get('propositions', [PropositionController::class, 'index'])
            ->name('propositions.index');
        Route::get('propositions/{proposition}', [PropositionController::class, 'show'])
            ->name('propositions.show');
        Route::get('propositions/{proposition}/pdf', [PropositionController::class, 'exportPdf'])
            ->name('propositions.pdf');
        Route::patch('propositions/{proposition}/status', [PropositionController::class, 'updateStatus'])
            ->middleware('role:admin,mediaplanner')
            ->name('propositions.update-status');

        // ── Tableau de bord FINANCIER ────────────────────────────────
        // Encaissements / créances / recouvrement / relances. Admin +
        // commercial + comptable (MP/Technique ne touchent pas la
        // facturation). Le scope commercial est appliqué dans le
        // controller via FinancialDashboardService → Invoice::forCommercialUser.
        // Comptable : vision globale non scopée (consolidation cabinet).
        Route::middleware('role:admin,commercial,comptable')->group(function () {
            Route::get('finance', [\App\Http\Controllers\Admin\FinanceDashboardController::class, 'index'])
                ->name('finance.index');
            Route::get('finance/series', [\App\Http\Controllers\Admin\FinanceDashboardController::class, 'series'])
                ->name('finance.series');
            // 2026-06-19 — Exports récap complet Finance (Excel multi-feuilles + PDF synthèse).
            Route::get('finance/export/excel', [\App\Http\Controllers\Admin\FinanceDashboardController::class, 'exportRecapExcel'])
                ->name('finance.export.excel');
            Route::get('finance/export/pdf', [\App\Http\Controllers\Admin\FinanceDashboardController::class, 'exportRecapPdf'])
                ->name('finance.export.pdf');
            Route::get('finance/relances', [\App\Http\Controllers\Admin\FinanceDashboardController::class, 'relances'])
                ->name('finance.relances');
            // Exports historique relances (Excel détaillé + PDF synthèse par client)
            // Avant /{relance}/detail pour éviter la collision sur "export" qui matche \w+
            Route::get('finance/relances/export/excel', [\App\Http\Controllers\Admin\FinanceDashboardController::class, 'exportRelancesExcel'])
                ->name('finance.relances.export.excel');
            Route::get('finance/relances/export/pdf', [\App\Http\Controllers\Admin\FinanceDashboardController::class, 'exportRelancesPdf'])
                ->name('finance.relances.export.pdf');
            Route::get('finance/relances/{relance}/detail', [\App\Http\Controllers\Admin\FinanceDashboardController::class, 'relanceDetail'])
                ->name('finance.relances.detail');
            Route::post('finance/relances', [\App\Http\Controllers\Admin\FinanceDashboardController::class, 'storeRelance'])
                ->name('finance.relances.store');
        });

        // ── Campagnes ─────────────────────────────────────────────────
        // Exports + Lecture (index + show + progress + available-panels)
        // ouverts à tous les staff. Le filtrage par owner se fait dans
        // CampaignController::index() via Policy CampaignPolicy::view().
        Route::get('campaigns/export/excel', [CampaignController::class, 'exportExcel'])
            ->name('campaigns.export.excel');
        Route::get('campaigns/export/pdf',   [CampaignController::class, 'exportPdf'])
            ->name('campaigns.export.pdf');

        // Lecture campagnes
        // ⚠️ campaigns/create DOIT être déclarée AVANT campaigns/{campaign}
        // sinon Laravel matche "create" comme un id de campagne → 404.
        // whereNumber sur les routes paramétriques garantit que les
        // segments non-numériques (comme 'create') ne soient pas capturés.
        Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');

        // Création (avant routes {campaign} paramétriques)
        Route::middleware('role:admin,mediaplanner')->group(function () {
            Route::get('campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
            Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');

            // Import depuis un PDF "Liste des panneaux commandes"
            Route::get ('campaigns/import-pdf',         [\App\Http\Controllers\Admin\CampaignImportController::class, 'form'])
                ->name('campaigns.import-pdf.form');
            Route::post('campaigns/import-pdf/preview', [\App\Http\Controllers\Admin\CampaignImportController::class, 'preview'])
                ->name('campaigns.import-pdf.preview');
            Route::post('campaigns/import-pdf/apply',   [\App\Http\Controllers\Admin\CampaignImportController::class, 'apply'])
                ->name('campaigns.import-pdf.apply');
        });

        // Routes paramétriques (id numérique uniquement)
        Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])
            ->whereNumber('campaign')->name('campaigns.show');
        // 2026-06-22 — Fiche de pose PDF (nom + client + période + panneaux
        // avec photos + dates de pose prévues + technicien/équipe).
        Route::get('campaigns/{campaign}/fiche-pose-pdf', [CampaignController::class, 'fichePosePdf'])
            ->whereNumber('campaign')->name('campaigns.fiche-pose.pdf');
        Route::get('campaigns/{campaign}/progress', [CampaignController::class, 'progress'])
            ->whereNumber('campaign')->name('campaigns.progress');
        Route::get('campaigns/{campaign}/available-panels', [CampaignController::class, 'availablePanels'])
            ->whereNumber('campaign')->name('campaigns.available-panels');
        // Page dédiée aux poses OOH d'une campagne (sans KPI/filtres globaux)
        Route::get('campaigns/{campaign}/poses', [CampaignController::class, 'poses'])
            ->whereNumber('campaign')->name('campaigns.poses');

        // Modification / actions = admin + MP (matrice CAMPAGNES)
        Route::middleware('role:admin,mediaplanner')->group(function () {
            Route::get('campaigns/{campaign}/edit', [CampaignController::class, 'edit'])
                ->whereNumber('campaign')->name('campaigns.edit');
            Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])
                ->whereNumber('campaign')->name('campaigns.update');
            Route::patch('campaigns/{campaign}', [CampaignController::class, 'update'])
                ->whereNumber('campaign')->name('campaigns.update.patch');

            // Renommer (nom seul) — marche aussi sur campagnes terminées
            Route::patch('campaigns/{campaign}/rename', [CampaignController::class, 'rename'])
                ->whereNumber('campaign')->name('campaigns.rename');

            // Actions groupées : 'pause' (actif→pause), 'resume' (pause→actif),
            // 'cancel' (annulation avec motif), 'delete' (uniquement
            // campagnes sans engagement actif — pose en cours / piges).
            Route::post('campaigns/bulk', [CampaignController::class, 'bulkAction'])
                ->name('campaigns.bulk');

            Route::patch('campaigns/{campaign}/status', [CampaignController::class, 'updateStatus'])
                ->whereNumber('campaign')->name('campaigns.update-status');
            Route::post ('campaigns/{campaign}/activate', [CampaignController::class, 'activate'])
                ->whereNumber('campaign')->name('campaigns.activate');
            Route::patch('campaigns/{campaign}/billing-quick', [CampaignController::class, 'billingQuick'])
                ->whereNumber('campaign')->name('campaigns.billing-quick');
            Route::patch('campaigns/{campaign}/prolonger', [CampaignController::class, 'prolonger'])
                ->whereNumber('campaign')->name('campaigns.prolonger');

            // Lien pige public (token partageable)
            Route::post  ('campaigns/{campaign}/pige-token', [CampaignController::class, 'generatePigeToken'])
                ->whereNumber('campaign')->name('campaigns.pige-token.generate');
            Route::delete('campaigns/{campaign}/pige-token', [CampaignController::class, 'revokePigeToken'])
                ->whereNumber('campaign')->name('campaigns.pige-token.revoke');

            // Panneaux d'une campagne
            Route::post('campaigns/{campaign}/panels', [CampaignController::class, 'addPanel'])
                ->whereNumber('campaign')->name('campaigns.panels.add');
            Route::delete('campaigns/{campaign}/panels/{panel}', [CampaignController::class, 'removePanel'])
                ->whereNumber('campaign')->whereNumber('panel')->name('campaigns.panels.remove');
            Route::delete('campaigns/{campaign}/external-panels/{externalPanel}', [CampaignController::class, 'removeExternalPanel'])
                ->whereNumber('campaign')->whereNumber('externalPanel')->name('campaigns.external-panels.remove');

            // Modification du prix d'un panneau attaché à la campagne
            // (prix négocié post-création ou correction). Met à jour le
            // pivot reservation_panels de la résa liée et recalcule le total.
            Route::patch('campaigns/{campaign}/panels/{panel}/price', [CampaignController::class, 'updatePanelPrice'])
                ->whereNumber('campaign')->whereNumber('panel')->name('campaigns.panels.price');
            Route::post('campaigns/{campaign}/panels/{panel}/price/reset', [CampaignController::class, 'resetPanelPrice'])
                ->whereNumber('campaign')->whereNumber('panel')->name('campaigns.panels.price.reset');

            // Notifier manuellement le client des modifications (panneaux
            // ajoutés/retirés, prix négocié). Renvoie un récap actualisé.
            Route::post('campaigns/{campaign}/notify-client', [CampaignController::class, 'notifyClient'])
                ->whereNumber('campaign')->name('campaigns.notify-client');

            // Override du montant total campagne (remise globale ou
            // négociation forfaitaire). Diverge de la somme des prix
            // panneau — l'utilisateur en prend la responsabilité.
            Route::patch('campaigns/{campaign}/total', [CampaignController::class, 'updateTotal'])
                ->whereNumber('campaign')->name('campaigns.total');

            // Dupliquer une campagne (renouvellement rapide : mêmes panneaux
            // + prix négociés + commercial, nouvelles dates).
            Route::post('campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])
                ->whereNumber('campaign')->name('campaigns.duplicate');
        });

        // Suppression campagne = admin uniquement (matrice CAMPAGNES)
        Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])
            ->middleware('role:admin')
            ->whereNumber('campaign')->name('campaigns.destroy');

        // ── Rapports business ──
        // Politique RBAC :
        //   • Index + AJAX + drilldown client + actions décapage + sa propre
        //     vue campagnes → ouvert au commercial (filtré sur son périmètre
        //     via DashboardKpiService scoping + RapportController::scopeUserCampaigns).
        //   • Taxes, exports complets, drilldown communes globales, drilldown
        //     panneaux globaux, motifs d'annulation entreprise → admin + MP
        //     uniquement (le commercial n'a pas à voir le CA d'autres
        //     commerciaux ni les taxes communales globales).
        Route::get('/rapports',      [RapportController::class, 'index'])->name('rapports.index');
        Route::get('/rapports/ajax', [RapportController::class, 'ajax'])->name('rapports.ajax');
        Route::get('/rapports/clients/{client}/detail', [RapportController::class, 'clientDetail'])
            ->whereNumber('client')->name('rapports.clients.detail');
        Route::get('/rapports/campagnes', [RapportController::class, 'campagnes'])->name('rapports.campagnes');

        // Actions / endpoints décapage (déjà scopés commercial — cf.
        // DashboardKpiService::decapStats et decapList).
        Route::post('/rapports/decap/mark',     [RapportController::class, 'markDecapped'])
            ->name('rapports.decap.mark');
        Route::post('/rapports/decap/mark-all', [RapportController::class, 'markAllDecapped'])
            ->name('rapports.decap.markAll');
        Route::get('/rapports/decap/summary',   [RapportController::class, 'decapSummary'])
            ->name('rapports.decap.summary');
        // 2026-06-19 — Feuille de décapage PDF imprimable pour les techs
        // terrain. Liste les panneaux NON ENCORE décapés avec adresse + GPS.
        Route::get('/rapports/decap/pdf',       [RapportController::class, 'decapPdf'])
            ->name('rapports.decap.pdf');

        // ── Sections admin + MP (vue production / opérationnelle) ──
        // MP = Media Planner : pilote la production et la diffusion. Il
        // a besoin de voir le parc global, la performance panneaux, la
        // répartition géographique, les taxes communales opérationnelles
        // (ODP/TM payées par commune) et les motifs d'annulation pour
        // analyse production. Il ne voit PAS le CA stratégique entreprise.
        Route::middleware('role:admin,mediaplanner')->group(function () {
            Route::get('/rapports/annulations', [RapportController::class, 'annulations'])->name('rapports.annulations');
            Route::get('/rapports/communes/{commune}/detail', [RapportController::class, 'communeDetail'])
                ->name('rapports.communes.detail');
            Route::get('/rapports/panels/{panel}/detail', [RapportController::class, 'panelDetail'])
                ->whereNumber('panel')->name('rapports.panels.detail');
            Route::get('/rapports/taxes', [RapportController::class, 'taxes'])->name('rapports.taxes');
        });

        // ── Exports complets — ADMIN UNIQUEMENT ──
        // RapportDashboardExport génère 8 feuilles dont CA, Forecast,
        // Synthèse exécutive — données financières stratégiques
        // réservées à la direction. Si MP a besoin d'un export terrain,
        // il passe par les exports modulaires (panels.export, campaigns.
        // export, taxes.export) qui restent ouverts à son périmètre.
        // ── ADMIN uniquement : dashboard complet + synthèse exécutive ──
        // Contiennent le CA stratégique entreprise / EBITDA / synthèse
        // direction : hors périmètre MP.
        Route::middleware('role:admin')->group(function () {
            Route::get('/rapports/export/excel', [RapportController::class, 'exportExcel'])
                ->name('rapports.export.excel');
            Route::get('/rapports/export/pdf', [RapportController::class, 'exportPdf'])
                ->name('rapports.export.pdf');
        });

        // ── ADMIN + MP : exports opérationnels des onglets Rapports ──
        // Ouverts au Media Planner (2026-07-01) car les onglets sources
        // (Performance panneaux, Zones & Communes, Occupation détaillée)
        // font déjà partie de son périmètre de visibilité. Ces exports
        // sont limités aux données opérationnelles (occupation, stats
        // par commune, panneau × campagne) — pas de CA stratégique
        // entreprise ni de KPI direction.
        Route::middleware('role:admin,mediaplanner')->group(function () {
            // Liste panneaux + taux d'occupation (onglet "Performance panneaux")
            Route::get('/rapports/export/panneaux-occupation-excel', [RapportController::class, 'exportPanelsOccupationExcel'])
                ->name('rapports.export.panels-occupation-excel');
            Route::get('/rapports/export/panneaux-occupation-pdf', [RapportController::class, 'exportPanelsOccupationPdf'])
                ->name('rapports.export.panels-occupation-pdf');
            // Onglet "Occupation détaillée" (panneau × campagne × période)
            Route::get('/rapports/export/occupation-details-excel', [RapportController::class, 'exportOccupationDetailsExcel'])
                ->name('rapports.export.occupation-details-excel');
            Route::get('/rapports/export/occupation-details-pdf', [RapportController::class, 'exportOccupationDetailsPdf'])
                ->name('rapports.export.occupation-details-pdf');
            // Onglet "Zones & Communes" (stats par commune : occupés, taux, CA)
            Route::get('/rapports/export/zones-communes-excel', [RapportController::class, 'exportZonesCommunesExcel'])
                ->name('rapports.export.zones-communes-excel');
            Route::get('/rapports/export/zones-communes-pdf', [RapportController::class, 'exportZonesCommunesPdf'])
                ->name('rapports.export.zones-communes-pdf');
        });

    });


