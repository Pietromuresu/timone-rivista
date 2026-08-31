<?php

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\User;
use App\Support\DeadlineReminderChecker;
use Illuminate\Support\Carbon;

/**
 * Pura: costruisce Issue/Magazine "finti" con setRelation()/attributi in
 * memoria, nessun DB — stesso stile di AdLoadCalculatorTest, coerente con
 * la natura pura di DeadlineReminderChecker (nessuna query al suo interno).
 *
 * Serve comunque Tests\TestCase (senza RefreshDatabase: nessuna query reale
 * viene mai eseguita) invece del default "puro" di tests/Unit — scoperto
 * qui, non presunto: `new Issue(['print_deadline_at' => ...])` con un cast
 * date/datetime chiama internamente getDateFormat(), che risolve il
 * connection resolver del Model anche senza mai interrogare il database
 * (a differenza degli attributi cast a enum/stringa usati da
 * AdLoadCalculatorTest, che non lo richiedono) — senza l'app Laravel
 * avviata (il default di tests/Pest.php per la cartella Unit) fallisce con
 * "Call to a member function connection() on null".
 */
uses(Tests\TestCase::class);
function makeIssueForDeadlineCheck(?string $deadline, ?string $reminderSentAt, IssueStatus $status = IssueStatus::InLavorazione, bool $hasRedattore = true): Issue
{
    $magazine = new Magazine(['redattore_id' => $hasRedattore ? 1 : null]);
    $magazine->setRelation('redattore', $hasRedattore ? new User(['id' => 1]) : null);

    $issue = new Issue([
        'print_deadline_at' => $deadline,
        'deadline_reminder_sent_at' => $reminderSentAt,
        'status' => $status->value,
    ]);
    $issue->setRelation('magazine', $magazine);

    return $issue;
}

test('an issue with no deadline is never due', function () {
    $issue = makeIssueForDeadlineCheck(null, null);

    expect(DeadlineReminderChecker::isDue($issue, Carbon::parse('2026-09-01'), 3))->toBeFalse();
});

test('a deadline exactly at the threshold is due', function () {
    $issue = makeIssueForDeadlineCheck('2026-09-04', null);

    expect(DeadlineReminderChecker::isDue($issue, Carbon::parse('2026-09-01'), 3))->toBeTrue();
});

test('a deadline further away than the threshold is not yet due', function () {
    $issue = makeIssueForDeadlineCheck('2026-09-10', null);

    expect(DeadlineReminderChecker::isDue($issue, Carbon::parse('2026-09-01'), 3))->toBeFalse();
});

test('an overdue deadline that was never reminded is still due (late better than never)', function () {
    $issue = makeIssueForDeadlineCheck('2026-08-20', null);

    expect(DeadlineReminderChecker::isDue($issue, Carbon::parse('2026-09-01'), 3))->toBeTrue();
});

test('a deadline already reminded is not due again', function () {
    $issue = makeIssueForDeadlineCheck('2026-09-02', '2026-08-30 07:30:00');

    expect(DeadlineReminderChecker::isDue($issue, Carbon::parse('2026-09-01'), 3))->toBeFalse();
});

test('a closed issue is never due, regardless of deadline', function () {
    $issue = makeIssueForDeadlineCheck('2026-09-02', null, IssueStatus::Chiuso);

    expect(DeadlineReminderChecker::isDue($issue, Carbon::parse('2026-09-01'), 3))->toBeFalse();
});

test('a magazine with no redattore assigned is never due', function () {
    $issue = makeIssueForDeadlineCheck('2026-09-02', null, IssueStatus::InLavorazione, hasRedattore: false);

    expect(DeadlineReminderChecker::isDue($issue, Carbon::parse('2026-09-01'), 3))->toBeFalse();
});
