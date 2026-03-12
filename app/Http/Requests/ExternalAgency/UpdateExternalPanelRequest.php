<?php
namespace App\Http\Requests\ExternalPanel;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExternalPanelRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'commune_id'   => 'required|exists:communes,id',
            'code_panneau' => 'required|string|max:100',
            'designation'  => 'required|string|max:255',
            'type'         => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'commune_id.required'   => 'La commune est obligatoire.',
            'code_panneau.required' => 'Le code panneau est obligatoire.',
            'designation.required'  => 'La désignation est obligatoire.',
        ];
    }
}