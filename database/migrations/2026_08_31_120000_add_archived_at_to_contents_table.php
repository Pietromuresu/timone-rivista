<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            // Fase 4.3 — colonna semplice, non Illuminate\Database\Eloquent
            // \SoftDeletes: SoftDeletes applica un global scope che
            // filtrerebbe SILENZIOSAMENTE ogni query su Content in tutto il
            // progetto (relazioni pages()/article()/advertisement() incluse
            // dove non esplicitamente ripristinate con withTrashed()) — un
            // raggio d'azione molto più ampio e rischioso di quanto serva
            // qui, dove l'unico comportamento richiesto è "non comparire nel
            // pannello contenuti da assegnare finché è archiviato". Una
            // colonna nullable filtrata esplicitamente solo in
            // Issue::unassignedContents() è più chirurgica — nessun altro
            // punto del progetto deve sapere che esiste.
            $table->timestamp('archived_at')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
