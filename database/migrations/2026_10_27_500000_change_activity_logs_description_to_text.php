<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisione 2, Fase 1, bug 2 (2026-10-27): "Text too long" creando un
 * numero con tante pubblicità previste. La colonna era
 * activity_logs.description, VARCHAR(255): la voce di registro della
 * creazione elencava TUTTE le posizioni dei segnaposto e con qualche
 * decina di pubblicità superava i 255 caratteri (MySQL rifiuta, SQLite dei
 * test no — per questo nessun test se ne accorgeva). La causa è tolta in
 * PlaceholderAdCreator (descrizione breve, posizioni solo nei dati
 * strutturati new_values); TEXT evita che lo stesso errore torni con altre
 * descrizioni composte da testo libero (es. rinomina di un contenuto con
 * un titolo lungo).
 *
 * down(): le descrizioni più lunghe di 255 caratteri vengono accorciate
 * prima di tornare a VARCHAR(255), altrimenti MySQL rifiuterebbe la
 * modifica della colonna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->text('description')->change();
        });
    }

    public function down(): void
    {
        DB::table('activity_logs')
            ->whereRaw('length(description) > 255')
            ->update(['description' => DB::raw('substr(description, 1, 255)')]);

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('description')->change();
        });
    }
};
