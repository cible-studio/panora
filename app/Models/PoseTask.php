<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoseTask extends Model
{
    protected $fillable = [
        'panel_id', 'campaign_id',
        'assigned_user_id', 'team_name',
        'scheduled_at', 'done_at', 'status'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'done_at'      => 'datetime',
    ];

    // ── RELATIONS ──

    // Une tâche concerne un panneau
    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }

    // Une tâche est liée à une campagne
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    // Une tâche est assignée à un technicien
    public function technicien()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
