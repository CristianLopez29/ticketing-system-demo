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
            'event_id' => 1,
            'row' => 'A',
            'number' => $this->faker->unique()->numberBetween(1, 10000),
            'price_amount' => 5000,
            'price_currency' => 'USD',
            'reserved_by_user_id' => null,
        ];
    }
}
