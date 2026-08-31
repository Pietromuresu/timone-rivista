<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magazines', function (Blueprint $table) {
            // nullOnDelete (non cascadeOnDelete): eliminare l'utente redattore
            // non deve mai eliminare la rivista, solo lasciarla senza
            // redattore assegnato finché un admin non ne sceglie un altro.
            $table->foreignId('redattore_id')->nullable()->after('notes')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('magazines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('redattore_id');
        });
    }
};
