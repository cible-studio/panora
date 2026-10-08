<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Journalisation qui ne peut pas casser une page.
 *
 * Monolog lève une exception quand il n'arrive pas à écrire : disque plein,
 * dossier non inscriptible, canal mal configuré. Cette exception remonte et
 * transforme une simple trace en erreur 500 — un comportement absurde, la
 * journalisation étant un service annexe, jamais l'objet de la requête.
 *
 * C'est ce qui a cassé la connexion à l'espace admin en production : le
 * formulaire public journalise ses succès en Log::info, filtré par
 * LOG_LEVEL=warning et donc jamais écrit, alors que l'admin trace chaque
 * tentative en Log::warning — le seul chemin qui touchait réellement le
 * fichier. Résultat : site public intact, admin en 500 à chaque connexion,
 * y compris avec de mauvais identifiants.
 *
 * À utiliser partout où une trace accompagne une action visible par un
 * visiteur. Pour du diagnostic interne, Log:: directement reste très bien.
 */
class Journal
{
    public static function avertir(string $message, array $contexte = []): void
    {
        self::ecrire('warning', $message, $contexte);
    }

    public static function erreur(string $message, array $contexte = []): void
    {
        self::ecrire('error', $message, $contexte);
    }

    private static function ecrire(string $niveau, string $message, array $contexte): void
    {
        try {
            Log::log($niveau, $message, $contexte);
        } catch (\Throwable) {
            // Dernier recours : la sortie d'erreur du processus, toujours
            // disponible et récupérée par Docker. Si elle échoue aussi, on
            // abandonne la trace — jamais la requête.
            try {
                error_log("[cible] $message " . json_encode($contexte, JSON_UNESCAPED_UNICODE));
            } catch (\Throwable) {
                // rien : aucune trace ne justifie de casser la page
            }
        }
    }
}
