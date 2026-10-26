<?php

use App\Support\Uploads\UploadLimits;

// Fase 1, bug 9 (2026-10-26).

test('php.ini size values are converted to bytes', function () {
    expect(UploadLimits::iniBytes('100M'))->toBe(100 * 1024 * 1024)
        ->and(UploadLimits::iniBytes('2G'))->toBe(2 * 1024 ** 3)
        ->and(UploadLimits::iniBytes('512K'))->toBe(512 * 1024)
        ->and(UploadLimits::iniBytes('1048576'))->toBe(1048576)
        ->and(UploadLimits::iniBytes('0'))->toBeNull()
        ->and(UploadLimits::iniBytes(false))->toBeNull();
});

test('the max rule is read from string and array rules', function () {
    expect(UploadLimits::ruleMaxKilobytes('nullable|file|max:20480'))->toBe(20480)
        ->and(UploadLimits::ruleMaxKilobytes(['required', 'file', 'max:102400']))->toBe(102400)
        ->and(UploadLimits::ruleMaxKilobytes(['required']))->toBeNull();
});

test('megabytes are shown the Italian way', function () {
    expect(UploadLimits::humanMegabytes(20 * 1024 * 1024))->toBe('20 MB')
        ->and(UploadLimits::humanMegabytes((int) (1.5 * 1024 * 1024)))->toBe('1,5 MB');
});
