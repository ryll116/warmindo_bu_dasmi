<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryItem> */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'resto_id' => null,
            'item_code' => fake()->unique()->bothify('INV-########'),
            'item_name' => fake()->word(),
            'unit' => 'kg',
            'current_stock' => '0.000',
            'minimum_stock' => '0.000',
            'is_active' => true,
        ];
    }
}
