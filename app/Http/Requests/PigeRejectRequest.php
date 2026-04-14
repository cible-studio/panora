<?php
// app/Http/Requests/Admin/PigeRejectRequest.php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PigeRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Le motif de rejet est obligatoire.',
            'rejection_reason.min'      => 'Le motif doit faire au moins 5 caractères.',
            'rejection_reason.max'      => 'Le motif ne peut pas dépasser 500 caractères.',
        ];
    }
}