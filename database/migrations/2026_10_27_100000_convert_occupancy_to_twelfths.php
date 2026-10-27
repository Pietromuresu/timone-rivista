<?php

use App\Support\Occupancy\OccupancyConversion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 (2026-10-27): occupazione in dodicesimi di pagina (interi) al
 * posto delle percentuali decimali.
 *
 * - `contents.occupancy_twelfths`: occupazione totale del contenuto
 *   (30 = 2 pagine e mezza).
 * - `page_content.occupied_twelfths`: spazio occupato su quella pagina
 *   (1–12, default 12 come il vecchio default 100%).
 *
 * Le colonne vecchie restano (nullable, non più lette né scritte), stesso
 * approccio della Fase 2 con `pages.role`: il rollback ritrova i valori
 * originali intatti. Cosa diventa cosa si vede PRIMA con
 * `php artisan timone:occupancy-dry-run` (stesso pianificatore:
 * App\Support\Occupancy\OccupancyConversionPlanner).
 */
return new class extends Migration
{
    /**
     * Fuori transazione: su SQLite `->change()` ricostruisce la tabella e
     * Laravel spegne le foreign key per non far scattare le cascade (che
     * cancellerebbero il pivot quando si ricostruisce `contents`), ma il
     * PRAGMA foreign_keys dentro una transazione è ignorato. Su MySQL
     * (l'ambiente reale) il DDL non è comunque transazionale.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->unsignedSmallInteger('occupancy_twelfths')->nullable()->after('page_occupancy');
        });

        Schema::table('page_content', function (Blueprint $table) {
            $table->unsignedTinyInteger('occupied_twelfths')->nullable()->after('occupied_percentage');
        });

        OccupancyConversion::applyAll();

        Schema::table('contents', function (Blueprint $table) {
            $table->unsignedSmallInteger('occupancy_twelfths')->nullable(false)->change();
            $table->decimal('page_occupancy', 5, 2)->nullable()->change();
        });

        Schema::table('page_content', function (Blueprint $table) {
            // Default 12 come il vecchio default(100) di occupied_percentage:
            // il codice dell'app lo scrive sempre, il default serve solo a
            // un attach() senza valore (seeder, test, comandi una tantum).
            $table->unsignedTinyInteger('occupied_twelfths')->nullable(false)->default(12)->change();
            $table->decimal('occupied_percentage', 5, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        OccupancyConversion::revertAll();

        Schema::table('page_content', function (Blueprint $table) {
            $table->decimal('occupied_percentage', 5, 2)->nullable(false)->default(100)->change();
            $table->dropColumn('occupied_twelfths');
        });

        Schema::table('contents', function (Blueprint $table) {
            $table->decimal('page_occupancy', 5, 2)->nullable(false)->change();
            $table->dropColumn('occupancy_twelfths');
        });
    }
};
