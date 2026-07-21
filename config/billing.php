<?php

/**
 * Configuration métier facturation FNE — Côte d'Ivoire.
 *
 * Les valeurs ici alimentent l'entête PDF de la facture, les calculs
 * de taxe, et la prochaine intégration éventuelle DGI. À chaque fois
 * qu'un taux légal change (TVA, TSP), on modifie ICI — pas dans le
 * code calculateur.
 *
 * Pour les infos entreprise CIBLE, on lit prioritairement les ENV
 * (déploiement multi-tenant futur facile) avec fallback codé en dur
 * sur les valeurs actuelles connues.
 */
return [

    // ─── Entreprise émettrice ────────────────────────────────────
    // Ces valeurs alimentent l'entête FNE du PDF facture — cf. modèle
    // officiel Côte d'Ivoire fourni par la patronne (bloc identité
    // haut-gauche encadré + bandeau vendeur/PDV/date/paiement).
    'company' => [
        'name'              => env('BILLING_COMPANY_NAME', 'CIBLE'),
        'legal'             => env('BILLING_COMPANY_LEGAL', 'Société à responsabilité limitée'),
        'address'           => env('BILLING_COMPANY_ADDRESS', '08 BP 687 ABIDJAN (VILLE)'),
        'phone'             => env('BILLING_COMPANY_PHONE', '27 22 20 80 08'),
        'email'             => env('BILLING_COMPANY_EMAIL', 'commercial@cible-ci.com'),
        'rccm'              => env('BILLING_COMPANY_RCCM', '262441 du 27-03-2001'),
        'ifu'               => env('BILLING_COMPANY_IFU', ''),
        'ncc'               => env('BILLING_COMPANY_NCC', '0184670J'),
        // FNE (nouveaux champs 2026-07-20) — apparaissent dans l'entête
        // encadrée en haut-gauche du PDF conformément au modèle DGI.
        'regime_imposition' => env('BILLING_COMPANY_REGIME',  'RNI'),
        'centre_impots'     => env('BILLING_COMPANY_CENTRE',  '877 Impôts de Bietry'),
        // Nom du PDV (point de vente) affiché sur chaque facture.
        // Peut varier selon l'agence physique où la facture est émise.
        'pdv_name'          => env('BILLING_COMPANY_PDV',     'COMPTA 1'),
        // Logo de l'émetteur (dans l'entête PDF). Version couleur.
        'logo_path'         => env('BILLING_COMPANY_LOGO', 'images/logol.png'),
    ],

    // ─── Taux légaux applicables ─────────────────────────────────
    'tva_rate'   => (float) env('BILLING_TVA_RATE', 18.0),
    'tsp_rate'   => (float) env('BILLING_TSP_RATE', 3.0),
    // TM base nationale 2025 — kept here as a fallback when the
    // commune_tariffs row has tm_rate = 0 (cas non seedé).
    'tm_default' => (float) env('BILLING_TM_DEFAULT', 1000.0),

    // ─── Mentions légales bas de facture ─────────────────────────
    'legal_mentions' => env(
        'BILLING_LEGAL_MENTIONS',
        'Facture émise conformément au Code Général des Impôts de Côte d\'Ivoire. '
        . 'Tout paiement après échéance entraîne des pénalités au taux légal en vigueur. '
        . 'Aucun escompte pour paiement anticipé sauf accord écrit.'
    ),

    // ─── Conditions de règlement par défaut ──────────────────────
    'payment_terms_default' => env(
        'BILLING_PAYMENT_TERMS',
        'Paiement à 30 jours nets à compter de la date de facturation.'
    ),

    // ─── Coordonnées bancaires (optionnel — affichées en bas) ────
    'bank' => [
        'name'   => env('BILLING_BANK_NAME', ''),
        'iban'   => env('BILLING_BANK_IBAN', ''),
        'rib'    => env('BILLING_BANK_RIB', ''),
        'swift'  => env('BILLING_BANK_SWIFT', ''),
    ],

    // ─── Numérotation ────────────────────────────────────────────
    // Format obtenu par sprintf — %s = année, %03d = compteur 3 chiffres.
    'reference_format' => env('BILLING_REF_FORMAT', 'FAC-%s-%03d'),
    'credit_note_format' => env('BILLING_CN_FORMAT', 'AV-%s-%03d'),

    // ─── ALERTES AUTOMATIQUES — Phase 4 (§7 cahier) ──────────────
    // Délais d'alerte autour de chaque échéance prévisionnelle.
    //   J-15/-7/-3/-1 = AVANT échéance (relances préventives)
    //   J+1/+7/+15/+30 = APRÈS (relances de retard)
    // Modifiables ici sans déploiement de code (juste cache:clear).
    'alerts' => [
        'offsets' => [
            'before' => [15, 7, 3, 1],
            'after'  => [1, 7, 15, 30],
        ],
        // Canaux ACTIVÉS. Mettre false pour désactiver totalement un canal.
        'channels' => [
            'in_app'   => (bool) env('BILLING_ALERTS_INAPP', true),
            'email'    => (bool) env('BILLING_ALERTS_EMAIL', true),
            'whatsapp' => (bool) env('BILLING_ALERTS_WA',    false),
        ],
    ],

];
