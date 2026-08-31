<?php

namespace App\Support;

use App\Enums\IssueStatus;
use App\Models\Issue;
use Carbon\CarbonInterface;

/**
 * Decide se un'issue è "matura" per il sollecito di scadenza stampa (Fase
 * 5.3) — pura, nessuna query Eloquent al suo interno, opera sui dati già
 * caricati su un'Issue (stesso pattern di PageReorderer/AdLoadCalculator/
 * PageGroupResolver). SendDeadlineReminders (il comando schedulato) fa la
 * query e il ciclo, questa classe fa solo la decisione booleana per
 * ciascuna issue, così la logica "quando è dovuto un sollecito" resta
 * testabile in isolamento con Carbon::setTestNow() senza toccare il DB.
 */
class DeadlineReminderChecker
{
    public static function isDue(Issue $issue, CarbonInterface $today, int $thresholdDays): bool
    {
        if ($issue->print_deadline_at === null) {
            return false;
        }

        // Già inviato per l'attuale scadenza: non risollecitare ogni giorno
        // finché la scadenza non viene spostata (che azzera esplicitamente
        // questo campo, vedi Grid::updatePrintDeadline()) o l'issue chiude.
        if ($issue->deadline_reminder_sent_at !== null) {
            return false;
        }

        if ($issue->status === IssueStatus::Chiuso) {
            return false;
        }

        if ($issue->magazine === null || $issue->magazine->redattore_id === null) {
            return false;
        }

        $daysUntilDeadline = (int) $today->copy()->startOfDay()
            ->diffInDays($issue->print_deadline_at->copy()->startOfDay(), false);

        // <= e non solo "dentro la finestra": se il job schedulato non ha
        // girato per qualche giorno (container fermo, ecc.) la scadenza
        // potrebbe già essere passata (daysUntilDeadline negativo) senza
        // che il sollecito sia mai partito — meglio un avviso tardivo che
        // nessun avviso, la deduplica sopra impedisce comunque i doppioni.
        return $daysUntilDeadline <= $thresholdDays;
    }
}
