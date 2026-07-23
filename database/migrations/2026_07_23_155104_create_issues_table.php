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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('magazine_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('issue_date')->nullable();
            $table->string('status')->default('bozza');
            $table->unsignedInteger('total_pages')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['magazine_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
