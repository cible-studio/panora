<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

/**
 * Corrige les clients importés avec un name au format "PERSONNE / ENTREPRISE".
 *
 *   Avant : name = "AMÉLIE GOUBO / SUCAF"  · contact_name = NULL
 *   Après : name = "SUCAF"                 · contact_name = "AMÉLIE GOUBO"
 *
 * - Dry-run par défaut → liste les modifs sans toucher la base.
 * - `--apply` pour persister.
 * - On préserve le contact_name déjà rempli (priorité à la valeur existante).
 * - Cas multi-slashes : la PARTIE APRÈS le dernier ` / ` devient le name
 *   (raison sociale), tout ce qui précède devient le contact.
 */
class SplitImportedClientNames extends Command
{
    protected $signature = 'clients:split-import-names {--apply : Persiste les modifs en base (sans cette option, dry-run uniquement)}';

    protected $description = 'Découpe les clients dont name = "PERSONNE / ENTREPRISE" en name=entreprise + contact_name=personne';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $matches = Client::query()
            ->where('name', 'like', '% / %')
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            $this->info('Aucun client à corriger — aucun nom ne contient " / ".');
            return self::SUCCESS;
        }

        $this->line('');
        $this->info(($apply ? 'APPLY' : 'DRY-RUN') . ' — ' . $matches->count() . ' client(s) trouvé(s) à corriger :');
        $this->line('');

        $this->table(
            ['ID', 'Ancien name', '→ Nouveau name', '→ Nouveau contact_name'],
            $matches->map(function (Client $c) {
                [$newName, $newContact] = $this->splitName($c->name);
                $effectiveContact = $c->contact_name ?: $newContact;
                $preservedFlag = $c->contact_name ? ' (existant conservé)' : '';
                return [
                    $c->id,
                    $c->name,
                    $newName,
                    $effectiveContact . $preservedFlag,
                ];
            })->toArray()
        );

        if (!$apply) {
            $this->line('');
            $this->warn('Aucune modification écrite. Relance avec --apply pour persister.');
            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($matches as $client) {
            [$newName, $newContact] = $this->splitName($client->name);

            $payload = ['name' => $newName];
            // On ne remplit contact_name QUE s'il est vide actuellement —
            // pour ne pas écraser un contact saisi manuellement.
            if (empty($client->contact_name) && $newContact !== '') {
                $payload['contact_name'] = $newContact;
            }
            $client->update($payload);
            $updated++;
        }

        $this->line('');
        $this->info("OK — {$updated} client(s) mis a jour.");
        return self::SUCCESS;
    }

    /**
     * Split sur le DERNIER ` / ` :
     *   "Jean Marie / Société X" → ['Société X', 'Jean Marie']
     *   "A / B / C"               → ['C', 'A / B']
     */
    private function splitName(string $name): array
    {
        $pos = mb_strrpos($name, ' / ');
        if ($pos === false) {
            return [$name, ''];
        }
        $left  = trim(mb_substr($name, 0, $pos));
        $right = trim(mb_substr($name, $pos + 3));
        return [$right, $left];
    }
}
