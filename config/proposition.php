<?php

return [
    // Durée avant expiration d'une proposition envoyée (en heures)
    'expire_hours' => env('PROPOSITION_EXPIRE_HOURS', 72),
 
    // Activer les propositions automatiques (feature flag)
    'auto_proposals_enabled' => env('PROPOSITION_AUTO_ENABLED', false),
];