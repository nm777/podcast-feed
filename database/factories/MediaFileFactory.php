<?php

namespace Database\Factories;

use App\Models\MediaFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MediaFile>
 */
class MediaFileFactory extends Factory
{
    protected $model = MediaFile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_path' => 'media/' . $this->faker->uuid() . '.mp3',
            'file_hash' => $this->faker->sha256(),
            'mime_type' => $this->faker->randomElement(['audio/mpeg', 'audio/mp4', 'audio/wav', 'video/mp4']),
            'filesize' => $this->faker->numberBetween(1000000, 50000000), // 1MB to 50MB
            'duration' => $this->faker->numberBetween(60, 7200), // 1 minute to 2 hours
        ];
    }
}
