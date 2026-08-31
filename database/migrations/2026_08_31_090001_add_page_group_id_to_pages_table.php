<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // nullOnDelete (non cascadeOnDelete): eliminare un gruppo non
            // deve mai eliminare le pagine che lo componevano, solo
            // scollegarle — coerente con Grid::unlinkGroup(), che fa
            // esattamente questo esplicitamente lato applicazione.
            $table->foreignId('page_group_id')->nullable()->after('locked_by')
                ->constrained('page_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_group_id');
        });
    }
};
