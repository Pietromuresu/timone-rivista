<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->date('print_deadline_at')->nullable()->after('issue_date');
            // Traccia se il sollecito per l'attuale print_deadline_at è già
            // stato inviato (Fase 5.3) — evita di rispedire la mail ogni
            // giorno finché la scadenza non passa. Azzerato esplicitamente
            // se la scadenza viene spostata (vedi Grid::updatePrintDeadline),
            // così una nuova data rientra di nuovo nella finestra di preavviso.
            $table->timestamp('deadline_reminder_sent_at')->nullable()->after('print_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn(['print_deadline_at', 'deadline_reminder_sent_at']);
        });
    }
};
