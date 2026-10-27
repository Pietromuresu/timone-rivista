<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisione 2, Fase 2.4 (2026-10-27): ritaglio delle anteprime in base
 * alla posizione del contenuto sulla griglia.
 * - content_files.aspect_ratio: larghezza/altezza della pagina del file,
 *   misurata una volta quando si genera la miniatura (job
 *   GenerateContentFileThumbnail; per i file già caricati:
 *   `php artisan timone:file-ratios`). Null = non ancora misurato →
 *   anteprima intera, senza tagli.
 * - contents.file_fit: scelta manuale della redazione ("a pagina intera" /
 *   "nel formato", App\Enums\FileFit); null = automatico.
 * Solo colonne nuove e nullable, nessun dato convertito qui. down(): le
 * colonne spariscono e le anteprime tornano intere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_files', function (Blueprint $table) {
            $table->decimal('aspect_ratio', 8, 5)->nullable()->after('measured_height_mm');
        });

        Schema::table('contents', function (Blueprint $table) {
            $table->string('file_fit', 16)->nullable()->after('is_placeholder');
        });
    }

    public function down(): void
    {
        Schema::table('content_files', function (Blueprint $table) {
            $table->dropColumn('aspect_ratio');
        });

        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn('file_fit');
        });
    }
};
