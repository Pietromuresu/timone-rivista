<?php

namespace Database\Factories;

use App\Enums\PageContentType;
use App\Enums\PageStatus;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Page>
 */
class PageFactory extends Factory
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
            'position' => fake()->unique()->numberBetween(1, 100000),
            'content_type' => PageContentType::Bianca->value,
            'status' => PageStatus::DaAssegnare->value,
            'notes' => null,
        ];
    }
}
