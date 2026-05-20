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

    /** Numéro de la ligne d'entête détectée (1 par défaut). */
    protected int $headingRow = 1;

    public function setHeadingRow(int $row): self
    {
        $this->headingRow = max(1, $row);
        return $this;
    }

    public function headingRow(): int
    {
        return $this->headingRow;
    }

    public function model(array $row): ?Client
    {
        // Normalisation des entêtes (insensible casse / espaces / accents simples)
        // Cast explicite en string pour gérer les NCC numériques (ex: 64525874558)
        $name       = trim((string) ($row['nom']        ?? $row['name']        ?? ''));
        $email      = strtolower(trim((string) ($row['email']  ?? '')));
        $phone      = trim((string) ($row['telephone']  ?? $row['phone']       ?? $row['tel']    ?? ''));
        $company    = trim((string) ($row['entreprise'] ?? $row['company']     ?? $row['raison'] ?? ''));
        $ncc        = trim((string) ($row['ncc']        ?? $row['numero_ncc']  ?? ''));
        $contact    = trim((string) ($row['contact']    ?? $row['contact_name']?? ''));
        $sector     = trim((string) ($row['secteur']    ?? $row['sector']      ?? ''));
        $address    = trim((string) ($row['adresse']    ?? $row['address']     ?? ''));

        // ── Validation tolérante de l'email ──────────────────────────────
        // Maatwebsite Excel applique les règles `rules()` AVANT model() et
        // skippe la ligne entière si invalid. Pour les imports en masse,
        // c'est trop strict : un client avec "n/a" ou "—" dans la colonne
        // email faisait perdre TOUTE la ligne. Désormais on accepte la
        // ligne avec un email = null si invalide, plutôt que tout
        // perdre. Le format est validé par filter_var (plus permissif que
        // le validator Laravel basé sur Symfony Email).
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info('clients.import.email_ignored', [
                'name'     => $name,
                'bad_email' => $email,
            ]);
            $email = '';
        }

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

        // Règle métier : si l'import fournit une "entreprise" (raison sociale),
        // c'est elle qui devient le `name` du client (ex: SUCAF, BEM…).
        // Le "nom" de la ligne (souvent la personne interlocutrice) devient
        // alors le `contact_name`, sauf si une colonne "contact" explicite
        // est déjà remplie — auquel cas celle-ci prime.
        //
        // Sans "entreprise", on garde l'ancien comportement : `name` = nom
        // de la ligne, `contact_name` = colonne contact.
        $finalName    = $company !== '' ? $company : $name;
        $finalContact = $contact;
        if (
            $finalContact === ''
            && $company !== ''
            && $name !== ''
            && mb_strtoupper($name) !== mb_strtoupper($company)
        ) {
            $finalContact = $name;
        }

        return new Client([
            'name'         => mb_strtoupper($finalName),
            'email'        => $email !== '' ? $email : null,
            'phone'        => $phone !== '' ? $phone : null,
            'ncc'          => $ncc !== '' ? $ncc : null,
            'contact_name' => $finalContact !== '' ? $finalContact : null,
            'sector'       => $sector !== '' ? $sector : null,
            'address'      => $address !== '' ? $address : null,
        ]);
    }

    /** Validation par ligne — TOLÉRANTE (objectif : importer un maximum
     *  de lignes ; les imperfections sont traitées dans model()).
     *  Les contraintes `string` sont retirées car Maatwebsite parse les
     *  cellules numériques (NCC, téléphone) en int/float et la validation
     *  les rejetterait. Tout est cast en (string) dans model(). */
    public function rules(): array
    {
        return [
            'nom'        => 'nullable|max:200',
            'email'      => 'nullable|max:200',
            'telephone'  => 'nullable|max:50',
            'ncc'        => 'nullable|max:80',
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
