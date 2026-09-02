<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('page_content', function (Blueprint $table) {
            // Quale file, tra i possibili N di un contenuto multi-file (un
            // PDF multi-pagina genera un ContentFile per pagina sorgente),
            // è quello rilevante per QUESTA riga pagina↔contenuto (Punto 4
            // del prompt, 2026-09-02). Null per un contenuto a file
            // singolo (immagine, PDF di una pagina, articolo senza file):
            // il rendering ricade su Content::files()->first() — nessuna
            // riga esistente va toccata, il comportamento per i contenuti
            // già assegnati oggi resta identico. nullOnDelete: eliminare
            // il file non deve far sparire l'assegnazione pagina↔contenuto
            // stessa, solo il riferimento a quale file specifico mostrare.
            $table->foreignId('content_file_id')->nullable()->after('content_id')->constrained('content_files')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_content', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_file_id');
        });
    }
};
