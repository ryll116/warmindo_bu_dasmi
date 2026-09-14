<?php

namespace Database\Factories;

use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'table_no' => fake()->unique()->numberBetween(1, 100000),
            'is_available' => true,
        ];
    }
}
