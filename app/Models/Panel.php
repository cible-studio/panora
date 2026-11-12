<?php

namespace App\Models;

use App\Enums\PanelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Panel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'name',
        'commune_id',
        'zone_id',
        'format_id',
        'category_id',
        'latitude',
        'longitude',
        'gps_source',
        'gps_dispersion_flag',
        'gps_computed_at',
        'status',
        'is_lit',
        'is_vip',
        'monthly_rate',
        'daily_traffic',
        'maintenance_status',
        'zone_description',
        'created_by',

        // Champs Dev A
        'nombre_faces',

        // TX-10 (2026-09-23) — Départ du calcul ODP (cf. booted() plus bas)
        'odp_start_date',
        'type_support',
        'orientation',
        'adresse',
        'quartier',
        'axe_routier',

        // Axes multi-pieds (2026-11-11) — cf. App\Services\Axes\AxeService
        // et docs/AXES_MULTI_PIEDS.md.
        'axe_id',
        'est_axe',
        'pied_numero',
        'faces_par_pied',
    ];

    protected $casts = [
        'is_lit'       => 'boolean',
        'is_vip'       => 'boolean',
        'monthly_rate' => 'decimal:2',
        'latitude'     => 'decimal:7',
        'longitude'    => 'decimal:7',
        'gps_dispersion_flag' => 'boolean',
        'gps_computed_at'     => 'datetime',
        'status'       => PanelStatus::class,
        'odp_start_date'      => 'date',
        'est_axe'             => 'boolean',
        'axe_id'              => 'integer',
        'pied_numero'         => 'integer',
        'faces_par_pied'      => 'integer',
    ];

    /**
     * TX-10 (2026-09-23) — Auto-remplissage de la date de départ ODP.
     *
     * Règle métier validée par la patronne :
     *   - Les panneaux déjà saisis dans Panora (odp_start_date = NULL) sont
     *     des panneaux qui existaient physiquement AVANT l'app. L'ODP leur
     *     est due sur toute la période demandée (cf. TaxCalculationService).
     *   - Tout panneau créé À PARTIR DE MAINTENANT est un nouveau panneau :
     *     son ODP ne court qu'à partir de sa date de création.
     *
     * ⚠ Si un jour on importe un lot de panneaux HISTORIQUES, il faut
     *   passer odp_start_date explicitement (ou la remettre à null après
     *   import), sinon ils hériteront de la date du jour.
     */
    protected static function booted(): void
    {
        static::creating(function (self $panel) {
            if (empty($panel->odp_start_date)) {
                $panel->odp_start_date = now()->toDateString();
            }
        });
    }

    // ───────────── Relations principales ─────────────

    public function photos()
    {
        return $this->hasMany(PanelPhoto::class);
    }

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function format()
    {
        return $this->belongsTo(PanelFormat::class, 'format_id');
    }

    public function category()
    {
        return $this->belongsTo(PanelCategory::class, 'category_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * Maintenance actuellement ouverte (signale OU en_cours) la plus récente.
     * Permet aux vues de récupérer l'état actuel d'un panneau en un eager-load
     * — pas de N+1 ni de calcul applicatif sur chaque ligne.
     */
    public function activeMaintenance()
    {
        return $this->hasOne(Maintenance::class)
            ->whereIn('statut', Maintenance::STATUTS_OUVERTS)
            ->latestOfMany('date_signalement');
    }

    public function poseTasks()
    {
        return $this->hasMany(PoseTask::class);
    }

    // ───────────── Relations commerciales ─────────────

    public function reservations()
    {
        return $this->belongsToMany(Reservation::class, 'reservation_panels')
            ->withPivot(['unit_price', 'total_price'])
            ->withTimestamps();
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_panels')
            ->withTimestamps();
    }

    public function piges()
    {
        return $this->hasMany(Pige::class);
    }

    // ───────────── Axes multi-pieds (2026-11-11) ─────────────
    //
    // Un axe regroupe plusieurs pieds. Le PIED est l'unité de réservation,
    // de disponibilité et de pige ; la ligne de l'AXE garde l'historique
    // mais ne se vend plus. La règle complète vit dans
    // App\Services\Axes\AxeService — ici, uniquement les accès aux données.

    /** L'axe parent, si ce panneau est un pied. */
    public function axe()
    {
        return $this->belongsTo(self::class, 'axe_id');
    }

    /** Les pieds de cet axe, dans l'ordre du terrain. */
    public function pieds()
    {
        return $this->hasMany(self::class, 'axe_id')->orderBy('pied_numero');
    }

    public function estAxe(): bool
    {
        return (bool) $this->est_axe;
    }

    public function estPied(): bool
    {
        return $this->axe_id !== null;
    }

    /**
     * Panneaux vendables : tout sauf les lignes d'axe.
     *
     * À utiliser partout où l'on PROPOSE un panneau — catalogue, PDF des
     * disponibilités, inventaire, cases à cocher d'une réservation. Sans
     * ce filtre, l'axe et ses pieds seraient comptés deux fois.
     */
    public function scopeVendables($query)
    {
        // Tant que la migration n'est pas jouée, tout panneau est vendable :
        // le filtre ne doit pas casser les écrans (cf. AxeService).
        return app(\App\Services\Axes\AxeService::class)->colonnesPresentes()
            ? $query->where('est_axe', false)
            : $query;
    }

    /** Les lignes d'axe seules. */
    public function scopeAxes($query)
    {
        return app(\App\Services\Axes\AxeService::class)->colonnesPresentes()
            ? $query->where('est_axe', true)
            : $query->whereRaw('1 = 0');
    }

    /**
     * Photo de référence (2026-11-12) : la sienne, sinon celle de son axe.
     * Les pieds naissent sans photo — elles restent sur la ligne de l'axe
     * (cf. DecouperAxesEnPieds). Charger `photos` et `axe.photos` avant.
     */
    public function photoDeReference(): ?PanelPhoto
    {
        return $this->photos->first() ?? $this->axe?->photos?->first();
    }

    /**
     * Position terrain (2026-11-12) : la sienne, sinon celle de son axe.
     * Un pied sans position (il l'apprend de sa première pige validée)
     * prend celle de l'axe, avec une tolérance élargie (cf. GeoService).
     * `axe` = la position est celle d'un AXE (tolérance de 500 m validée le
     * 2026-11-12) : celle de l'axe d'un pied, ou celle d'un axe entier
     * (ancien contrat) dont on photographie chaque pied le long de la route.
     *
     * @return array{lat: float, lng: float, axe: bool}|null
     */
    public function positionTerrain(): ?array
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return ['lat' => (float) $this->latitude, 'lng' => (float) $this->longitude, 'axe' => $this->estAxe()];
        }
        $axe = $this->axe_id ? $this->axe : null;
        if ($axe && $axe->latitude !== null && $axe->longitude !== null) {
            return ['lat' => (float) $axe->latitude, 'lng' => (float) $axe->longitude, 'axe' => true];
        }

        return null;
    }

    /**
     * ODP et TM d'un axe : JAMAIS calculées par Panora (règle validée le
     * 2026-11-12). Les comptables les gèrent à la main, autrement que pour
     * les panneaux ordinaires. Vaut pour la ligne d'axe comme pour ses pieds.
     */
    public function taxesSaisiesALaMain(): bool
    {
        return $this->estAxe() || $this->estPied();
    }

    /**
     * Panneaux dont Panora calcule l'ODP et la TM : tout sauf les axes et
     * leurs pieds (cf. taxesSaisiesALaMain). À utiliser dans le module
     * Taxes. Avant la migration des axes : tous les panneaux.
     */
    public function scopeAvecTaxesAutomatiques($query)
    {
        return app(\App\Services\Axes\AxeService::class)->colonnesPresentes()
            ? $query->where('est_axe', false)->whereNull('axe_id')
            : $query;
    }

    // ───────────── Helpers ─────────────

    public function isAvailable(): bool
    {
        return $this->status === PanelStatus::LIBRE;
    }
}
