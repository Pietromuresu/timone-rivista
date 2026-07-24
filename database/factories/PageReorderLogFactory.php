<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\Page;
use App\Models\PageReorderLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageReorderLog>
 */
class PageReorderLogFactory extends Factory
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
            'page_id' => Page::factory(),
            'user_id' => User::factory(),
            'from_position' => fake()->numberBetween(1, 50),
            'to_position' => fake()->numberBetween(1, 50),
        ];
    }
}
