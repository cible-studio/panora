<?php
namespace App\Http\Requests\ExternalPanel;
use Illuminate\Foundation\Http\FormRequest;

class StoreExternalPanelRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'agency_id'    => 'required|exists:external_agencies,id',
            'commune_id'   => 'required|exists:communes,id',
            'code_panneau' => 'required|string|max:100',
            'designation'  => 'required|string|max:255',
            'type'         => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'agency_id.required'    => 'La régie est obligatoire.',
            'commune_id.required'   => 'La commune est obligatoire.',
            'code_panneau.required' => 'Le code panneau est obligatoire.',
            'designation.required'  => 'La désignation est obligatoire.',
        ];
    }
}