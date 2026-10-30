<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ritaglio delle miniature (2026-10-30, Fase B1), solo colonne nuove e
 * vuote, nessun dato toccato:
 * - `trim_box`: misure della pagina del PDF (MediaBox e TrimBox in mm,
 *   App\Support\PdfBoxes), per ritagliare sulla pagina rifilata. NULL =
 *   non ancora misurata (i file vecchi li misura `timone:file-ratios`);
 *   misurata ma illeggibile = {"media": null, "trim": null};
 * - `large_thumbnail_path`: miniatura da 1200 px per i riquadri ritagliati
 *   (quella da 400 px, ingrandita, era sfocata).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_files', function (Blueprint $table) {
            $table->json('trim_box')->nullable()->after('aspect_ratio');
            $table->string('large_thumbnail_path')->nullable()->after('thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('content_files', function (Blueprint $table) {
            $table->dropColumn(['trim_box', 'large_thumbnail_path']);
        });
    }
};
