<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Étend l'ENUM users.role pour inclure 'comptable'.
 *
 * Contexte urgent (2026-07-20) : la patronne signale une erreur 500 en
 * prod (main) sur /admin/users lors de la création d'un utilisateur.
 * Root cause : l'ENUM MySQL de la colonne `role` a été défini à l'origine
 * avec 4 valeurs ('admin','commercial','mediaplanner','technique'), mais
 * l'app supporte désormais un 5e rôle 'comptable' (UserRole::COMPTABLE
 * dans app/Enums/UserRole.php, validation 'in:...,comptable' dans
 * UserController::store).
 *
 * Résultat : la validation Laravel accepte 'comptable', mais l'INSERT
 * MySQL rejette la valeur (« Data truncated for column 'role' »),
 * remontant une SQLException 500.
 *
 * Cette migration ajoute 'comptable' à l'ENUM sans toucher aux données
 * existantes (opération ADDITIVE). Idempotente via ALTER TABLE
 * MODIFY COLUMN qui redéfinit l'ENUM même s'il inclut déjà 'comptable'
 * — l'opération est sans effet dans ce cas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL ne fournit pas de "ADD VALUE" pour ENUM ; on redéfinit la
        // colonne avec la liste complète. Sûr sur toutes les DB déjà en
        // place — les valeurs existantes sont conservées.
        DB::statement("
            ALTER TABLE `users`
            MODIFY COLUMN `role` ENUM('admin','commercial','mediaplanner','comptable','technique')
            NOT NULL DEFAULT 'commercial'
        ");
    }

    public function down(): void
    {
        // Revient à l'ENUM d'origine (4 valeurs). ATTENTION : si des
        // utilisateurs ont déjà le rôle 'comptable', MySQL les ramènera
        // à la valeur par défaut ('commercial') — down destructif.
        // Ne rollback qu'en connaissance de cause.
        DB::statement("
            ALTER TABLE `users`
            MODIFY COLUMN `role` ENUM('admin','commercial','mediaplanner','technique')
            NOT NULL DEFAULT 'commercial'
        ");
    }
};
