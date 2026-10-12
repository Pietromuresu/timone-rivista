<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bug 5 (2026-10-12): terzo livello del vincolo "occupazione obbligatoria"
 * (dopo validazione Livewire e App\Support\ContentCreator). Si rifiuta di
 * partire se esistono ancora contenuti senza occupazione invece di
 * riempirli con un default: il backfill è una decisione redazionale, non
 * tecnica (vedi HANDOFF.md, sessione 2026-10-12). L'elenco dei record da
 * sistemare lo stampa `php artisan timone:missing-occupancy`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $missing = DB::table('contents')->whereNull('page_occupancy')->count();

        if ($missing > 0) {
            throw new RuntimeException(
                "{$missing} contenuti non hanno ancora un'occupazione di pagina: impossibile applicare il vincolo NOT NULL. "
                .'Elencali con "php artisan timone:missing-occupancy", valorizzali e rilancia la migrazione.'
            );
        }

        Schema::table('contents', function (Blueprint $table) {
            $table->decimal('page_occupancy', 5, 2)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->decimal('page_occupancy', 5, 2)->nullable()->change();
        });
    }
};
