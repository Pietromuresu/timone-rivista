<?php

namespace Database\Factories;

use App\Enums\ContentType;
use App\Models\Advertisement;
use App\Models\Article;
use App\Models\Content;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Content>
 */
class ContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'section_id' => null,
            'type' => ContentType::Articolo->value,
            'title' => fake()->sentence(3),
            // Obbligatoria da bug 5 (2026-10-12, colonna NOT NULL): 1 pagina
            // come valore neutro per i test che non la riguardano.
            'occupancy_twelfths' => 12,
        ];
    }

    public function article(): static
    {
        return $this->state(fn () => ['type' => ContentType::Articolo->value])
            ->afterCreating(fn ($content) => Article::factory()->create(['content_id' => $content->id]));
    }

    public function advertisement(): static
    {
        return $this->state(fn () => ['type' => ContentType::Pubblicita->value])
            ->afterCreating(fn ($content) => Advertisement::factory()->create(['content_id' => $content->id]));
    }
}
