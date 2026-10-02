<?php

namespace Database\Factories;

use App\Models\InventorySession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventorySession> */
class InventorySessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shift_type' => 'morning',
            'session_date' => CarbonImmutable::now(config('attendance.timezone'))->toDateString(),
            'opened_by' => User::factory(),
            'opened_at' => now(),
            'status' => 'open',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'closed', 'closed_at' => now(), 'closed_by' => $attributes['opened_by'],
        ]);
    }
}
