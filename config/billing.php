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
    'company' => [
        'name'      => env('BILLING_COMPANY_NAME', 'CIBLE SARL'),
        'legal'     => env('BILLING_COMPANY_LEGAL', 'Société à responsabilité limitée'),
        'address'   => env('BILLING_COMPANY_ADDRESS', 'Abidjan, Côte d\'Ivoire'),
        'phone'     => env('BILLING_COMPANY_PHONE', ''),
        'email'     => env('BILLING_COMPANY_EMAIL', ''),
        'rccm'      => env('BILLING_COMPANY_RCCM', ''),
        'ifu'       => env('BILLING_COMPANY_IFU', ''),
        'ncc'       => env('BILLING_COMPANY_NCC', ''),
        'logo_path' => env('BILLING_COMPANY_LOGO', 'images/panora.png'),
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
