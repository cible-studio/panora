<?php
namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'client_id', 'reservation_id',
        'start_date', 'end_date', 'status',
        'total_panels', 'total_amount', 'notes'
    ];

    protected $casts = [
        'status'       => CampaignStatus::class,
        'start_date'   => 'date',
        'end_date'     => 'date',
        'total_amount' => 'decimal:2',
    ];

    // ── RELATIONS ──

    // Une campagne appartient à un client
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // Une campagne vient d'une réservation
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    // Une campagne a plusieurs panneaux internes
    public function panels()
    {
        return $this->belongsToMany(
            Panel::class,
            'campaign_panels'
        )->wherePivot('type', 'interne')
         ->withTimestamps();
    }

    // Une campagne a plusieurs panneaux externes
    public function externalPanels()
    {
        return $this->belongsToMany(
            ExternalPanel::class,
            'campaign_panels',
            'campaign_id',
            'external_panel_id'
        )->wherePivot('type', 'externe');
    }

    // Une campagne a plusieurs piges
    public function piges()
    {
        return $this->hasMany(Pige::class);
    }

    // Une campagne a plusieurs tâches de pose
    public function poseTasks()
    {
        return $this->hasMany(PoseTask::class);
    }

    // Une campagne a une facture
    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
