<?php

namespace Database\Factories;

use App\Models\Resto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Resto> */
class RestoFactory extends Factory
{
    /** @return array{resto_name: string} */
    public function definition(): array
    {
        return ['resto_name' => fake()->unique()->words(3, true)];
    }
}
