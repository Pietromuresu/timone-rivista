<?php

use App\Mail\BackupAlert;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->statusDir = sys_get_temp_dir().'/timone-backup-status-'.uniqid();
    File::ensureDirectoryExists($this->statusDir);
    config(['timone.backup.status_path' => $this->statusDir]);
});

afterEach(function () {
    File::deleteDirectory($this->statusDir);
});

function writeBackupSettings(string $dir, string $emails): void
{
    file_put_contents($dir.'/settings.json', json_encode([
        'written_at' => now()->toIso8601String(),
        'alert_emails' => $emails,
        'max_age_hours' => 26,
        'local_min_free_gb' => 10,
        'remote_quota_gb' => null,
        'remote_warn_percent' => 80,
        'remote_configured' => false,
        'remote_type' => null,
        'config_error' => null,
    ]));
}

test('the test alert is queued to every address of BACKUP_ALERT_EMAIL', function () {
    Mail::fake();
    writeBackupSettings($this->statusDir, 'sistemista@esempio.it, redazione@esempio.it');

    $this->artisan('timone:backup-test-alert')->assertExitCode(0);

    Mail::assertQueued(BackupAlert::class, fn (BackupAlert $mail) => $mail->hasTo('sistemista@esempio.it')
        && $mail->hasTo('redazione@esempio.it')
        && $mail->headline === 'mail di prova');
});

test('--to sends the test alert without the backup settings', function () {
    Mail::fake();

    $this->artisan('timone:backup-test-alert', ['--to' => 'prova@esempio.it'])->assertExitCode(0);

    Mail::assertQueued(BackupAlert::class, fn (BackupAlert $mail) => $mail->hasTo('prova@esempio.it'));
});

test('without settings written by the backup agent the command explains what to do', function () {
    Mail::fake();

    $this->artisan('timone:backup-test-alert')
        ->expectsOutputToContain('Il servizio backup-agent non è attivo')
        ->assertExitCode(1);

    Mail::assertNothingQueued();
});

test('an invalid address in BACKUP_ALERT_EMAIL is reported and nothing is sent', function () {
    Mail::fake();
    writeBackupSettings($this->statusDir, 'sistemista@esempio.it,sistemista-at-esempio');

    $this->artisan('timone:backup-test-alert')
        ->expectsOutputToContain('sistemista-at-esempio')
        ->assertExitCode(1);

    Mail::assertNothingQueued();
});

test('an empty BACKUP_ALERT_EMAIL is an error', function () {
    Mail::fake();
    writeBackupSettings($this->statusDir, '');

    $this->artisan('timone:backup-test-alert')->assertExitCode(1);

    Mail::assertNothingQueued();
});

test('with the log mailer the command warns that the mail does not really leave', function () {
    Mail::fake();
    config(['mail.default' => 'log']);
    writeBackupSettings($this->statusDir, 'sistemista@esempio.it');

    $this->artisan('timone:backup-test-alert')
        ->expectsOutputToContain('MAIL_MAILER=log')
        ->assertExitCode(0);
});

test('the alert mail renders its details and the operations guide', function () {
    $mail = new BackupAlert('mail di prova', ['Riga uno'], 'niente.');

    $mail->assertSeeInHtml('Riga uno')
        ->assertSeeInHtml('docs/OPERATIONS.md')
        ->assertHasSubject('['.config('app.name').'] Backup: mail di prova');
});
