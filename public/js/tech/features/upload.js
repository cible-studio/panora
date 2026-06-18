// public/js/tech/features/upload.js — Phase 3 SM1 (STUB Lot G).
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
// Blocs sources à migrer (cf. tech-space.blade.php post-lot-F) :
//   - Compression image côté client (canvas) — bloc 1 (~lignes 317-351)
//   - Géolocalisation pour EXIF (best-effort) — bloc 1 (~lignes 352-367)
//   - Modale "justifier la pige malgré signalement" — bloc 1 (~lignes 407-477)
//   - Upload photo + auto-completion — bloc 1 (~lignes 513-728)
//   - Aperçu photo avant upload — bloc 1 (~lignes 514-562)
//   - Background Sync queue offline (IndexedDB) — bloc 2 (~lignes 1565-1699)
//
// Plan SM1.5 (futur tour, briefing à demander) :
//   1. Compresser sections en fonctions exportées
//   2. Réutiliser postJson() depuis core/api.js
//   3. Brancher init() depuis tech-app.js
//   4. Supprimer les blocs inline correspondants
//   5. Validation snapshot pixel + checklist 90 items

import { postJson, urlForTask } from '../core/api.js';

export function init() {
    // No-op : le code inline historique gère encore upload/compression/queue.
    // Décommenter et implémenter dans SM1.5.
}
