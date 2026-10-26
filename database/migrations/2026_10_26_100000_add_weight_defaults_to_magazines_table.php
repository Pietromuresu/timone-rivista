<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parametri di default per il calcolo del peso teorico della rivista
 * (App\Support\Weight\MagazineWeightCalculator). Sono solo "default":
 * alla creazione di un numero vengono COPIATI sull'issue (migration
 * successiva), che da lì in poi ha i propri valori — cambiarli qui vale
 * solo per i numeri creati dopo.
 *
 * Formato e grammature restano nullable senza default: non esiste un
 * valore sensato per tutte le testate, e un campo vuoto deve produrre
 * "Parametri peso da configurare" in UI invece di un peso inventato.
 * Formato in mm interi (mai float): 21 cm = 210.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magazines', function (Blueprint $table) {
            $table->unsignedSmallInteger('formato_base_mm')->nullable()->after('redattore_id');
            $table->unsignedSmallInteger('formato_altezza_mm')->nullable()->after('formato_base_mm');
            $table->decimal('grammatura_interno', 6, 2)->nullable()->after('formato_altezza_mm');
            $table->decimal('grammatura_copertina', 6, 2)->nullable()->after('grammatura_interno');
            $table->unsignedTinyInteger('pagine_copertina')->nullable()->default(4)->after('grammatura_copertina');
            $table->decimal('maggiorazione_percentuale', 5, 2)->nullable()->default(2.00)->after('pagine_copertina');
            $table->decimal('peso_bancale_kg', 6, 2)->nullable()->default(20.00)->after('maggiorazione_percentuale');
            $table->decimal('tolleranza_grammatura_perc', 4, 2)->nullable()->default(4.00)->after('peso_bancale_kg');
            $table->decimal('tolleranza_formato_mm', 4, 2)->nullable()->default(1.00)->after('tolleranza_grammatura_perc');
            $table->decimal('tolleranza_maggiorazione_perc', 4, 2)->nullable()->default(1.00)->after('tolleranza_formato_mm');
        });
    }

    public function down(): void
    {
        Schema::table('magazines', function (Blueprint $table) {
            $table->dropColumn([
                'formato_base_mm',
                'formato_altezza_mm',
                'grammatura_interno',
                'grammatura_copertina',
                'pagine_copertina',
                'maggiorazione_percentuale',
                'peso_bancale_kg',
                'tolleranza_grammatura_perc',
                'tolleranza_formato_mm',
                'tolleranza_maggiorazione_perc',
            ]);
        });
    }
};
