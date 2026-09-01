<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            // Versione leggera dello stato pagine di questo numero (2026-09-01):
            // toccata esplicitamente da Issue::markPagesUpdated() ovunque il
            // codice già trasmette un evento broadcast (PageMoved,
            // ContentAssigned, PageStatusUpdated, ecc. — vedi HANDOFF.md) —
            // NON un cast datetime sul modello, di proposito: va confrontata
            // come stringa grezza contro il valore letto da una query a
            // colonna singola (Grid::checkForUpdates()), mai formattata o
            // manipolata come data altrove.
            $table->timestamp('pages_updated_at')->nullable()->after('reorder_version');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('pages_updated_at');
        });
    }
};
