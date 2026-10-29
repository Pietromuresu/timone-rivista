<?php

namespace App\Support\Manual;

/** Correzioni tenute in memoria: nessuna (default), o quelle appena calcolate dal job. */
final class ArrayLearnedCorrections implements LearnedCorrections
{
    /** @param  array<string, array<string, float>>  $corrections  chiave → slug → correzione */
    public function __construct(private readonly array $corrections = []) {}

    public function forKey(string $key): array
    {
        return $this->corrections[$key] ?? [];
    }
}
