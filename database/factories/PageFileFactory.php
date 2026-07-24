<?php

namespace Database\Factories;

use App\Enums\ThumbnailStatus;
use App\Models\Page;
use App\Models\PageFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageFile>
 */
class PageFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => 'pages/1/'.fake()->uuid().'.pdf',
            'thumbnail_path' => null,
            'original_name' => fake()->word().'.pdf',
            'size' => fake()->numberBetween(50_000, 20_000_000),
            'thumbnail_status' => ThumbnailStatus::Pending,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'thumbnail_status' => ThumbnailStatus::Ready,
            'thumbnail_path' => 'pages/1/thumbnails/'.fake()->uuid().'.png',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'thumbnail_status' => ThumbnailStatus::Failed,
            'thumbnail_path' => null,
        ]);
    }
}
