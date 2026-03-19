<?php
namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'client_id', 'reservation_id',
        'user_id', 'updated_by',
        'start_date', 'end_date', 'status',
        'total_panels', 'total_amount', 'notes',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'total_amount' => 'decimal:2',
        'total_panels' => 'integer',
        'status'       => CampaignStatus::class,
    ];

    // ── Relations ─────────────────────────────────────────────────
    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function panels()
    {
        return $this->belongsToMany(Panel::class, 'campaign_panels')
                    ->withTimestamps();
    }

    public function externalPanels()
    {
        return $this->belongsToMany(ExternalPanel::class, 'campaign_panels')
                    ->withTimestamps();
    }

    public function piges()
    {
        return $this->hasMany(Pige::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // ── Scopes ────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('status', CampaignStatus::ACTIF->value);
    }

    public function scopeEnded($query)
    {
        return $query->where('status', CampaignStatus::TERMINE->value);
    }

    public function scopeNonFacturees($query)
    {
        return $query->whereIn('status', ['actif', 'pose', 'termine'])
                     ->doesntHave('invoices');
    }

    // ── Helpers ───────────────────────────────────────────────────
    public function isActive(): bool
    {
        return $this->status === CampaignStatus::ACTIF;
    }

    public function durationInDays(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date);
    }

    public function durationInMonths(): int
    {
        return max(1, (int) ceil($this->start_date->diffInDays($this->end_date) / 30));
    }

    public function progressPercent(): int
    {
        $total   = $this->start_date->diffInDays($this->end_date);
        $elapsed = min($total, max(0, $this->start_date->diffInDays(now())));
        return $total > 0 ? (int) round($elapsed / $total * 100) : 0;
    }

    public function daysRemaining(): int
    {
        return max(0, (int) now()->diffInDays($this->end_date, false));
    }
}