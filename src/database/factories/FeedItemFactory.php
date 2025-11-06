<?php

namespace Database\Factories;

use App\Models\Feed;
use App\Models\LibraryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FeedItem>
 */
class FeedItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feed_id' => Feed::factory(),
            'library_item_id' => LibraryItem::factory(),
            'sequence' => fake()->numberBetween(0, 100),
        ];
    }
}
