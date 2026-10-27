<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 (2026-10-26): copertine separate dalle pagine interne.
 *
 * - `cover_slot` (I, II, III, IV, bandella — App\Enums\CoverSlot): una
 *   riga di `pages` con questo campo valorizzato è una copertina, fuori
 *   dalla sequenza delle pagine interne.
 * - `position` diventa NULLABLE: le copertine non hanno posizione. Il
 *   vincolo unique(issue_id, position) resta e continua a garantire la
 *   sequenza delle interne (più NULL sono ammessi da MySQL e SQLite).
 * - unique(issue_id, cover_slot): una sola II di copertina per numero.
 *
 * Solo schema: la conversione dei dati è nella migration successiva
 * (…_200002_convert_cover_pages_to_slots). down() presuppone che quella sia
 * già stata annullata (Laravel annulla in ordine inverso): a quel punto
 * nessuna riga ha più position NULL e la colonna può tornare NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('cover_slot', 10)->nullable()->after('role');
            $table->unique(['issue_id', 'cover_slot']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->unsignedInteger('position')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->unsignedInteger('position')->nullable(false)->change();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique(['issue_id', 'cover_slot']);
            $table->dropColumn('cover_slot');
        });
    }
};
