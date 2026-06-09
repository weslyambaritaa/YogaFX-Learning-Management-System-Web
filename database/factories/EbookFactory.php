<?php

namespace Database\Factories;

use App\Models\Ebook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ebook>
 */
class EbookFactory extends Factory
{
    protected $model = Ebook::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'file' => 'ebooks/sample.pdf',
            'sort_order' => null,
        ];
    }
}
