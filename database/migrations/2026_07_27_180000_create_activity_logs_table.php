<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cronologia generale (spec §9) — distinta da page_reorder_logs, che
     * resta specifica del riordino pagine (from/to position, UI dedicata
     * già esistente dal Punto 2). Questa tabella copre tutto il resto:
     * cambi stato, assegnazione/creazione contenuti, upload PDF, modifica
     * pagine totali, soglia pubblicitaria — e, per una cronologia davvero
     * completa in un unico posto, anche gli spostamenti pagina (descritti
     * qui in forma generica, i dettagli from/to restano lo storico
     * spostamenti). issue_id/user_id nullOnDelete, non cascade: una riga
     * di cronologia deve sopravvivere anche se l'issue o l'utente
     * vengono eliminati in seguito — è un registro storico, non un
     * riferimento che ha senso solo finché l'entità esiste (stesso
     * principio già in `page_reorder_logs`... salvo che lì è
     * cascadeOnDelete, scelta diversa presa a suo tempo perché quello
     * storico è specifico di una singola issue e non ha senso tenerlo
     * "orfano"; qui invece la cronologia generale è pensata anche come
     * registro di accountability che deve restare leggibile).
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action');
            $table->string('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['issue_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
