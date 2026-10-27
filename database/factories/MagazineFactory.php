<?php

namespace Database\Factories;

use App\Enums\Periodicity;
use App\Models\Magazine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Magazine>
 */
class MagazineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Lo slug non viene impostato qui: se ne occupa sempre il model
     * (Magazine::booted) a partire dal nome finale, incluso quando il
     * chiamante sovrascrive 'name' rispetto al valore fake generato qui.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->words(2, true)),
            'periodicity' => fake()->randomElement(Periodicity::cases())->value,
            'color' => fake()->hexColor(),
            'notes' => null,
        ];
    }

    /**
     * Default del calcolo peso con i valori del foglio Excel di NM
     * (21 × 27 cm, interno 80 g/m², copertina 6 pagine 170 g/m²). Le colonne
     * con un default a database (maggiorazione, tolleranze, peso bancale)
     * vengono comunque esplicitate: Eloquent non rilegge i default di
     * colonna dopo un insert.
     */
    public function withWeightDefaults(): static
    {
        return $this->state(fn () => [
            'formato_base_mm' => 210,
            'formato_altezza_mm' => 270,
            'grammatura_interno' => 80,
            'grammatura_copertina' => 170,
            'pagine_copertina' => 6,
            'maggiorazione_percentuale' => 2,
            'peso_bancale_kg' => 20,
            'tolleranza_grammatura_perc' => 4,
            'tolleranza_formato_mm' => 1,
            'tolleranza_maggiorazione_perc' => 1,
        ]);
    }
}
