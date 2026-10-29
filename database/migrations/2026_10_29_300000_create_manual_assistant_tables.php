<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assistente «Chiedi al manuale» (2026-10-29).
 *
 * - `manual_feedback`: un voto 👍/👎 su una risposta. `utente` è un'impronta
 *   (HMAC dell'id con la chiave dell'app), non l'id: serve solo a contare
 *   «un voto per persona», non a sapere chi ha votato. Nessuna FK verso
 *   users, voluto. `risposta_id` + `su_alternativa` unici: si vota una volta
 *   la risposta e una volta (al massimo) una pagina alternativa. Gli hash
 *   dicono su quale versione della pagina si è votato (vedi ManualLearning).
 *   Tenuti 12 mesi (manuale:pulisci).
 * - `manual_learned_boosts` e `manual_learned_questions`: il risultato del
 *   job notturno, RIFATTI DA ZERO a ogni giro (manuale:apprendi). Anche
 *   le risposte «probabilmente sbagliate» stanno qui (sospetta = true).
 * - `manual_unanswered`: domande a cui l'assistente non ha dato una
 *   risposta sicura, per chiave, senza utente. Tenute 12 mesi.
 * - `manual_learning_runs`: un giro del job (anche quelli bloccati dal cancello).
 * - `manual_disabled`: domande imparate o correzioni spente a mano da un admin;
 *   il job le salta e l'assistente le ignora subito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('risposta_id', 32);
            $table->string('chiave', 255);
            $table->string('domanda', 500);
            $table->string('slug_mostrato', 100)->nullable();
            $table->string('slug_scelto', 100)->nullable();
            $table->boolean('su_alternativa')->default(false);
            $table->unsignedTinyInteger('posizione');
            $table->tinyInteger('voto');
            $table->string('contesto', 40)->nullable();
            $table->string('hash_versione', 40);
            $table->string('hash_in_breve', 40);
            $table->string('utente', 64);
            $table->timestamp('created_at')->index();

            $table->unique(['risposta_id', 'su_alternativa']);
            $table->index('chiave');
        });

        Schema::create('manual_learned_boosts', function (Blueprint $table) {
            $table->id();
            $table->string('chiave', 255);
            $table->string('slug', 100);
            $table->float('correzione');
            $table->unsignedInteger('voti');
            $table->unsignedInteger('utenti');
            $table->float('positivi');
            $table->boolean('sospetta')->default(false);
            $table->timestamp('created_at');

            $table->unique(['chiave', 'slug']);
        });

        Schema::create('manual_learned_questions', function (Blueprint $table) {
            $table->id();
            $table->string('chiave', 255)->unique();
            $table->string('domanda', 500);
            $table->string('slug', 100);
            $table->unsignedInteger('voti');
            $table->unsignedInteger('utenti');
            $table->timestamp('created_at');
        });

        Schema::create('manual_unanswered', function (Blueprint $table) {
            $table->id();
            $table->string('chiave', 255)->unique();
            $table->string('domanda', 500);
            $table->unsignedInteger('volte')->default(1);
            $table->timestamp('ultima_volta')->index();
        });

        Schema::create('manual_learning_runs', function (Blueprint $table) {
            $table->id();
            $table->boolean('pubblicato');
            $table->unsignedInteger('voti');
            $table->unsignedInteger('correzioni');
            $table->unsignedInteger('domande_imparate');
            $table->unsignedInteger('sospette');
            $table->json('fallimenti')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('manual_disabled', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);
            $table->string('chiave', 255);
            $table->string('slug', 100);
            $table->timestamp('created_at');

            $table->unique(['tipo', 'chiave', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_disabled');
        Schema::dropIfExists('manual_learning_runs');
        Schema::dropIfExists('manual_unanswered');
        Schema::dropIfExists('manual_learned_questions');
        Schema::dropIfExists('manual_learned_boosts');
        Schema::dropIfExists('manual_feedback');
    }
};
