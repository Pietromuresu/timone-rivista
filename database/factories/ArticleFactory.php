<?php

namespace Database\Factories;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Models\Content;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_id' => Content::factory()->state(['type' => ContentType::Articolo->value]),
            'author' => fake()->name(),
            'editorial_status' => fake()->randomElement(EditorialStatus::cases())->value,
            'expected_length' => fake()->numberBetween(2, 12),
        ];
    }
}
