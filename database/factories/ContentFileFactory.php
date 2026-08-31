<?php

namespace Database\Factories;

use App\Enums\ThumbnailStatus;
use App\Models\Content;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ContentFile>
 */
class ContentFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => 'contents/1/'.fake()->uuid().'.pdf',
            'thumbnail_path' => null,
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(50_000, 20_000_000),
            'thumbnail_status' => ThumbnailStatus::Pending,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'thumbnail_status' => ThumbnailStatus::Ready,
            'thumbnail_path' => 'contents/1/thumbnails/'.fake()->uuid().'.png',
        ]);
    }
}
