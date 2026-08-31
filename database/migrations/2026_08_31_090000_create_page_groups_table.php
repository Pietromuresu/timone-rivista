<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            // Chiave nella palette fissa di App\Models\PageGroup, non un
            // valore CSS/hex libero: le classi Tailwind sono letterali nel
            // metodo badgeClasses()/ringClasses() (app/**/*.php è scansionato
            // da tailwind.config.js), scegliere un colore libero renderebbe
            // impossibile per Tailwind generare il CSS corrispondente.
            $table->string('color', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_groups');
    }
};
