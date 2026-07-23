<?php

namespace Database\Factories;

use App\Enums\AdConfirmationStatus;
use App\Enums\AdFormat;
use App\Enums\ContentType;
use App\Models\Content;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Advertisement>
 */
class AdvertisementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_id' => Content::factory()->state(['type' => ContentType::Pubblicita->value]),
            'client' => fake()->company(),
            'agency' => fake()->optional()->company(),
            'format' => fake()->randomElement(AdFormat::cases())->value,
            'occupied_percentage_override' => null,
            'confirmation_status' => fake()->randomElement(AdConfirmationStatus::cases())->value,
            'commercial_notes' => null,
        ];
    }
}
