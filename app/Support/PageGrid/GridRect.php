<?php

namespace App\Support\PageGrid;

/**
 * Rettangolo di celle nella griglia 6×6 di una pagina (revisione 2, Fase
 * 2.3, 2026-10-27): x, y = cella in alto a sinistra (0…5), w, h = celle.
 * Una pagina intera è 6×6 = 36 celle = 12 dodicesimi, quindi un dodicesimo
 * vale 3 celle e ogni formato ha un rettangolo esatto (1/2 verticale 3×6,
 * 1/3 orizzontale 6×2, 1/4 3×3…). Immutabile.
 */
final readonly class GridRect
{
    public const SIZE = 6;

    public function __construct(
        public int $x,
        public int $y,
        public int $w,
        public int $h,
    ) {}

    public function area(): int
    {
        return $this->w * $this->h;
    }

    public function insidePage(): bool
    {
        return $this->w >= 1 && $this->h >= 1 && $this->x >= 0 && $this->y >= 0
            && $this->x + $this->w <= self::SIZE && $this->y + $this->h <= self::SIZE;
    }

    public function overlaps(self $other): bool
    {
        return $this->x < $other->x + $other->w && $other->x < $this->x + $this->w
            && $this->y < $other->y + $other->h && $other->y < $this->y + $this->h;
    }

    public function at(int $x, int $y): self
    {
        return new self($x, $y, $this->w, $this->h);
    }

    /** Stessa cella d'angolo, larghezza e altezza scambiate. */
    public function transposed(): self
    {
        return new self($this->x, $this->y, $this->h, $this->w);
    }

    /** @return list<array{0: int, 1: int}> */
    public function cells(): array
    {
        $cells = [];

        for ($y = $this->y; $y < $this->y + $this->h; $y++) {
            for ($x = $this->x; $x < $this->x + $this->w; $x++) {
                $cells[] = [$x, $y];
            }
        }

        return $cells;
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y && $this->w === $other->w && $this->h === $other->h;
    }

    /** In percentuale della pagina, per il disegno (card, export). */
    public function toPercent(): array
    {
        $unit = 100 / self::SIZE;

        return [
            'x' => round($this->x * $unit, 4),
            'y' => round($this->y * $unit, 4),
            'w' => round($this->w * $unit, 4),
            'h' => round($this->h * $unit, 4),
        ];
    }

    /** @return array{x: int, y: int, w: int, h: int} */
    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'w' => $this->w, 'h' => $this->h];
    }

    public function label(): string
    {
        return "{$this->w}×{$this->h} in ({$this->x},{$this->y})";
    }
}
