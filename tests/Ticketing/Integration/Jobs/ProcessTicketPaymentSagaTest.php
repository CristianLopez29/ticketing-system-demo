<?php

declare(strict_types = 1)
;

namespace Tests\Ticketing\Integration\Jobs;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Events\TicketSold;
use Src\Ticketing\Infrastructure\Jobs\ProcessTicketPayment;
use Src\Ticketing\Infrastructure\Payment\FakePaymentGateway;
use Src\Ticketing\Infrastructure\Persistence\EventModel;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;
use Tests\TestCase;

class ProcessTicketPaymentSagaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
    }

    public function test_it_compensates_and_records_pending_refund_if_refund_fails_after_db_error(): void
    {
        Event::fake([TicketSold::class]);

        $user = User::factory()->create();

        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $seat = SeatModel::create([
            'event_id' => $event->id,
            'row' => 'A',
            'number' => 1,
            'price_amount' => 5000,
            'price_currency' => 'USD',
            'reserved_by_user_id' => $user->id,
        ]);

        $reservationId = 'res-123';
        DB::table('reservations')->insert([
            'id' => $reservationId,
            'event_id' => $event->id,
            'seat_id' => $seat->id,
            'user_id' => $user->id,
            'status' => ReservationStatus::PENDING_PAYMENT->value,
            'price_amount' => 5000,
            'price_currency' => 'USD',
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Redis::set("event:{$event->id}:stock", 99);

        // We want the charge to succeed, but the subsequent DB operation to fail.
        // And then the refund should also fail.

        // Let's force a failure in the DB transaction by deleting the reservation before it runs,
        // wait, the job uses `findAndLock`, if it returns null, it throws an Exception.

        // We can just delete the reservation directly right after charge by mocking the PaymentGateway?
        // Let's use a mock for PaymentGateway to delete the reservation during the charge method
        // But wait, charge is called, then DB transaction. We can just delete the reservation manually by mocking PaymentGateway's charge to call parent::charge and also delete reservation.

        $gateway = new class extends FakePaymentGateway {
            public string $resId = '';
            public function charge(int $userId, \Src\Ticketing\Domain\ValueObjects\Money $amount): string
            {
                $id = parent::charge($userId, $amount);
                // Introduce inconsistency: Delete reservation so transactionManager throws exception
                DB::table('reservations')->where('id', $this->resId)->delete();
                return $id;
            }
        };
        $gateway->resId = $reservationId;
        $this->app->instance(\Src\Ticketing\Domain\Ports\PaymentGateway::class , $gateway);

        FakePaymentGateway::forceFailNextRefund(true);

        $job = new ProcessTicketPayment($reservationId);
        try {
            app()->call([$job, 'handle']);
            $this->fail('Expected an exception to be thrown, but none was.');
        }
        catch (\Exception $e) {
        // Exception is expected — continue to assertions below
        }

        // Assert pending refund was created
        $this->assertDatabaseCount('pending_refunds', 1);
        $pendingRefund = DB::table('pending_refunds')->first();
        $this->assertEquals($reservationId, $pendingRefund->reservation_id);
        $this->assertStringContainsString('Refund declined by the bank.', $pendingRefund->reason);
    }
}
