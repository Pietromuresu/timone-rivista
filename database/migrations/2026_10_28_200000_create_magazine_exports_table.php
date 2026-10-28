<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisione 2, Fase 5 (2026-10-28): stato e file dell'export PDF
 * sfogliabile di un numero. Una riga per (numero, variante): rigenerare
 * sostituisce file e stato, non aggiunge righe. Tabella nuova, nessun dato
 * esistente da convertire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magazine_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('variant', 20);
            $table->string('status', 20);
            $table->string('disk', 40)->nullable();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            // Contenuti disegnati come «File mancante» (file assente o illeggibile).
            $table->unsignedInteger('missing_files')->default(0);
            $table->text('error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['issue_id', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magazine_exports');
    }
};
