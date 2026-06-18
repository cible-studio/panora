// public/js/tech/features/report.js — Phase 3 SM1 (STUB Lot G).
//
// ⚠ EXTRACTION INCOMPLÈTE — laissée intentionnellement en stand-by pour
// rester dans le scope raisonnable de la SM1 livrable.
//
// Statut Phase 3 SM1 :
//   - Structure du module créée (init() exporté, prêt à recevoir le code)
//   - Code source NON encore migré depuis le <script> inline
//   - Le <script> inline historique de tech-space.blade.php OPÈRE TOUJOURS
//   - Aucun double-binding possible (init() est un no-op dans cette version)
//
// Bloc source à migrer (cf. tech-space.blade.php post-lot-F) :
//   - Signaler un problème (1 tap) — bloc 1 (~lignes 763-885)
//     Ouverture modale #ts-report-modal au tap "⚠️ Souci" sur une card,
//     sélection motif (9 DelayReason), photo optionnelle, POST vers
//     /tech/{token}/poses/{taskId}/report, bandeau "déjà signalé" sans reload.
//
// Note d'architecture pour SM1.5 :
//   - Importer { postJson, urlForTask } depuis '../core/api.js'
//     (URL via window.TECH_CONFIG.routes.reportTpl)
//   - Labels motifs déjà disponibles via window.TECH_CONFIG.motifLabels
//   - IDs DOM critiques préservés dans _modal_report.blade.php (Phase 2)

import { postJson, urlForTask } from '../core/api.js';

export function init() {
    // No-op : le code inline historique gère encore le signalement.
}
