<?php

/*
 * La configurazione del backup contiene credenziali e la password che apre
 * tutti i backup: non deve mai finire su GitHub né nell'immagine Docker
 * (2026-10-30). Fallisce se qualcuno la aggiunge a git o toglie le regole
 * da .gitignore/.dockerignore.
 */

function backupGit(string $arguments): array
{
    $root = dirname(__DIR__, 3);
    exec('git -C '.escapeshellarg($root).' '.$arguments.' 2>/dev/null', $output, $code);

    return [$code, $output];
}

beforeEach(function () {
    [$code] = backupGit('rev-parse --git-dir');
    if ($code !== 0) {
        $this->markTestSkipped('Non è un clone git (es. dentro l\'immagine Docker).');
    }
});

test('backup.env and the backup secrets are not tracked by git', function () {
    [, $tracked] = backupGit('ls-files -- backup.env backup-secrets');

    expect($tracked)->toBe([]);
});

test('backup.env and the backup secrets are ignored by git', function () {
    foreach (['backup.env', 'backup-secrets/restic-password', 'backup-secrets/ssh/id_ed25519'] as $path) {
        [$code] = backupGit('check-ignore -q '.escapeshellarg($path));
        expect($code)->toBe(0, "$path deve essere ignorato da git");
    }
});

test('backup.env.example is not ignored and contains only placeholder secrets', function () {
    $root = dirname(__DIR__, 3);
    [$code] = backupGit('check-ignore -q backup.env.example');
    expect($code)->toBe(1, 'il modello deve restare nel repository');

    $example = file_get_contents($root.'/backup.env.example');
    expect($example)->toContain('BACKUP_REMOTE_REPOSITORY=DA_CONFIGURARE')
        ->and($example)->toContain('BACKUP_DB_PASSWORD=password-finta-generata-da-setup');
})->skip(fn () => ! is_file(dirname(__DIR__, 3).'/backup.env.example'), 'backup.env.example assente');

test('backup.env and the backup secrets are excluded from the Docker build context', function () {
    $lines = array_map('trim', file(dirname(__DIR__, 3).'/.dockerignore'));

    expect($lines)->toContain('backup.env')
        ->and($lines)->toContain('backup-secrets');
});
