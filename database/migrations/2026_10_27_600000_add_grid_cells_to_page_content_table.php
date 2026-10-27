<?php

use App\Support\PageGrid\GridConversion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisione 2, Fase 2.3 (2026-10-27): posizione di ogni contenuto su ogni
 * pagina, come rettangolo di celle nella griglia 6×6 (grid_x, grid_y =
 * angolo in alto a sinistra 0–5; grid_w, grid_h = celle). Nullable: un
 * contenuto a L (7/12, 3/4, 11/12) o oltre capienza non ha un rettangolo
 * e si ricalcola a ogni disegno (App\Support\PageGrid\PageGridPlanner).
 *
 * I dati esistenti ricevono la posizione in cui la disposizione della
 * Fase 7a li disegnava (App\Support\PageGrid\GridConversion): nessun
 * cambiamento visibile per le combinazioni comuni. Dry-run prima del
 * migrate: `php artisan timone:grid-dry-run`.
 * down(): le colonne spariscono e la disposizione torna calcolata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_content', function (Blueprint $table) {
            $table->unsignedTinyInteger('grid_x')->nullable()->after('position');
            $table->unsignedTinyInteger('grid_y')->nullable()->after('grid_x');
            $table->unsignedTinyInteger('grid_w')->nullable()->after('grid_y');
            $table->unsignedTinyInteger('grid_h')->nullable()->after('grid_w');
        });

        GridConversion::applyAll();
    }

    public function down(): void
    {
        Schema::table('page_content', function (Blueprint $table) {
            $table->dropColumn(['grid_x', 'grid_y', 'grid_w', 'grid_h']);
        });
    }
};
