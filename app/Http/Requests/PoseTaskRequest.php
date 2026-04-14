<?php
namespace App\Http\Requests\Admin;
 
use Illuminate\Foundation\Http\FormRequest;
 
class PoseTaskRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    // ── Nettoyer les données AVANT la validation ──────────────
    // Supprime les panel_ids vides que le formulaire peut envoyer
    protected function prepareForValidation(): void
    {
        // Filtrer les valeurs vides du tableau panel_ids
        if ($this->has('panel_ids')) {
            $cleaned = array_values(
                array_filter(
                    (array) $this->input('panel_ids', []),
                    fn($v) => $v !== null && $v !== '' && $v !== '0'
                )
            );
            $this->merge(['panel_ids' => $cleaned]);
        }
    }
 
    public function rules(): array
    {
        $rules = [
            'campaign_id'      => ['nullable', 'integer', 'exists:campaigns,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'team_name'        => ['nullable', 'string', 'max:100'],
            'scheduled_at'     => ['required', 'date'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ];
 
        if ($this->isMethod('POST')) {
            $rules['panel_ids']   = ['required', 'array', 'min:1', 'max:100'];
            // ← 'integer' + 'exists' suffisent, PAS 'required' sur les items
            //   car prepareForValidation() a déjà nettoyé les vides
            $rules['panel_ids.*'] = ['integer', 'exists:panels,id'];
            $rules['status']      = ['required', 'in:planifiee,en_cours'];
        }
 
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['panel_id'] = ['required', 'integer', 'exists:panels,id'];
            $rules['status']   = ['required', 'in:planifiee,en_cours,annulee'];
        }
 
        return $rules;
    }
 
    public function messages(): array
    {
        return [
            'panel_ids.required'     => 'Veuillez sélectionner au moins un panneau.',
            'panel_ids.array'        => 'La sélection de panneaux est invalide.',
            'panel_ids.min'          => 'Veuillez sélectionner au moins un panneau.',
            'panel_ids.max'          => 'Vous ne pouvez pas sélectionner plus de 100 panneaux à la fois.',
            'panel_ids.*.integer'    => 'Identifiant de panneau invalide.',
            'panel_ids.*.exists'     => 'Un ou plusieurs panneaux sélectionnés sont introuvables.',
            'panel_id.required'      => 'Le panneau est obligatoire.',
            'panel_id.exists'        => 'Le panneau sélectionné est introuvable.',
            'campaign_id.exists'     => 'La campagne sélectionnée est introuvable.',
            'scheduled_at.required'  => 'La date et heure de pose sont obligatoires.',
            'scheduled_at.date'      => 'La date et heure de pose sont invalides.',
            'assigned_user_id.exists'=> 'Le technicien sélectionné est introuvable.',
            'team_name.max'          => "Le nom d'équipe ne doit pas dépasser 100 caractères.",
            'notes.max'              => 'Les notes ne doivent pas dépasser 1000 caractères.',
            'status.required'        => 'Le statut est obligatoire.',
            'status.in'              => 'Statut invalide.',
        ];
    }
 
    public function attributes(): array
    {
        return [
            'panel_ids'        => 'panneaux',
            'panel_id'         => 'panneau',
            'campaign_id'      => 'campagne',
            'assigned_user_id' => 'technicien',
            'team_name'        => "nom d'équipe",
            'scheduled_at'     => 'date planifiée',
            'status'           => 'statut',
            'notes'            => 'notes',
        ];
    }
}