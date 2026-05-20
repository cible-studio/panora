<?php

/**
 * Benchmarks sectoriels OOH (Out-of-Home advertising) — Côte d'Ivoire & Afrique.
 *
 * Ce fichier centralise les données de marché utilisées pour situer la
 * performance de Panora par rapport au secteur. Mettez à jour ces valeurs
 * trimestriellement à partir de :
 *   - Études Outdoor Africa / OAAA / Médiamétrie CI
 *   - Rapports Annonceurs (UDECI, MARP, etc.)
 *   - Pige interne sur la concurrence (cf. module Piges Photos)
 *   - Données publiques (CAA, INS Côte d'Ivoire)
 *
 * Les valeurs sont volontairement déclaratives : aucun appel API externe
 * n'est requis pour faire fonctionner les rapports. Quand des données
 * externes sont indisponibles, conservez la dernière valeur connue avec
 * sa date de mise à jour.
 */

return [

    // ── Méta — date de dernière mise à jour de ce fichier ───────────────
    'last_updated' => '2026-05-20',
    'source_notes' => 'Estimations internes CIBLE CI · à compléter avec études OAAA et UDECI 2026',

    // ── Taux d'occupation moyen du secteur (référentiel) ────────────────
    'occupation' => [
        // Plage typique d'occupation dans l'OOH Côte d'Ivoire
        'ci_average'        => 55,   // % moyen marché CI
        'ci_top_performers' => 75,   // % seuil "performant"
        'africa_average'    => 48,   // % moyen Afrique de l'Ouest
        'unit'              => '%',
        'note'              => "L'OOH urbain Abidjan est plus tendu que Bouaké/San-Pedro (+10-15 pts).",
    ],

    // ── Tarification moyenne (FCFA / panneau / mois) ────────────────────
    'pricing' => [
        // Tarif mensuel moyen Abidjan, 4×3 lumineux
        'abidjan_4x3_lit'        => 350000,
        'abidjan_4x3_classique'  => 200000,
        'intérieur_pays_4x3'     => 120000,
        'panneau_geant_8x3'      => 750000,
        'note'                   => "Tarifs publics indicatifs hors remise. Marges typiques 30-45 %.",
    ],

    // ── Croissance du secteur OOH (annuel) ──────────────────────────────
    'growth' => [
        'ci_yoy_2024_2025'  => 8.5,   // % de croissance estimée
        'ci_yoy_2025_2026'  => 11.0,  // projection
        'africa_yoy_2025'   => 6.7,
        'unit'              => '% YoY',
        'note'              => "Croissance portée par les télécoms, fintechs et brasseurs (3 secteurs > 50 % du marché).",
    ],

    // ── Mix sectoriel des annonceurs OOH en CI ──────────────────────────
    'industry_mix' => [
        ['sector' => 'Télécoms (MTN, Orange, Moov)',    'share_pct' => 28],
        ['sector' => 'Banque & Fintech',                  'share_pct' => 17],
        ['sector' => 'Brasseries & boissons',            'share_pct' => 14],
        ['sector' => 'Distribution / retail',            'share_pct' => 11],
        ['sector' => 'Transport & mobilité',             'share_pct' => 8],
        ['sector' => 'Cosmétiques & beauté',             'share_pct' => 7],
        ['sector' => 'Construction & immobilier',        'share_pct' => 6],
        ['sector' => 'Autres',                            'share_pct' => 9],
    ],

    // ── Taux d'annulation typique ───────────────────────────────────────
    'cancel_rate' => [
        'industry_healthy'   => 8,    // < 8% considéré sain
        'industry_average'   => 12,   // moyenne marché
        'industry_warning'   => 18,   // > 18% problématique
        'unit'               => '%',
    ],

    // ── Acteurs concurrents principaux (Côte d'Ivoire) ──────────────────
    'competitors' => [
        ['name' => 'Affichage Géant (AG)',           'estimated_parc' => 1800, 'tier' => 'leader'],
        ['name' => 'OCCAB',                          'estimated_parc' => 1200, 'tier' => 'challenger'],
        ['name' => 'Phenix Pub',                     'estimated_parc' => 900,  'tier' => 'challenger'],
        ['name' => 'Boutiques régionales (cumul)',   'estimated_parc' => 2200, 'tier' => 'fragmenté'],
        // ⚠️ Estimations indicatives — non sourcées officiellement
    ],

    // ── Tendances structurelles à surveiller (qualitatif) ───────────────
    'trends' => [
        [
            'icon'  => '📱',
            'title' => 'Convergence OOH + Digital (DOOH)',
            'desc'  => "Les écrans LED programmatiques gagnent du terrain à Abidjan. Investissement à anticiper sur les 2-3 ans.",
        ],
        [
            'icon'  => '📈',
            'title' => 'Hausse demande fintech & e-commerce',
            'desc'  => "Wave, Djamo, Jumia : nouveaux annonceurs majeurs. Cycles de campagnes plus courts mais plus fréquents.",
        ],
        [
            'icon'  => '🌍',
            'title' => 'Régulation municipale renforcée',
            'desc'  => "Plusieurs communes durcissent les autorisations (Cocody, Marcory). Anticiper les conformités ODP.",
        ],
        [
            'icon'  => '🏗️',
            'title' => 'Saturation Plateau / Cocody',
            'desc'  => "L'offre dépasse la demande sur ces zones premium. Opportunité de rééquilibrage vers Yopougon, Abobo.",
        ],
    ],

];
