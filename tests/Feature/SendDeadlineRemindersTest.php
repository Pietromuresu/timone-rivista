<?php

use App\Enums\IssueStatus;
use App\Enums\UserRole;
use App\Mail\IssueDeadlineReminder;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('an issue with a deadline inside the configured threshold gets a reminder queued to its redattore', function () {
    Mail::fake();
    config(['timone.deadline_reminder_days_before' => 3]);

    $redattore = User::factory()->create(['role' => UserRole::Redattore]);
    $magazine = Magazine::factory()->create(['redattore_id' => $redattore->id]);
    $issue = Issue::factory()->for($magazine)->create([
        'print_deadline_at' => now()->addDays(2),
        'status' => IssueStatus::InLavorazione,
    ]);

    $this->artisan('timone:send-deadline-reminders')->assertExitCode(0);

    Mail::assertQueued(IssueDeadlineReminder::class, function ($mail) use ($issue, $redattore) {
        return $mail->hasTo($redattore->email) && $mail->issue->is($issue);
    });

    expect($issue->fresh()->deadline_reminder_sent_at)->not->toBeNull();
});

test('an issue with a deadline outside the threshold gets no reminder', function () {
    Mail::fake();
    config(['timone.deadline_reminder_days_before' => 3]);

    $redattore = User::factory()->create(['role' => UserRole::Redattore]);
    $magazine = Magazine::factory()->create(['redattore_id' => $redattore->id]);
    $issue = Issue::factory()->for($magazine)->create([
        'print_deadline_at' => now()->addDays(10),
        'status' => IssueStatus::InLavorazione,
    ]);

    $this->artisan('timone:send-deadline-reminders');

    Mail::assertNothingQueued();
    expect($issue->fresh()->deadline_reminder_sent_at)->toBeNull();
});

test('running the command twice does not send a second reminder for the same deadline', function () {
    Mail::fake();
    config(['timone.deadline_reminder_days_before' => 3]);

    $redattore = User::factory()->create(['role' => UserRole::Redattore]);
    $magazine = Magazine::factory()->create(['redattore_id' => $redattore->id]);
    Issue::factory()->for($magazine)->create([
        'print_deadline_at' => now()->addDay(),
        'status' => IssueStatus::InLavorazione,
    ]);

    $this->artisan('timone:send-deadline-reminders');
    $this->artisan('timone:send-deadline-reminders');

    Mail::assertQueued(IssueDeadlineReminder::class, 1);
});

test('a magazine with no redattore assigned is skipped, no error', function () {
    Mail::fake();
    config(['timone.deadline_reminder_days_before' => 3]);

    $magazine = Magazine::factory()->create(['redattore_id' => null]);
    $issue = Issue::factory()->for($magazine)->create([
        'print_deadline_at' => now()->addDay(),
        'status' => IssueStatus::InLavorazione,
    ]);

    $this->artisan('timone:send-deadline-reminders')->assertExitCode(0);

    Mail::assertNothingQueued();
    expect($issue->fresh()->deadline_reminder_sent_at)->toBeNull();
});

test('a closed issue is never reminded even if its deadline is imminent', function () {
    Mail::fake();
    config(['timone.deadline_reminder_days_before' => 3]);

    $redattore = User::factory()->create(['role' => UserRole::Redattore]);
    $magazine = Magazine::factory()->create(['redattore_id' => $redattore->id]);
    Issue::factory()->for($magazine)->create([
        'print_deadline_at' => now()->addDay(),
        'status' => IssueStatus::Chiuso,
    ]);

    $this->artisan('timone:send-deadline-reminders');

    Mail::assertNothingQueued();
});

test('the reminder email content mentions the correct issue and magazine', function () {
    Mail::fake();

    $redattore = User::factory()->create(['role' => UserRole::Redattore]);
    $magazine = Magazine::factory()->create(['redattore_id' => $redattore->id, 'name' => 'Motori Elettrici']);
    $issue = Issue::factory()->for($magazine)->create([
        'title' => 'Novembre 2026',
        'print_deadline_at' => now()->addDay(),
        'status' => IssueStatus::InLavorazione,
    ]);

    $this->artisan('timone:send-deadline-reminders');

    Mail::assertQueued(IssueDeadlineReminder::class, function ($mail) {
        $rendered = $mail->render();

        return str_contains($rendered, 'Novembre 2026') && str_contains($rendered, 'Motori Elettrici');
    });
});
