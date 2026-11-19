<?php

/**
 * Bon de commande (2026-11-13) — « sans BC, pas de campagne ».
 *
 * RÈGLE MÉTIER VALIDÉE PAR ÉCRIT :
 *   Quand un client s'engage (clic sur la proposition, devis accepté,
 *   réservation ferme saisie, renouvellement accepté), les panneaux sont
 *   tenus EN OPTION pendant N jours. La campagne n'existe qu'une fois le
 *   bon de commande reçu — confirmé verbalement par le commercial, sans
 *   pièce jointe obligatoire — puis créée par le média-planneur.
 *   Passé le délai, l'option expire et les panneaux repartent au catalogue.
 *
 * Avant ce changement, le clic du client créait la campagne immédiatement,
 * alors qu'aucun BC n'était forcément arrivé.
 *
 * 2026-11-19 — Précisions validées :
 *   - c'est le BC reçu qui CONFIRME la réservation (bloque les panneaux) ;
 *   - devis accepté → réservation en option, BC attendu (même délai) ;
 *   - « Ferme » dans Disponibilités = BC déjà reçu : il est enregistré à la
 *     création ;
 *   - le BC est toujours reçu par le commercial du dossier (bc_recu_par) ;
 *     qui l'a saisi dans Panora est gardé à part (bc_saisi_par) ;
 *   - un panneau en option peut être retenu par un autre dossier : le
 *     premier BC reçu l'emporte, les autres options tombent.
 */
return [

    // Durée de l'option en attente de bon de commande, en jours.
    'delai_jours' => (int) env('BC_DELAI_JOURS', 30),

    // Relances automatiques : nombre de jours RESTANTS avant l'échéance.
    // Destinataires : le commercial du dossier, le MP en copie.
    // Doit rester trié du plus lointain au plus proche.
    'relances_jours_restants' => [15, 7, 2],

    // Colonne qui mémorise chaque relance déjà partie, pour ne jamais
    // envoyer deux fois la même (clé = jours restants).
    'colonnes_relance' => [
        15 => 'bc_relance_j15_at',
        7  => 'bc_relance_j7_at',
        2  => 'bc_relance_j2_at',
    ],

];
