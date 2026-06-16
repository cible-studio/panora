<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * Convertit un diff brut d'audit (old_values / new_values laravel-auditing)
 * en libellés humains lisibles dans la page Audit facture.
 *
 * Avant : "Modification facture (updated)" + JSON brut → illisible pour un
 * commercial / comptable. Après : lignes en français du type
 *
 *   « Total TTC est passé de 5 782 000 F à 5 800 000 F »
 *   « Statut est passé de Brouillon à Soldée »
 *   « Client est passé de "Egyptair" à "Air France" »
 *
 * Le diff JSON brut reste accessible via un détail technique replié pour
 * ceux qui veulent inspecter en profondeur.
 */
class InvoiceDiffHumanizer
{
    /** Libellés humains des champs Invoice. */
    private const FIELD_LABELS = [
        'reference'                 => 'Référence',
        'status'                    => 'Statut',
        'client_id'                 => 'Client',
        'campaign_id'               => 'Campagne',
        'commercial_user_id'        => 'Commercial assigné',
        'issued_at'                 => 'Date d\'émission',
        'due_date'                  => 'Date d\'échéance',
        'paid_at'                   => 'Date de règlement',
        'amount'                    => 'Montant HT',
        'amount_ht'                 => 'Total HT',
        'amount_tva'                => 'TVA',
        'amount_ttc'                => 'Total TTC',
        'tva'                       => 'Taux TVA',
        'total_a_payer'             => 'Total à payer',
        'autres_taxes_total'        => 'Autres taxes',
        'autres_taxes_label'        => 'Libellé autres taxes',
        'notes_client'              => 'Notes client',
        'notes_internes'            => 'Notes internes',
        'fne_type'                  => 'Type FNE',
        'fne_devise'                => 'Devise FNE',
        'fne_taux_change'           => 'Taux de change FNE',
        'created_by'                => 'Créé par',
        'is_locked'                 => 'Verrouillée',
        'locked_at'                 => 'Date de verrouillage',
        'locked_by'                 => 'Verrouillée par',
    ];

    /** Champs à masquer (techniques, pas d'intérêt humain). */
    private const HIDDEN_FIELDS = [
        'updated_at', 'created_at', 'deleted_at',
    ];

    /** Champs monétaires (formatage en FCFA). */
    private const MONEY_FIELDS = [
        'amount', 'amount_ht', 'amount_tva', 'amount_ttc',
        'total_a_payer', 'autres_taxes_total', 'montant', 'fne_taux_change',
    ];

    /** Champs date (formatage français). */
    private const DATE_FIELDS = [
        'issued_at', 'due_date', 'paid_at', 'locked_at',
    ];

    /** Champs booléens (oui/non). */
    private const BOOL_FIELDS = [
        'is_locked', 'is_acompte',
    ];

    /**
     * Transforme un couple (old_values, new_values) en lignes humaines.
     *
     * @return array<int, array{field: string, label: string, before: string, after: string}>
     */
    public static function humanize(array $oldValues, array $newValues, string $modelType = 'invoice'): array
    {
        $lines = [];
        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($allKeys as $field) {
            if (in_array($field, self::HIDDEN_FIELDS, true)) {
                continue;
            }

            $oldRaw = $oldValues[$field] ?? null;
            $newRaw = $newValues[$field] ?? null;

            // Skip si pas de vrai changement (cas trim, casting…)
            if ($oldRaw === $newRaw) {
                continue;
            }

            $lines[] = [
                'field'  => $field,
                'label'  => self::FIELD_LABELS[$field] ?? self::prettify($field),
                'before' => self::format($field, $oldRaw, $modelType),
                'after'  => self::format($field, $newRaw, $modelType),
            ];
        }

        return $lines;
    }

    private static function format(string $field, $value, string $modelType): string
    {
        if ($value === null || $value === '') {
            return '— (vide)';
        }

        // Statut facture : passe par le label centralisé.
        if ($field === 'status' && $modelType === 'invoice') {
            return Invoice::statusLabel((string) $value);
        }

        if (in_array($field, self::MONEY_FIELDS, true) && is_numeric($value)) {
            return number_format((float) $value, 0, ',', ' ') . ' FCFA';
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            try {
                return \Carbon\Carbon::parse((string) $value)->format('d/m/Y');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        }

        if (in_array($field, self::BOOL_FIELDS, true)) {
            return ((bool) $value) ? 'Oui' : 'Non';
        }

        // Relations id → on retourne brut (le résolveur via Eloquent
        // serait coûteux ici ; on affiche "ID 42" si la valeur est
        // numérique et finit en _id).
        if (str_ends_with($field, '_id') && is_numeric($value)) {
            return 'ID ' . $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private static function prettify(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }
}
