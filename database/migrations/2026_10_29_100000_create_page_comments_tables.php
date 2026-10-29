<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commenti per pagina (2026-10-29).
 *
 * - `page_id` segue la PAGINA come entità (non la posizione): spostandola i
 *   commenti la seguono da soli. È nullable con nullOnDelete: ridurre il
 *   totale pagine (o togliere la bandella) elimina davvero la pagina, e il
 *   commento deve restare consultabile senza errori di FK. Prima
 *   dell'eliminazione App\Support\Comments\DeletedPageComments scrive in
 *   `page_label` il nome della pagina («pagina 47») e `page_deleted_at`;
 *   se un percorso futuro dimentica di chiamarlo, la FK mette comunque
 *   page_id a NULL e resta il nome di quando il commento è stato scritto.
 * - `issue_id` serve proprio per i commenti senza pagina (e per contarli
 *   per numero senza join); in cascata col numero.
 * - `user_id` nullOnDelete: il commento resta anche se l'utente viene
 *   eliminato (come content_files.uploaded_by dalla revisione 2).
 * - soft delete = commento eliminato dall'autore o da un admin.
 * - `issues.comments_updated_at`: colonna osservata dal polling SOLO per i
 *   commenti, separata da pages_updated_at per non ridisegnare la griglia
 *   a tutti a ogni commento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('page_label', 60);
            $table->timestamp('page_deleted_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['issue_id', 'page_id']);
        });

        Schema::create('page_comment_reads', function (Blueprint $table) {
            $table->foreignId('page_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->primary(['page_comment_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('issues', function (Blueprint $table) {
            // Microsecondi: due commenti nello stesso secondo devono dare due
            // valori diversi, o il secondo sfuggirebbe al polling.
            $table->timestamp('comments_updated_at', 6)->nullable()->after('pages_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('comments_updated_at');
        });
        Schema::dropIfExists('page_comment_reads');
        Schema::dropIfExists('page_comments');
    }
};
