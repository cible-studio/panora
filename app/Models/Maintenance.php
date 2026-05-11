<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Maintenance extends Model
{
    use HasFactory;

    public const STATUTS_OUVERTS = ['signale', 'en_cours'];
    public const STATUTS_FERMES  = ['resolu', 'annule'];

    protected $fillable = [
        'panel_id', 'parent_maintenance_id',
        'technicien_id', 'signale_par',
        'type_panne', 'priorite', 'statut',
        'date_signalement', 'date_resolution',
        'description', 'solution',
    ];

    protected $casts = [
        'date_signalement' => 'date',
        'date_resolution'  => 'date',
    ];

    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }

    public function technicien()
    {
        return $this->belongsTo(User::class, 'technicien_id');
    }

    public function signaledBy()
    {
        return $this->belongsTo(User::class, 'signale_par');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_maintenance_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_maintenance_id');
    }

    /**
     * Une maintenance verrouillée (résolue ou annulée) ne doit plus être
     * modifiée — pour rouvrir la même panne, on crée une nouvelle ligne
     * via le bouton "Rouvrir" (parent_maintenance_id pointe ici).
     */
    public function isLocked(): bool
    {
        return in_array($this->statut, self::STATUTS_FERMES, true);
    }

    public function isOpen(): bool
    {
        return in_array($this->statut, self::STATUTS_OUVERTS, true);
    }

    public function scopeOuvertes($q)
    {
        return $q->whereIn('statut', self::STATUTS_OUVERTS);
    }

    public function scopeFermees($q)
    {
        return $q->whereIn('statut', self::STATUTS_FERMES);
    }

    /**
     * Maintenance "orpheline" — signalée sans technicien assigné, état
     * qui doit déclencher une alerte côté admin (badge rouge dans la
     * liste, KPI dédié sur le dashboard).
     */
    public function isUnassigned(): bool
    {
        return $this->statut === 'signale' && $this->technicien_id === null;
    }
}
