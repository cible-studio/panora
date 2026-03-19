<?php
namespace App\Http\Requests\Client;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clientId = $this->route('client')->id;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('clients', 'name')
                    ->ignore($clientId)
                    ->whereNull('deleted_at'),
            ],
            'ncc' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('clients', 'ncc')
                    ->ignore($clientId)
                    ->whereNull('deleted_at'),
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
                Rule::unique('clients', 'email')
                    ->ignore($clientId)
                    ->whereNull('deleted_at'),
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
            'phone.regex'   => 'Le format du téléphone est invalide.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->name) {
            $this->merge(['name' => mb_strtoupper(trim($this->name))]);
        }
        if ($this->email) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
    }
}