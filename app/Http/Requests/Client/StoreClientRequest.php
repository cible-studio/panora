<?php
namespace App\Http\Requests\Client;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                // Unicité insensible à la casse
                Rule::unique('clients', 'name')->whereNull('deleted_at'),
            ],
            'ncc' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('clients', 'ncc')->whereNull('deleted_at'),
            ],
            'sector' => [
                'nullable',
                Rule::in(Client::SECTORS),
            ],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:150',
                Rule::unique('clients', 'email')->whereNull('deleted_at'),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+\d\s\-\(\)\.]{6,20}$/',
            ],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de l\'entreprise est obligatoire.',
            'name.unique'   => 'Un client avec ce nom existe déjà.',
            'ncc.unique'    => 'Ce numéro de compte client est déjà utilisé.',
            'sector.in'     => 'Le secteur sélectionné n\'est pas valide.',
            'email.email'   => 'L\'adresse email n\'est pas valide.',
            'email.unique'  => 'Cette adresse email est déjà utilisée par un autre client.',
            'phone.regex'   => 'Le format du téléphone est invalide (ex: +225 07 00 00 00 00).',
        ];
    }

    // Normalisation avant validation
    protected function prepareForValidation(): void
    {
        if ($this->name) {
            $this->merge(['name' => mb_strtoupper(trim($this->name))]);
        }
        if ($this->email) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
        // Auto-générer NCC si vide
        if (empty($this->ncc)) {
            $this->merge(['ncc' => Client::generateNcc()]);
        }
    }
}