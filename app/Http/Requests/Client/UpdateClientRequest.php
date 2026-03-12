<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:150'],
            'sector'       => ['nullable', 'string', 'max:80'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'max:150', 'unique:clients,email,' . $this->client->id],
            'phone'        => ['nullable', 'string', 'max:20'],
            'address'      => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du client est obligatoire.',
            'email.unique'  => 'Cette adresse email est déjà utilisée par un autre client.',
            'email.email'   => 'L\'adresse email n\'est pas valide.',
        ];
    }
}