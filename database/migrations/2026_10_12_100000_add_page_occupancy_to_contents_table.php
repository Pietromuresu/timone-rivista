<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bug 5 (2026-10-12): occupazione di pagina del contenuto, in pagine (vedi
 * App\Support\PageOccupancy). Aggiunta NULLABLE di proposito: i contenuti
 * già esistenti non hanno un valore, e inventarne uno (es. "1 pagina")
 * renderebbe silenziosamente sbagliati i calcoli che il campo serve proprio
 * a correggere. Il vincolo NOT NULL arriva nella migration successiva,
 * solo dopo un backfill deciso esplicitamente dalla redazione.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->decimal('page_occupancy', 5, 2)->nullable()->after('title')
                ->comment('occupazione in pagine: 0.25/0.33/0.50/0.67 o pagine intere 1..N');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn('page_occupancy');
        });
    }
};
