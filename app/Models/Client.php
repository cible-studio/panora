<?php

namespace App\Models;

// ⚠️ IMPORTANT : remplacer "use Illuminate\Database\Eloquent\Model;"
// par "use Illuminate\Foundation\Auth\User as Authenticatable;"
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Client extends Authenticatable  // ← était "extends Model"
{
    use HasFactory, SoftDeletes, Notifiable;

    // Guard explicite — évite toute confusion avec le guard 'web' des admins
    protected $guard = 'client';

    protected $fillable = [
        // ── Champs existants (à conserver tels quels) ──
        'name',
        'email',
        'phone',
        'address',
        'city',
        'company',
        'siret',
        'contact_name',
        'notes',
        'ncc',
        'sector',
        'user_id',
        // Ajoute ici tout autre champ déjà présent dans ta table

        // ── Nouveaux champs auth ──
        'password',
        'must_change_password',
        'password_changed_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password_changed_at'  => 'datetime',
        'last_login_at'        => 'datetime',
        'must_change_password' => 'boolean',
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
    // public static function generateNcc(): string
    // {
    //     $year    = now()->year;
    //     $prefix  = "CLT-{$year}-";
    //     $last    = static::withTrashed()
    //         ->where('ncc', 'like', "{$prefix}%")
    //         ->orderByDesc('ncc')
    //         ->value('ncc');

    //     $next = $last
    //         ? (int)substr($last, strlen($prefix)) + 1
    //         : 1;

    //     return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    // }




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

    /**
     * Le client doit-il changer son mot de passe à la prochaine connexion ?
     */
    public function mustChangePassword(): bool
    {
        return (bool) $this->must_change_password;
    }

    /**
     * A-t-il un compte activé (password défini) ?
     */
    public function hasAccount(): bool
    {
        return !empty($this->password);
    }
}