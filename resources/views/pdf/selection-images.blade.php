<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>CIBLE CI - Fiche Panneau</title>
    <style>
        /* ========== RESET & CONFIGURATION GLOBALE ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f8fafc;
            padding: 16px;
            color: #1e293b;
            font-size: 10px;
        }

        /* ========== PAGE : UN PANNEAU PAR PAGE ========== */
        .panel-page {
            max-width: 1100px;
            margin: 0 auto 24px auto;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            page-break-after: always;   /* Force chaque fiche sur une nouvelle page en PDF */
            break-inside: avoid;
            transition: all 0.2s;
        }

        /* ========== EN-TÊTE CHARTE ENTREPRISE ========== */
        .brand-header {
            background: linear-gradient(135deg, #0b2b26 0%, #0f3a33 100%);
            padding: 20px 28px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            border-bottom: 4px solid #e3a51e;
        }
        .brand-header h1 {
            font-size: 24px;
            font-weight: 800;
            color: #e3a51e;
            letter-spacing: -0.3px;
            margin: 0;
            text-transform: uppercase;
            font-family: inherit;
        }
        .brand-header h1 small {
            font-size: 11px;
            font-weight: 400;
            color: #cbd5e1;
            display: block;
            margin-top: 4px;
            letter-spacing: normal;
        }
        .badge-status-global {
            background: rgba(255,255,240,0.12);
            backdrop-filter: blur(2px);
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 600;
            color: #facc15;
            border: 1px solid rgba(227,165,30,0.3);
        }

        /* ========== GRILLE PRINCIPALE : PHOTO + INFOS ========== */
        .panel-master {
            display: flex;
            flex-wrap: wrap;
            padding: 28px 28px 20px 28px;
            gap: 28px;
            background: #ffffff;
        }

        /* ZONE IMAGE - ratio préservé, pas d'étirement */
        .visual-zone {
            flex: 1.4;
            min-width: 260px;
            background: #f8fafc;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid #e9edf2;
        }
        .image-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f1f5f9;
            min-height: 280px;
            padding: 12px;
        }
        .panel-img {
            max-width: 100%;
            max-height: 340px;
            width: auto;
            height: auto;
            object-fit: contain;   /* Évite tout étirement, respecte les proportions */
            display: block;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .no-image-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 20px;
            background: #eef2f6;
            border-radius: 16px;
            width: 100%;
        }
        .no-image-placeholder span:first-child {
            font-size: 48px;
            opacity: 0.5;
            margin-bottom: 12px;
        }
        .no-image-placeholder p {
            font-size: 13px;
            color: #4b5563;
            font-weight: 500;
            background: white;
            padding: 4px 12px;
            border-radius: 30px;
            display: inline-block;
        }

        /* ZONE INFORMATIONS DÉTAILLÉES */
        .info-zone {
            flex: 1.6;
            min-width: 280px;
        }
        .ref-badge {
            display: inline-block;
            background: #f1f5f9;
            padding: 5px 14px;
            border-radius: 30px;
            font-family: monospace;
            font-weight: 700;
            font-size: 14px;
            color: #0f3a33;
            border-left: 3px solid #e3a51e;
            margin-bottom: 14px;
        }
        .designation-title {
            font-size: 20px;
            font-weight: 700;
            color: #0b2b26;
            margin: 6px 0 12px 0;
            line-height: 1.3;
            border-bottom: 2px dashed #e2e8f0;
            padding-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 20px;
            margin: 20px 0 20px;
            background: #fefcf5;
            padding: 16px 18px;
            border-radius: 20px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            color: #5b6e8c;
        }
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-top: 4px;
            word-break: break-word;
        }
        .info-value.dim {
            font-family: monospace;
            font-size: 13px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            padding: 10px 18px;
            border-radius: 50px;
            margin-top: 12px;
            width: fit-content;
        }
        .status-circle {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        .badge-libre { background: #10b981; box-shadow: 0 0 0 2px #d1fae5; }
        .badge-occupe { background: #ef4444; box-shadow: 0 0 0 2px #fee2e2; }
        .badge-option { background: #f59e0b; box-shadow: 0 0 0 2px #fed7aa; }
        .badge-maint { background: #64748b; box-shadow: 0 0 0 2px #e2e8f0; }
        .badge-confirme { background: #8b5cf6; box-shadow: 0 0 0 2px #ede9fe; }
        .status-text {
            font-weight: 700;
            font-size: 14px;
        }

        .price-block {
            margin-top: 20px;
            background: #0b2b26;
            color: white;
            padding: 12px 18px;
            border-radius: 28px;
            display: inline-block;
            width: auto;
        }
        .price-label {
            font-size: 11px;
            font-weight: 500;
            opacity: 0.8;
        }
        .price-value {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #facc15;
            line-height: 1;
        }
        .meta-supp {
            margin-top: 16px;
            font-size: 10px;
            color: #5b6e8c;
            border-top: 1px solid #eef2f6;
            padding-top: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        /* LIBÉRATION INFO (release) */
        .release-warning {
            margin-top: 12px;
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
            color: #92400e;
        }

        /* ========== PIED DE PAGE CHARTE ========== */
        .footer-note {
            background: #fafcff;
            padding: 14px 28px;
            border-top: 1px solid #e9edf2;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #617388;
            font-weight: 500;
        }
        .footer-note .date {
            font-family: monospace;
        }
        .footer-note .confidential {
            letter-spacing: 0.6px;
        }

        /* RESPONSIVE POUR LECTURE ÉCRAN (mais surtout valide pour PDF) */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .panel-page {
                box-shadow: none;
                margin: 0;
                border-radius: 0;
                page-break-after: always;
            }
            .brand-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .status-badge, .price-block, .badge-status-global {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<!-- 
    MODÈLE PDF "UN PANNEAU PAR PAGE"
    Ce template récupère les VRAIES données depuis le contrôleur Laravel :
    - La variable $panels est une collection de modèles Panel (avec relations chargées : commune, format, zone, category, photos)
    - Les données supplémentaires (display_status, release_info, is_selectable, etc.) sont déjà formatées
      par la méthode formatInternalPanel du contrôleur (via panneauxAjax). 
    - Pour la génération PDF via pdfImages(), on reçoit $panels (Panel models).
    - On adapte dynamiquement les statuts et libérations selon la période transmise (startDate/endDate).
    
    AMÉLIORATIONS :
    - Images non étirées (object-fit: contain, max-height)
    - Design premium respectant charte (#0b2b26, #e3a51e)
    - Affichage clair des dimensions, tarifs, disponibilités et infos techniques
    - Chaque page = un panneau, comme demandé.
-->

@php
    // Helper pour formater les dimensions à partir du format (width/height)
    if (!function_exists('formatDimensions')) {
        function formatDimensions($format) {
            if (!$format || !$format->width || !$format->height) {
                return null;
            }
            $w = rtrim(rtrim(number_format($format->width, 2, '.', ''), '0'), '.');
            $h = rtrim(rtrim(number_format($format->height, 2, '.', ''), '0'), '.');
            return "{$w}×{$h}m";
        }
    }

    // Helper pour déterminer le statut d'affichage et les infos de libération en fonction de la période passée (startDate, endDate)
    // Utilisé si les données ne contiennent pas déjà 'display_status' et 'release_info' (cas du pdfImages)
    if (!function_exists('computeDisplayStatus')) {
        function computeDisplayStatus($panel, $startDate, $endDate, $occupiedIds = null, $optionIds = null, $releaseDates = null) {
            // Si le panneau est en maintenance
            if ($panel->status->value === 'maintenance') {
                return ['status' => 'maintenance', 'release_info' => null];
            }
            
            // Si pas de période définie, on se base sur le statut DB
            if (!$startDate || !$endDate) {
                $dbStatus = $panel->status->value;
                $display = ($dbStatus === 'libre') ? 'libre' : (($dbStatus === 'maintenance') ? 'maintenance' : 'occupe');
                return ['status' => $display, 'release_info' => null];
            }
            
            // Simulation de conflits si on a des IDs (généralement fournis via le contrôleur)
            // En PDF direct, on pourrait appeler un service, mais ici on suppose que le contrôleur a passé
            // les infos ou qu'on utilise la logique simplifiée: on regarde via la relation reservations.
            // Pour la fiabilité, on utilise le système de réservations en base.
            $conflictingReservations = $panel->reservations()
                ->where(function($q) use ($startDate, $endDate) {
                    $q->where('start_date', '<', $endDate)
                      ->where('end_date', '>', $startDate);
                })
                ->whereIn('status', ['confirme', 'en_attente']) // EN_ATTENTE = option
                ->get();
                
            $isOccupied = $conflictingReservations->where('status', 'confirme')->isNotEmpty();
            $isOption   = $conflictingReservations->where('status', 'en_attente')->isNotEmpty();
            
            $releaseInfo = null;
            if ($isOccupied || $isOption) {
                $maxEndDate = $conflictingReservations->max('end_date');
                if ($maxEndDate) {
                    $rd = \Carbon\Carbon::parse($maxEndDate)->startOfDay();
                    $now = \Carbon\Carbon::now()->startOfDay();
                    $daysLeft = (int)$now->diffInDays($rd, false);
                    $releaseInfo = [
                        'date' => $rd->format('d/m/Y'),
                        'days_left' => $daysLeft,
                        'label' => $daysLeft <= 0 ? 'Libre aujourd\'hui' 
                                : ($daysLeft === 1 ? 'Libre demain' 
                                : "Libre le {$rd->format('d/m/Y')} ({$daysLeft}j)"),
                        'color' => $daysLeft <= 0 ? 'green' : ($daysLeft <= 7 ? 'orange' : 'default'),
                    ];
                }
            }
            
            if ($isOccupied) {
                return ['status' => 'occupe', 'release_info' => $releaseInfo];
            } elseif ($isOption) {
                return ['status' => 'option_periode', 'release_info' => $releaseInfo];
            } else {
                return ['status' => 'libre', 'release_info' => null];
            }
        }
    }
    
    // Générer la date d'édition
    $generated = now()->format('d/m/Y à H:i');
    
    // Déterminer si on a des infos pré-formatées (depuis panneauxAjax) ou des modèles bruts (depuis pdfImages)
    $hasFormattedData = isset($panels[0]['reference']) || (isset($panels[0]->reference) && isset($panels[0]->display_status));
    
@endphp

@foreach($panels as $panel)
@php
    // Récupération des vraies données : soit tableau formaté (avec display_status) soit modèle Panel
    if (is_array($panel)) {
        // Cas où les données viennent du JSON de panneauxAjax (déjà formatées)
        $reference      = $panel['reference'] ?? '—';
        $name           = $panel['name'] ?? '—';
        $commune        = $panel['commune'] ?? '—';
        $zone           = $panel['zone'] ?? '—';
        $dimensions     = $panel['dimensions'] ?? null;
        $category       = $panel['category'] ?? '—';
        $isLit          = $panel['is_lit'] ?? false;
        $monthlyRate    = $panel['monthly_rate'] ?? 0;
        $dailyTraffic   = $panel['daily_traffic'] ?? 0;
        $displayStatus  = $panel['display_status'] ?? 'libre';
        $releaseInfo    = $panel['release_info'] ?? null;
        $photoUrl       = $panel['photo_url'] ?? null;
        $zoneDesc       = $panel['zone_description'] ?? '';
        $statusDb       = $panel['status_db'] ?? 'libre';
        
        // Construction dimension texte si non fournie
        if (!$dimensions && isset($panel['format']) && $panel['format'] !== '—') {
            $dimensions = $panel['format'];
        }
    } else {
        // Cas modèle Panel (pdfImages) - on utilise les relations et on calcule le statut en fonction de la période
        $reference      = $panel->reference ?? '—';
        $name           = $panel->name ?? '—';
        $commune        = $panel->commune?->name ?? '—';
        $zone           = $panel->zone?->name ?? '—';
        $dimensions     = formatDimensions($panel->format);
        $category       = $panel->category?->name ?? '—';
        $isLit          = (bool)($panel->is_lit ?? false);
        $monthlyRate    = (float)($panel->monthly_rate ?? 0);
        $dailyTraffic   = (int)($panel->daily_traffic ?? 0);
        $zoneDesc       = $panel->zone_description ?? '';
        $statusDb       = $panel->status->value ?? 'libre';
        
        // Récupération de la première photo (ordre normal)
        $firstPhoto = $panel->photos->sortBy('ordre')->first();
        $photoUrl = $firstPhoto ? asset('storage/' . ltrim($firstPhoto->path, '/')) : null;
        
        // Calcul du statut et release_info selon période (startDate/endDate venant du contrôleur)
        $computed = computeDisplayStatus($panel, $startDate ?? null, $endDate ?? null);
        $displayStatus = $computed['status'];
        $releaseInfo = $computed['release_info'];
    }
    
    // Définition des badges CSS selon le statut d'affichage
    $badgeClass = match($displayStatus) {
        'libre'          => 'badge-libre',
        'occupe'         => 'badge-occupe',
        'option_periode' => 'badge-option',
        'maintenance'    => 'badge-maint',
        'confirme'       => 'badge-confirme',
        default          => 'badge-libre',
    };
    $statusLabel = match($displayStatus) {
        'libre'          => 'LIBRE - Disponible',
        'occupe'         => 'OCCUPÉ',
        'option_periode' => 'EN OPTION',
        'maintenance'    => 'MAINTENANCE',
        'confirme'       => 'CONFIRMÉ (Réservé)',
        default          => ucfirst($displayStatus),
    };
    
    // Formater le tarif
    $formattedRate = number_format($monthlyRate, 0, ',', ' ') . ' FCFA';
    
    // Récupération des métadonnées additionnelles
    $dimensionsDisplay = $dimensions ?? '—';
    $trafficDisplay = $dailyTraffic > 0 ? number_format($dailyTraffic, 0, ',', ' ') . ' véh./jour' : 'Non renseigné';
    
@endphp
<div class="panel-page">
    <!-- EN-TÊTE CHARTE -->
    <div class="brand-header">
        <div>
            <h1>CIBLE CI <small>Régie Publicitaire & Out-of-Home</small></h1>
        </div>
        <div class="badge-status-global">
            FICHE TECHNIQUE PANO
        </div>
    </div>

    <!-- CORPS : IMAGE + INFOS -->
    <div class="panel-master">
        <!-- SECTION VISUAL : IMAGE NON ÉTIRÉE -->
        <div class="visual-zone">
            <div class="image-wrapper">
                @if($photoUrl && filter_var($photoUrl, FILTER_VALIDATE_URL) && @getimagesize($photoUrl))
                    <img class="panel-img" src="{{ $photoUrl }}" alt="Visuel panneau {{ $reference }}" loading="lazy">
                @elseif($photoUrl && file_exists(public_path(str_replace(asset(''), '', $photoUrl))))
                    <img class="panel-img" src="{{ $photoUrl }}" alt="Visuel panneau {{ $reference }}">
                @else
                    <div class="no-image-placeholder">
                        <span>📸</span>
                        <p>Visuel non disponible</p>
                        <small style="font-size:10px; margin-top:6px;">{{ $reference }}</small>
                    </div>
                @endif
            </div>
        </div>

        <!-- ZONE INFORMATIONS DÉTAILLÉES -->
        <div class="info-zone">
            <div class="ref-badge">REF: {{ $reference }}</div>
            <div class="designation-title">{{ \Illuminate\Support\Str::limit($name, 70) }}</div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Commune / Zone</div>
                    <div class="info-value">{{ $commune }} @if($zone && $zone !== '—') · {{ $zone }} @endif</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Dimensions / Format</div>
                    <div class="info-value dim">{{ $dimensionsDisplay }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Catégorie / Type</div>
                    <div class="info-value">{{ $category }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Éclairage</div>
                    <div class="info-value">{{ $isLit ? '✅ Éclairé (LED)' : '⚡ Non éclairé' }}</div>
                </div>
                @if($dailyTraffic > 0)
                <div class="info-item">
                    <div class="info-label">Trafic moyen</div>
                    <div class="info-value">{{ $trafficDisplay }}</div>
                </div>
                @endif
                @if($zoneDesc)
                <div class="info-item">
                    <div class="info-label">Description zone</div>
                    <div class="info-value" style="font-size:12px;">{{ \Illuminate\Support\Str::limit($zoneDesc, 60) }}</div>
                </div>
                @endif
            </div>

            <!-- BADGE STATUT DYNAMIQUE -->
            <div class="status-badge">
                <span class="status-circle {{ $badgeClass }}"></span>
                <span class="status-text">{{ $statusLabel }}</span>
            </div>

            <!-- INFOS DE LIBÉRATION SI PRÉSENTES -->
            @if($releaseInfo && isset($releaseInfo['label']))
            <div class="release-warning">
                📅 {{ $releaseInfo['label'] }}
            </div>
            @endif

            <!-- TARIF MENSUEL CHARTE -->
            <div class="price-block">
                <div class="price-label">Tarif mensuel net (hors taxes)</div>
                <div class="price-value">{{ $formattedRate }} <span style="font-size:12px;">/ mois</span></div>
                <div style="font-size: 9px; opacity:0.8; margin-top:4px;">*Offre commerciale selon durée</div>
            </div>

            <!-- MÉTADONNÉES SUPPLÉMENTAIRES -->
            <div class="meta-supp">
                <span>📌 Référence unique: {{ $reference }}</span>
                @if($dimensionsDisplay !== '—')
                <span>📐 Surface estimée: {{ $dimensionsDisplay }}</span>
                @endif
                @if($displayStatus === 'libre')
                <span>🔥 Disponible immédiatement</span>
                @endif
            </div>
        </div>
    </div>

    <!-- PIED DE PAGE PROFESSIONNEL -->
    <div class="footer-note">
        <div class="confidential">🔒 Document confidentiel - CIBLE CI Régie</div>
        <div class="date">Généré le {{ $generated }} · Panneau unique</div>
        <div>Service commercial: +225 27 22 51 00 11</div>
    </div>
</div>
@endforeach

<!-- 
    NOTES TECHNIQUES D'INTÉGRATION LARAVEL / DOMPDF :
    - Ce template est conçu pour être utilisé avec la méthode pdfImages() du ReservationController.
    - Dans le contrôleur, il suffit d'appeler : 
        $pdf = Pdf::loadView('pdf.panneau-unique', compact('panels', 'startDate', 'endDate'));
    - Les variables $startDate et $endDate sont optionnelles mais améliorent le calcul de disponibilité.
    - Les modèles Panel doivent charger les relations : 'commune', 'format', 'zone', 'category', 'photos'.
    - Le design respecte la charte (#0b2b26, #e3a51e) et garantit images non étirées via object-fit: contain.
    - Chaque panneau = une page distincte (page-break-after:always).
-->
</body>
</html>