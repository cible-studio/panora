<?php
namespace App\Http\Requests\ExternalAgency;
use Illuminate\Foundation\Http\FormRequest;

class StoreExternalPanelRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'agency_id'        => 'required|exists:external_agencies,id',
            'commune_id'       => 'required|exists:communes,id',
            'code_panneau'     => 'required|string|max:100',
            'designation'      => 'required|string|max:255',
            'type'             => 'nullable|string|max:100',
            'zone_id'          => 'nullable|exists:zones,id',
            'format_id'        => 'nullable|exists:panel_formats,id',
            'category_id'      => 'nullable|exists:panel_categories,id',
            'quartier'         => 'nullable|string|max:255',
            'adresse'          => 'nullable|string|max:255',
            'axe_routier'      => 'nullable|string|max:255',
            'zone_description' => 'nullable|string',
            'nombre_faces'     => 'nullable|integer|min:1|max:6',
            'type_support'     => 'nullable|string|max:100',
            'orientation'      => 'nullable|string|max:50',
            'is_lit'           => 'nullable|boolean',
            'monthly_rate'     => 'nullable|numeric|min:0',
            'daily_traffic'    => 'nullable|integer|min:0',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
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

    protected function prepareForValidation(): void
    {
        $this->merge(['is_lit' => $this->boolean('is_lit')]);
    }
}
