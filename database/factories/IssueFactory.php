<?php

namespace Database\Factories;

use App\Enums\IssueStatus;
use App\Models\Magazine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Issue>
 */
class IssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'magazine_id' => Magazine::factory(),
            'title' => fake()->monthName().' '.fake()->year(),
            'issue_date' => fake()->dateTimeBetween('now', '+2 months'),
            'status' => IssueStatus::InLavorazione->value,
            'total_pages' => 0,
            'notes' => null,
        ];
    }
}
