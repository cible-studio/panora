<?php
namespace App\Http\Requests\ExternalAgency;
use Illuminate\Foundation\Http\FormRequest;

class StoreExternalAgencyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:150',
            'contact' => 'nullable|string|max:150',
            'email'   => 'nullable|email|max:150',
            'address' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la régie est obligatoire.',
            'email.email'   => 'Adresse email invalide.',
        ];
    }
}