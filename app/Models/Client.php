<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'ncc',
        'sector',
        'contact_name',
        'email',
        'phone',
        'address',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Liste fixe des secteurs métier ────────────────────────────
    // Source unique de vérité — utilisée partout (validation, vues, filtres)
    public const SECTORS = [
        'Agroalimentaire',
        'Administration & Services Publics',
        'Assurance',
        'Automobile',
        'Banque & Finance',
        'BTP & Immobilier',
        'Commerce & Vente',
        'Eau & Assainissement',
        'Éducation & Formation',
        'Énergie & Pétrole',
        'Grande Distribution',
        'Hôtellerie & Tourisme',
        'Industrie',
        'Médias & Communication',
        'ONG & Institutions',
        'Pharmaceutique & Santé',
        'Restauration',
        'Agence & Conseil',
        'Télécommunications',
        'Transport',
        'Logistique & Manutention',
        'Autre',
    ];

    // ── Normalisation automatique du nom ─────────────────────────
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = mb_strtoupper(trim($value));
    }

    // ── Génération automatique NCC ────────────────────────────────
    // Format : CLT-2026-0001
    public static function generateNcc(): string
    {
        $year    = now()->year;
        $prefix  = "CLT-{$year}-";
        $last    = static::withTrashed()
            ->where('ncc', 'like', "{$prefix}%")
            ->orderByDesc('ncc')
            ->value('ncc');

        $next = $last
            ? (int)substr($last, strlen($prefix)) + 1
            : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // ── Relations ─────────────────────────────────────────────────
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function propositions()
    {
        return $this->hasMany(Proposition::class);
    }

    // ── Scopes ────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name',         'like', "%{$term}%")
              ->orWhere('ncc',          'like', "%{$term}%")
              ->orWhere('email',        'like', "%{$term}%")
              ->orWhere('contact_name', 'like', "%{$term}%")
              ->orWhere('phone',        'like', "%{$term}%");
        });
    }

    // ── Helpers ───────────────────────────────────────────────────
    public function hasActiveCampaigns(): bool
    {
        return $this->campaigns()->where('status', 'actif')->exists();
    }
}