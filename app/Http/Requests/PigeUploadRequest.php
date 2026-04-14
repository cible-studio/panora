<?php
// app/Http/Requests/Admin/PigeUploadRequest.php
namespace App\Http\Requests\Admin;

use App\Services\PigeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PigeUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'panel_id'    => ['required', 'integer', 'exists:panels,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'photo'       => [
                'required',
                'file',
                'max:' . PigeService::MAX_FILE_SIZE_BYTES / 1024,
                'mimes:jpeg,jpg,png,webp',
            ],
            'taken_at'    => ['required', 'date', 'before_or_equal:today'],
            'gps_lat'     => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lng'     => ['nullable', 'numeric', 'between:-180,180'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'panel_id.required' => 'Veuillez sélectionner un panneau.',
            'photo.required'    => 'La photo est obligatoire.',
            'photo.max'         => 'La photo ne doit pas dépasser 30 Mo.',
            'photo.mimes'       => 'Format non supporté (JPEG, PNG, WebP).',
            'taken_at.before_or_equal' => 'La date ne peut pas être dans le futur.',
            'gps_lat.between'   => 'Latitude invalide (-90 à 90).',
            'gps_lng.between'   => 'Longitude invalide (-180 à 180).',
        ];
    }
}