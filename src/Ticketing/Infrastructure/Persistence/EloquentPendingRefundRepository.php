<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Ticketing\Domain\Repositories\PendingRefundRepository;

class EloquentPendingRefundRepository implements PendingRefundRepository
{
    public function save(string $transactionId, string $reservationId, string $reason): void
    {
        DB::table('pending_refunds')->insert([
            'transaction_id' => $transactionId,
            'reservation_id' => $reservationId,
            'reason'         => $reason,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}
