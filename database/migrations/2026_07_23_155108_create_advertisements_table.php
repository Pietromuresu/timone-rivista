<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->string('client');
            $table->string('agency')->nullable();
            $table->string('format');
            $table->decimal('occupied_percentage_override', 5, 2)->nullable()
                ->comment('sovrascrive la percentuale di default associata al formato');
            $table->string('confirmation_status')->default('in_trattativa');
            $table->text('commercial_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
