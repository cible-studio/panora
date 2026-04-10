<?php

namespace App\Models;

use App\Models\ExternalAgency;
use App\Models\Commune;
use App\Models\Zone;
use App\Models\PanelFormat;
use App\Models\PanelCategory;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalPanel extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id', 'client_id', 'campaign_id',
        'commune_id', 'zone_id', 'format_id', 'category_id',
        'code_panneau', 'designation', 'type',
        'quartier', 'adresse', 'axe_routier', 'zone_description',
        'nombre_faces', 'type_support', 'orientation', 'is_lit',
        'monthly_rate', 'daily_traffic',
        'latitude', 'longitude',
    ];

    protected $casts = [
        'is_lit'       => 'boolean',
        'monthly_rate' => 'decimal:2',
        'latitude'     => 'decimal:7',
        'longitude'    => 'decimal:7',
    ];

    public function agency()    { return $this->belongsTo(ExternalAgency::class, 'agency_id'); }
    public function client()    { return $this->belongsTo(\App\Models\Client::class); }
    public function campaign()  { return $this->belongsTo(\App\Models\Campaign::class); }
    public function commune()   { return $this->belongsTo(Commune::class); }
    public function zone()      { return $this->belongsTo(Zone::class); }
    public function format()    { return $this->belongsTo(PanelFormat::class, 'format_id'); }
    public function category()  { return $this->belongsTo(PanelCategory::class, 'category_id'); }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_panels')->withTimestamps();
    }
}
