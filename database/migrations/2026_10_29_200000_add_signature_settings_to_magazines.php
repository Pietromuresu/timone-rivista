<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organizzazione in segnature (2026-10-29): impostazioni per rivista, lette
 * dai suoi numeri a ogni render (App\Support\Signatures\SignaturePlan::forIssue()).
 * Non copiate sul numero come i parametri del peso: sono una caratteristica
 * di stampa della testata, non un dato storico del singolo numero.
 * - signature_pages: pagine per segnatura (8, 16, 32, 64; default 32).
 * - signature_includes_covers: le facciate di copertina (4, o 6 con la
 *   bandella) contano in testa alla prima segnatura (default no: stampate a parte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magazines', function (Blueprint $table) {
            $table->unsignedTinyInteger('signature_pages')->default(32);
            $table->boolean('signature_includes_covers')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('magazines', function (Blueprint $table) {
            $table->dropColumn(['signature_pages', 'signature_includes_covers']);
        });
    }
};
