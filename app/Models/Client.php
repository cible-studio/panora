<?php

namespace App\Models;

// ⚠️ IMPORTANT : remplacer "use Illuminate\Database\Eloquent\Model;"
// par "use Illuminate\Foundation\Auth\User as Authenticatable;"
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
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
        'regime_imposition',
        'sector',
        'statut_commercial',
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

    public function contacts()
    {
        return $this->hasMany(ClientContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function primaryContact()
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }

    // ── Client ou prospect (2026-10-28) ──────────────────────────
    // Un prospect est une entreprise sans affaire conclue (contacts Gmail).
    // Il passe client à sa première campagne (CampaignObserver).
    public const STATUT_CLIENT   = 'client';
    public const STATUT_PROSPECT = 'prospect';
    public const STATUTS = [self::STATUT_CLIENT => 'Client', self::STATUT_PROSPECT => 'Prospect'];

    public function estProspect(): bool
    {
        return $this->statut_commercial === self::STATUT_PROSPECT;
    }

    public function scopeClients($query)
    {
        return $query->where('statut_commercial', self::STATUT_CLIENT);
    }

    public function scopeProspects($query)
    {
        return $query->where('statut_commercial', self::STATUT_PROSPECT);
    }

    /**
     * L'entreprise devient cliente : elle et tous ses interlocuteurs passent
     * « client » (ils reçoivent donc les disponibilités). Première campagne
     * (CampaignObserver) ou fiche passée en Client à la main (2026-10-28).
     *
     * « Reçoit les mails de campagne » : seul l'interlocuteur de la campagne
     * est coché, les autres cases ne bougent pas (direction, 2026-10-29 :
     * on ne traite pas avec tous les contacts — anciens employés, siège,
     * autres pays…). Passage à la main : aucun interlocuteur, rien de coché.
     */
    public function passerClient(?ClientContact $interlocuteur = null): void
    {
        DB::transaction(function () use ($interlocuteur) {
            $this->update(['statut_commercial' => self::STATUT_CLIENT]);
            // Requête simple : la relation contacts() est triée (UPDATE … ORDER BY refusé par SQLite).
            ClientContact::where('client_id', $this->id)->update(['statut' => self::STATUT_CLIENT]);
            if ($interlocuteur && (int) $interlocuteur->client_id === (int) $this->id) {
                ClientContact::where('id', $interlocuteur->id)->update(['receives_notifications' => true]);
            }
        });
    }

    /**
     * Mails liés à une campagne (renouvellement, fin de campagne, accusé de
     * réception) : chaque interlocuteur dont la case « reçoit les mails »
     * est cochée, avec une adresse valide. Plus de contact principal
     * (règle de la direction, 2026-10-28). À défaut d'interlocuteur coché,
     * l'adresse de la fiche client. Source unique de cette règle.
     *
     * @return list<array{email: string, contact: ?string}>
     */
    public function destinatairesCampagne(): array
    {
        $liste = $this->adressesDe($this->contacts->filter(fn ($ct) => $ct->receives_notifications));

        return $liste ?: $this->adresseFiche();
    }

    /**
     * Mails de suivi d'un dossier (démarrage, rappel J-7, renouvellement,
     * fin de campagne, satisfaction, maintenance) : l'interlocuteur du
     * dossier, à lui seul (règle de la direction, 2026-10-29). Sans
     * interlocuteur utilisable (aucun, supprimé, d'un autre client, sans
     * adresse) : destinatairesCampagne(). Source unique de cette règle.
     *
     * @return list<array{email: string, contact: ?string}>
     */
    public function destinatairesSuivi(?ClientContact $interlocuteur): array
    {
        return $this->adresseInterlocuteur($interlocuteur) ?: $this->destinatairesCampagne();
    }

    /**
     * Documents (factures et leurs relances, devis, propositions, piges,
     * mails de réservation) : l'adresse de la fiche ET l'interlocuteur du
     * dossier, sans doublon (2026-10-29). Vide si ni l'un ni l'autre.
     * Source unique de cette règle.
     *
     * @return list<array{email: string, contact: ?string}>
     */
    public function destinatairesDocuments(?ClientContact $interlocuteur): array
    {
        $liste = [];
        foreach (array_merge($this->adresseFiche(), $this->adresseInterlocuteur($interlocuteur)) as $d) {
            $liste[$d['email']] ??= $d;
        }

        return array_values($liste);
    }

    /**
     * L'interlocuteur d'un dossier s'il est utilisable : de ce client, non
     * supprimé, avec une adresse valide. Sinon null (règle par défaut).
     */
    public function interlocuteurRetenu(?ClientContact $interlocuteur): ?ClientContact
    {
        if (!$interlocuteur || $interlocuteur->trashed() || (int) $interlocuteur->client_id !== (int) $this->id) {
            return null;
        }

        return $this->adressesDe([$interlocuteur]) ? $interlocuteur : null;
    }

    /** @return list<array{email: string, contact: ?string}> l'interlocuteur s'il est retenu */
    private function adresseInterlocuteur(?ClientContact $interlocuteur): array
    {
        $retenu = $this->interlocuteurRetenu($interlocuteur);

        return $retenu ? $this->adressesDe([$retenu]) : [];
    }

    /**
     * Diffusion des disponibilités : chaque interlocuteur au statut client,
     * avec une adresse valide. Un prospect ne la reçoit pas (pas de
     * consentement : il passera par une liste Brevo séparée). À défaut
     * d'interlocuteur client, l'adresse de la fiche. Source unique.
     *
     * @return list<array{email: string, contact: ?string}>
     */
    public function destinatairesDiffusion(): array
    {
        if ($this->estProspect()) {
            return [];
        }
        $liste = $this->adressesDe($this->contacts->filter(fn ($ct) => ($ct->statut ?? self::STATUT_CLIENT) === self::STATUT_CLIENT));

        return $liste ?: $this->adresseFiche();
    }

    /** @return list<array{email: string, contact: ?string}> */
    private function adressesDe(iterable $contacts): array
    {
        $vus = [];
        foreach ($contacts as $ct) {
            $email = mb_strtolower(trim((string) $ct->email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && !isset($vus[$email])) {
                $vus[$email] = ['email' => $email, 'contact' => $ct->name];
            }
        }

        return array_values($vus);
    }

    /** @return list<array{email: string, contact: ?string}> */
    private function adresseFiche(): array
    {
        $email = mb_strtolower(trim((string) $this->email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? [['email' => $email, 'contact' => $this->contact_name]] : [];
    }

    /**
     * Ancienne règle : UNE seule adresse par client (le principal d'abord,
     * puis la fiche). Remplacée le 2026-10-28 par destinatairesCampagne()
     * et destinatairesDiffusion() ; conservée pour l'existant.
     *
     * @return array{email: string, contact: ?string}  email vide si aucune
     */
    public function destinataireCommercial(): array
    {
        $contact = $this->contacts->first(fn($ct) => filter_var($ct->email, FILTER_VALIDATE_EMAIL));

        return [
            'email'   => mb_strtolower(trim((string) ($contact?->email ?: $this->email))),
            'contact' => $contact?->name ?: $this->contact_name,
        ];
    }

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
        'Régie publicitaire',      // 2026-10-28 : régies concurrentes ou partenaires (contacts Gmail)
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
        $year   = now()->year;
        $prefix = "CLT-{$year}-";
        $last   = static::withTrashed()
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

    /**
     * Utilisateurs de l'espace client rattachés à ce client.
     * Rôles : owner (gère l'équipe + accepte propositions) / member (lecture).
     */
    public function users()
    {
        return $this->hasMany(ClientUser::class);
    }

    /**
     * Messages envoyés via le formulaire « Contacter la régie ».
     */
    public function messages()
    {
        return $this->hasMany(ClientMessage::class);
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