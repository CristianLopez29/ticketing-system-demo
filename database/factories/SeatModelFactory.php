<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;

class SeatModelFactory extends Factory
{
    protected $model = SeatModel::class;

    public function definition(): array
    {
        static $numberCounter = 1;

        return [
            'event_id'           => 1,
            'row'                => $this->faker->randomElement(['A', 'B', 'C', 'D']),
            // Custom unique implementation per PHP instance for tests
            'number'             => $numberCounter++,
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
