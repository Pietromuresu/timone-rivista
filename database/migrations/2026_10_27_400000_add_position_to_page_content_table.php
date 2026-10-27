<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7b (2026-10-27): ordine dei contenuti dentro una pagina, che decide
 * l'ordine dei blocchi nella disposizione (App\Support\PageLayoutPlanner).
 *
 * I dati esistenti ricevono 1, 2, 3… per pagina nell'ordine di
 * assegnazione (id del pivot), cioè esattamente l'ordine già mostrato dal
 * passo 7a: nessun cambiamento visibile. Nullable perché un attach() senza
 * posizione (test, comandi una tantum) resta valido: Page::contents() mette
 * in coda le righe senza posizione, nell'ordine di assegnazione.
 * down(): la colonna sparisce, l'ordine torna quello di assegnazione.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_content', function (Blueprint $table) {
            $table->unsignedSmallInteger('position')->nullable()->after('occupied_twelfths');
        });

        $rank = [];

        DB::table('page_content')->orderBy('page_id')->orderBy('id')->select(['id', 'page_id'])->chunk(500, function ($rows) use (&$rank) {
            foreach ($rows as $row) {
                $rank[$row->page_id] = ($rank[$row->page_id] ?? 0) + 1;
                DB::table('page_content')->where('id', $row->id)->update(['position' => $rank[$row->page_id]]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('page_content', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
