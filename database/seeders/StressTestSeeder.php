<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class StressTestSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clean up
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('seats')->truncate();
        DB::table('events')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        Redis::flushall();

        // 2. Create Event
        $eventId = DB::table('events')->insertGetId([
            'name' => 'High Demand Concert',
            'total_seats' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Create 100 Seats
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
        
        // Insert in chunks to be faster
        foreach (array_chunk($seats, 50) as $chunk) {
            DB::table('seats')->insert($chunk);
        }

        // 4. Initialize Redis Stock
        Redis::set("event:{$eventId}:stock", 100);
        
        $this->command->info("Seeded Event {$eventId} with 100 seats and Redis stock.");
    }
}
