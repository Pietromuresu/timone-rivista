<?php

namespace Database\Factories;

use App\Enums\ContentType;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Content>
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
        ];
    }

    public function article(): static
    {
        return $this->state(fn () => ['type' => ContentType::Articolo->value])
            ->afterCreating(fn ($content) => \App\Models\Article::factory()->create(['content_id' => $content->id]));
    }

    public function advertisement(): static
    {
        return $this->state(fn () => ['type' => ContentType::Pubblicita->value])
            ->afterCreating(fn ($content) => \App\Models\Advertisement::factory()->create(['content_id' => $content->id]));
    }
}
