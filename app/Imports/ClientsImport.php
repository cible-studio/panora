<?php
namespace App\Imports;

use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Throwable;

/**
 * Import Excel/CSV des clients.
 *
 * Format attendu (1ère ligne = entêtes — insensible à la casse) :
 *   nom | email | telephone | entreprise | ncc | contact | secteur | adresse
 *
 * Comportement :
 *   - Ligne ignorée si "nom" vide
 *   - Doublons ignorés silencieusement (même email OU même ncc déjà présents)
 *     → comptés dans $this->skipped
 *   - Erreurs de validation accumulées via SkipsOnError + SkipsErrors
 *
 * Volumétrie : ChunkReading 200 lignes — supporte 100k clients sans saturer la mémoire.
 */
class ClientsImport implements ToModel, WithHeadingRow, WithChunkReading, SkipsOnError, WithValidation
{
    use Importable, SkipsErrors;

    public int $imported = 0;
    public int $skipped  = 0;

    public function model(array $row): ?Client
    {
        // Normalisation des entêtes (insensible casse / espaces / accents simples)
        $name       = trim((string) ($row['nom']        ?? $row['name']        ?? ''));
        $email      = strtolower(trim((string) ($row['email']  ?? '')));
        $phone      = trim((string) ($row['telephone']  ?? $row['phone']       ?? $row['tel']    ?? ''));
        $company    = trim((string) ($row['entreprise'] ?? $row['company']     ?? $row['raison'] ?? ''));
        $ncc        = trim((string) ($row['ncc']        ?? $row['numero_ncc']  ?? ''));
        $contact    = trim((string) ($row['contact']    ?? $row['contact_name']?? ''));
        $sector     = trim((string) ($row['secteur']    ?? $row['sector']      ?? ''));
        $address    = trim((string) ($row['adresse']    ?? $row['address']     ?? ''));

        // Ligne vide ou sans nom → ignorée
        if ($name === '') {
            $this->skipped++;
            return null;
        }

        // Doublon par email (si fourni)
        if ($email !== '' && Client::query()->where('email', $email)->whereNull('deleted_at')->exists()) {
            $this->skipped++;
            return null;
        }

        // Doublon par NCC (si fourni)
        if ($ncc !== '' && Client::query()->where('ncc', $ncc)->whereNull('deleted_at')->exists()) {
            $this->skipped++;
            return null;
        }

        // Auto-NCC si vide (aligné avec StoreClientRequest)
        if ($ncc === '') {
            try { $ncc = Client::generateNcc(); } catch (Throwable $e) { $ncc = null; }
        }

        $this->imported++;

        return new Client([
            'name'         => mb_strtoupper($name),
            'email'        => $email !== '' ? $email : null,
            'phone'        => $phone !== '' ? $phone : null,
            'ncc'          => $ncc !== '' ? $ncc : null,
            'contact_name' => $contact !== '' ? $contact : null,
            'sector'       => $sector !== '' ? $sector : null,
            'address'      => $address !== '' ? $address : null,
            'company'      => $company !== '' ? $company : null,
        ]);
    }

    /** Validation par ligne (Maatwebsite Excel injecte la ligne courante) */
    public function rules(): array
    {
        return [
            'nom'        => 'nullable|string|max:200',
            'email'      => 'nullable|email|max:200',
            'telephone'  => 'nullable|string|max:25',
            'ncc'        => 'nullable|string|max:50',
        ];
    }

    public function chunkSize(): int
    {
        return 200;
    }

    public function onError(Throwable $e): void
    {
        Log::error('clients.import.row_error', [
            'error' => $e->getMessage(),
        ]);
    }
}
