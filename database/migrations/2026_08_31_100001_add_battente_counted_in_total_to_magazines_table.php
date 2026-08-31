<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magazines', function (Blueprint $table) {
            // Default false: convenzione editoriale più comune, il
            // battente è un lembo pieghevole in più agganciato alla
            // copertina, tipicamente NON conteggiato nel numero di pagine
            // dichiarato del numero — vedi HANDOFF.md "Decisioni
            // architetturali da ricordare" per la scelta completa e perché
            // è resa configurabile per rivista invece che fissa.
            $table->boolean('battente_counted_in_total')->default(false)->after('ad_threshold_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('magazines', function (Blueprint $table) {
            $table->dropColumn('battente_counted_in_total');
        });
    }
};
