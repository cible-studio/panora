<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Service annexe du devis (impression, frais de pose, électricité…).
 * Miroir de InvoiceService.
 *
 * `tva_applicable` (2026-10-30) : chaque service est soumis à la TVA par
 * défaut, mais on peut la retirer sur un service donné — un frais
 * refacturé déjà taxé par le fournisseur, typiquement. Le devis annonce
 * ainsi le même montant que la facture qui en découlera.
 */
class QuoteService extends Model
{
    protected $fillable = ['quote_id', 'label', 'quantite', 'prix_ht', 'tva_applicable', 'order_index'];

    protected $casts = [
        'quantite'       => 'integer',
        'prix_ht'        => 'integer',
        'tva_applicable' => 'boolean',
        'order_index'    => 'integer',
    ];

    /**
     * Montant HT de la ligne (2026-11-16).
     *
     * `prix_ht` est le prix UNITAIRE depuis l'ajout de la quantité. Les
     * lignes antérieures ont quantite = 1 : leur montant ne change pas.
     * Le `?: 1` couvre une ligne chargée avant la migration.
     */
    public function montantHt(): int
    {
        return (int) ($this->prix_ht * max(1, (int) ($this->quantite ?: 1)));
    }

    /** Montant TTC de ce service : avec ou sans TVA selon le choix. */
    public function montantTtc(float $tauxTva): int
    {
        return self::ttcPour($this->montantHt(), (bool) $this->tva_applicable, $tauxTva);
    }

    /**
     * TTC d'un service de devis — SOURCE UNIQUE (2026-11-19).
     *
     * Appelée par montantTtc() (service enregistré) et par le récapitulatif
     * en direct du formulaire (QuoteController::totaux), qui ne manipule
     * que des tableaux. Le formulaire ne recalcule plus ce montant en
     * JavaScript : il affiche ce que le serveur renvoie.
     *
     * Même règle que le calculateur : TVA ajoutée seulement si le service
     * y est soumis, arrondi au franc.
     */
    public static function ttcPour(float $montantHt, bool $tvaApplicable, float $tauxTva): int
    {
        return (int) round($montantHt * ($tvaApplicable ? 1 + $tauxTva / 100 : 1));
    }

    public function quote(): BelongsTo { return $this->belongsTo(Quote::class); }
}
