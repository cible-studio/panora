<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CampaignPanel extends Model
{
<<<<<<< HEAD
    use HasFactory;

    protected $table = 'campaign_panels';

    protected $fillable = [
        'campaign_id', 'panel_id', 'external_panel_id', 'type',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }

    public function externalPanel()
    {
        return $this->belongsTo(ExternalPanel::class);
    }
}
=======
    //
}
>>>>>>> a1831ad3b4dae4af9cc7002b8d31eda3189f9202
