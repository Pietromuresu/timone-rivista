<?php

namespace Database\Factories;

use App\Enums\Periodicity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Magazine>
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
            'ad_threshold_percentage' => 30,
            'notes' => null,
        ];
    }
}
