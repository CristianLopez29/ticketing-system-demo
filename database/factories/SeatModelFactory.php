<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;

class SeatModelFactory extends Factory
{
    protected $model = SeatModel::class;

    public function definition(): array
    {
        return [
            'event_id'           => 1,
            'row'                => $this->faker->randomElement(['A', 'B', 'C', 'D']),
            // Use a large random range without faker unique() — unique() state persists
            // across factory instances in the same PHP process and can cause collisions
            // in multi-test sessions. The DB UNIQUE(event_id, row, number) constraint
            // enforces real uniqueness; use withNumber() in tests that need a specific value.
            'number'             => $this->faker->numberBetween(1, 999999),
            'price_amount'       => 5000,
            'price_currency'     => 'USD',
            'reserved_by_user_id' => null,
        ];
    }

    public function withNumber(int $number): static
    {
        return $this->state(['number' => $number]);
    }
}
