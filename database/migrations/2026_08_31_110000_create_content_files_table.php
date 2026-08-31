<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name');
            // A differenza di page_files (sempre un PDF, mimes:pdf in
            // Grid::uploadPageFile()), qui il file può essere un'immagine —
            // il job di generazione miniatura deve sapere quale ramo
            // prendere (rasterizzare via Ghostscript o copiare com'è) senza
            // dover riaprire il file per indovinarlo.
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('thumbnail_status')->default('pending');
            $table->timestamps();

            $table->index(['content_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_files');
    }
};
