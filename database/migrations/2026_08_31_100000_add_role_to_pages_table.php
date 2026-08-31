<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Default 'interna' (non nullable): ogni pagina esistente prima
            // di questa migrazione è di fatto una pagina interna, nessun
            // backfill dedicato necessario. Copertina1/Copertina4 vengono
            // ricalcolate su prima/ultima pagina da App\Support\
            // PageRoleAssigner al prossimo resize o al prossimo accesso al
            // pannello Copertina/Battente — non qui, una migration non deve
            // contenere logica di business.
            $table->string('role', 20)->default('interna')->after('page_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
