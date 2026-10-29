<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annulla/ripeti (2026-10-28): cronologia delle azioni annullabili, per
 * utente e per numero (App\Support\Timeline\TimelineActionService).
 * `changes` = le sole righe e i soli campi cambiati, prima e dopo, delle
 * tabelle del timone. Al massimo 50 passi per utente e numero, rimossi dopo
 * 24 ore da `timone:history-prune`: è uno strumento di lavoro, non un
 * registro (quello resta activity_logs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timone_azioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('type', 40);
            $table->string('label', 255);
            $table->longText('changes');
            // eseguita | annullata
            $table->string('status', 20)->default('eseguita');
            // Motivo per cui il passo non si può annullare (PDF sostituito, conflitto…); null = annullabile.
            $table->text('blocked_reason')->nullable();
            $table->string('batch_key', 64)->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index(['issue_id', 'user_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timone_azioni');
    }
};
