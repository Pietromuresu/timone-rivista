<?php

use App\Support\Backup\BackupSettings;

test('more addresses separated by comma or semicolon are all recipients', function () {
    [$valid, $invalid] = BackupSettings::parseRecipients(' a@esempio.it, b@esempio.it;c@esempio.it ,, ');

    expect($valid)->toBe(['a@esempio.it', 'b@esempio.it', 'c@esempio.it'])
        ->and($invalid)->toBe([]);
});

test('invalid addresses are reported, not silently dropped', function () {
    [$valid, $invalid] = BackupSettings::parseRecipients('a@esempio.it,non-una-mail,a@esempio.it');

    expect($valid)->toBe(['a@esempio.it'])
        ->and($invalid)->toBe(['non-una-mail']);
});

test('settings.json written by the backup agent is read with its defaults', function () {
    $settings = BackupSettings::fromArray([
        'written_at' => '2026-10-30T15:00:00+01:00',
        'alert_emails' => 'sistemista@esempio.it',
        'max_age_hours' => 26,
        'local_min_free_gb' => 10,
        'remote_quota_gb' => null,
        'remote_warn_percent' => 80,
        'remote_configured' => false,
        'remote_type' => null,
        'remote_2_configured' => true,
        'remote_2_type' => 's3',
        'remote_2_quota_gb' => 500,
        'config_error' => null,
    ]);

    expect($settings->alertRecipients)->toBe(['sistemista@esempio.it'])
        ->and($settings->maxAgeHours)->toBe(26)
        ->and($settings->remoteQuotaGb)->toBeNull()
        ->and($settings->remoteConfigured)->toBeFalse()
        ->and($settings->remote2Configured)->toBeTrue()
        ->and($settings->remote2Type)->toBe('s3')
        ->and($settings->remote2QuotaGb)->toBe(500)
        ->and($settings->writtenAt?->toIso8601String())->toBe('2026-10-30T15:00:00+01:00');
});

test('settings.json written before the second destination existed still reads', function () {
    $settings = BackupSettings::fromArray(['alert_emails' => 'a@esempio.it']);

    expect($settings->remote2Configured)->toBeFalse()
        ->and($settings->remote2Type)->toBeNull();
});
