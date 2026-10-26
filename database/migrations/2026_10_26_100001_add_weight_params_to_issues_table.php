<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parametri del peso teorico sul singolo numero: stessi nomi e tipi dei
 * default su `magazines` (migration precedente), copiati alla creazione
 * del numero (Issues\Create) e modificabili poi dal pannello Peso. Una
 * copia, non un riferimento: se cambia la carta della testata, i numeri
 * già usciti NON devono cambiare peso.
 *
 * Le pagine interne NON sono qui: si derivano da issues.total_pages
 * (vedi Issue::weightInput()), così il peso segue il timone da solo.
 *
 * Le issue già esistenti ricevono i default di colonna (maggiorazione,
 * tolleranze, pagine copertina) ma formato e grammature restano NULL:
 * nessuna riga viene "indovinata", il pannello mostrerà "Parametri peso
 * da configurare" finché qualcuno non li imposta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->unsignedSmallInteger('formato_base_mm')->nullable()->after('pages_updated_at');
            $table->unsignedSmallInteger('formato_altezza_mm')->nullable()->after('formato_base_mm');
            $table->decimal('grammatura_interno', 6, 2)->nullable()->after('formato_altezza_mm');
            $table->decimal('grammatura_copertina', 6, 2)->nullable()->after('grammatura_interno');
            $table->unsignedTinyInteger('pagine_copertina')->nullable()->default(4)->after('grammatura_copertina');
            $table->decimal('maggiorazione_percentuale', 5, 2)->nullable()->default(2.00)->after('pagine_copertina');
            $table->decimal('peso_bancale_kg', 6, 2)->nullable()->default(20.00)->after('maggiorazione_percentuale');
            $table->decimal('tolleranza_grammatura_perc', 4, 2)->nullable()->default(4.00)->after('peso_bancale_kg');
            $table->decimal('tolleranza_formato_mm', 4, 2)->nullable()->default(1.00)->after('tolleranza_grammatura_perc');
            $table->decimal('tolleranza_maggiorazione_perc', 4, 2)->nullable()->default(1.00)->after('tolleranza_formato_mm');
            $table->unsignedInteger('n_copie')->nullable()->after('tolleranza_maggiorazione_perc');
            $table->unsignedSmallInteger('n_bancali')->nullable()->after('n_copie');
            $table->decimal('peso_reale_g', 8, 2)->nullable()->after('n_bancali');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
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
                'n_copie',
                'n_bancali',
                'peso_reale_g',
            ]);
        });
    }
};
