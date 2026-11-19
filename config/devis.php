<?php

/*
|--------------------------------------------------------------------------
| DEVIS — cachet et signature de la Directrice Commerciale (2026-11-19)
|--------------------------------------------------------------------------
|
| Demande écrite du 19/11 : le cachet signé apparaît automatiquement sur
| CHAQUE devis généré, dans les cadres « Pour CIBLE SARL », sans circuit
| de validation.
|
| Le fichier vit sur le disque privé « local » (storage/app/private) :
| jamais dans public/ ni dans Git (storage/app/private/.gitignore). Il est
| intégré au PDF en base64. Fichier absent : le cadre reste vide, comme
| avant, et le PDF se génère normalement.
|
*/

return [

    // Chemin du cachet signé, relatif au disque « local ».
    'cachet_signature' => env('DEVIS_CACHET_SIGNATURE', 'signatures/directrice.png'),

];
