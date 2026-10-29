<?php

namespace App\Rules;

use App\Support\NomEntreprise;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nom d'une fiche client (2026-10-29) : refusé s'il existe déjà, même écrit
 * autrement ; accepté s'il ressemble à une fiche existante seulement quand
 * l'utilisateur a confirmé « C'est une autre entreprise » (champ
 * nom_proche_confirme). Règle : App\Support\NomEntreprise.
 */
class NomEntrepriseLibre implements ValidationRule
{
    /** Case à cocher qui confirme une entreprise différente d'un nom proche. */
    public const CONFIRMATION = 'nom_proche_confirme';

    /**
     * @param int|null    $sauf         fiche renommée (ne se compare pas à elle-même)
     * @param bool        $confirme     « C'est une autre entreprise » coché
     * @param string|null $nomActuel    nom actuel de la fiche renommée : inchangé (même clé), rien à vérifier
     */
    public function __construct(private ?int $sauf = null, private bool $confirme = false, private ?string $nomActuel = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->nomActuel !== null && NomEntreprise::cle($this->nomActuel) === NomEntreprise::cle((string) $value)) {
            return;
        }

        $v = app(NomEntreprise::class)->verifier((string) $value, $this->sauf);
        if ($v['identique']) {
            $fail("« {$v['identique']['name']} » existe déjà dans Panora : utilisez cette fiche.");
        } elseif ($v['proches'] && !$this->confirme) {
            $fail(NomEntreprise::texteProches($v['proches']) . ' Si c\'est bien une autre entreprise, cochez « C\'est une autre entreprise ».');
        }
    }
}
