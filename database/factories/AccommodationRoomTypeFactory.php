<?php

namespace Database\Factories;

use App\Models\Accommodation;
use App\Models\AccommodationRoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccommodationRoomType>
 */
class AccommodationRoomTypeFactory extends Factory
{
    protected $model = AccommodationRoomType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'accommodation_id' => Accommodation::factory(),
            'title' => fake()->randomElement(['Deluxe Room', 'Suite', 'Standard Room']),
            'price' => fake()->randomFloat(2, 30, 300),
            'total_rooms' => fake()->numberBetween(1, 5),
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
