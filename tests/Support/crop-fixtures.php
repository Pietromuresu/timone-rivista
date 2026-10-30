<?php

use Tests\Support\CropFixtures;

// Scrive i PDF di prova del ritaglio in una cartella, per guardarli:
// php tests/Support/crop-fixtures.php /percorso/cartella
require __DIR__.'/../../vendor/autoload.php';

$dir = $argv[1] ?? exit("Uso: php tests/Support/crop-fixtures.php <cartella>\n");

foreach (CropFixtures::writeAll($dir) as $name => $path) {
    echo "{$name}: {$path}\n";
}
