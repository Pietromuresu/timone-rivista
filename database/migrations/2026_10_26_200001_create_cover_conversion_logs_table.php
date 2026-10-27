<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 (2026-10-26): registro di ogni modifica fatta dalla conversione
 * delle copertine (App\Support\Covers\CoverConversion), riga per riga. È
 * ciò che rende la migration dei dati davvero reversibile — il down()
 * rimette ogni pagina nella posizione, nel ruolo e nel gruppo di prima — e
 * resta come traccia consultabile di "cosa è diventato cosa".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cover_conversion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            // NULL solo per la riga 'issue_totals'.
            $table->unsignedBigInteger('page_id')->nullable();
            // to_cover | renumbered | created_cover | ungrouped | issue_totals
            $table->string('action', 20);
            $table->unsignedInteger('old_position')->nullable();
            $table->unsignedInteger('new_position')->nullable();
            $table->string('old_role', 20)->nullable();
            $table->string('cover_slot', 10)->nullable();
            $table->unsignedBigInteger('old_page_group_id')->nullable();
            $table->unsignedInteger('old_total_pages')->nullable();
            $table->unsignedTinyInteger('old_pagine_copertina')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cover_conversion_logs');
    }
};
