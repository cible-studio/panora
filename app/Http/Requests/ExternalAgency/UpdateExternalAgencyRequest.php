<?php
namespace App\Http\Requests\ExternalAgency;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExternalAgencyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'             => 'required|string|max:150',
            'contact'          => 'nullable|string|max:150',
            'email'            => 'nullable|email|max:150',
            'phone'            => 'nullable|string|max:25',
            'address'          => 'nullable|string',
            'city'             => 'nullable|string|max:100',
            'is_active'        => 'nullable|boolean',
            'notes'            => 'nullable|string|max:2000',
            'manager_name'     => 'nullable|string|max:200',
            'commercial_name'  => 'nullable|string|max:200',
            'commercial_email' => 'nullable|email|max:200',
            'commercial_phone' => 'nullable|string|max:25',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'Le nom de la régie est obligatoire.',
            'email.email'            => 'Adresse email invalide.',
            'commercial_email.email' => 'Email commercial invalide.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => (bool) $this->is_active]);
        }
    }
}