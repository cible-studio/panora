# Snapshot ground truth — CA réel vs CA contractuel

**Bloc 4 — Famille B (CA réel sur Rapports), Commit 15/16.**
**Créé : 2026-06-18.**

## Objectif

Geler une photographie chiffrée AVANT/APRÈS la livraison du Bloc 4 pour
deux scénarios de filtres distincts. Le but :

1. **Garantir la non-régression** des autres KPIs (occupation, panneaux,
   clients, etc.) — ces chiffres ne doivent pas dériver d'un seul franc
   entre AVANT et APRÈS Bloc 4.
2. **Documenter le comportement attendu** du nouveau CA réel face aux
   filtres incompatibles (commune/zone/category) — il ne doit PAS bouger
   quand on active ces filtres, contrairement au CA contractuel qui suit.

Ce document doit être rempli **sur la base de production** (panora-cible.com)
ou sur une copie fidèle. La base locale du repo est vide → exécuter sur prod.

---

## Méthode

### Exécuter le script tinker ci-dessous

```bash
ssh user@panora-cible.com
cd /chemin/vers/panora
php artisan tinker
```

Puis copier-coller :

```php
use App\Services\CaRealService;
use App\Services\FinancialDashboardService;
use App\Models\Campaign;
use Carbon\Carbon;

$from = Carbon::create(2026, 1, 1)->startOfDay();
$to   = Carbon::create(2026, 12, 31)->endOfDay();

$ca   = app(CaRealService::class);
$fin  = app(FinancialDashboardService::class);

// Scénario 1 : "Tout" (pas de filtre commune)
$scn1Real    = $ca->kpis($from, $to);
$scn1Contrat = Campaign::where('start_date', '<=', $to)->where('end_date', '>=', $from)->sum('total_amount');
echo "── Scénario 1 : Période 2026 — pas de filtre commune ──\n";
echo "  CA contractuel (Campaign.total_amount)       : " . number_format($scn1Contrat, 0, ',', ' ') . " FCFA\n";
echo "  CA HT facturé   (CaRealService::ht_facture)  : " . number_format($scn1Real['ht_facture'], 0, ',', ' ') . " FCFA\n";
echo "  Encaissé TTC    (CaRealService::ttc_encaisse): " . number_format($scn1Real['ttc_encaisse'], 0, ',', ' ') . " FCFA\n";
echo "  Taux recouvrement                            : " . $scn1Real['taux_recouvrement'] . " %\n";
echo "  Cohérence Finance (encaisse identique ?)     : " . (abs($fin->kpis($from, $to)['encaisse'] - $scn1Real['ttc_encaisse']) < 0.01 ? '✓ OK' : '✗ DIVERGE') . "\n";

// Scénario 2 : "Cocody"
$cocody = \App\Models\Commune::where('name', 'COCODY')->orWhere('name', 'Cocody')->first();
if ($cocody) {
    $scn2Real = $ca->kpis($from, $to, null, null, ['commune_id' => $cocody->id]);
    $scn2Contrat = Campaign::where('start_date', '<=', $to)->where('end_date', '>=', $from)
        ->whereHas('panels', fn($p) => $p->where('commune_id', $cocody->id))
        ->sum('total_amount');
    echo "\n── Scénario 2 : Période 2026 — filtre commune=Cocody ──\n";
    echo "  CA contractuel (filtré sur Cocody)           : " . number_format($scn2Contrat, 0, ',', ' ') . " FCFA\n";
    echo "  CA HT facturé   (IGNORE commune)             : " . number_format($scn2Real['ht_facture'], 0, ',', ' ') . " FCFA\n";
    echo "  Encaissé TTC    (IGNORE commune)             : " . number_format($scn2Real['ttc_encaisse'], 0, ',', ' ') . " FCFA\n";
    echo "  Filtres ignorés signalés                     : " . implode(', ', $scn2Real['ignored_filters']) . "\n";
    echo "  Attendu : HT et TTC IDENTIQUES au Scénario 1, contrat ≠ Scénario 1\n";
}
```

---

## Résultats observés

> ⚠️ À remplir lors de l'exécution sur la base de production.
> Date d'exécution : `_______________`
> Branche déployée : `main` au commit `_______________`

### Scénario 1 — Période 2026, pas de filtre commune

| KPI | Valeur |
|---|---|
| CA contractuel (Campaign.total_amount) | `_____________ FCFA` |
| CA HT facturé (CaRealService) | `_____________ FCFA` |
| Encaissé TTC (CaRealService) | `_____________ FCFA` |
| Taux de recouvrement | `_____ %` |
| Cohérence Finance (encaisse identique ?) | ☐ OK / ☐ DIVERGE |

### Scénario 2 — Période 2026, filtre commune=Cocody

| KPI | Valeur |
|---|---|
| CA contractuel (filtré sur Cocody) | `_____________ FCFA` |
| CA HT facturé (doit IGNORER commune) | `_____________ FCFA` |
| Encaissé TTC (doit IGNORER commune) | `_____________ FCFA` |
| Filtres ignorés signalés | `commune_id` (attendu) |

### Vérifications

- [ ] **Scénario 1 vs Scénario 2** : `CA HT facturé` est **strictement identique** dans les 2 scénarios (commune n'est pas un filtre légitime sur la facturation).
- [ ] **Scénario 1 vs Scénario 2** : `CA contractuel` est **différent** entre les 2 scénarios (commune scope les campagnes).
- [ ] **Cohérence Finance ↔ Rapports** : `Encaissé TTC` (CaRealService) = `encaisse` (FinancialDashboardService) au franc près.
- [ ] **Bandeau d'info** : sur `/admin/rapports?filter_commune_id={cocody_id}`, le bandeau bleu pâle "ℹ️ Filtres ignorés" apparait au-dessus des 2 KPIs CA réel.
- [ ] **Garde-fou 3** : `tests/Feature/CaRealServiceConsistencyTest.php` passe en CI (skip OK sur sqlite, requiert MySQL pour run réel).

### Non-régression KPIs autres

Vérifier que ces KPIs n'ont pas bougé d'un seul franc entre l'avant-dernier déploiement et la livraison Bloc 4 :

| KPI | Valeur AVANT | Valeur APRÈS | Δ |
|---|---|---|---|
| Taux d'occupation | `____ %` | `____ %` | `_____` |
| Panneaux disponibles | `____` | `____` | `_____` |
| Clients actifs | `____` | `____` | `_____` |
| À décaper (30j) | `____` | `____` | `_____` |
| En maintenance | `____` | `____` | `_____` |

Toute divergence ≠ 0 doit être investigée AVANT de considérer la mission close.

---

## Notes

- Le `CaRealService` n'a **aucune dépendance directe** aux tables `campaigns` /
  `panels` / `communes` : il ne lit que `invoices` + `invoice_payments`. C'est
  pourquoi les filtres commune/zone/category n'ont aucun effet — la chaîne
  facturation ↔ géographie n'est pas modélisée côté invoices.
- Si un besoin futur de CA réel filtré géographiquement émerge, l'option B
  de l'arbitrage Q2 est documentée dans `docs/TECHNICAL_DEBT.md` (join
  `invoices → invoice_lines → campaigns → panels → communes`).
