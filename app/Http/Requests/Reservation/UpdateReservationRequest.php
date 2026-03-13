<?php
namespace App\Http\Requests\Reservation;

use App\Enums\PanelStatus;
use App\Models\Panel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReservationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'client_id'   => 'required|exists:clients,id',
    
            // En modification : start_date peut rester dans le passé
            // (réservation déjà commencée) — on enlève after_or_equal:today
            'start_date'  => 'required|date',
    
            // end_date STRICTEMENT après start_date
            'end_date'    => [
                'required',
                'date',
                'after:start_date',
            ],
    
            'notes'       => 'nullable|string|max:2000',
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
    
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $start = $this->date('start_date');
            $end   = $this->date('end_date');
    
            if ($start && $end) {
                if ($end->lte($start)) {
                    $v->errors()->add(
                        'end_date',
                        'La date de fin doit être strictement après la date de début.'
                    );
                }
    
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
            'client_id.required'  => 'Le client est obligatoire.',
            'start_date.required' => 'La date de début est obligatoire.',
            'end_date.required'   => 'La date de fin est obligatoire.',
            'end_date.after'      => 'La date de fin doit être strictement après la date de début.',
            'panel_ids.required'  => 'Sélectionnez au moins un panneau.',
            'panel_ids.max'       => 'Maximum 50 panneaux par réservation.',
            'panel_ids.*.not_in'  => 'Un ou plusieurs panneaux sont en maintenance.',
        ];
    }
}
