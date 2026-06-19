# SM2b Lot 1.5 — Décision : pas de nouveaux endpoints

## Contexte

Le brief Phase 1 Lot 1.5 demandait de créer :
- `POST /admin/pige/{pige}/validate`
- `POST /admin/pige/{pige}/reject`

avec un payload JSON contenant `reason` (enum `blurry|wrong_panel|gps_too_far|other`)
+ `comment`.

## Audit du code existant (CLAUDE.md règle 1 — harmonisation)

Les endpoints équivalents existent **déjà** dans Panora depuis SM1 :

| Brief | Réel | Statut |
|---|---|---|
| `POST /admin/pige/{pige}/validate` | `POST /admin/piges/{pige}/verify` → `PigeController::verify` | ✅ Fonctionnel, JSON-friendly via `$request->wantsJson()` |
| `POST /admin/pige/{pige}/reject` | `POST /admin/piges/{pige}/reject` → `PigeController::reject` | ✅ Idem, validation `rejection_reason` obligatoire |

Caractéristiques des endpoints existants :
- Acceptent `Accept: application/json` → réponse JSON structurée
- Délégation au `PigeService::verify`/`reject` (audit + status BDD)
- Notifications email automatiques (client + admin)
- Création d'`Alert` via `AlertService` (visibles dashboard)
- Refresh de l'objet pige post-action retourné en réponse

Réponse type :
```json
{
  "success": true,
  "message": "Pige vérifiée avec succès.",
  "data": {
    "id": 42,
    "status": "verifie",
    "status_label": "Vérifiée",
    "status_color": "#22c55e", ...
  }
}
```

## Décision SM2b Lot 1.5

**Aucun nouveau endpoint créé.** Phase 5 (modale validation photo A4) consommera les endpoints existants `admin.piges.verify` et `admin.piges.reject` directement.

## Adaptations Phase 5

- Le frontend (modale A4) enverra le rejet avec `rejection_reason` (texte
  libre) comme actuellement, pas l'enum `blurry|wrong_panel|gps_too_far|other`
  du brief.
- Si la patronne veut le choix rapide à 4 options de la spec A4, on peut
  préfixer le texte côté JS :
  - "blurry" → `rejection_reason = "[Photo floue] " + comment`
  - "wrong_panel" → `rejection_reason = "[Mauvais panneau] " + comment`
  - "gps_too_far" → `rejection_reason = "[GPS trop loin] " + comment`
  - "other" → texte libre
  → conserve la liberté éditoriale + active le filtrage rapide côté admin
  via LIKE sur le préfixe.

## Compatibilité ascendante

- Aucune modification de schéma BDD.
- Aucune modification du `PigeService` ni du `PigeController`.
- Les tests existants (s'il y en a, à vérifier) restent valides.

## Si demande future

Si la patronne veut un enum strict pour les motifs de refus :
1. Ajouter colonne `pige.rejection_kind` ENUM (~10 min)
2. Update `PigeService::reject` pour stocker `kind` séparément du `reason` libre
3. Update `PigeController::reject` validation
4. Migration `down()` pour revert si besoin

Coût total : ~30 min. À demander explicitement.
