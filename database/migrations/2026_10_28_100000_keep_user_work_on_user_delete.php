<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisione 2, Fase 4.3 (2026-10-28): eliminare un utente NON deve
 * cancellare il suo lavoro. Queste tre chiavi erano `cascadeOnDelete`:
 * togliere un utente cancellava i file che aveva caricato (content_files,
 * page_files) e lo storico degli spostamenti che aveva fatto
 * (page_reorder_logs). Ora la colonna diventa NULL e l'interfaccia mostra
 * «Utente eliminato». Nessun dato viene modificato: cambia solo il vincolo.
 *
 * down(): torna a NOT NULL + cascade solo se non ci sono righe con
 * l'utente già NULL (altrimenti non si può: lo dice e si ferma, invece di
 * cancellare righe per far tornare il vincolo).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'content_files' => 'uploaded_by',
        'page_files' => 'uploaded_by',
        'page_reorder_logs' => 'user_id',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->foreignId($column)->nullable()->change();
                $blueprint->foreign($column)->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            if (DB::table($table)->whereNull($column)->exists()) {
                throw new RuntimeException("{$table}.{$column} contiene righe di utenti eliminati (NULL): non si può tornare a NOT NULL senza perderle.");
            }
        }

        foreach (self::COLUMNS as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->foreignId($column)->nullable(false)->change();
                $blueprint->foreign($column)->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
