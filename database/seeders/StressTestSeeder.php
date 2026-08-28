<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

class StressTestSeeder extends Seeder
{
    private const SEAT_COUNT = 100;

    private const SEAT_PRICE_CENTS = 5000;

    /**
     * One authenticated buyer per k6 VU. A single shared token would hit the
     * per-user `throttle:60,1` limit after 60 requests and the run would measure
     * the rate limiter instead of the concurrency contract.
     */
    private const BUYER_POOL_SIZE = 1000;

    private const TOKEN_FILE = 'tests/Load/k6/stress-tokens.json';

    public function run(): void
    {
        $this->resetTicketingState();

        $eventId = $this->seedSoldOutScenario();
        $tokenCount = $this->seedBuyerPool();

        $this->command->info("Seeded event {$eventId} with ".self::SEAT_COUNT.' seats, Redis stock and '.$tokenCount.' buyer tokens.');
        $this->command->info('Tokens written to '.self::TOKEN_FILE);
    }

    private function resetTicketingState(): void
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('seats')->truncate();
            DB::table('events')->truncate();
            DB::table('reservations')->truncate();
            DB::table('tickets')->truncate();
            DB::table('pending_refunds')->truncate();
            // Stale payment jobs from a previous run would fail against truncated
            // reservations and pollute the evidence of the next one.
            DB::table('jobs')->truncate();
            DB::table('failed_jobs')->truncate();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // Clear only ticketing-related Redis keys (preserve sessions, cache, queues)
        $keys = Redis::keys('event:*:stock');
        if (! empty($keys)) {
            Redis::del(...$keys);
        }
    }

    private function seedSoldOutScenario(): int
    {
        $eventId = DB::table('events')->insertGetId([
            'name' => 'High Demand Concert',
            'total_seats' => self::SEAT_COUNT,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $seats = [];
        for ($number = 1; $number <= self::SEAT_COUNT; $number++) {
            $seats[] = [
                'event_id' => $eventId,
                'row' => 'A',
                'number' => $number,
                'price_amount' => self::SEAT_PRICE_CENTS,
                'price_currency' => 'USD',
                'reserved_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($seats, 50) as $chunk) {
            DB::table('seats')->insert($chunk);
        }

        Redis::set("event:{$eventId}:stock", self::SEAT_COUNT);

        return $eventId;
    }

    /**
     * Creates the buyer pool and writes one bearer token per buyer so k6 can
     * skip `/api/login` entirely — that endpoint is capped at 30 logins/min per IP.
     */
    private function seedBuyerPool(): int
    {
        $hashedPassword = Hash::make('password');
        $now = now();

        $buyers = [];
        for ($index = 1; $index <= self::BUYER_POOL_SIZE; $index++) {
            $buyers[] = [
                'name' => "K6 Buyer {$index}",
                'email' => "k6-buyer-{$index}@stress.test",
                'password' => $hashedPassword,
                'role' => 'user',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($buyers, 250) as $chunk) {
            DB::table('users')->upsert($chunk, ['email'], ['name', 'password', 'role', 'updated_at']);
        }

        $emails = array_column($buyers, 'email');
        $users = User::whereIn('email', $emails)->get();

        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $users->pluck('id'))
            ->delete();

        $tokens = [];
        DB::transaction(function () use ($users, &$tokens): void {
            foreach ($users as $user) {
                $tokens[] = $user->createToken('k6-stress')->plainTextToken;
            }
        });

        file_put_contents(
            base_path(self::TOKEN_FILE),
            json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return count($tokens);
    }
}
