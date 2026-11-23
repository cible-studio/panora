<?php

/*
|--------------------------------------------------------------------------
| DEVIS — cachet et signature de la Directrice Commerciale (2026-11-19)
|--------------------------------------------------------------------------
|
| Demande écrite du 19/11 : le cachet signé apparaît automatiquement sur
| CHAQUE devis généré, dans les cadres « Pour CIBLE SARL », quel que soit
| le commercial, sans circuit de validation ni aucune action.
|
| 2026-11-23 — L'image est versionnée avec le code
| (resources/signatures/directrice.png), à la demande de l'utilisateur :
| elle part avec chaque mise en ligne, sans dépôt manuel sur le serveur.
| Dossier NON public (jamais servi par une adresse web) ; l'image est
| intégrée au PDF en base64. Fichier absent : le cadre reste vide et le
| PDF se génère normalement.
|
*/

return [

    // Chemin du cachet signé, relatif à la racine du projet.
    'cachet_signature' => env('DEVIS_CACHET_SIGNATURE', 'resources/signatures/directrice.png'),

];
