<?php

use App\Support\Covers\FlapConversion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Battenti di copertina (2026-10-30): la bandella di ogni numero diventa
 * la prima pagina del battente di I (la seconda nasce vuota) e i default
 * delle riviste passano da «pagine copertina» a una scelta di battenti
 * (`magazines.default_cover_flaps`). `pagine_copertina` resta: è il
 * parametro del peso, ora calcolato dalla scelta.
 *
 * Prima/dopo: `php artisan timone:battenti-dry-run`. down() torna alla
 * bandella e si ferma senza modifiche se qualche numero usa il battente
 * di IV o la seconda pagina del battente di I (dettaglio in
 * App\Support\Covers\FlapConversion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magazines', function (Blueprint $table) {
            $table->string('default_cover_flaps', 10)->default('nessuno')->after('pagine_copertina');
        });

        FlapConversion::apply();
    }

    public function down(): void
    {
        FlapConversion::revert();

        Schema::table('magazines', function (Blueprint $table) {
            $table->dropColumn('default_cover_flaps');
        });
    }
};
