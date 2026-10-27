<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (2026-10-27): pubblicità segnaposto, create alla nascita di un
 * numero dalle "pubblicità previste".
 *
 * - `contents.is_placeholder`: true finché la pubblicità non ha un
 *   inserzionista, un titolo proprio o un PDF (vedi ContentUpdater e
 *   Grid::renameContent()). Default false: i contenuti esistenti non sono
 *   segnaposto, nessun dato da convertire.
 * - `advertisements.client` nullable: un segnaposto non ha ancora un
 *   inserzionista; finora era sempre obbligatorio.
 *
 * down(): un cliente vuoto torna stringa vuota (la colonna era NOT NULL)
 * e la colonna del segnaposto sparisce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->boolean('is_placeholder')->default(false)->after('occupancy_twelfths');
        });

        Schema::table('advertisements', function (Blueprint $table) {
            $table->string('client')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('advertisements')->whereNull('client')->update(['client' => '']);

        Schema::table('advertisements', function (Blueprint $table) {
            $table->string('client')->nullable(false)->change();
        });

        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn('is_placeholder');
        });
    }
};
