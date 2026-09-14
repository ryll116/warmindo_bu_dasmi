<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_code' => fake()->unique()->bothify('PRD-####??'),
            'category_id' => CategoryFactory::new(),
            'product_name' => fake()->words(3, true),
            'price' => '15000.00',
            'is_available' => true,
        ];
    }
}
