<?php

use App\Support\Covers\CoverConversion;
use Illuminate\Database\Migrations\Migration;

/**
 * Fase 2 (2026-10-26): converte i numeri esistenti al modello "copertine
 * separate". Per ogni numero: le pagine con ruolo copertina1-4/battente
 * diventano gli slot I-IV/bandella (escono dalla sequenza), le pagine
 * interne vengono rinumerate da 1 mantenendo l'ordine, gli slot I-IV
 * mancanti vengono creati vuoti, total_pages = sole pagine interne,
 * pagine_copertina = 4 (6 con bandella). Nessun contenuto viene staccato:
 * le assegnazioni sono per id di pagina, che non cambia.
 *
 * Il dettaglio di cosa diventa cosa si vede PRIMA con
 * `php artisan timone:convert-covers --dry-run` (stesso pianificatore:
 * App\Support\Covers\CoverConversionPlanner). down() ripristina tutto dal
 * registro cover_conversion_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        CoverConversion::applyAll();
    }

    public function down(): void
    {
        CoverConversion::revertAll();
    }
};
