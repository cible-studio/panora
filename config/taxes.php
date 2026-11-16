<?php

/*
|--------------------------------------------------------------------------
| Suivi des mairies — taxes ODP et TM (2026-11-16)
|--------------------------------------------------------------------------
|
| Lu par App\Services\Taxes\TaxeExerciceService.
|
*/

return [

    // Exercice de reprise (décision écrite du 2026-11-16, option B) : pour
    // cette année, le dû d'une mairie est ce qu'ELLE a réclamé, saisi par
    // les comptables ; le calcul de Panora est affiché pour contrôle. Avant
    // cette année : aucun exercice suivi. Après : le dû est le calcul de
    // Panora, et les arriérés sont le solde de l'année précédente.
    'exercice_reprise' => (int) env('TAXES_EXERCICE_REPRISE', 2026),

    // Alerte de début de trimestre (taxes:notify-due-soon, planifiée chaque
    // jour à 8 h 45) : part le premier jour ouvré de chaque trimestre, aux
    // comptables et aux administrateurs.
    'alerte_trimestre' => (bool) env('TAXES_ALERTE_TRIMESTRE', true),

];
