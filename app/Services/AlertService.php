<?php
// app/Services/AlertService.php

namespace App\Services;

use App\Models\Alert;
use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AlertService — création et gestion des alertes système.
 *
 * Catalogue des 20 motifs supportés (TYPES) avec icon, couleur, niveau par
 * défaut. Le code `type` est la clé canonique : on s'en sert dans toute la
 * stack (filtre, dedup, trigger).
 *
 * Création d'une alerte : ::notify(type, model, ['title', 'message', 'lien'])
 * — la dedup est automatique sur (type + related_*).
 *
 * Bumping : si une alerte non lue identique existe, on rafraîchit son
 * triggered_at au lieu d'en créer une nouvelle (le badge ne s'envole pas).
 */
class AlertService
{
    // ══════════════════════════════════════════════════════════════════
    // CATALOGUE DES 20 MOTIFS
    //
    // Format : 'code' => ['icon', 'niveau', 'color', 'label', 'group']
    //   - icon    : SVG path id ou emoji (utilisé par le composant blade)
    //   - niveau  : 'info' | 'warning' | 'danger' (défaut quand on appelle notify)
    //   - color   : couleur principale (mappe au design system)
    //   - label   : libellé court FR pour les filtres et la liste
    //   - group   : regroupement métier (Réservations, Campagnes, ...)
    //
    // ══════════════════════════════════════════════════════════════════
    public const TYPES = [
        // ── Réservations ──────────────────────────────────────────
        'reservation_nouvelle' => [
            'icon' => '📋', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Nouvelle réservation',
            'group' => 'Réservations',
        ],
        'reservation_confirmee' => [
            'icon' => '✅', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Réservation confirmée',
            'group' => 'Réservations',
        ],
        'reservation_annulee' => [
            'icon' => '❌', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Réservation annulée',
            'group' => 'Réservations',
        ],
        'reservation_expiree' => [
            'icon' => '⏱', 'niveau' => 'warning', 'color' => '#f97316',
            'label' => 'Réservation expirée',
            'group' => 'Réservations',
        ],
        'reservation_en_attente_longue' => [
            'icon' => '⌛', 'niveau' => 'warning', 'color' => '#eab308',
            'label' => 'Réservation en attente > 48h',
            'group' => 'Réservations',
        ],

        // ── Campagnes ─────────────────────────────────────────────
        'campagne_creee' => [
            'icon' => '🎯', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Campagne créée',
            'group' => 'Campagnes',
        ],
        'campagne_modifiee' => [
            'icon' => '✏️', 'niveau' => 'info',    'color' => '#8b5cf6',
            'label' => 'Campagne modifiée',
            'group' => 'Campagnes',
        ],
        'campagne_supprimee' => [
            'icon' => '🗑', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Campagne supprimée',
            'group' => 'Campagnes',
        ],
        'campagne_prolongee' => [
            'icon' => '📅', 'niveau' => 'info',    'color' => '#0ea5e9',
            'label' => 'Campagne prolongée',
            'group' => 'Campagnes',
        ],
        'campagne_statut' => [
            'icon' => '🔄', 'niveau' => 'info',    'color' => '#0ea5e9',
            'label' => 'Statut campagne',
            'group' => 'Campagnes',
        ],
        'campagne_panneau_ajoute' => [
            'icon' => '➕', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Panneau ajouté',
            'group' => 'Campagnes',
        ],
        'campagne_panneau_retire' => [
            'icon' => '➖', 'niveau' => 'warning', 'color' => '#f97316',
            'label' => 'Panneau retiré',
            'group' => 'Campagnes',
        ],
        'campagne_active' => [
            'icon' => '🚀', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Campagne active',
            'group' => 'Campagnes',
        ],
        'campagne_terminee' => [
            'icon' => '🏁', 'niveau' => 'info',    'color' => '#6b7280',
            'label' => 'Campagne terminée',
            'group' => 'Campagnes',
        ],
        'campagne_sans_panneau' => [
            'icon' => '⚠️', 'niveau' => 'warning', 'color' => '#f97316',
            'label' => 'Campagne sans panneau',
            'group' => 'Campagnes',
        ],
        'fin_campagne_j7' => [
            'icon' => '📅', 'niveau' => 'warning', 'color' => '#eab308',
            'label' => 'Fin campagne dans 7 jours',
            'group' => 'Campagnes',
        ],
        'fin_campagne_j3' => [
            'icon' => '⚠️', 'niveau' => 'warning', 'color' => '#f97316',
            'label' => 'Fin campagne dans 3 jours',
            'group' => 'Campagnes',
        ],
        'fin_campagne_j0' => [
            'icon' => '🔥', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Campagne expirée aujourd\'hui',
            'group' => 'Campagnes',
        ],

        // ── Panneaux ──────────────────────────────────────────────
        'panneau_cree' => [
            'icon' => '🪧', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Panneau créé',
            'group' => 'Panneaux',
        ],
        'panneau_modifie' => [
            'icon' => '✏️', 'niveau' => 'info',    'color' => '#8b5cf6',
            'label' => 'Panneau modifié',
            'group' => 'Panneaux',
        ],
        'panneau_supprime' => [
            'icon' => '🗑', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Panneau supprimé',
            'group' => 'Panneaux',
        ],
        'panneau_statut' => [
            'icon' => '🔄', 'niveau' => 'info',    'color' => '#0ea5e9',
            'label' => 'Statut panneau',
            'group' => 'Panneaux',
        ],
        'panneau_libre' => [
            'icon' => '🟢', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Panneau libéré',
            'group' => 'Panneaux',
        ],
        'panneau_occupe' => [
            'icon' => '🔴', 'niveau' => 'info',    'color' => '#ef4444',
            'label' => 'Panneau occupé',
            'group' => 'Panneaux',
        ],
        'panneau_maintenance' => [
            'icon' => '🛠', 'niveau' => 'warning', 'color' => '#6b7280',
            'label' => 'Panneau en maintenance',
            'group' => 'Panneaux',
        ],
        'conflit_reservation' => [
            'icon' => '⚡', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Conflit de réservation',
            'group' => 'Panneaux',
        ],

        // ── Poses ─────────────────────────────────────────────────
        'pose_creee' => [
            'icon' => '🔧', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Pose créée',
            'group' => 'Poses',
        ],
        'pose_modifiee' => [
            'icon' => '✏️', 'niveau' => 'info',    'color' => '#8b5cf6',
            'label' => 'Pose modifiée',
            'group' => 'Poses',
        ],
        'pose_supprimee' => [
            'icon' => '🗑', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Pose supprimée',
            'group' => 'Poses',
        ],
        'pose_planifiee' => [
            'icon' => '📌', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Pose planifiée',
            'group' => 'Poses',
        ],
        'pose_en_cours' => [
            'icon' => '⏳', 'niveau' => 'info',    'color' => '#eab308',
            'label' => 'Pose en cours',
            'group' => 'Poses',
        ],
        'pose_terminee' => [
            'icon' => '✅', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Pose terminée',
            'group' => 'Poses',
        ],
        'avancement_pose' => [
            'icon' => '📊', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Mise à jour avancement',
            'group' => 'Poses',
        ],

        // ── Factures ──────────────────────────────────────────────
        'facture_creee' => [
            'icon' => '🧾', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Facture créée',
            'group' => 'Factures',
        ],
        'facture_envoyee' => [
            'icon' => '📤', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Facture envoyée',
            'group' => 'Factures',
        ],
        'facture_payee' => [
            'icon' => '💵', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Facture payée',
            'group' => 'Factures',
        ],
        'facture_annulee' => [
            'icon' => '🚫', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Facture annulée',
            'group' => 'Factures',
        ],

        // ── Réservations (suite — supprimée + statut) ─────────────
        'reservation_supprimee' => [
            'icon' => '🗑', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Réservation supprimée',
            'group' => 'Réservations',
        ],
        'reservation_statut' => [
            'icon' => '🔄', 'niveau' => 'info',    'color' => '#0ea5e9',
            'label' => 'Statut réservation',
            'group' => 'Réservations',
        ],

        // ── Propositions ──────────────────────────────────────────
        'proposition_prete' => [
            'icon' => '📋', 'niveau' => 'info',    'color' => '#0ea5e9',
            'label' => 'Proposition prête',
            'group' => 'Propositions',
        ],
        'proposition_envoyee' => [
            'icon' => '📤', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Proposition envoyée',
            'group' => 'Propositions',
        ],
        'proposition_acceptee' => [
            'icon' => '✅', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Proposition acceptée',
            'group' => 'Propositions',
        ],
        'proposition_refusee' => [
            'icon' => '❌', 'niveau' => 'warning', 'color' => '#f97316',
            'label' => 'Proposition refusée',
            'group' => 'Propositions',
        ],

        // ── Clients ───────────────────────────────────────────────
        'client_cree' => [
            'icon' => '👥', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Client créé',
            'group' => 'Clients',
        ],
        'client_modifie' => [
            'icon' => '✏️', 'niveau' => 'info',    'color' => '#8b5cf6',
            'label' => 'Client modifié',
            'group' => 'Clients',
        ],
        'client_supprime' => [
            'icon' => '🗑', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Client supprimé',
            'group' => 'Clients',
        ],
        'client_importe' => [
            'icon' => '📥', 'niveau' => 'info',    'color' => '#0ea5e9',
            'label' => 'Import clients',
            'group' => 'Clients',
        ],

        // ── Maintenances ──────────────────────────────────────────
        'maintenance_signalee' => [
            'icon' => '🔧', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Panne signalée',
            'group' => 'Maintenances',
        ],
        'maintenance_modifiee' => [
            'icon' => '✏️', 'niveau' => 'info',    'color' => '#8b5cf6',
            'label' => 'Maintenance modifiée',
            'group' => 'Maintenances',
        ],
        'maintenance_rouverte' => [
            'icon' => '🔄', 'niveau' => 'warning', 'color' => '#f97316',
            'label' => 'Maintenance rouverte',
            'group' => 'Maintenances',
        ],
        'maintenance_resolue' => [
            'icon' => '✅', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Panne résolue',
            'group' => 'Maintenances',
        ],

        // ── Piges ─────────────────────────────────────────────────
        'pige_uploadee' => [
            'icon' => '📸', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Pige uploadée',
            'group' => 'Piges',
        ],
        'pige_verifiee' => [
            'icon' => '✅', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Pige vérifiée',
            'group' => 'Piges',
        ],
        'pige_supprimee' => [
            'icon' => '🗑', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Pige supprimée',
            'group' => 'Piges',
        ],

        // ── Satisfaction ──────────────────────────────────────────
        'satisfaction_recue' => [
            'icon' => '⭐', 'niveau' => 'info',    'color' => '#eab308',
            'label' => 'Avis client reçu',
            'group' => 'Satisfaction',
        ],

        // ── Système ───────────────────────────────────────────────
        'taxe_echeance' => [
            'icon' => '💰', 'niveau' => 'warning', 'color' => '#f97316',
            'label' => 'Échéance taxe communale',
            'group' => 'Système',
        ],

        // ── Recouvrement (échéancier facture) ─────────────────────
        // Émis par le cron finance:check-due-dates (06h30). Cible :
        // admin + commercial via broadcastTypesForRole(). Le palier J-7
        // / J-3 / dépassée est différencié via dedup_extra pour ne pas
        // se bumper l'un l'autre.
        'echeance_proche_j7' => [
            'icon' => '📅', 'niveau' => 'info', 'color' => '#3b82f6',
            'label' => 'Échéance facture dans 7 jours',
            'group' => 'Recouvrement',
        ],
        'echeance_proche_j3' => [
            'icon' => '⚠️', 'niveau' => 'warning', 'color' => '#f97316',
            'label' => 'Échéance facture dans 3 jours',
            'group' => 'Recouvrement',
        ],
        'echeance_depassee' => [
            'icon' => '🔴', 'niveau' => 'danger', 'color' => '#ef4444',
            'label' => 'Échéance facture dépassée',
            'group' => 'Recouvrement',
        ],
        'nouveau_client' => [
            'icon' => '👤', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Nouveau client',
            'group' => 'Système',
        ],
        // Message envoyé depuis l'espace client (formulaire « Contacter
        // la régie »). Niveau warning car nécessite une réponse SLA 24h.
        'client_message' => [
            'icon' => '✉️', 'niveau' => 'warning', 'color' => '#f59e0b',
            'label' => 'Message client',
            'group' => 'Espace client',
        ],
    ];

    public const DEFAULT_META = [
        'icon'   => '🔔',
        'niveau' => 'info',
        'color'  => '#6b7280',
        'label'  => 'Notification',
        'group'  => 'Autre',
    ];

    // ══════════════════════════════════════════════════════════════════
    // API PUBLIQUE — création
    // ══════════════════════════════════════════════════════════════════

    /**
     * Crée (ou bump) une alerte. Source unique de création.
     *
     * @param  string      $type     Code de TYPES (ex: 'reservation_confirmee')
     * @param  string      $title    Libellé court
     * @param  string      $message  Message détaillé
     * @param  Model|null  $related  Modèle lié (Reservation, Campaign, …)
     * @param  array       $opts     [niveau?, lien?, user_id?, dedup_extra?]
     */
    public static function notify(
        string  $type,
        string  $title,
        string  $message,
        ?Model  $related = null,
        array   $opts = []
    ): ?Alert {
        $meta   = self::TYPES[$type] ?? self::DEFAULT_META;
        $niveau = $opts['niveau'] ?? $meta['niveau'];

        $relatedType = $related ? class_basename($related) : null;
        $relatedId   = $related?->getKey();

        // dedup_key : par défaut type+model+id, surchargeable via opts['dedup_extra']
        // (utile pour différencier "fin_campagne_j7" de "fin_campagne_j3" sur la même campagne).
        $dedupKey = sprintf(
            '%s:%s:%s:%s',
            $type,
            $relatedType ?? '-',
            $relatedId ?? '-',
            $opts['dedup_extra'] ?? '-'
        );

        try {
            // Bump si une alerte non lue existe déjà avec ce dedup_key
            $existing = Alert::query()
                ->where('dedup_key', $dedupKey)
                ->where('is_read', false)
                ->whereNull('archived_at')
                ->first();

            if ($existing) {
                // Si existing->lien était null (anciennes alertes pré-fix),
                // on profite du bump pour le renseigner via auto-dérivation.
                $newLien = $opts['lien']
                    ?? $existing->lien
                    ?? self::deriveLinkFromRelated($related);
                $existing->forceFill([
                    'title'        => $title,
                    'message'      => $message,
                    'niveau'       => $niveau,
                    'lien'         => $newLien,
                    'triggered_at' => now(),
                ])->save();
                return $existing;
            }

            return Alert::create([
                'type'         => $type,
                'niveau'       => $niveau,
                'title'        => $title,
                'message'      => $message,
                'related_type' => $relatedType,
                'related_id'   => $relatedId,
                'dedup_key'    => $dedupKey,
                'user_id'      => $opts['user_id'] ?? null,
                // Si l'appelant fournit `lien` explicitement, on le garde.
                // Sinon on dérive automatiquement la route show du modèle
                // — ainsi 100% des alertes existantes deviennent cliquables
                // sans avoir à modifier les 30+ controllers appelants.
                'lien'         => $opts['lien'] ?? self::deriveLinkFromRelated($related),
                'is_read'      => false,
                'triggered_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('alert.notify.failed', [
                'type'    => $type,
                'error'   => $e->getMessage(),
                'related' => $relatedType.'#'.$relatedId,
            ]);
            return null;
        }
    }

    /**
     * Dérive automatiquement l'URL de l'écran cible à partir du modèle
     * lié à l'alerte (Reservation → admin.reservations.show, etc.).
     *
     * Permet d'avoir un bouton « Voir le détail » dans la liste des alertes
     * sans devoir patcher les 30+ appels existants à AlertService::create().
     * Si la route n'existe pas ou si le modèle n'est pas mappé, renvoie null
     * (le bouton « Voir » est alors masqué — la liste reste utilisable).
     */
    private static function deriveLinkFromRelated(?Model $related): ?string
    {
        if (!$related) return null;
        $id = $related->getKey();
        if (!$id) return null;

        // Map modèle → fabrique de route. Préfère la route show ;
        // pour les modèles sans show, on retombe sur edit/index parent.
        $factories = [
            'Reservation'   => fn($k) => route('admin.reservations.show',  $k),
            'Campaign'      => fn($k) => route('admin.campaigns.show',     $k),
            'Client'        => fn($k) => route('admin.clients.show',       $k),
            'Maintenance'   => fn($k) => route('admin.maintenances.show',  $k),
            'Panel'         => fn($k) => route('admin.panels.show',        $k),
            'PoseTask'      => fn($k) => route('admin.pose-tasks.show',    $k),
            'Proposition'   => fn($k) => route('admin.propositions.show',  $k),
            'Invoice'       => fn($k) => route('admin.invoices.show',      $k),
            'ClientMessage' => fn($k) => route('admin.messages.show',      $k),
        ];

        $name = class_basename($related);
        if (!isset($factories[$name])) return null;

        try {
            return $factories[$name]($id);
        } catch (\Throwable $e) {
            // Route inconnue (typiquement messages.show pas encore migré sur prod).
            // On log en debug uniquement — ne pas spammer warning, l'auto-dérivation
            // est un bonus, son échec ne doit jamais bloquer la création d'alerte.
            Log::debug('alert.link.derive_failed', [
                'model' => $name, 'id' => $id, 'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Helper rétro-compat : signature ancienne ::create($type, $niveau, …)
     * encore utilisée par 30+ controllers. On mappe (type+niveau) vers un
     * code du catalogue TYPES pour bénéficier de l'icon/couleur uniformes,
     * sinon fallback générique.
     */
    public static function create(
        string $type,
        string $niveau,
        string $title,
        string $message,
        $model = null
    ): ?Alert {
        $code = self::resolveLegacyCode($type, $niveau, $title);

        return self::notify($code, $title, $message, $model, [
            'niveau' => $niveau,
        ]);
    }

    /**
     * Mappe (type historique, niveau, indices dans le titre) vers un code
     * du catalogue TYPES. On détecte d'abord l'**action** dans le titre
     * (créé / modifié / supprimé / ajouté / retiré / activé / terminé / …)
     * puis on retombe sur l'heuristique métier pour les cas restants.
     */
    private static function resolveLegacyCode(string $type, string $niveau, string $title): string
    {
        // Si le type est déjà un code catalogué, on l'utilise tel quel
        if (isset(self::TYPES[$type])) return $type;

        $titleLc = mb_strtolower($title);

        // ── 1. Détection de l'ACTION dans le titre ───────────────
        // Ordre important : "supprim" avant "modifi" (un titre rare pourrait
        // contenir les deux, on privilégie l'action destructive).
        $action = match (true) {
            str_contains($title,   '🗑') || str_contains($titleLc, 'supprim')   => 'supprime',
            str_contains($title,   '➖') || str_contains($titleLc, 'retir')     => 'retire',
            str_contains($title,   '➕') || str_contains($titleLc, 'ajout')     => 'ajoute',
            str_contains($title,   '✏️') || str_contains($titleLc, 'modifi')   => 'modifie',
            str_contains($title,   '📥') || str_contains($titleLc, 'import')    => 'importe',
            str_contains($title,   '📅') || str_contains($titleLc, 'prolong')   => 'prolonge',
            default => null,
        };

        // ── 2. Mapping (module + action) → code catalogue ────────
        return match ($type) {
            'reservation' => match (true) {
                $action === 'supprime'                                     => 'reservation_supprimee',
                str_contains($titleLc, 'proposition') && str_contains($titleLc, 'accept')
                                                                            => 'proposition_acceptee',
                str_contains($titleLc, 'proposition') && str_contains($titleLc, 'refus')
                                                                            => 'proposition_refusee',
                str_contains($titleLc, 'proposition') && (str_contains($titleLc, 'envoyé') || str_contains($titleLc, 'envoyée'))
                                                                            => 'proposition_envoyee',
                str_contains($titleLc, 'proposition')                      => 'proposition_prete',
                str_contains($titleLc, 'confirm')                          => 'reservation_confirmee',
                str_contains($titleLc, 'annul')                            => 'reservation_annulee',
                str_contains($titleLc, 'expir')                            => 'reservation_expiree',
                str_contains($titleLc, 'attente')                          => 'reservation_en_attente_longue',
                $niveau === 'success'                                      => 'reservation_confirmee',
                $niveau === 'danger'                                       => 'reservation_annulee',
                $niveau === 'warning'                                      => 'reservation_en_attente_longue',
                default                                                    => 'reservation_nouvelle',
            },

            'campagne', 'campaign' => match (true) {
                $action === 'supprime'                                     => 'campagne_supprimee',
                $action === 'modifie'                                      => 'campagne_modifiee',
                $action === 'ajoute' && str_contains($titleLc, 'panneau')  => 'campagne_panneau_ajoute',
                $action === 'retire' && str_contains($titleLc, 'panneau')  => 'campagne_panneau_retire',
                $action === 'prolonge'                                     => 'campagne_prolongee',
                str_contains($titleLc, 'sans panneau')                     => 'campagne_sans_panneau',
                str_contains($titleLc, 'expire') || str_contains($titleLc, 'fin')
                    => match (true) {
                        $niveau === 'danger'  => 'fin_campagne_j0',
                        $niveau === 'warning' => 'fin_campagne_j3',
                        default               => 'fin_campagne_j7',
                    },
                str_contains($titleLc, 'termin')                           => 'campagne_terminee',
                str_contains($titleLc, 'activ') || str_contains($titleLc, 'planifi') || $niveau === 'success'
                                                                            => 'campagne_active',
                str_contains($titleLc, '→') || str_contains($titleLc, 'statut')
                                                                            => 'campagne_statut',
                str_contains($titleLc, 'créé')                             => 'campagne_creee',
                default                                                    => 'campagne_statut',
            },

            'panneau', 'panel' => match (true) {
                $action === 'supprime'                                     => 'panneau_supprime',
                $action === 'modifie'                                      => 'panneau_modifie',
                str_contains($titleLc, 'maintenance')                      => 'panneau_maintenance',
                str_contains($titleLc, 'conflit') || $niveau === 'danger'  => 'conflit_reservation',
                str_contains($titleLc, 'statut')                           => 'panneau_statut',
                str_contains($titleLc, 'nouveau') || str_contains($titleLc, 'créé')
                                                                            => 'panneau_cree',
                $niveau === 'success'                                      => 'panneau_libre',
                default                                                    => 'panneau_occupe',
            },

            'maintenance' => match (true) {
                $action === 'modifie'                                      => 'maintenance_modifiee',
                str_contains($titleLc, 'résolu') || str_contains($titleLc, 'resolu')
                                                                            => 'maintenance_resolue',
                str_contains($titleLc, 'rouvert')                          => 'maintenance_rouverte',
                str_contains($titleLc, 'signal') || str_contains($titleLc, 'panne')
                                                                            => 'maintenance_signalee',
                default                                                    => 'panneau_maintenance',
            },

            'pose' => match (true) {
                $action === 'supprime'                                     => 'pose_supprimee',
                $action === 'modifie'                                      => 'pose_modifiee',
                str_contains($titleLc, 'nouvelle') || str_contains($titleLc, 'créé')
                                                                            => 'pose_creee',
                str_contains($titleLc, 'termin') || str_contains($titleLc, 'réalis') || $niveau === 'success'
                                                                            => 'pose_terminee',
                str_contains($titleLc, 'cours') || str_contains($titleLc, 'avance')
                                                                            => 'pose_en_cours',
                str_contains($titleLc, 'retard')                           => 'pose_planifiee',
                default                                                    => 'pose_planifiee',
            },

            'pige' => match (true) {
                $action === 'supprime'                                     => 'pige_supprimee',
                str_contains($titleLc, 'vérifi') || str_contains($titleLc, 'verifi')
                                                                            => 'pige_verifiee',
                str_contains($titleLc, 'rejet')                            => 'avancement_pose',
                str_contains($titleLc, 'upload') || str_contains($titleLc, 'nouvelle')
                                                                            => 'pige_uploadee',
                default                                                    => 'avancement_pose',
            },

            'client' => match (true) {
                $action === 'supprime'                                     => 'client_supprime',
                $action === 'modifie'                                      => 'client_modifie',
                $action === 'importe'                                      => 'client_importe',
                default                                                    => 'client_cree',
            },

            'proposition' => match (true) {
                str_contains($titleLc, 'accept')                           => 'proposition_acceptee',
                str_contains($titleLc, 'refus')                            => 'proposition_refusee',
                str_contains($titleLc, 'prêt') || str_contains($titleLc, 'pret')
                                                                            => 'proposition_prete',
                str_contains($titleLc, 'envoyé') || str_contains($titleLc, 'envoyée')
                                                                            => 'proposition_envoyee',
                default                                                    => 'proposition_prete',
            },

            'satisfaction' => 'satisfaction_recue',

            'taxe' => 'taxe_echeance',

            // Fallback : on garde le type tel quel mais on saura via meta
            // qu'il sort du catalogue.
            default => $type,
        };
    }

    // ══════════════════════════════════════════════════════════════════
    // STATS / READ — lecture optimisée
    // ══════════════════════════════════════════════════════════════════

    /**
     * Liste des TYPES d'alertes "broadcast" (user_id IS NULL, générées par
     * le cron) qu'un rôle non-admin doit voir dans sa cloche/page Alertes.
     *
     * Les alertes ciblées (user_id == uid) sont TOUJOURS visibles à leur
     * destinataire — cette liste règle UNIQUEMENT les alertes système.
     *
     * Pourquoi cette logique :
     *   - Avant : scope = `WHERE user_id = uid` strict → MP ne voyait rien
     *     des alertes "fin campagne J-7", "réservation en attente >48h",
     *     etc., toutes générées avec user_id NULL par le cron.
     *   - Maintenant : MP/Technique voient en plus les broadcasts pertinents
     *     pour leur métier ; commercial reste strict (ses résas seulement).
     */
    public static function broadcastTypesForRole(string $role): array
    {
        return match ($role) {
            // MP : pilotage production → tout ce qui touche planning campagnes,
            // dispo panneaux, retards opérationnels, et messages clients.
            'mediaplanner' => [
                // Campagnes — fin / suivi
                'fin_campagne_j7', 'fin_campagne_j3', 'fin_campagne_j0',
                'campagne_sans_panneau', 'campagne_terminee', 'campagne_active',
                // Réservations — alertes opérationnelles
                'reservation_en_attente_longue', 'reservation_expiree',
                'reservation_nouvelle',
                'proposition_acceptee', 'proposition_refusee',
                // Panneaux — état du parc
                'conflit_reservation', 'panneau_maintenance',
                // Poses — pilotage prod
                'pose_planifiee', 'pose_terminee', 'pose_en_cours',
                // Maintenances — visibilité production
                'maintenance_signalee', 'maintenance_rouverte',
                // Espace client
                'satisfaction_recue', 'client_message',
            ],

            // Commercial : visibilité ECHEANCIER FACTURE (J-7 / J-3 / dépassée).
            // Les alertes sont créées avec user_id == commercial_id par le cron,
            // donc ce broadcast est en réalité couvert par l'autre branche du
            // scope (where user_id = uid). On NE met PAS ces types dans la
            // liste 'commercial' = on évite de leur envoyer les alertes des
            // autres dossiers. Le scope reste strict pour le commercial.
            // → cf. commentaire 'commercial' ci-dessous (default => []).

            // Technique : terrain → poses, maintenances, piges.
            'technique' => [
                'pose_creee', 'pose_planifiee', 'pose_en_cours', 'pose_terminee',
                'maintenance_signalee', 'maintenance_rouverte', 'maintenance_resolue',
                'panneau_maintenance',
                'pige_uploadee', 'pige_verifiee',
                'avancement_pose',
            ],

            // Comptable : vision financière consolidée — toutes les alertes
            // facturation/recouvrement/taxes (broadcasts cron user_id NULL),
            // pas les alertes opérationnelles (pose/pige/maintenance).
            'comptable' => [
                // Échéancier facture (CheckDueDates)
                'echeance_proche_j7', 'echeance_proche_j3', 'echeance_depassee',
                // Recouvrement / relances
                'relance_envoyee', 'facture_en_retard', 'facture_litige',
                // Taxes communales
                'taxe_due_soon', 'taxe_en_retard', 'taxe_payee',
                // Cycle de vie facture
                'facture_emise', 'facture_payee', 'facture_partiellement_payee',
                'facture_annulee', 'facture_litige',
            ],

            // Commercial : reste strict — uniquement ses alertes ciblées.
            // Ses alertes pertinentes (reservation_nouvelle sur sa résa, etc.)
            // doivent être créées avec opts['user_id' => $commercialId] côté
            // controllers métier (ReservationController, etc.).
            default => [],
        };
    }

    /**
     * Restreint une query Alert à l'utilisateur courant.
     *
     *   - Admin       : voit TOUT (alertes ciblées + broadcasts système)
     *   - MP/Technique : voient leurs alertes ciblées + les broadcasts
     *                    pertinents à leur rôle (cf. broadcastTypesForRole)
     *   - Commercial  : strict — uniquement ses alertes ciblées
     */
    protected function scopeCurrentUser($query)
    {
        $user = auth()->user();
        if (!$user) return $query->whereRaw('1 = 0');
        if ($user->role?->value === 'admin') return $query;

        $uid       = (int) $user->id;
        $broadcast = self::broadcastTypesForRole($user->role->value);

        return $query->where(function ($q) use ($uid, $broadcast) {
            $q->where('user_id', $uid);
            if (!empty($broadcast)) {
                $q->orWhere(function ($qq) use ($broadcast) {
                    $qq->whereNull('user_id')->whereIn('type', $broadcast);
                });
            }
        });
    }

    public function unreadCount(): int
    {
        return $this->scopeCurrentUser(Alert::unread())->count();
    }

    /**
     * Résumé par niveau pour le badge cloche (compte les NON LUES).
     * Utilisé uniquement par /api/alerts/count (polling navigation).
     */
    public function unreadSummary(): array
    {
        $row = $this->scopeCurrentUser(Alert::unread())
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN niveau = 'danger'  THEN 1 ELSE 0 END) as danger,
                SUM(CASE WHEN niveau = 'warning' THEN 1 ELSE 0 END) as warning,
                SUM(CASE WHEN niveau = 'info'    THEN 1 ELSE 0 END) as info
            ")->first();

        return [
            'total'   => (int) ($row->total   ?? 0),
            'danger'  => (int) ($row->danger  ?? 0),
            'warning' => (int) ($row->warning ?? 0),
            'info'    => (int) ($row->info    ?? 0),
        ];
    }

    /**
     * Résumé par niveau sur toutes les alertes actives (lues OU non lues,
     * non archivées). C'est cette stat qu'on affiche dans les KPI de la
     * page index — elles restent significatives après le mark-all-as-read
     * automatique à l'ouverture.
     *
     * Accepte des filtres optionnels (type, niveau, non_lues) pour rester
     * cohérent avec la liste affichée juste en dessous.
     */
    public function activeSummary(array $filters = []): array
    {
        $q = $this->scopeCurrentUser(Alert::active());

        if (!empty($filters['type']))   $q->ofType($filters['type']);
        if (!empty($filters['niveau'])) $q->ofNiveau($filters['niveau']);
        if (!empty($filters['non_lues'])) $q->where('is_read', false);

        $row = $q->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN niveau = 'danger'  THEN 1 ELSE 0 END) as danger,
                SUM(CASE WHEN niveau = 'warning' THEN 1 ELSE 0 END) as warning,
                SUM(CASE WHEN niveau = 'info'    THEN 1 ELSE 0 END) as info
            ")->first();

        return [
            'total'   => (int) ($row->total   ?? 0),
            'danger'  => (int) ($row->danger  ?? 0),
            'warning' => (int) ($row->warning ?? 0),
            'info'    => (int) ($row->info    ?? 0),
        ];
    }

    public function latest(int $limit = 8)
    {
        return $this->scopeCurrentUser(Alert::unread())
            ->latest('triggered_at')
            ->limit($limit)
            ->get(['id', 'type', 'niveau', 'title', 'message', 'lien', 'triggered_at']);
    }

    /**
     * Marque toutes les alertes non lues comme lues — atomique.
     * Retourne le nombre de lignes affectées. Scope par utilisateur courant
     * (un commercial ne marque pas comme lues celles des autres).
     */
    public function markAllAsRead(): int
    {
        return $this->scopeCurrentUser(Alert::unread())->update(['is_read' => true]);
    }

    /**
     * Archivage de masse des alertes lues > N jours — pour purge périodique.
     */
    public function archiveOldRead(int $olderThanDays = 30): int
    {
        return Alert::read()
            ->whereNull('archived_at')
            ->where('updated_at', '<', now()->subDays($olderThanDays))
            ->update(['archived_at' => now()]);
    }

    public function getForModel(string $modelClass, int $modelId, int $limit = 5)
    {
        return Alert::unread()
            ->where('related_type', class_basename($modelClass))
            ->where('related_id', $modelId)
            ->orderByDesc('triggered_at')
            ->limit($limit)
            ->get();
    }

    public function countForModule(string $type): int
    {
        return Alert::unread()->ofType($type)->count();
    }

    // ══════════════════════════════════════════════════════════════════
    // GÉNÉRATION BATCH — appelée par artisan alerts:generate (cron)
    // ══════════════════════════════════════════════════════════════════

    public function generateAll(): array
    {
        return [
            'reservations_attente' => $this->triggerReservationsEnAttenteLongue(),
            'maintenances'         => $this->triggerMaintenancesUrgentes(),
            'campagnes_fin'        => $this->triggerFinDeCampagne(),
            'panneaux_maintenance' => $this->triggerPanneauxMaintenancePourLongtemps(),
            'poses_retard'         => $this->triggerPosesEnRetard(),
            'piges_manquantes'     => $this->triggerPosesSansPige(),
        ];
    }

    public function triggerReservationsEnAttenteLongue(): int
    {
        $count = 0;
        $reservations = Reservation::with('client')
            ->where('status', 'en_attente')
            ->where('created_at', '<=', now()->subHours(48))
            ->get();

        foreach ($reservations as $r) {
            $alert = self::notify(
                'reservation_en_attente_longue',
                "Réservation en attente — {$r->client?->name}",
                "La réservation {$r->reference} est en attente de confirmation depuis plus de 48h.",
                $r,
                ['lien' => route('admin.reservations.show', $r)]
            );
            if ($alert) $count++;
        }
        return $count;
    }

    public function triggerMaintenancesUrgentes(): int
    {
        if (!class_exists(\App\Models\Maintenance::class)) return 0;

        $count = 0;
        $maintenances = \App\Models\Maintenance::with('panel')
            ->where('priorite', 'urgente')
            ->where('statut', '!=', 'resolu')
            ->where('created_at', '<=', now()->subHours(24))
            ->get();

        foreach ($maintenances as $m) {
            $alert = self::notify(
                'panneau_maintenance',
                "Maintenance urgente — {$m->panel?->reference}",
                "Panne urgente non résolue : {$m->type_panne}. Panneau {$m->panel?->reference} hors service.",
                $m,
                ['niveau' => 'danger']
            );
            if ($alert) $count++;
        }
        return $count;
    }

    public function triggerFinDeCampagne(): int
    {
        $count = 0;
        $today = now()->startOfDay();
        $campaigns = Campaign::with('client')
            ->where('status', 'actif')
            ->whereBetween('end_date', [$today, $today->copy()->addDays(8)])
            ->get();

        foreach ($campaigns as $c) {
            $jours = (int) $today->diffInDays($c->end_date->copy()->startOfDay());
            $type  = match (true) {
                $jours <= 0 => 'fin_campagne_j0',
                $jours <= 3 => 'fin_campagne_j3',
                $jours <= 7 => 'fin_campagne_j7',
                default     => null,
            };
            if (!$type) continue;

            $alert = self::notify(
                $type,
                "Fin de campagne — {$c->name}",
                $jours <= 0
                    ? "La campagne « {$c->name} » se termine aujourd'hui."
                    : "La campagne « {$c->name} » se termine dans {$jours} jour(s) (le {$c->end_date->format('d/m/Y')}).",
                $c,
                [
                    'lien'        => route('admin.campaigns.show', $c),
                    'dedup_extra' => $type, // dedup différent par seuil j7/j3/j0
                ]
            );
            if ($alert) $count++;
        }
        return $count;
    }

    public function triggerPanneauxMaintenancePourLongtemps(): int
    {
        $count = 0;
        $panels = Panel::where('status', 'maintenance')
            ->where('updated_at', '<=', now()->subDays(7))
            ->get(['id', 'reference', 'name', 'updated_at']);

        foreach ($panels as $p) {
            $jours = (int) $p->updated_at->diffInDays(now());
            $alert = self::notify(
                'panneau_maintenance',
                "Maintenance prolongée — {$p->reference}",
                "Le panneau {$p->reference} ({$p->name}) est en maintenance depuis {$jours} jours.",
                $p,
                [
                    'niveau' => 'warning',
                    'lien'   => route('admin.panels.show', $p),
                ]
            );
            if ($alert) $count++;
        }
        return $count;
    }

    public function triggerPosesEnRetard(): int
    {
        $count = 0;
        $tasks = PoseTask::where('status', 'planifiee')
            ->where('scheduled_at', '<', PoseTask::lateThreshold())
            ->with(['panel:id,reference', 'campaign:id,name'])
            ->get(['id', 'panel_id', 'campaign_id', 'scheduled_at']);

        foreach ($tasks as $t) {
            $ref = $t->panel?->reference ?? "#{$t->panel_id}";
            $alert = self::notify(
                'pose_planifiee',
                "Pose en retard — {$ref}",
                "La pose du panneau {$ref}"
                    . ($t->campaign ? " (campagne « {$t->campaign->name} »)" : '')
                    . " était planifiée le {$t->scheduled_at->format('d/m/Y à H:i')} et n'a pas été réalisée.",
                $t,
                [
                    'niveau' => 'warning',
                    'lien'   => route('admin.pose-tasks.show', $t),
                ]
            );
            if ($alert) $count++;
        }
        return $count;
    }

    public function triggerPosesSansPige(): int
    {
        $count = 0;
        $tasks = PoseTask::where('status', 'realisee')
            ->whereNotNull('campaign_id')
            ->where('done_at', '<', now()->subHours(24))
            ->with(['panel:id,reference', 'campaign:id,name'])
            ->get(['id', 'panel_id', 'campaign_id', 'done_at']);

        foreach ($tasks as $t) {
            $hasPige = Pige::where('panel_id', $t->panel_id)
                ->where('campaign_id', $t->campaign_id)
                ->where('status', '!=', 'rejete')
                ->exists();

            if ($hasPige) continue;

            $ref = $t->panel?->reference ?? "#{$t->panel_id}";
            $alert = self::notify(
                'pose_terminee',
                "Pose sans pige — {$ref}",
                "Le panneau {$ref}"
                    . ($t->campaign ? " (campagne « {$t->campaign->name} »)" : '')
                    . " a été posé le {$t->done_at->format('d/m/Y')} mais aucune pige n'a été enregistrée.",
                $t,
                [
                    'niveau'      => 'warning',
                    'lien'        => route('admin.pose-tasks.show', $t),
                    'dedup_extra' => 'sans_pige_24h',
                ]
            );
            if ($alert) $count++;
        }
        return $count;
    }

    // ══════════════════════════════════════════════════════════════════
    // ALERTES INSTANTANÉES — appelées depuis les controllers / observers
    // ══════════════════════════════════════════════════════════════════

    public function notifyPoseComplete(PoseTask $task, bool $hasPige): void
    {
        if (!$task->campaign_id) return;

        $ref = $task->panel?->reference ?? "#{$task->panel_id}";

        if ($hasPige) {
            self::notify(
                'pose_terminee',
                "Pose réalisée — {$ref}",
                "Le panneau {$ref} a été posé avec succès. Pige photo enregistrée.",
                $task,
                ['lien' => route('admin.pose-tasks.show', $task)]
            );
            return;
        }

        self::notify(
            'pose_terminee',
            "Pose terminée — pige manquante · {$ref}",
            "Panneau {$ref} posé avec succès, mais aucune photo de pige enregistrée. Pensez à uploader la preuve.",
            $task,
            [
                'niveau'      => 'warning',
                'lien'        => route('admin.pose-tasks.show', $task),
                'dedup_extra' => 'complete_sans_pige',
            ]
        );
    }

    public function notifyPigeRejected(Pige $pige, string $reason): void
    {
        $ref = $pige->panel?->reference ?? "#{$pige->panel_id}";

        self::notify(
            'avancement_pose',
            "Pige rejetée — {$ref}",
            "La pige du panneau {$ref} a été rejetée : {$reason}",
            $pige,
            ['niveau' => 'warning']
        );
    }
}
