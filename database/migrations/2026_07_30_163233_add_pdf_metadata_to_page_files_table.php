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
        Schema::table('page_files', function (Blueprint $table) {
            // Quale pagina interna del PDF sorgente rappresenta questa riga
            // (Fase 2, §2.1/§2.2): un PDF caricato con N pagine occupa le N
            // pagine successive del timone, una riga PageFile per pagina
            // timone coinvolta, ciascuna con la propria copia del file (più
            // semplice di un riferimento condiviso: ogni riga resta
            // proprietaria del proprio file fisico, coerente con la cascata
            // di eliminazione/pulizia file orfani già esistente) ma un
            // pdf_page_number diverso per sapere quale pagina interna
            // renderizzare in miniatura e su quale posizionarsi aprendo il
            // PDF (frammento #page=N, nessun pdf.js — vedi HANDOFF.md).
            $table->unsignedInteger('pdf_page_number')->default(1)->after('thumbnail_status');

            // Esito del controllo formato pubblicitario (§2.3): null finché
            // il job non l'ha ancora calcolato (stesso ciclo di vita di
            // thumbnail_status, ma un campo a sé perché sono due controlli
            // indipendenti — una thumbnail può generarsi con successo anche
            // quando il formato non è applicabile o non corrisponde).
            $table->string('format_check_status')->nullable()->after('pdf_page_number');
            $table->decimal('measured_width_mm', 6, 1)->nullable()->after('format_check_status');
            $table->decimal('measured_height_mm', 6, 1)->nullable()->after('measured_width_mm');

            // "Forza accettazione" di un formato non conforme (§2.3): un
            // avviso, non un blocco — l'utente può confermare esplicitamente
            // di aver visto e accettato la non conformità per un caso limite
            // legittimo. nullOnDelete come locked_by/uploaded_by: non deve
            // impedire l'eliminazione di un utente.
            $table->foreignId('format_override_confirmed_by')->nullable()->after('measured_height_mm')->constrained('users')->nullOnDelete();
            $table->timestamp('format_override_confirmed_at')->nullable()->after('format_override_confirmed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('format_override_confirmed_by');
            $table->dropColumn([
                'pdf_page_number',
                'format_check_status',
                'measured_width_mm',
                'measured_height_mm',
                'format_override_confirmed_at',
            ]);
        });
    }
};
