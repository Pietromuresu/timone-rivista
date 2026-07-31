<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            // Fase 3 (§3): posizione di pagina preferita indicata al momento
            // della prenotazione, prima che esista una vera assegnazione a
            // una pagina (page_content) — puramente informativa, non vincola
            // né automatizza l'assegnazione reale (che resta un drag&drop
            // esplicito sulla griglia, vedi Grid::assignContent()).
            $table->unsignedInteger('preferred_position')->nullable()->after('format');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropColumn('preferred_position');
        });
    }
};
