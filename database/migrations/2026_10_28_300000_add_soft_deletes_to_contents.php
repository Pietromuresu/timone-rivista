<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annulla/ripeti (2026-10-28, scelta dell'utente): l'eliminazione di un
 * contenuto (e l'annulla di una creazione) diventa un soft delete, così un
 * Ctrl+Z lo ripristina con file e dati. I contenuti eliminati da più di 24
 * ore vengono tolti davvero, con i loro file, da `timone:history-prune`.
 * Solo una colonna nuova: nessun dato esistente cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
