<?php
namespace App\Http\Requests\Reservation;

use App\Enums\PanelStatus;
use App\Models\Panel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'client_id'   => 'required|exists:clients,id',
    
            // Côté serveur :
            // - start_date : ne peut pas être dans le passé (after_or_equal:today)
            // - max 24 mois à l'avance
            'start_date'  => [
                'required',
                'date',
                'after_or_equal:today',
                'before:' . now()->addMonths(24)->format('Y-m-d'),
            ],
    
            // - end_date STRICTEMENT après start_date (after, pas after_or_equal)
            //   → minimum 1 jour de réservation
            // - max 36 mois depuis start_date (protège contre les erreurs de saisie)
            'end_date'    => [
                'required',
                'date',
                'after:start_date',
            ],
    
            'notes'       => 'nullable|string|max:2000',
            'type'        => 'required|in:option,ferme',
            'panel_ids'   => 'required|array|min:1|max:50',
            'panel_ids.*' => [
                'required',
                'integer',
                'exists:panels,id',
                Rule::notIn(
                    Panel::where('status', PanelStatus::MAINTENANCE->value)
                        ->pluck('id')
                        ->toArray()
                ),
            ],
        ];
    }
 

    // Validation croisée APRÈS les règles individuelles
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $start = $this->date('start_date');
            $end   = $this->date('end_date');
    
            if ($start && $end) {
                // end_date doit être strictement après start_date
                if ($end->lte($start)) {
                    $v->errors()->add(
                        'end_date',
                        'La date de fin doit être strictement après la date de début.'
                    );
                }
    
                // Protection anti-erreur : durée max 36 mois
                if ($start->diffInMonths($end) > 36) {
                    $v->errors()->add(
                        'end_date',
                        'La durée maximale d\'une réservation est de 36 mois.'
                    );
                }
            }
        });
    }
    
    public function messages(): array
    {
        return [
            'client_id.required'        => 'Le client est obligatoire.',
            'client_id.exists'          => 'Client introuvable.',
            'start_date.required'       => 'La date de début est obligatoire.',
            'start_date.after_or_equal' => 'La date de début ne peut pas être dans le passé.',
            'start_date.before'         => 'La date de début ne peut pas dépasser 24 mois à l\'avance.',
            'end_date.required'         => 'La date de fin est obligatoire.',
            'end_date.after'            => 'La date de fin doit être strictement après la date de début.',
            'type.required'             => 'Le type de réservation est obligatoire.',
            'type.in'                   => 'Type invalide : option ou ferme uniquement.',
            'panel_ids.required'        => 'Sélectionnez au moins un panneau.',
            'panel_ids.min'             => 'Sélectionnez au moins un panneau.',
            'panel_ids.max'             => 'Maximum 50 panneaux par réservation.',
            'panel_ids.*.not_in'        => 'Un ou plusieurs panneaux sélectionnés sont en maintenance.',
            'panel_ids.*.exists'        => 'Un panneau sélectionné est introuvable.',
        ];
    }
    
}