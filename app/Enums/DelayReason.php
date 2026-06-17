<?php
// app/Enums/DelayReason.php
namespace App\Enums;

/**
 * Motifs de retard / problème terrain — source unique de vérité.
 *
 * Consommé par :
 *   - PoseTaskPublicController       : signalement via lien public tech
 *   - TechSpaceController            : signalement depuis l'espace tech connecté
 *   - Admin\SignalementsController   : liste admin + résolution
 *   - Admin\SlaDelaysController      : page analytique /admin/sla/retards
 *   - Admin\RapportController        : onglet "SLA & Retards" dans Rapports
 *   - Services\DelayReasonsService   : agrégations stats
 *
 * 9 motifs au total, validés métier le 2026-06-17 :
 *   - 4 motifs historiques (panneau_casse, acces_bloque, mauvaise_adresse, autre)
 *   - 5 nouveaux motifs (technicien_absent, materiel_indisponible, meteo,
 *     retard_impression, retard_client)
 *
 * IMPORTANT : modifier l'ordre des cases ne change PAS la persistence
 * (on stocke la string 'panneau_casse' etc dans le payload JSON). Mais
 * l'ordre détermine l'ordre d'affichage dans les UI (radio buttons,
 * select, doughnut Chart.js).
 */
enum DelayReason: string
{
    // ── Motifs historiques (déjà en BDD via SignalementsController::PROBLEM_MAP) ──
    case PANNEAU_CASSE         = 'panneau_casse';
    case ACCES_BLOQUE          = 'acces_bloque';
    case MAUVAISE_ADRESSE      = 'mauvaise_adresse';

    // ── Motifs nouveaux (mission 2026-06-17 — Module 3 SLA enrichi) ──
    case TECHNICIEN_ABSENT     = 'technicien_absent';
    case MATERIEL_INDISPONIBLE = 'materiel_indisponible';
    case METEO                 = 'meteo';
    case RETARD_IMPRESSION     = 'retard_impression';
    case RETARD_CLIENT         = 'retard_client';

    // ── Fallback ──
    case AUTRE                 = 'autre';

    /** Libellé court humain (radio button, badge). */
    public function label(): string
    {
        return match ($this) {
            self::PANNEAU_CASSE         => 'Panneau cassé / abîmé',
            self::ACCES_BLOQUE          => 'Accès bloqué / impossible',
            self::MAUVAISE_ADRESSE      => 'Mauvaise adresse / introuvable',
            self::TECHNICIEN_ABSENT     => 'Technicien absent',
            self::MATERIEL_INDISPONIBLE => 'Matériel indisponible',
            self::METEO                 => 'Conditions météo défavorables',
            self::RETARD_IMPRESSION     => 'Retard impression / fournisseur',
            self::RETARD_CLIENT         => 'Retard validation client',
            self::AUTRE                 => 'Autre problème',
        };
    }

    /** Icône emoji 1 char — utilisée dans les radio buttons + badges. */
    public function icon(): string
    {
        return match ($this) {
            self::PANNEAU_CASSE         => '🔨',
            self::ACCES_BLOQUE          => '⛔',
            self::MAUVAISE_ADRESSE      => '❓',
            self::TECHNICIEN_ABSENT     => '🙅',
            self::MATERIEL_INDISPONIBLE => '📭',
            self::METEO                 => '☔',
            self::RETARD_IMPRESSION     => '🖨',
            self::RETARD_CLIENT         => '⏳',
            self::AUTRE                 => '📝',
        };
    }

    /** Couleur principale (texte / bordure). */
    public function color(): string
    {
        return match ($this) {
            self::PANNEAU_CASSE         => '#dc2626',
            self::ACCES_BLOQUE          => '#ea580c',
            self::MAUVAISE_ADRESSE      => '#d97706',
            self::TECHNICIEN_ABSENT     => '#7c3aed',
            self::MATERIEL_INDISPONIBLE => '#0891b2',
            self::METEO                 => '#0284c7',
            self::RETARD_IMPRESSION     => '#9333ea',
            self::RETARD_CLIENT         => '#ca8a04',
            self::AUTRE                 => '#6b7280',
        };
    }

    /** Couleur fond léger (badge). */
    public function bg(): string
    {
        return match ($this) {
            self::PANNEAU_CASSE         => 'rgba(220,38,38,.10)',
            self::ACCES_BLOQUE          => 'rgba(234,88,12,.10)',
            self::MAUVAISE_ADRESSE      => 'rgba(217,119,6,.10)',
            self::TECHNICIEN_ABSENT     => 'rgba(124,58,237,.10)',
            self::MATERIEL_INDISPONIBLE => 'rgba(8,145,178,.10)',
            self::METEO                 => 'rgba(2,132,199,.10)',
            self::RETARD_IMPRESSION     => 'rgba(147,51,234,.10)',
            self::RETARD_CLIENT         => 'rgba(202,138,4,.10)',
            self::AUTRE                 => 'rgba(107,114,128,.10)',
        };
    }

    /**
     * Mapping → type_panne pour création Maintenance.
     * Aligné sur l'ancien SignalementsController::PROBLEM_MAP + extensions.
     */
    public function panneType(): string
    {
        return match ($this) {
            self::PANNEAU_CASSE => 'mecanique',
            default             => 'autre',
        };
    }

    /** Vérifie qu'une valeur string est un motif valide. */
    public static function isValid(?string $value): bool
    {
        return $value !== null && self::tryFrom($value) !== null;
    }

    /** Récupère un motif depuis une valeur, ou AUTRE si invalide. */
    public static function fromValueOrAutre(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::AUTRE;
    }

    /**
     * Liste pour radio buttons / select — array de {value, label, icon, color}.
     * Ordre d'affichage = ordre des cases ci-dessus.
     */
    public static function toSelectOptions(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'icon'  => $c->icon(),
            'color' => $c->color(),
        ], self::cases());
    }
}
