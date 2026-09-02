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
        Schema::table('content_files', function (Blueprint $table) {
            // Colonne parallele a quelle già aggiunte a page_files (Punto
            // 4 del prompt "modifiche timone", 2026-09-02): il contenuto
            // diventa l'unico oggetto a cui si può allegare un PDF, quindi
            // deve poter supportare esattamente la stessa logica multi-
            // pagina/controllo formato già esistente per page_files —
            // niente di nuovo concettualmente, solo lo stesso schema
            // applicato qui. content_files non ha un vincolo unique su
            // content_id (verificato): un contenuto con un PDF multi-
            // pagina riceve più righe, una per pagina sorgente, ciascuna
            // con il proprio pdf_page_number — stesso principio già in uso
            // per page_files quando un unico PDF occupa più pagine timone.
            $table->unsignedInteger('pdf_page_number')->default(1)->after('thumbnail_status');
            $table->string('format_check_status')->nullable()->after('pdf_page_number');
            $table->decimal('measured_width_mm', 6, 1)->nullable()->after('format_check_status');
            $table->decimal('measured_height_mm', 6, 1)->nullable()->after('measured_width_mm');
            $table->foreignId('format_override_confirmed_by')->nullable()->after('measured_height_mm')->constrained('users')->nullOnDelete();
            $table->timestamp('format_override_confirmed_at')->nullable()->after('format_override_confirmed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_files', function (Blueprint $table) {
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
