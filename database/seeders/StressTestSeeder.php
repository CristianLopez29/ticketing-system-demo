<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

class StressTestSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clean up database tables
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('seats')->truncate();
        DB::table('events')->truncate();
        DB::table('reservations')->truncate();
        DB::table('tickets')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Clear only ticketing-related Redis keys (preserve sessions, cache, queues)
        $keys = Redis::keys('event:*:stock');
        if (!empty($keys)) {
            Redis::del(...$keys);
        }

        // 2. Create or update stress test user for k6 authentication
        DB::table('users')->updateOrInsert(
            ['email' => 'stress@test.com'],
            [
                'name' => 'Stress Test User',
                'email' => 'stress@test.com',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 3. Create Event
        $eventId = DB::table('events')->insertGetId([
            'name' => 'High Demand Concert',
            'total_seats' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Create 100 Seats
        $seats = [];
        for ($i = 1; $i <= 100; $i++) {
            $seats[] = [
                'event_id' => $eventId,
                'row' => 'A',
                'number' => $i,
                'price_amount' => 5000, // $50.00
                'price_currency' => 'USD',
                'reserved_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert in chunks for performance
        foreach (array_chunk($seats, 50) as $chunk) {
            DB::table('seats')->insert($chunk);
        }

        // 5. Initialize Redis Stock
        Redis::set("event:{$eventId}:stock", 100);

        $this->command->info("Seeded Event {$eventId} with 100 seats and Redis stock.");
    }
}
