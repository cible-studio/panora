<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrée ou sortie d'un panneau dans une campagne (2026-11-16).
 *
 * Répond à : « cette campagne a démarré avec 10 panneaux et finit avec 6 —
 * lesquels sont partis, quand, par qui, et pourquoi ? »
 *
 * Une ligne par mouvement, jamais modifiée ni supprimée : c'est un
 * journal, pas un état. L'état courant reste dans `campaign_panels`.
 *
 * `reference` est un instantané : un panneau démantelé ou renommé plus
 * tard ne doit pas rendre l'historique illisible.
 *
 * Écrit uniquement par App\Services\Campagnes\MouvementsPanneaux — source
 * unique. Aucun appelant ne crée un mouvement à la main.
 */
class CampaignPanelMouvement extends Model
{
    protected $table = 'campaign_panel_mouvements';

    // ── Sens du mouvement ──
    public const AJOUT   = 'ajout';
    public const RETRAIT = 'retrait';

    /** Sens → [libellé, fond, texte]. */
    public const SENS = [
        self::AJOUT   => ['Ajouté',  '#dcfce7', '#166534'],
        self::RETRAIT => ['Retiré',  '#fee2e2', '#991b1b'],
    ];

    // ── Motifs de retrait ──
    //
    // Liste fermée (plus une note libre) : un motif saisi au clavier
    // s'écrit de dix façons et ne se compte jamais. Avec des codes, on
    // pourra répondre un jour à « combien de panneaux perdus pour telle
    // raison, et chez quels clients ».
    //
    // Les quatre derniers sont posés AUTOMATIQUEMENT par le code : ils
    // n'apparaissent pas dans la liste déroulante du retrait manuel.
    public const MOTIF_CLIENT        = 'demande_client';
    public const MOTIF_DEMANTELE     = 'panneau_demantele';
    public const MOTIF_MAINTENANCE   = 'maintenance';
    public const MOTIF_REMPLACE      = 'remplace';
    public const MOTIF_ERREUR        = 'erreur_saisie';
    public const MOTIF_NEGOCIATION   = 'negociation';
    public const MOTIF_AUTRE         = 'autre';
    // Automatiques
    public const MOTIF_CONFLIT       = 'conflit_reservation';
    public const MOTIF_ANNULATION    = 'campagne_annulee';
    public const MOTIF_CREATION      = 'creation_campagne';
    public const MOTIF_AJOUT_MANUEL  = 'ajout_manuel';

    /** Motif → libellé affiché. */
    public const MOTIFS = [
        self::MOTIF_CLIENT       => 'Demande du client',
        self::MOTIF_DEMANTELE    => 'Panneau démantelé ou indisponible',
        self::MOTIF_MAINTENANCE  => 'Panneau en maintenance',
        self::MOTIF_REMPLACE     => 'Remplacé par un autre panneau',
        self::MOTIF_ERREUR       => 'Erreur de saisie',
        self::MOTIF_NEGOCIATION  => 'Révision commerciale / négociation',
        self::MOTIF_AUTRE        => 'Autre (préciser)',
        self::MOTIF_CONFLIT      => 'Conflit de réservation résolu',
        self::MOTIF_ANNULATION   => 'Campagne annulée',
        self::MOTIF_CREATION     => 'Panneau initial de la campagne',
        self::MOTIF_AJOUT_MANUEL => 'Ajouté en cours de campagne',
    ];

    /**
     * Motifs proposés au MP lors d'un retrait manuel.
     * Les motifs automatiques en sont exclus : les choisir à la main
     * donnerait une trace fausse.
     */
    public const MOTIFS_RETRAIT_MANUEL = [
        self::MOTIF_CLIENT,
        self::MOTIF_DEMANTELE,
        self::MOTIF_MAINTENANCE,
        self::MOTIF_REMPLACE,
        self::MOTIF_NEGOCIATION,
        self::MOTIF_ERREUR,
        self::MOTIF_AUTRE,
    ];

    // ── Origine du mouvement ──
    public const SOURCE_MANUEL      = 'manuel';
    public const SOURCE_CONFLIT     = 'conflit';
    public const SOURCE_ANNULATION  = 'annulation';
    public const SOURCE_CREATION    = 'creation';
    public const SOURCE_IMPORT      = 'import';

    protected $fillable = [
        'campaign_id', 'panel_id', 'external_panel_id', 'reference',
        'sens', 'motif', 'note', 'source', 'user_id',
    ];

    protected $casts = [
        'campaign_id'       => 'integer',
        'panel_id'          => 'integer',
        'external_panel_id' => 'integer',
        'user_id'           => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    /** withTrashed : un panneau démantelé depuis doit rester cliquable. */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class)->withTrashed();
    }

    public function externalPanel(): BelongsTo
    {
        return $this->belongsTo(ExternalPanel::class, 'external_panel_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Libellé du motif, ou le code brut si un motif inconnu a été posé. */
    public function motifLibelle(): string
    {
        if (!$this->motif) {
            return '—';
        }

        return self::MOTIFS[$this->motif] ?? $this->motif;
    }

    /** « Ajouté » / « Retiré » avec ses couleurs. */
    public function sensAffiche(): array
    {
        return self::SENS[$this->sens] ?? ['—', '#f1f5f9', '#475569'];
    }

    /** Référence lisible : l'instantané d'abord, la fiche actuelle ensuite. */
    public function referenceAffichee(): string
    {
        return $this->reference
            ?: ($this->panel?->reference ?? $this->externalPanel?->code_panneau ?? '—');
    }

    /** Un mouvement automatique n'a pas d'auteur humain. */
    public function auteurAffiche(): string
    {
        return $this->auteur?->name ?? 'Panora (automatique)';
    }
}
