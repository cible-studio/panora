# CIBLE CI — Rôles & Permissions (Document validé)
# À utiliser directement dans Claude Code / VS Code

---

## CONTEXTE

Application Laravel CIBLE CI — Régie Publicitaire OOH, Côte d'Ivoire.
Stack : Laravel 10+, Blade, Tailwind, MySQL, Spatie Permissions.

Ce document définit la **refonte des rôles et permissions**.
Implémentation : Policies Laravel + directives Blade `@can()`.
**Aucune migration BDD destructive** — les rôles existants restent valides.

---

## LES 6 RÔLES

### 🔴 ADMIN
Super-utilisateur — accès total lecture + écriture sur toutes les ressources.

**Peut :**
- Gérer les utilisateurs (créer / désactiver / changer les rôles)
- Gérer les paramètres système (communes, formats, zones, tarifs ODP/TM)
- Émettre et marquer les factures comme payées
- Forcer toutes les transitions (annuler proposition, supprimer campagne)
- Valider ou invalider une pige en cas de litige

---

### 🟢 COMMERCIAL
Relation client · Négociation · Envoi des propositions.

**Peut :**
- Voir tous les clients et leurs fiches
- Créer un client + ajouter ses interlocuteurs
- Consulter le catalogue panneaux et les disponibilités
- Recevoir les propositions préparées par le MP (statut "pending_send")
- **Envoyer** la proposition au client par email (signée avec son nom)
- Relancer un client (modifier les rappels automatiques)
- Suivre son propre portefeuille : tableau "mes campagnes"
- Consulter les rapports de son portefeuille client
- Télécharger les piges ZIP de ses campagnes

**Ne peut PAS :**
- ❌ Créer une réservation (c'est le MP qui planifie)
- ❌ Créer ou modifier une campagne
- ❌ Ajouter / retirer des panneaux d'une campagne
- ❌ Accéder au suivi terrain (poses, piges)
- ❌ Voir les utilisateurs ou les paramètres système

---

### 🟣 MEDIA PLANNER (MP)
Planification production · Du devis à la pige.

**Peut :**
- Voir tous les clients (lecture seule — pas d'édition)
- **Gérer l'inventaire panneaux** : créer, modifier, supprimer, photos
- **Créer une réservation** + sélectionner les panneaux (interne / externe)
- **Construire la proposition** : panneaux, tarifs, périodes, option/ferme
- **Marquer la proposition "prête à envoyer"** → bascule dans l'inbox du commercial
- **Créer une campagne** depuis une proposition signée OU manuellement
- Gérer le planning des poses : créer, assigner, replanifier, suivre
- **Valider ou rejeter une pige** photo terrain
- Consulter les rapports : poses, piges, taxes
- Créer un technicien rapidement depuis la section Maintenance
- Modifier le statut panneau (libre / maintenance)

**Ne peut PAS :**
- ❌ Envoyer la proposition au client directement (seul le commercial signe)
- ❌ Annuler une proposition déjà signée par le client (admin only)
- ❌ Modifier les informations d'un client
- ❌ Émettre une facture
- ❌ Gérer les utilisateurs ou les paramètres système

---

### 📊 COMPTABLE
Vision financière consolidée · Saisie encaissements · Recouvrement.

**Peut :**
- Voir **toutes** les factures de la régie (pas de filtre par commercial)
- Saisir les versements / acomptes / soldes manuels (cf. solde manuel
  Mission C — traçable avec justification obligatoire)
- Marquer une facture comme soldée, en litige (13 motifs prédéfinis)
- Générer / modifier l'échéancier de paiement
- Consulter et télécharger les PDF factures + exports Excel/CSV
- Accéder au tableau de bord financier (encaissements, créances,
  balance âgée, recouvrement)
- Saisir les paiements de taxes communales (TM / ODP)
- Voir les rapports comptables et déclarations fiscales
- Voir tous les clients, campagnes et panneaux en lecture
- Créer un client (cas saisie encaissement d'un client manquant)
- Recevoir les alertes finance (échéances J-7/J-3/dépassée, litiges)

**Ne peut PAS :**
- ❌ Créer / modifier / supprimer / annuler une facture (admin only —
  garde-fou comptabilité)
- ❌ Déverrouiller une facture (admin only)
- ❌ Gérer les campagnes / réservations / panneaux
- ❌ Gérer les utilisateurs ou les paramètres système
- ❌ Accéder au suivi terrain (poses, piges, maintenances)

---

### 🟠 TECHNICIEN
Terrain uniquement · Via lien mobile sécurisé WhatsApp.

**Peut :**
- Recevoir un lien WhatsApp pour chaque intervention (`/pose/{token}`)
- Mettre à jour le statut d'une pose : en route → en cours → posé
- Uploader des photos sur place (pige terrain)
- Remplacer une photo qu'il a lui-même prise
- Voir ses interventions du jour (interface mobile)

**Ne peut PAS :**
- ❌ Accéder à l'interface admin (pas de login web)
- ❌ Modifier les informations d'un panneau ou d'une campagne
- ❌ Valider sa propre pige (c'est le MP qui valide)

---

### 🔵 CLIENT
Espace dédié `/client` · Cloisonnement strict.

**Peut :**
- Se connecter à son espace /client
- Voir ses propositions, signer électroniquement ou refuser
- Consulter l'avancement de ses campagnes (panneaux, poses, piges)
- Télécharger ses factures et piges d'affichage
- Gérer plusieurs interlocuteurs sur son compte

**Ne peut PAS :**
- ❌ Voir les données d'autres clients (cloisonnement strict)

---

## MATRICE DE PERMISSIONS COMPLÈTE

Format : ✅ Autorisé | ✗ Refusé | — Sans objet | "siennes" = limité à ses propres données

### TABLEAU DE BORD
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Dashboard principal | ✅ | ✅ portefeuille | ✅ pipeline | ✅ interventions | ✅ campagnes |

### CLIENTS
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Voir liste clients | ✅ | ✅ | ✅ | ✗ | ✗ |
| Voir fiche client | ✅ | ✅ | ✅ | ✗ | sa fiche |
| Créer / modifier client | ✅ | ✅ | ✗ lecture | ✗ | son profil |
| Supprimer client | ✅ | ✗ | ✗ | ✗ | ✗ |

### PANNEAUX
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Voir / filtrer panneaux | ✅ | ✅ | ✅ | ✅ | les siens |
| Créer / modifier / supprimer panneau | ✅ | ✗ | ✅ | ✗ | ✗ |
| Gérer photos panneau | ✅ | ✗ | ✅ | ✗ | ✗ |
| Changer statut (libre/maintenance) | ✅ | ✗ | ✅ | ✗ | ✗ |

### DISPONIBILITÉS
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Recherche disponibilités | ✅ | ✅ | ✅ | ✗ | ✗ |
| Créer une réservation | ✅ | ✗ | ✅ | ✗ | ✗ |

### RÉSERVATIONS
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Voir les réservations | ✅ | ✅ siennes | ✅ toutes | ✗ | les siennes |
| Modifier panneaux réservation | ✅ | ✗ | ✅ | ✗ | ✗ |

### PROPOSITIONS
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Voir les propositions | ✅ | ✅ siennes | ✅ toutes | ✗ | siennes |
| Construire la proposition | ✅ | ✗ | ✅ | ✗ | ✗ |
| Marquer "prête à envoyer" | ✅ | ✗ | ✅ | ✗ | ✗ |
| Envoyer au client (email) | ✅ | ✅ | ✗ | ✗ | ✗ |
| Modifier après envoi | ✅ | ✗ | ✗ | ✗ | ✗ |
| Annuler / supprimer | ✅ | ✗ | ✗ | ✗ | ✗ |
| Signer / refuser | ✗ | ✗ | ✗ | ✗ | ✅ |

### CAMPAGNES
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Voir les campagnes | ✅ | ✅ siennes | ✅ toutes | ✗ | les siennes |
| Créer manuellement | ✅ | ✗ | ✅ | ✗ | ✗ |
| Modifier (panneaux, dates) | ✅ | ✗ | ✅ | ✗ | ✗ |
| Activer / pause / terminer | ✅ | ✗ | ✅ | ✗ | ✗ |
| Annuler campagne | ✅ | ✗ | ✅ | ✗ | ✗ |
| Supprimer campagne | ✅ | ✗ | ✗ | ✗ | ✗ |

### POSES (TÂCHES TERRAIN)
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Voir les poses | ✅ | ✗ | ✅ | les siennes | les siennes |
| Créer batch / assigner technicien | ✅ | ✗ | ✅ | ✗ | ✗ |
| Modifier / replanifier | ✅ | ✗ | ✅ | ✗ | ✗ |
| Changer statut (terrain) | — | — | — | ✅ via lien | — |

### PIGES D'AFFICHAGE
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Voir les piges | ✅ | ✗ | ✅ | les siennes | siennes |
| Uploader photo terrain | ✅ | ✗ | ✅ | ✅ via lien | ✗ |
| Valider / rejeter pige | ✅ | ✗ | ✅ | ✗ | ✗ |
| Télécharger ZIP | ✅ | ✅ siennes | ✅ | ✗ | siennes |

### MAINTENANCE
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Voir les tickets | ✅ | ✗ | ✅ | les siens | ✗ |
| Créer un ticket | ✅ | ✗ | ✅ | ✗ | ✗ |
| Assigner / résoudre | ✅ | ✗ | ✅ | son propre | ✗ |

### FACTURES
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Voir les factures | ✅ | ✅ siennes | ✅ | ✗ | siennes |
| Créer / émettre | ✅ | ✗ | ✗ | ✗ | ✗ |
| Marquer comme payée | ✅ | ✗ | ✗ | ✗ | ✗ |

### TAXES COMMUNALES
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Voir rapports + export | ✅ | ✗ | ✅ | ✗ | ✗ |
| Modifier tarifs commune | ✅ | ✗ | ✗ | ✗ | ✗ |

### ALERTES & RAPPORTS
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Voir les alertes | ✅ | ✅ siennes | ✅ siennes+équipe | ✗ | ✗ |
| Rapports business | ✅ | ✅ portefeuille | ✅ production | ✗ | ✗ |

### UTILISATEURS & PARAMÈTRES
| Action | Admin | Commercial | MP | Technicien | Client |
|--------|:-----:|:----------:|:--:|:----------:|:------:|
| Voir / créer / modifier users | ✅ | ✗ | ✗ | ✗ | ✗ |
| Création rapide technicien | ✅ | ✗ | ✅ | ✗ | ✗ |
| Paramètres système | ✅ | ✗ | ✗ | ✗ | ✗ |

---

## NOUVEAU WORKFLOW PROPOSITION

```
Avant : Commercial crée tout → Commercial envoie → Client signe
Après :
  1. MP      → Crée la réservation + sélectionne les panneaux
  2. MP      → Clique "Soumettre au commercial" (statut = prepared)
  3. Système → Bascule dans l'inbox du Commercial (statut = pending_send)
  4. Commercial → Reçoit, peut : Envoyer | Demander modifs (retour draft) | Rejeter
  5. Commercial → Envoie au client (email signé avec son nom + coordonnées)
  6. Client  → Reçoit, peut : Signer | Refuser (avec motif)
  7. Système → Si signée : Campagne créée automatiquement
  8. MP      → Planifie les poses, assigne les techniciens
  9. Technicien → Reçoit lien WhatsApp, exécute, uploade la pige photo
 10. MP      → Valide la pige
 11. Admin   → Facture le client
```

### Statuts internes de la proposition (à ajouter à l'enum)
```
draft        → En cours de construction par le MP
prepared     → MP a terminé, soumis au commercial
pending_send → En attente d'envoi par le commercial
envoyee      → Envoyée au client (email envoyé)
vue          → Client a ouvert le lien
signee       → Client a accepté → déclenche création campagne auto
refusee      → Client a refusé (avec motif)
expiree      → 7 jours sans réponse → expirée automatiquement
```

---

## IMPLÉMENTATION TECHNIQUE

### Fichiers à créer / modifier

```
app/Policies/
├── CampaignPolicy.php       ← à créer/refondre
├── ReservationPolicy.php    ← à créer/refondre
├── PropositionPolicy.php    ← à créer/refondre
├── PosePolicy.php           ← à créer/refondre
├── PigePolicy.php           ← à créer/refondre
└── MaintenancePolicy.php    ← à créer/refondre
```

### Structure d'une Policy type (exemple CampaignPolicy)

```php
<?php
namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    // Voir les campagnes
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'commercial', 'mediaplanner']);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        if ($user->role === 'admin') return true;
        if ($user->role === 'mediaplanner') return true;
        // Commercial : uniquement ses campagnes (client à lui)
        if ($user->role === 'commercial') {
            return $campaign->reservation?->user_id === $user->id
                || $campaign->client?->commercial_id === $user->id;
        }
        return false;
    }

    // Créer une campagne : Admin + MP uniquement
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'mediaplanner']);
    }

    // Modifier : Admin + MP uniquement
    public function update(User $user, Campaign $campaign): bool
    {
        return in_array($user->role, ['admin', 'mediaplanner']);
    }

    // Annuler : Admin + MP
    public function annuler(User $user, Campaign $campaign): bool
    {
        return in_array($user->role, ['admin', 'mediaplanner']);
    }

    // Supprimer : Admin ONLY
    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->role === 'admin';
    }
}
```

### Structure PropositionPolicy (la plus importante)

```php
<?php
namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class PropositionPolicy
{
    // Construire / modifier les panneaux : MP uniquement
    public function build(User $user, Reservation $reservation): bool
    {
        return in_array($user->role, ['admin', 'mediaplanner']);
    }

    // Marquer "prête à envoyer" : MP uniquement
    public function markReady(User $user, Reservation $reservation): bool
    {
        return in_array($user->role, ['admin', 'mediaplanner']);
    }

    // Envoyer au client : Commercial uniquement (+ Admin)
    public function send(User $user, Reservation $reservation): bool
    {
        return in_array($user->role, ['admin', 'commercial']);
    }

    // Annuler : Admin uniquement
    public function cancel(User $user, Reservation $reservation): bool
    {
        return $user->role === 'admin';
    }
}
```

### Middleware / Middleware route par rôle

```php
// Dans routes/admin.php

// Réservations — MP uniquement pour créer
Route::post('disponibilites/confirmer',
    [ReservationController::class, 'confirmerSelection'])
    ->middleware('role:admin,mediaplanner');  // ← Commercial retiré

// Proposition — envoyer : Commercial uniquement
Route::post('reservations/{reservation}/proposition/envoyer',
    [PropositionController::class, 'envoyerProposition'])
    ->middleware('role:admin,commercial');  // ← MP retiré

// Campagnes — créer/modifier : MP + Admin
Route::resource('campaigns', CampaignController::class)
    ->middleware('role:admin,mediaplanner');

// Utilisateurs : Admin ONLY
Route::resource('users', UserController::class)
    ->middleware('role:admin');
```

### Directives Blade — exemples

```blade
{{-- Bouton créer réservation : MP + Admin uniquement --}}
@can('create', App\Models\Reservation::class)
    <a href="{{ route('admin.reservations.disponibilites') }}" class="btn btn-primary">
        + Nouvelle réservation
    </a>
@endcan

{{-- Bouton envoyer proposition : Commercial + Admin uniquement --}}
@can('send', $reservation)
    <button onclick="openPropositionModal()">📤 Envoyer au client</button>
@endcan

{{-- Bouton construire proposition : MP + Admin uniquement --}}
@can('build', $reservation)
    <a href="{{ route('admin.reservations.edit', $reservation) }}">✏️ Modifier</a>
@endcan

{{-- Supprimer campagne : Admin ONLY --}}
@can('delete', $campaign)
    <form method="POST" action="{{ route('admin.campaigns.destroy', $campaign) }}">
        @csrf @method('DELETE')
        <button class="btn btn-danger">🗑️ Supprimer</button>
    </form>
@endcan
```

---

## NOTES IMPORTANTES POUR CLAUDE CODE

1. Le rôle `commercial` perd les droits : `créer réservation`, `créer campagne`, `modifier campagne`
2. Le rôle `mediaplanner` gagne : `créer/modifier/annuler campagne`, `valider pige`
3. Le rôle `mediaplanner` PERD : `envoyer proposition au client` (bouton caché avec @can)
4. Nouveau statut proposition à ajouter à l'enum : `prepared`, `pending_send`
5. La suppression de campagne reste Admin ONLY
6. Le Technicien n'a PAS de login web — uniquement via token public `/pose/{token}`
7. Utiliser Spatie Laravel Permission pour les vérifications de rôle
8. Enregistrer les Policies dans `app/Providers/AuthServiceProvider.php`

```php
// AuthServiceProvider.php
protected $policies = [
    Campaign::class    => CampaignPolicy::class,
    Reservation::class => ReservationPolicy::class,
    Pige::class        => PigePolicy::class,
    PoseTask::class    => PosePolicy::class,
    Maintenance::class => MaintenancePolicy::class,
];
```
